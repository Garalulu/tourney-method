<?php

namespace App\Http\Controllers;

use App\Jobs\SyncUserFromOsu;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class AuthController extends Controller
{
    /**
     * Redirect to osu! OAuth provider
     */
    public function login(Request $request): RedirectResponse|SymfonyRedirectResponse
    {
        $this->storeLoginRedirectUrl($request);

        return Socialite::driver('osu')->redirect();
    }

    /**
     * Handle OAuth callback from osu!
     */
    public function callback(): RedirectResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\User $osuUser */
            $osuUser = Socialite::driver('osu')->user();

            // Get user data from Socialite provider
            $osuId = $osuUser->getId();
            /** @var array<string, mixed> $userData Raw API data */
            $userData = $osuUser->getRaw();

            // Prepare user data from OAuth
            $userDataForUpdate = [
                'username' => $osuUser->getNickname(),
            ];

            // Update from API data if available
            if (isset($userData['country_code'])) {
                $userDataForUpdate['country_code'] = $userData['country_code'];
            }

            $existingUser = User::withTrashed()
                ->where('osu_id', $osuId)
                ->first();

            if (! $existingUser) {
                $detectedLocale = session('locale') ?? $this->detectLocaleFromRequest(request());
                if ($detectedLocale) {
                    $userDataForUpdate['locale'] = $detectedLocale;
                }
            }

            // Update or create user (handles soft-deleted users)
            try {
                $user = User::updateOrCreate(
                    ['osu_id' => $osuId],
                    $userDataForUpdate
                );
            } catch (QueryException $e) {
                // Check if it's a unique constraint violation on osu_id
                if (str_contains($e->getMessage(), 'users_osu_id_unique')) {
                    // User might be soft-deleted - restore them
                    $user = User::withTrashed()
                        ->where('osu_id', $osuId)
                        ->firstOrFail();

                    // Restore the soft-deleted user
                    if ($user->trashed()) {
                        $user->restore();
                    }

                    // Update their data
                    $user->update($userDataForUpdate);
                } else {
                    // Re-throw if it's a different database error
                    throw $e;
                }
            }

            // Note: Rank and PP data is handled by background sync job
            // and stored in user_rank_history table, not on users table

            // Queue background sync for full data (badges, rank history, etc.)
            // Only sync if last sync was more than 24 hours ago (FR-006)
            $lastSyncAt = $user->osu_data_synced_at;
            $shouldSync = ! $lastSyncAt || $lastSyncAt->lt(now()->subDay());

            if ($shouldSync) {
                SyncUserFromOsu::dispatch($user);
            }

            // Log the user in
            $user->forceFill(['last_login_at' => now()])->save();
            auth()->login($user);

            // Store OAuth access token and saved locale in session for future requests
            session([
                'osu_access_token' => $osuUser->token,
                'locale' => $user->locale,
            ]);

            // Redirect to setup if user hasn't completed it, otherwise dashboard
            if ($user->needsManualModeSetup()) {
                return redirect()->route('setup.show');
            }

            $redirectUrl = session()->pull('login_redirect_url') ?? route('dashboard');

            return redirect()->to($redirectUrl)
                ->with('success', 'Welcome back, '.$user->username.'!');

        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('home')
                ->with('error', 'Authentication failed. Please try again.');
        }
    }

    /**
     * Log the user out
     */
    public function logout(Request $request): RedirectResponse
    {
        $redirectUrl = $this->postLogoutRedirectUrl($request);
        $guestLocale = $request->session()->get('guest_locale');

        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($guestLocale) {
            $request->session()->put('locale', $guestLocale);
            $request->session()->put('guest_locale', $guestLocale);
        }

        return redirect()->to($redirectUrl);
    }

    /**
     * Get current authenticated user
     *
     * Per OpenAPI spec: GET /auth/me
     * Returns User schema: id, osu_id, username, avatar_url, country_code,
     *                      main_mode, role, setup_complete, created_at
     * Returns 401 Unauthorized if not authenticated
     */
    public function me(Request $request): JsonResponse
    {
        if (! auth()->check()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'country_code' => $user->country_code,
            'main_mode' => $user->main_mode,
            'role' => $user->role,
            'setup_complete' => $user->hasCompletedSetup(),
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }

    /**
     * Manual sync of user data from osu! API
     *
     * FR-006: Users can sync once per 24 hours
     * Rate limited via 'user_sync' limiter in RouteServiceProvider
     *
     * POST /auth/sync
     * Returns 200 with sync job dispatched, 429 if rate limited
     */
    public function sync(Request $request): JsonResponse
    {
        if (! auth()->check()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = $request->user();

        // Dispatch sync job
        SyncUserFromOsu::dispatch($user);

        return response()->json([
            'message' => 'Sync job dispatched successfully.',
            'data' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'last_sync' => $user->osu_data_synced_at?->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Detect locale from browser Accept-Language header.
     *
     * Maps common Accept-Language values to supported locales.
     * Falls back to null if no supported locale is detected.
     *
     * @param  Request  $request  The HTTP request
     * @return string|null Detected locale code (e.g., 'en', 'ko', 'ru') or null
     */
    private function detectLocaleFromRequest(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if (! $acceptLanguage) {
            return null;
        }

        // Supported locales mapping
        $supportedLocales = [
            'ko' => 'ko', // Korean
            'en' => 'en', // English
            'ru' => 'ru', // Russian
            'es' => 'es', // Spanish
            'zh' => 'zh-Hans',
            'zh_cn' => 'zh-Hans',
            'zh_hans' => 'zh-Hans',
            'zh_tw' => 'zh-Hant',
            'zh_hk' => 'zh-Hant',
            'zh_hant' => 'zh-Hant',
        ];

        // Parse Accept-Language header (e.g., "ko-KR,ko;q=0.9,en;q=0.8")
        preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)\s*(?:;\s*q\s*=\s*(1\.0|0?\.[0-9]+))?/i', $acceptLanguage, $matches);

        if (empty($matches[1])) {
            return null;
        }

        // Build locale list with quality values
        $locales = [];
        foreach ($matches[1] as $i => $locale) {
            $locale = strtolower(str_replace('-', '_', $locale));
            $quality = isset($matches[2][$i]) ? (float) $matches[2][$i] : 1.0;
            $locales[$locale] = $quality;
        }

        // Sort by quality (highest first)
        arsort($locales);

        // Check each locale against supported locales
        foreach (array_keys($locales) as $locale) {
            // Extract primary language (e.g., 'ko' from 'ko_KR')
            $primaryLang = substr($locale, 0, 2);

            // Check exact match first
            if (isset($supportedLocales[$locale])) {
                return $supportedLocales[$locale];
            }

            // Check primary language match
            if (isset($supportedLocales[$primaryLang])) {
                return $supportedLocales[$primaryLang];
            }
        }

        return null;
    }

    /**
     * Get the safe post-logout destination.
     */
    private function postLogoutRedirectUrl(Request $request): string
    {
        $fallbackUrl = route('home');
        $previousUrl = url()->previous();

        if (! $previousUrl || ! $this->isSameHostUrl($previousUrl, $request)) {
            return $fallbackUrl;
        }

        $previousRequest = Request::create($previousUrl, 'GET');

        try {
            $route = app('router')->getRoutes()->match($previousRequest);
        } catch (\Throwable) {
            return $fallbackUrl;
        }

        if (! in_array($route->getName(), $this->publicPostLogoutRouteNames(), true)) {
            return $fallbackUrl;
        }

        return $this->sanitizePostLogoutUrl($previousUrl);
    }

    private function storeLoginRedirectUrl(Request $request): void
    {
        $previousUrl = url()->previous();

        if (! $previousUrl || ! $this->isSameHostUrl($previousUrl, $request)) {
            session()->forget('login_redirect_url');

            return;
        }

        $previousRequest = Request::create($previousUrl, 'GET');

        try {
            $route = app('router')->getRoutes()->match($previousRequest);
        } catch (\Throwable) {
            session()->forget('login_redirect_url');

            return;
        }

        if (! in_array($route->getName(), $this->publicLoginReturnRouteNames(), true)) {
            session()->forget('login_redirect_url');

            return;
        }

        session()->put('login_redirect_url', $this->sanitizePostLogoutUrl($previousUrl));
    }

    /**
     * @return list<string>
     */
    private function publicPostLogoutRouteNames(): array
    {
        return [
            'home',
            'tournaments.index',
            'tournaments.show',
            'users.show',
        ];
    }

    /**
     * @return list<string>
     */
    private function publicLoginReturnRouteNames(): array
    {
        return [
            'tournaments.index',
            'tournaments.show',
            'users.show',
        ];
    }

    private function isSameHostUrl(string $url, Request $request): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return ! $host || $host === $request->getHost();
    }

    private function sanitizePostLogoutUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $query = Arr::except($query, ['eligible', 'eligible_only']);
        }

        $queryString = http_build_query($query);

        return url($path.($queryString ? '?'.$queryString : ''));
    }
}
