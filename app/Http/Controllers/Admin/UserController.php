<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSelectedUserProfilesJob;
use App\Models\AdminMaintenanceRun;
use App\Models\User;
use App\Services\AdminMaintenanceRunRecorder;
use App\Services\AuditLogger;
use App\Services\OrphanedUserCleanupService;
use App\Services\OsuApiService;
use App\Services\UserDeletionCleanupService;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Admin User Management Controller
 *
 * Per OpenAPI spec:
 * - GET /admin/users - List users (Master only)
 * - PATCH /admin/users/{userId}/role - Update user role (Master only)
 */
class UserController extends Controller
{
    private const MODES = ['osu', 'taiko', 'catch', 'mania'];

    private const ROLES = ['player', 'admin', 'master'];

    private const LOCALES = ['en', 'ko', 'ru', 'zh-Hans', 'zh-Hant', 'es'];

    /** @var array<string, string> */
    private const SORT_COLUMNS = [
        'id' => 'id',
        'user' => 'username',
        'osu_id' => 'osu_id',
        'country' => 'country_code',
        'mode' => 'main_mode',
        'role' => 'role',
        'last_login' => 'last_login_at',
        'rank_updated' => 'latest_rank_recorded_at',
        'locale' => 'locale',
    ];

    public function __construct(
        private AuditLogger $auditLogger,
        private OsuApiService $osuApiService,
        private UserDeletionCleanupService $userDeletionCleanup
    ) {}

