<?php

namespace App\Services;

use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserRankHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OsuApiService
{
    private const BASE_URL = 'https://osu.ppy.sh/api/v2';

    private const REQUEST_INTERVAL_PER_USER_MICROSECONDS = 1_000_000;

    private const MAX_RATE_LIMIT_RETRIES = 3;

    private const DEFAULT_RATE_LIMIT_RETRY_SECONDS = 60;

    /**
     * Get OAuth2 access token (client credentials flow)
     */
    private function getAccessToken(): string
    {
        return Cache::remember('osu_api_token', now()->addHours(23), function () {
            $response = Http::asForm()->post('https://osu.ppy.sh/oauth/token', [
                'client_id' => config('services.osu.client_id'),
                'client_secret' => config('services.osu.client_secret'),
                'grant_type' => 'client_credentials',
                'scope' => 'public',
            ]);

            if ($response->failed()) {
                Log::error('Failed to get osu! API token', ['response' => $response->body()]);
                throw new \Exception('Failed to authenticate with osu! API');
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Make authenticated request to osu! API.
     *
     * @param  array<string, mixed>  $params
     * @param  int  $retryCount  Internal counter for recursive retries
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $params = [], int $retryCount = 0): array
    {
        $token = $this->getAccessToken();

        $this->waitForRequestSlot($endpoint, $params);

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::BASE_URL.$endpoint, $params);

        // 429s come from osu!, not Laravel's local throttling. Keep this explicit so
        // sync commands can log the real upstream response instead of a generic error.
        if ($response->status() === 429) {
            Log::error('osu! API rate limit exceeded', [
                'endpoint' => $endpoint,
                'retry_after' => $response->header('Retry-After'),
                'retry_count' => $retryCount,
                'body' => $response->body(),
            ]);

            if ($retryCount < self::MAX_RATE_LIMIT_RETRIES) {
                $retryAfter = $this->rateLimitRetryAfterSeconds($response->header('Retry-After'));

                Log::warning('Retrying osu! API request after upstream 429', [
                    'endpoint' => $endpoint,
                    'retry_after_seconds' => $retryAfter,
                    'next_retry_count' => $retryCount + 1,
                ]);

                sleep($retryAfter);

                return $this->request($endpoint, $params, $retryCount + 1);
            }

            throw new \Exception('osu! API returned 429 Too Many Requests.');
        }

        if ($response->failed()) {
            Log::error('osu! API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('osu! API request failed');
        }

        return $response->json();
    }

    /**
     * Pace osu! API requests across queue workers.
     *
     * The sync commands intentionally batch 50 users per /users request. Without a
     * shared request slot, queued chunks can still start at the same time and trip
     * osu!'s upstream 429 response.
     */
    /**
     * @param  array<string, mixed>  $params
     */
    private function waitForRequestSlot(string $endpoint, array $params): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $waitedMicroseconds = 0;
        $requestStartedAt = microtime(true);
        $lastRequestAt = 0.0;
        $requestIntervalMicroseconds = $this->requestIntervalMicroseconds($endpoint, $params);

        $lock = Cache::lock('osu_api_request_slot', 30);

        $lock->block(10, function () use ($requestIntervalMicroseconds, &$waitedMicroseconds, &$requestStartedAt, &$lastRequestAt) {
            $now = microtime(true);
            $lastRequestAt = (float) Cache::get('osu_api_last_request_at', 0);
            $earliestRequestAt = $lastRequestAt > 0
                ? $lastRequestAt + ($requestIntervalMicroseconds / 1_000_000)
                : $now;

            $requestStartedAt = max($now, $earliestRequestAt);
            $waitedMicroseconds = (int) max(0, ($requestStartedAt - $now) * 1_000_000);

            Cache::put('osu_api_last_request_at', $requestStartedAt, now()->addMinutes(5));
        });

        if ($waitedMicroseconds > 0) {
            Log::debug('Waiting for reserved osu! API request slot', [
                'endpoint' => $endpoint,
                'wait_ms' => (int) ceil($waitedMicroseconds / 1000),
                'interval_ms' => (int) ceil($requestIntervalMicroseconds / 1000),
            ]);

            usleep($waitedMicroseconds);
        }

        Log::info('osu! API request slot acquired', [
            'endpoint' => $endpoint,
            'started_at' => sprintf('%.6f', $requestStartedAt),
            'elapsed_since_previous_ms' => $lastRequestAt > 0
                ? (int) (($requestStartedAt - $lastRequestAt) * 1000)
                : null,
            'interval_ms' => (int) ceil($requestIntervalMicroseconds / 1000),
            'waited_ms' => (int) ceil($waitedMicroseconds / 1000),
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function requestIntervalMicroseconds(string $endpoint, array $params): int
    {
        if ($endpoint === '/users' && isset($params['ids']) && is_array($params['ids'])) {
            return max(1, count($params['ids'])) * self::REQUEST_INTERVAL_PER_USER_MICROSECONDS;
        }

        return self::REQUEST_INTERVAL_PER_USER_MICROSECONDS;
    }

    private function rateLimitRetryAfterSeconds(?string $retryAfter): int
    {
        if (app()->runningUnitTests()) {
            return 0;
        }

        if (is_numeric($retryAfter) && (int) $retryAfter > 0) {
            return (int) $retryAfter;
        }

        return self::DEFAULT_RATE_LIMIT_RETRY_SECONDS;
    }

    /**
     * Get user data from osu! API
     * Cached for 24 hours
     *
     * @return array<string, mixed>
     */
    public function getUser(int $osuId): array
    {
        return Cache::remember("osu_user_{$osuId}", now()->addDay(), function () use ($osuId) {
            return $this->request("/users/{$osuId}");
        });
    }

    /**
     * Get user data for a specific mode from osu! API.
     *
     * @return array<string, mixed>
     */
    public function getUserForMode(int $osuId, string $mode): array
    {
        $apiMode = $this->convertAppModeToApiMode($mode);

        return Cache::remember("osu_user_{$osuId}_{$apiMode}", now()->addHour(), function () use ($osuId, $apiMode) {
            return $this->request("/users/{$osuId}/{$apiMode}");
        });
    }

    /**
     * Get user data from osu! API by username
     * Cached for 24 hours
     *
     * @return array<string, mixed>|null Returns null if user not found
     */
    public function getUserByUsername(string $username): ?array
    {
        $cacheKey = "osu_user_by_username_{$username}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($username) {
            try {
                // CRITICAL: osu! API requires @ prefix for usernames
                return $this->request("/users/@{$username}");
            } catch (\Exception $e) {
                Log::warning("Failed to fetch user by username: {$username}", [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Get multiple users from osu! API in batch
     * Optimized for fetching up to 50 users per request
     *
     * @param  array<int>  $osuIds  Array of osu! user IDs
     * @return array<int, array<string, mixed>> Associative array keyed by osu_id
     */
    public function getUsers(array $osuIds): array
    {
        if (empty($osuIds)) {
            Log::warning('OsuApiService::getUsers() called with empty array');

            return [];
        }

        $inputCount = count($osuIds);
        $uniqueIds = array_unique($osuIds);
        $uniqueCount = count($uniqueIds);

        // Log if duplicates were detected
        if ($inputCount !== $uniqueCount) {
            Log::warning('OsuApiService::getUsers() detected duplicate osu_ids', [
                'input_count' => $inputCount,
                'unique_count' => $uniqueCount,
                'duplicates' => $inputCount - $uniqueCount,
                'sample_ids' => array_slice($osuIds, 0, 10),
            ]);
        }

        Log::info('OsuApiService::getUsers() starting', [
            'input_count' => $inputCount,
            'unique_count' => $uniqueCount,
            'sample_ids' => array_slice($uniqueIds, 0, 5),
        ]);

        // Split into chunks of 50 (osu! API limit per request)
        $chunks = array_chunk($uniqueIds, 50);
        $users = [];

        foreach ($chunks as $index => $chunk) {
            $chunkCount = count($chunk);
            Log::debug('OsuApiService::getUsers() processing chunk', [
                'chunk' => $index + 1,
                'chunk_size' => $chunkCount,
                'total_chunks' => count($chunks),
                'sample_ids' => array_slice($chunk, 0, 5),
            ]);

            $results = $this->request('/users', [
                'ids' => $chunk,
            ]);

            // API returns array wrapped in various formats:
            // 1. ['users' => [['id' => 123, ...], ...]] - wrapped with 'users' key (most common)
            // 2. [['id' => 123, ...], ...] - direct numeric array
            // 3. ['id' => 123, ...] - single user object
            $userArray = $results;
            $responseType = 'unknown';

            // Unwrap if wrapped under 'users' key (standard API response)
            if (isset($results['users'])) {
                $userArray = $results['users'];
                $responseType = 'wrapped_users_key';
            } elseif (! is_numeric(array_key_first($results))) {
                // Not a numeric array, wrap it
                $userArray = [$results];
                $responseType = 'single_object_wrapped';
            } else {
                $responseType = 'numeric_array';
            }

            $returnedCount = count($userArray);
            Log::debug('OsuApiService::getUsers() API response received', [
                'chunk' => $index + 1,
                'response_type' => $responseType,
                'requested_count' => $chunkCount,
                'returned_count' => $returnedCount,
                'missing' => $chunkCount - $returnedCount,
            ]);

            $validUsersInChunk = 0;
            foreach ($userArray as $user) {
                if (isset($user['id'])) {
                    $users[$user['id']] = $user;
                    $validUsersInChunk++;
                } else {
                    Log::warning('OsuApiService::getUsers() skipping user without id', [
                        'chunk' => $index + 1,
                        'user_data' => $user,
                    ]);
                }
            }

            Log::debug('OsuApiService::getUsers() chunk processed', [
                'chunk' => $index + 1,
                'valid_users' => $validUsersInChunk,
                'skipped_users' => $returnedCount - $validUsersInChunk,
            ]);

            // Track missing users from this chunk
            $returnedIds = array_column($userArray, 'id');
            $missingIds = array_diff($chunk, $returnedIds);
            if (! empty($missingIds)) {
                Log::warning('OsuApiService::getUsers() API did not return some users', [
                    'chunk' => $index + 1,
                    'missing_count' => count($missingIds),
                    'missing_ids' => array_slice($missingIds, 0, 10), // First 10
                ]);
            }

        }

        $finalCount = count($users);
        $totalMissing = $uniqueCount - $finalCount;

        $lossPercentage = $uniqueCount > 0 ? round(($totalMissing / $uniqueCount) * 100, 2) : 0;

        Log::info('OsuApiService::getUsers() completed', [
            'input_count' => $inputCount,
            'unique_count' => $uniqueCount,
            'final_count' => $finalCount,
            'total_missing' => $totalMissing,
            'loss_percentage' => $lossPercentage,
        ]);

        return $users;
    }

    /**
     * Get user badges
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserBadges(int $osuId): array
    {
        $user = $this->getUser($osuId);

        return $user['badges'] ?? [];
    }

    /**
     * Get user rank statistics for a specific mode
     * Cached for 1 hour
     *
     * @return array{global_rank: int|null, country_rank: int|null, pp: float|null}|null
     */
    public function getUserRankStats(int $osuId, string $mode): ?array
    {
        $cacheKey = "osu_rank_{$osuId}_{$mode}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($osuId, $mode) {
            $user = $this->request("/users/{$osuId}/{$mode}");

            return [
                'global_rank' => $user['statistics']['global_rank'] ?? null,
                'country_rank' => $user['statistics']['country_rank'] ?? null,
                'pp' => $user['statistics']['pp'] ?? null,
            ];
        });
    }

    /**
     * Get mania-specific rankings (4K and 7K variants)
     * Cached for 1 hour
     *
     * @return array<string, int|null>
     */
    public function getUserManiaRankings(int $osuId): array
    {
        $cacheKey = "osu_mania_rankings_{$osuId}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($osuId) {
            $rankings = [
                '4k' => ['global_rank' => null, 'pp' => null],
                '7k' => ['global_rank' => null, 'pp' => null],
            ];

            try {
                // Single API call to get mania mode with all variants
                $response = $this->request("/users/{$osuId}/mania");

                // Parse variants array (not an object!)
                if (isset($response['statistics']['variants']) && is_array($response['statistics']['variants'])) {
                    foreach ($response['statistics']['variants'] as $variant) {
                        if (isset($variant['variant'])) {
                            $variantKey = $variant['variant']; // '4k' or '7k'
                            if (in_array($variantKey, ['4k', '7k'])) {
                                $rankings[$variantKey] = [
                                    'global_rank' => $variant['global_rank'] ?? null,
                                    'pp' => $variant['pp'] ?? null,
                                ];
                            }
                        }
                    }
                }

                Log::debug('Fetched mania variant rankings', [
                    'osu_id' => $osuId,
                    '4k_rank' => $rankings['4k']['global_rank'],
                    '7k_rank' => $rankings['7k']['global_rank'],
                ]);

            } catch (\Exception $e) {
                Log::warning('Failed to fetch mania variant rankings', [
                    'osu_id' => $osuId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $rankings;
        });
    }

    /**
     * Get match data from osu! API
     * Cached for 1 hour (historical match data rarely changes)
     *
     * @return array<string, mixed>
     */
    public function getMatch(int $matchId): array
    {
        return Cache::remember("osu_match_{$matchId}", now()->addHour(), function () use ($matchId) {
            try {
                $data = $this->request("/matches/{$matchId}");

                Log::info('Match fetched from osu! API', [
                    'match_id' => $matchId,
                    'match_name' => $data['match']['name'] ?? 'Unknown',
                ]);

                return $data;
            } catch (\Exception $e) {
                // Handle specific error cases
                if (str_contains($e->getMessage(), '404')) {
                    Log::warning('Match not found on osu! API', ['match_id' => $matchId]);
                    throw new \Exception("Match {$matchId} not found. The match may have been deleted or made private.");
                }

                if (str_contains($e->getMessage(), '403')) {
                    Log::warning('Match is private or restricted', ['match_id' => $matchId]);
                    throw new \Exception("Match {$matchId} is private or restricted and cannot be accessed.");
                }

                throw $e;
            }
        });
    }

    /**
     * Get forum topics from a specific forum
     *
     * @return array<string, mixed>
     */
    public function getForumTopics(int $forumId, int $limit = 50, int $page = 1): array
    {
        return Cache::remember("forum_topics_{$forumId}_page_{$page}", now()->addMinutes(60), function () use ($forumId, $limit, $page) {
            return $this->request('/forums/topics', [
                'forum_id' => $forumId,
                'limit' => $limit,
                'sort' => 'id_desc',
                'page' => $page,
            ]);
        });
    }

    /**
     * Get a specific forum topic with posts
     * Used for parsing tournament information
     *
     * @return array<string, mixed>|null
     */
    public function getForumTopic(int $topicId): ?array
    {
        return Cache::remember("forum_topic_{$topicId}", now()->addMinutes(60), function () use ($topicId) {
            try {
                $topicData = $this->request("/forums/topics/{$topicId}");

                // Extract post content from first post's body.raw
                // API returns posts[] array, each with body: {html, raw}
                // We add top-level 'post' key with raw BBCode for convenience
                if (isset($topicData['posts']) && ! empty($topicData['posts']) && isset($topicData['posts'][0]['body']['raw'])) {
                    $topicData['post'] = $topicData['posts'][0]['body']['raw'];
                }

                return $topicData;
            } catch (\Exception $e) {
                Log::warning('Failed to fetch forum topic', [
                    'topic_id' => $topicId,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Sync user data from osu! API to database
     */
    public function syncUser(int $osuId): User
    {
        $userData = $this->getUser($osuId);

        $user = User::updateOrCreate(
            ['osu_id' => $osuId],
            [
                'username' => $userData['username'],
                'country_code' => $userData['country_code'],
                'previous_usernames' => $userData['previous_usernames'] ?? null,
                'osu_data_synced_at' => now(),
            ]
        );

        // Sync badges
        $this->syncUserBadges($user, $userData['badges'] ?? []);

        // Sync rank history for all modes
        $this->syncUserRanks($user);

        return $user;
    }

    /**
     * Sync full user data including rank, PP, badges, and rank history
     * This method updates all user statistics and related data
     */
    public function syncUserData(User $user): void
    {
        // Get fresh user data from API (use osu mode for basic profile info)
        $cacheKey = "osu_user_{$user->osu_id}_osu";
        $userData = Cache::remember($cacheKey, now()->addHour(), function () use ($user) {
            return $this->request("/users/{$user->osu_id}/osu");
        });

        // Update basic profile information
        $user->username = $userData['username'];
        $user->country_code = $userData['country_code'];
        $user->previous_usernames = $userData['previous_usernames'] ?? null;
        $user->osu_data_synced_at = now();
        $user->save();

        // Sync badges with BWS eligibility check
        if (isset($userData['badges'])) {
            $this->syncUserBadges($user, $userData['badges']);
        }

        // Sync ALL mode ranks (not just main_mode) - FIX FOR BROKEN ELIGIBLE FILTER
        $apiModes = ['osu', 'taiko', 'fruits', 'mania'];
        foreach ($apiModes as $apiMode) {
            $rankData = $this->getUserRankStats($user->osu_id, $apiMode);

            if ($rankData) {
                $appMode = $this->convertApiModeToAppMode($apiMode);
                UserRankHistory::updateOrCreate(
                    ['user_id' => $user->id, 'mode' => $appMode],
                    [
                        'rank' => $rankData['global_rank'],
                        'country_rank' => $rankData['country_rank'],
                        'pp' => $rankData['pp'],
                        'recorded_at' => now(),
                    ]
                );
            }
        }

        // Sync mania variants (4K, 7K) to UserRankHistory and users table
        $maniaRankings = $this->getUserManiaRankings($user->osu_id);

        // Update users table with mania variant ranks
        $user->rank_mania_4k = $maniaRankings['4k']['global_rank'] ?? null;
        $user->rank_mania_7k = $maniaRankings['7k']['global_rank'] ?? null;
        $user->save();

        // Also store in UserRankHistory for historical tracking
        foreach (['4k', '7k'] as $variant) {
            if (isset($maniaRankings[$variant]['global_rank'])) {
                UserRankHistory::updateOrCreate(
                    ['user_id' => $user->id, 'mode' => $variant],
                    [
                        'rank' => $maniaRankings[$variant]['global_rank'],
                        'pp' => $maniaRankings[$variant]['pp'],
                        'country_rank' => null,
                        'recorded_at' => now(),
                    ]
                );
            }
        }

        Log::info('User data synced from osu! API', [
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
        ]);
    }

    /**
     * Sync current rank statistics from API statistics data
     * This stores the user's current rank, PP, and country rank
     *
     * @param  array<string, mixed>  $statistics
     *
     * @phpstan-ignore method.unused (Reserved for future use)
     */
    private function syncCurrentRankStats(User $user, string $mode, array $statistics): void
    {
        UserRankHistory::updateOrCreate(
            ['user_id' => $user->id, 'mode' => $mode],
            [
                'rank' => $statistics['global_rank'] ?? null,
                'country_rank' => $statistics['country_rank'] ?? null,
                'pp' => $statistics['pp'] ?? null,
                'recorded_at' => now(),
            ]
        );
    }

    /**
     * Sync historical rank data from API rank_history.data
     * Note: This stores historical rank snapshots, not current stats
     * For current stats, use syncCurrentRankStats() instead
     *
     * @param  array<int, int|null>  $rankData
     *
     * @phpstan-ignore method.unused (Reserved for future use with detailed rank history tracking)
     */
    private function syncRankHistory(User $user, string $mode, array $rankData): void
    {
        // Rank history data is an array of ranks for the last 90 days
        // Each entry represents rank at intervals (approximately every 1.5 days)
        $dataPoints = count($rankData);

        if ($dataPoints === 0) {
            return;
        }

        // Calculate approximate dates for each data point
        // osu! provides roughly 60 data points for 90 days
        $daysPerPoint = 90 / max($dataPoints, 1);

        foreach ($rankData as $index => $rank) {
            // Skip null values
            if ($rank === null) {
                continue;
            }

            // Calculate date for this data point (going backwards from today)
            $daysAgo = (int) (($dataPoints - $index - 1) * $daysPerPoint);
            $recordedAt = now()->subDays($daysAgo);

            UserRankHistory::create([
                'user_id' => $user->id,
                'mode' => $mode,
                'rank' => $rank,
                'recorded_at' => $recordedAt,
            ]);
        }
    }

    /**
     * Sync user badges - SAVE ALL BADGES
     * Saves all badges from osu! API, marks tournament badges with tournament_id
     * Display logic filters to show only tournament badges (tournament_id IS NOT NULL)
     *
     * @param  array<int, array<string, mixed>>  $badges
     */
    private function syncUserBadges(User $user, array $badges): void
    {
        // Get all tournament winners for this user to match against
        $userTournamentWinners = TournamentWinner::where('user_id', $user->id)
            ->whereNotNull('badge_description')
            ->with('tournament')
            ->get()
            ->keyBy('tournament_id');

        $syncedCount = 0;
        $tournamentBadgeCount = 0;
        $nonTournamentBadgeCount = 0;

        foreach ($badges as $badge) {
            // Try to match this badge to a tournament winner record
            $matchedWinner = $this->matchBadgeToTournamentWinner($badge, $userTournamentWinners);

            if ($matchedWinner) {
                // This is a tournament badge - save with tournament_id
                $user->badges()->updateOrCreate(
                    [
                        'tournament_id' => $matchedWinner->tournament_id,
                    ],
                    [
                        'name' => $badge['description'],
                        'image_url' => $badge['image_url'],
                        'image_2x_url' => $badge['image_2x_url'] ?? null,
                        'badge_url' => $badge['url'] ?? null,
                        'awarded_at' => $badge['awarded_at'],
                        'is_bws_eligible' => true,  // Tournament badges are always BWS eligible
                    ]
                );

                // Update the tournament winner record with badge data to keep them in sync
                $matchedWinner->update([
                    'badge_description' => $badge['description'],
                    'badge_image_url' => $badge['image_url'],
                    'badge_image_2x_url' => $badge['image_2x_url'] ?? null,
                    'badge_awarded_at' => $badge['awarded_at'],
                    'badge_url' => $badge['url'] ?? $matchedWinner->badge_url,
                ]);

                $tournamentBadgeCount++;

                Log::debug('Synced tournament badge for user', [
                    'user_id' => $user->id,
                    'tournament_id' => $matchedWinner->tournament_id,
                    'badge' => $badge['description'],
                ]);
            } else {
                // Not a tournament badge - save WITHOUT tournament_id
                // Display logic will hide these (WHERE tournament_id IS NOT NULL)
                $user->badges()->updateOrCreate(
                    [
                        'name' => $badge['description'],
                    ],
                    [
                        'image_url' => $badge['image_url'],
                        'image_2x_url' => $badge['image_2x_url'] ?? null,
                        'badge_url' => $badge['url'] ?? null,
                        'awarded_at' => $badge['awarded_at'],
                        'is_bws_eligible' => false,  // Non-tournament badges are not BWS eligible
                        'tournament_id' => null,  // Explicitly NULL for non-tournament badges
                    ]
                );

                $nonTournamentBadgeCount++;

                Log::debug('Synced non-tournament badge for user', [
                    'user_id' => $user->id,
                    'badge' => $badge['description'],
                ]);
            }

            $syncedCount++;
        }

        // Delete badges that no longer exist in osu! API
        // (Use badge name + awarded_at as unique identifier)
        // Parse dates to match database format for comparison
        $apiBadgeKeys = collect($badges)->map(function ($b) {
            $date = Carbon::parse($b['awarded_at'])->format('Y-m-d H:i:s');

            return $b['description'].'|'.$date;
        })->toArray();

        $user->badges()->whereNotIn(
            DB::raw("CONCAT(name, '|', awarded_at)"),
            $apiBadgeKeys
        )->delete();

        Log::info('Badge sync completed - all badges saved', [
            'user_id' => $user->id,
            'total_api_badges' => count($badges),
            'synced' => $syncedCount,
            'tournament_badges' => $tournamentBadgeCount,
            'non_tournament_badges' => $nonTournamentBadgeCount,
        ]);
    }

    /**
     * Match a badge from osu! API to a tournament winner record
     * Priority: Forum topic ID → Wiki URL → Badge description contains title
     *
     * @param  array<string, mixed>  $badge
     * @param  Collection<int, TournamentWinner>  $userTournamentWinners
     */
    private function matchBadgeToTournamentWinner(array $badge, Collection $userTournamentWinners): ?TournamentWinner
    {
        // Method 1: Match by forum topic ID (most reliable for community tournaments)
        if (isset($badge['url']) && ! empty($badge['url'])) {
            // Extract topic ID from badge URL
            // Format: https://osu.ppy.sh/community/forums/topics/1977529?n=1
            if (preg_match('/forums\/topics\/(\d+)/', $badge['url'], $matches)) {
                $badgeTopicId = (int) $matches[1];

                // Find tournament winners with matching forum_topic_id
                $topicMatches = $userTournamentWinners->filter(function ($winner) use ($badgeTopicId) {
                    return $winner->tournament && $winner->tournament->forum_topic_id === $badgeTopicId;
                });

                if ($topicMatches->count() > 1) {
                    $matchedByTopicIdAndImage = $topicMatches->first(
                        fn (TournamentWinner $winner) => $this->badgeImageMatchesTournament($badge, $winner)
                    );

                    if ($matchedByTopicIdAndImage) {
                        Log::debug('Badge matched by duplicated topic ID and image URL', [
                            'badge' => $badge['description'],
                            'topic_id' => $badgeTopicId,
                            'tournament_id' => $matchedByTopicIdAndImage->tournament_id,
                        ]);

                        return $matchedByTopicIdAndImage;
                    }

                    Log::debug('Badge topic ID matched multiple tournaments but no image URL matched', [
                        'badge' => $badge['description'],
                        'topic_id' => $badgeTopicId,
                    ]);

                    return null;
                }

                $matchedByTopicId = $topicMatches->first();

                if ($matchedByTopicId) {
                    Log::debug('Badge matched by topic ID', [
                        'badge' => $badge['description'],
                        'topic_id' => $badgeTopicId,
                        'tournament_id' => $matchedByTopicId->tournament_id,
                    ]);

                    return $matchedByTopicId;
                }
            }
        }

        // Method 2: Match by wiki URL (for official tournaments like OWC, MWC)
        if (isset($badge['url']) && ! empty($badge['url']) && str_contains($badge['url'], 'wiki')) {
            $wikiUrl = $badge['url'];

            // Find tournament winner with matching forum_post_url (wiki URL)
            $matchedByWikiUrl = $userTournamentWinners->first(function ($winner) use ($wikiUrl) {
                return $winner->tournament && $winner->tournament->forum_post_url === $wikiUrl;
            });

            if ($matchedByWikiUrl) {
                Log::debug('Badge matched by wiki URL', [
                    'badge' => $badge['description'],
                    'wiki_url' => $wikiUrl,
                    'tournament_id' => $matchedByWikiUrl->tournament_id,
                ]);

                return $matchedByWikiUrl;
            }
        }

        // Method 3: Fallback - match by badge description contains tournament title
        // This handles old badges without URLs and provides fuzzy matching
        $badgeDescription = $badge['description'];
        $matchedByDescription = $userTournamentWinners->first(function ($winner) use ($badgeDescription) {
            if (! $winner->tournament) {
                return false;
            }

            // Check if badge description CONTAINS tournament title
            // Example: "osu! World Cup 2013 (Winning Team)" contains "osu! World Cup 2013"
            return str_contains($badgeDescription, $winner->tournament->title);
        });

        if ($matchedByDescription) {
            Log::debug('Badge matched by description (contains title)', [
                'badge' => $badgeDescription,
                'tournament_title' => $matchedByDescription->tournament->title,
                'tournament_id' => $matchedByDescription->tournament_id,
            ]);

            return $matchedByDescription;
        }

        // No match found - this is not a tournament badge
        return null;
    }

    /**
     * @param  array<string, mixed>  $badge
     */
    private function badgeImageMatchesTournament(array $badge, TournamentWinner $winner): bool
    {
        if (! $winner->tournament) {
            return false;
        }

        $tournamentBadgeUrls = collect($winner->tournament->badge_urls ?? [])
            ->flatten()
            ->filter()
            ->map(fn ($url) => $this->normalizeUrl((string) $url))
            ->unique();

        if ($tournamentBadgeUrls->isEmpty()) {
            return false;
        }

        return $tournamentBadgeUrls->contains($this->normalizeUrl($badge['image_url'] ?? null))
            || $tournamentBadgeUrls->contains($this->normalizeUrl($badge['image_2x_url'] ?? null));
    }

    private function normalizeUrl(mixed $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return mb_strtolower($url);
        }

        $scheme = mb_strtolower($parts['scheme'] ?? 'https');
        $host = mb_strtolower($parts['host'] ?? '');
        $path = rtrim($parts['path'] ?? '', '/');

        return "{$scheme}://{$host}{$path}";
    }

    /**
     * Convert application mode name to osu! API mode name
     */
    private function convertAppModeToApiMode(string $appMode): string
    {
        return match ($appMode) {
            'catch' => 'fruits',
            default => $appMode, // osu, taiko, mania stay the same
        };
    }

    /**
     * Convert osu! API mode name to application mode name
     */
    private function convertApiModeToAppMode(string $apiMode): string
    {
        return match ($apiMode) {
            'fruits' => 'catch',
            default => $apiMode, // osu, taiko, mania stay the same
        };
    }

    /**
     * Sync user rank history for all modes
     */
    private function syncUserRanks(User $user): void
    {
        $apiModes = ['osu', 'taiko', 'fruits', 'mania'];

        foreach ($apiModes as $apiMode) {
            $rankData = $this->getUserRankStats($user->osu_id, $apiMode);

            if ($rankData) {
                $appMode = $this->convertApiModeToAppMode($apiMode);
                UserRankHistory::updateOrCreate(
                    ['user_id' => $user->id, 'mode' => $appMode],
                    [
                        'rank' => $rankData['global_rank'],
                        'country_rank' => $rankData['country_rank'],
                        'pp' => $rankData['pp'],
                        'recorded_at' => now(),
                    ]
                );
            }
        }

        // Sync mania-specific rankings (4K and 7K)
        $maniaRankings = $this->getUserManiaRankings($user->osu_id);
        $user->rank_mania_4k = $maniaRankings['4k']['global_rank'] ?? null;
        $user->rank_mania_7k = $maniaRankings['7k']['global_rank'] ?? null;
        $user->save();
    }

    /**
     * Get PP values at specific rank milestones (FR-036)
     * Cached for 1 day since rankings change slowly
     *
     * @param  string  $mode  Game mode (osu, taiko, fruits, mania)
     * @param  array<int>  $ranks  Target ranks to get PP for (e.g., [100, 1000, 10000])
     * @return array<string, float|null> Associative array with pp_at_{rank} keys
     */
    public function getRankingPP(string $mode, array $ranks = [100, 1000, 10000]): array
    {
        // Normalize mode name (catch -> fruits for API)
        $apiMode = $this->convertAppModeToApiMode($mode);

        return Cache::remember($this->rankingPpCacheKey($apiMode), now()->addDay(), function () use ($apiMode, $ranks) {
            return $this->fetchRankingPP($apiMode, $ranks);
        });
    }

    /**
     * Read cached PP milestones without making osu! API requests.
     *
     * @param  array<int>  $ranks
     * @return array<string, float|null>
     */
    public function getCachedRankingPP(string $mode, array $ranks = [100, 1000, 10000]): array
    {
        $apiMode = $this->convertAppModeToApiMode($mode);
        $emptyMilestones = collect($ranks)
            ->mapWithKeys(fn (int $rank): array => ["pp_at_{$rank}" => null])
            ->all();

        return array_replace($emptyMilestones, Cache::get($this->rankingPpCacheKey($apiMode), []));
    }

    /**
     * Refresh cached PP milestones now.
     *
     * @param  array<int>  $ranks
     * @return array<string, float|null>
     */
    public function refreshRankingPP(string $mode, array $ranks = [100, 1000, 10000]): array
    {
        $apiMode = $this->convertAppModeToApiMode($mode);
        $result = $this->fetchRankingPP($apiMode, $ranks);

        Cache::put($this->rankingPpCacheKey($apiMode), $result, now()->addDay());

        return $result;
    }

    private function rankingPpCacheKey(string $apiMode): string
    {
        return "osu_ranking_pp_{$apiMode}";
    }

    /**
     * @param  array<int>  $ranks
     * @return array<string, float|null>
     */
    private function fetchRankingPP(string $apiMode, array $ranks): array
    {
        $result = [];

        foreach ($ranks as $rank) {
            try {
                $pp = $this->getPPAtRank($apiMode, $rank);
                $result["pp_at_{$rank}"] = $pp;
            } catch (\Exception $e) {
                Log::warning("Failed to get PP at rank {$rank} for {$apiMode}", [
                    'error' => $e->getMessage(),
                ]);
                $result["pp_at_{$rank}"] = null;
            }
        }

        return $result;
    }

    /**
     * Get PP value at a specific rank
     * osu! API returns 50 players per page
     */
    private function getPPAtRank(string $mode, int $rank): ?float
    {
        // Calculate page number (1-indexed, 50 per page)
        $page = (int) ceil($rank / 50);

        // Position within the page (0-49)
        $positionInPage = ($rank - 1) % 50;

        $response = $this->request("/rankings/{$mode}/performance", [
            'cursor[page]' => $page,
        ]);

        $rankings = $response['ranking'] ?? [];

        if (isset($rankings[$positionInPage])) {
            return $rankings[$positionInPage]['pp'] ?? null;
        }

        return null;
    }
}