    /**
     * List users (Master only)
     *
     * Per OpenAPI: operationId: listUsers
     * Query params: mode, status, locale, search, sort, direction, page
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = User::query()
            ->withMax('rankHistory as latest_rank_recorded_at', 'recorded_at');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $users = $query->paginate($request->integer('per_page', 20))
            ->withQueryString();

        // Return JSON for API requests
        if ($request->wantsJson()) {
            return response()->json($this->paginatedUsersPayload($users));
        }

        $options = [
            'modes' => self::MODES,
            'roles' => self::ROLES,
            'locales' => self::LOCALES,
            'statuses' => ['logged_in', 'auto', 'manual'],
            'sorts' => array_keys(self::SORT_COLUMNS),
        ];

        // Return View for web requests
        return view('admin.users.index', compact('users', 'options'));
    }

    /**
     * Search users by username (for admin staff management)
     * Also supports adding unregistered osu! users
     *
     * GET /api/users/search?q=username
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q', '');

        if (strlen($query) < 3) {
            return response()->json([], 200);
        }

        $users = User::query()
            ->where(function ($q) use ($query) {
                $q->where('username', 'ilike', "%{$query}%")
                    ->orWhere('previous_usernames', 'ilike', "%\"{$query}%");
            })
            ->orderBy('username')
            ->limit(10)
            ->get();

        $results = $users->map(function (User $user) {
            return [
                'id' => $user->id,
                'osu_id' => $user->osu_id,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
                'registered' => true,
            ];
        })->toArray();

        // Add option to add unregistered user if no exact match found
        $exactMatch = collect($results)->first(fn ($u) => strcasecmp($u['username'], $query) === 0);

        if (! $exactMatch && strlen($query) >= 3) {
            $results[] = [
                'id' => 'new:'.$query,
                'osu_id' => null,
                'username' => $query,
                'avatar_url' => 'https://a.ppy.sh/',
                'registered' => false,
                'is_new' => true,
            ];
        }

        return response()->json($results);
    }

    /**
     * Sync user data from osu! API by username
     * Creates or updates user with fresh data from osu!
     *
     * GET /api/users/{username}/sync
     */
    public function sync(Request $request, string $username): JsonResponse
    {
        Log::info("Syncing user: {$username}");

        // Fetch from osu! API by username
        $osuUserData = $this->osuApiService->getUserByUsername($username);

        if (! $osuUserData) {
            Log::warning("User not found on osu!: {$username}");

            return response()->json(['error' => 'User not found on osu!'], 404);
        }

        Log::info("osu! API returned data for {$username}", [
            'osu_id' => $osuUserData['id'],
        ]);

        // Create or update user
        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'osu_id' => $osuUserData['id'],
                'country_code' => $osuUserData['country_code'],
                'osu_data_synced_at' => now(),
            ]
        );

        // Refresh to get fresh data from database
        $user->refresh();

        Log::info("User synced successfully: {$username} -> DB ID: {$user->id}, osu_id: {$user->osu_id}");

        // Log the sync action (if authenticated)
        if (Auth::check()) {
            $this->auditLogger->log('user_sync', 'user', $user->id, [
                'username' => $username,
                'osu_id' => $user->osu_id,
            ]);
        }

        return response()->json($user);
    }

    /**
     * Update user role (Master only)
     *
     * Per OpenAPI: operationId: updateUserRole
     * Body: { role: 'player' | 'admin' }
     * Returns 400 BadRequest for validation errors (per OpenAPI spec)
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        return $this->update($request, $user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'main_mode' => 'sometimes|nullable|string|in:osu,taiko,catch,mania',
            'role' => 'sometimes|required|string|in:player,admin,master',
            'locale' => 'sometimes|required|string|in:en,ko,ru,zh-Hans,zh-Hant,es',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 400);
        }

        $validated = $validator->validated();

        if ($validated === []) {
            return response()->json([
                'message' => 'No changes were provided.',
            ], 400);
        }

        $oldValues = $user->only(['main_mode', 'role', 'locale']);

        if (array_key_exists('main_mode', $validated)) {
            $user->main_mode = $validated['main_mode'];
            $user->main_mode_source = 'manual_update';
        }

        if (array_key_exists('role', $validated)) {
            $user->role = $validated['role'];
        }

        if (array_key_exists('locale', $validated)) {
            $user->locale = $validated['locale'];
        }

        $user->save();

        $this->auditLogger->log(
            action: 'user_updated',
            entityType: 'user',
            entityId: $user->id,
            details: [
                'old' => $oldValues,
                'new' => $user->fresh()->only(['main_mode', 'role', 'locale']),
            ]
        );

        $user = User::query()
            ->withMax('rankHistory as latest_rank_recorded_at', 'recorded_at')
            ->findOrFail($user->id);

        return response()->json($this->userPayload($user));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot delete your own active account.',
            ], 400);
        }

        $connections = $this->userDeletionCleanup->connectionCounts($user);
        if (! $request->boolean('confirmed') && $this->userDeletionCleanup->hasConnections($connections)) {
            return response()->json([
                'message' => $this->deleteConfirmationMessage($user, $connections),
                'requires_confirmation' => true,
                'connections' => $connections,
            ], 409);
        }

        $cleanup = DB::transaction(function () use ($user): array {
            $cleanup = $this->userDeletionCleanup->cleanupBeforeDelete($user);
            $user->delete();

            return $cleanup;
        });

        $this->auditLogger->log('user_deleted', 'user', $user->id, [
            'username' => $user->username,
            'osu_id' => $user->osu_id,
            'cleanup' => $cleanup,
        ]);

        return response()->json(['deleted' => 1]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $userIds = $this->validatedUserIds($request);

        if (in_array(Auth::id(), $userIds, true)) {
            return response()->json([
                'message' => 'You cannot delete your own active account.',
            ], 400);
        }

        if (! $request->boolean('confirmed')) {
            $connectedUsers = User::query()
                ->whereIn('id', $userIds)
                ->get()
                ->map(function (User $user): array {
                    $connections = $this->userDeletionCleanup->connectionCounts($user);

                    return [
                        'id' => $user->id,
                        'username' => $user->username,
                        'connections' => $connections,
                        'has_connections' => $this->userDeletionCleanup->hasConnections($connections),
                    ];
                })
                ->filter(fn (array $user): bool => (bool) $user['has_connections'])
                ->values();

            if ($connectedUsers->isNotEmpty()) {
                return response()->json([
                    'message' => $this->bulkDeleteConfirmationMessage($connectedUsers->all()),
                    'requires_confirmation' => true,
                    'users' => $connectedUsers,
                ], 409);
            }
        }

        $deleted = 0;

        User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->each(function (User $user) use (&$deleted) {
                $cleanup = DB::transaction(function () use ($user): array {
                    $cleanup = $this->userDeletionCleanup->cleanupBeforeDelete($user);
                    $user->delete();

                    return $cleanup;
                });
                $deleted++;

                $this->auditLogger->log('user_deleted', 'user', $user->id, [
                    'username' => $user->username,
                    'osu_id' => $user->osu_id,
                    'bulk' => true,
                    'cleanup' => $cleanup,
                ]);
            });

        return response()->json(['deleted' => $deleted]);
    }

    public function syncSelected(Request $request, AdminMaintenanceRunRecorder $maintenanceRuns): JsonResponse
    {
        $userIds = $this->validatedUserIds($request);
        $currentYear = (int) date('Y');
        $startYear = $request->integer('start_year', $currentYear - 1);
        $endYear = $request->integer('end_year', $currentYear);
        $chunkSize = max(1, min($request->integer('chunk_size', 10), 50));
        $skipSip = $request->boolean('skip_sip', false);
        $chunks = array_chunk($userIds, $chunkSize);

        $run = $maintenanceRuns->start(AdminMaintenanceRun::COMMAND_SYNC_USER_PROFILES, [
            'selected_user_ids' => $userIds,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'skip_sip' => $skipSip,
            'chunk_size' => $chunkSize,
        ], 'web');

        $maintenanceRuns->updateSummary($run, [
            'selected' => count($userIds),
            'processed' => 0,
            'synced' => 0,
            'failed' => 0,
            'total_batches' => count($chunks),
        ]);

        $jobs = collect($chunks)
            ->map(fn (array $ids) => new SyncSelectedUserProfilesJob($ids, $startYear, $endYear, $skipSip, $run->id))
            ->all();

        $batch = Bus::batch($jobs)
            ->then(fn (Batch $batch) => app(AdminMaintenanceRunRecorder::class)->complete($run->id))
            ->catch(fn (Batch $batch, \Throwable $e) => app(AdminMaintenanceRunRecorder::class)->fail($run->id, $e->getMessage()))
            ->name('Sync selected user profiles')
            ->dispatch();

        $this->auditLogger->log('selected_user_sync_dispatched', 'maintenance_run', $run->id, [
            'user_ids' => $userIds,
            'batch_id' => $batch->id,
        ]);

        return response()->json([
            'message' => 'Selected user sync queued.',
            'maintenance_run_id' => $run->id,
            'batch_id' => $batch->id,
            'selected' => count($userIds),
        ]);
    }

    public function cleanupOrphans(Request $request, OrphanedUserCleanupService $orphanedUsers): JsonResponse
    {
        $limit = max(1, min($request->integer('limit', 500), 1000));
        $minAge = max(0, $request->integer('min_age', 0));
        $whitelist = $this->configuredCleanupWhitelist();
        $deleted = 0;

        $orphanedUsers->query($whitelist, $minAge)
            ->limit($limit)
            ->get()
            ->each(function (User $user) use (&$deleted, $orphanedUsers, $whitelist) {
                if ($user->id === Auth::id() || $orphanedUsers->isProtected($user, $whitelist)) {
                    return;
                }

                $cleanup = DB::transaction(function () use ($user): array {
                    $cleanup = $this->userDeletionCleanup->cleanupBeforeDelete($user);
                    $user->delete();

                    return $cleanup;
                });
                $deleted++;

                $this->auditLogger->log('orphan_user_deleted', 'user', $user->id, [
                    'username' => $user->username,
                    'osu_id' => $user->osu_id,
                    'cleanup' => $cleanup,
                ]);
            });

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if (in_array($request->query('mode'), self::MODES, true)) {
            $query->where('main_mode', $request->query('mode'));
        }

        if (in_array($request->query('locale'), self::LOCALES, true)) {
            $query->where('locale', $request->query('locale'));
        }

        match ($request->query('status')) {
            'logged_in' => $query->where('main_mode_source', 'oauth_setup'),
            'auto' => $query->where(fn ($q) => $q->where('main_mode_source', 'auto_detected')->orWhereNull('main_mode_source')),
            'manual' => $query->where('main_mode_source', 'manual_update'),
            default => null,
        };

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'ilike', "%{$search}%");

                if (ctype_digit($search)) {
                    $q->orWhere('osu_id', (int) $search)
                        ->orWhereRaw('CAST(osu_id AS TEXT) LIKE ?', ["%{$search}%"]);
                }
            });
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applySorting(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'id');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'status') {
            $query->orderByRaw(
                "CASE WHEN main_mode_source = 'oauth_setup' THEN 1 WHEN main_mode_source = 'manual_update' THEN 3 ELSE 2 END {$direction}"
            )->orderBy('id', 'desc');

            return;
        }

        $column = self::SORT_COLUMNS[$sort] ?? self::SORT_COLUMNS['id'];
        $query->orderBy($column, $direction);

        if ($column !== 'id') {
            $query->orderBy('id', 'desc');
        }
    }

    /**
     * @param  LengthAwarePaginator<int, User>  $users
     * @return array<string, mixed>
     */
    private function paginatedUsersPayload(LengthAwarePaginator $users): array
    {
        return [
            'data' => $users->getCollection()->map(fn (User $user) => $this->userPayload($user))->values(),
            'links' => [
                'first' => $users->url(1),
                'last' => $users->url($users->lastPage()),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $users->currentPage(),
                'from' => $users->firstItem(),
                'last_page' => $users->lastPage(),
                'links' => collect($users->linkCollection())->map(fn ($link) => [
                    'url' => $link['url'],
                    'label' => $link['label'],
                    'active' => $link['active'],
                ])->all(),
                'path' => $users->path(),
                'per_page' => $users->perPage(),
                'to' => $users->lastItem(),
                'total' => $users->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $latestRankRecordedAtValue = $user->getAttribute('latest_rank_recorded_at');
        $latestRankRecordedAt = $latestRankRecordedAtValue ? Carbon::parse($latestRankRecordedAtValue) : null;

        return [
            'id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'country_code' => $user->country_code,
            'main_mode' => $user->main_mode,
            'main_mode_source' => $user->main_mode_source,
            'role' => $user->role,
            'status' => $this->statusFor($user),
            'locale' => $user->locale,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'latest_rank_recorded_at' => $latestRankRecordedAt?->toIso8601String(),
            'setup_complete' => $user->hasCompletedSetup(),
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }

    private function statusFor(User $user): string
    {
        return match ($user->main_mode_source) {
            'oauth_setup' => 'logged_in',
            'manual_update' => 'manual',
            default => 'auto',
        };
    }

    /**
     * @param  array<string, int>  $connections
     */
    private function deleteConfirmationMessage(User $user, array $connections): string
    {
        return "Delete {$user->username}? This user has tournament connections: ".$this->connectionSummary($connections).'. Their account will be soft-deleted and their own staff, podium, and participation presence will be removed.';
    }

    /**
     * @param  array<int, array{id: int, username: string, connections: array<string, int>, has_connections: bool}>  $users
     */
    private function bulkDeleteConfirmationMessage(array $users): string
    {
        $summaries = collect($users)
            ->take(5)
            ->map(fn (array $user): string => $user['username'].' ('.$this->connectionSummary($user['connections']).')')
            ->join('; ');

        $remaining = count($users) > 5 ? ' and '.(count($users) - 5).' more' : '';

        return 'Some selected users have tournament connections: '.$summaries.$remaining.'. Confirm to soft-delete them and remove only their own staff, podium, and participation presence.';
    }

    /**
     * @param  array<string, int>  $connections
     */
    private function connectionSummary(array $connections): string
    {
        $labels = [
            'participation_records' => 'participation records',
            'teammate_rosters' => 'teammate rosters',
            'staff_roles' => 'staff roles',
            'podium_rows' => 'podium rows',
            'contributions' => 'contributions',
            'hosted_tournaments' => 'hosted tournaments',
        ];

        $parts = collect($connections)
            ->filter(fn (int $count): bool => $count > 0)
            ->map(fn (int $count, string $key): string => $count.' '.($labels[$key] ?? str($key)->replace('_', ' ')->toString()))
            ->values()
            ->all();

        return $parts === [] ? 'none' : implode(', ', $parts);
    }

    /**
     * @return array<int>
     */
    private function validatedUserIds(Request $request): array
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:500',
            'user_ids.*' => 'integer|distinct|exists:users,id',
        ]);

        /** @var array<int, int|string> $userIds */
        $userIds = $validated['user_ids'];

        return array_map('intval', $userIds);
    }

    /**
     * @return array<string>
     */
    private function configuredCleanupWhitelist(): array
    {
        $whitelist = config('user-cleanup.whitelist', []);

        if (! is_array($whitelist)) {
            return [];
        }

        return array_values(array_filter($whitelist, 'is_string'));
    }
}
