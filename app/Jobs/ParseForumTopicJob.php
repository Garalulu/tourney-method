<?php

namespace App\Jobs;

use App\Models\Tournament;
use App\Models\User;
use App\Services\AdminMaintenanceRunRecorder;
use App\Services\BatchTransactionService;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use App\Support\QueueNames;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Parse osu! Forum Topic Job
 *
 * Fetches tournament data from osu! forum topic and creates/updates tournament records.
 *
 * UPDATE BEHAVIOR:
 * - Only updates tournaments with "pending" status
 * - Skips approved and rejected tournaments (they are considered finalized)
 * - Respects field_sources for smart field protection
 * - New tournaments are always created regardless of status
 *
 * USE CASES:
 * - tournaments:parse command: Automated parsing of new forum topics
 * - tournaments:reparse-staff command: Manual re-parse of specific tournaments
 * - Manual re-parse: Admin triggers parse for specific tournament
 * - OTR dump import: ChunkedForumParseJob with bypassOverlapping=true
 *
 * MIDDLEWARE:
 * - WithoutOverlapping: Per-topic lock with 1h expiry (for manual/admin parses only)
 *   For OTR imports: Custom Redis lock in handle() ensures 1 req/sec
 *
 * @see Tournament::updateFromParsedData()
 * @see ForumParser::parseForumTopic()
 * @see ChunkedForumParseJob
 */
class ParseForumTopicJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int|array<int, int>
     */
    public int|array $backoff = [60, 120, 240]; // 1min, 2min, 4min

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Delete the job if its models no longer exist (not applicable here).
     */
    public bool $deleteWhenMissingModels = false;

    /**
     * Force re-parse even if tournament is approved/rejected.
     * Used when admin manually triggers re-parse from admin panel.
     */
    private bool $forceReparse = false;

    /**
     * Bypass WithoutOverlapping middleware.
     * Used for OTR dump parsing where multiple chunks may process the same topics.
     */
    private bool $bypassOverlapping = false;

    /**
     * Target specific tournament ID for re-parse.
     * If provided, only this tournament will be updated.
     */
    private ?int $targetTournamentId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $topicId, // Promoted to public property for testing
        private ?string $transactionId = null, // Optional transaction ID for chained jobs
        bool $forceReparse = false, // Bypass status check for manual admin re-parses
        bool $bypassOverlapping = false, // Bypass WithoutOverlapping for OTR dump parsing
        ?int $targetTournamentId = null, // Target specific tournament ID
        string $queueName = QueueNames::OSU_ADMIN
    ) {
        $this->onQueue($queueName);
        $this->forceReparse = $forceReparse;
        $this->bypassOverlapping = $bypassOverlapping;
        $this->targetTournamentId = $targetTournamentId;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $middleware = [
        ];

        // Prevent duplicate jobs for same topic (for manual/admin parses only)
        // For OTR imports, we use custom Redis lock in handle() method
        if (! $this->bypassOverlapping) {
            // Include target tournament ID in lock key if provided to allow parallel re-parses of different tournaments for same topic
            $lockKey = "forum_topic_{$this->topicId}";
            if ($this->targetTournamentId) {
                $lockKey .= "_tourney_{$this->targetTournamentId}";
            }

            $middleware[] = (new WithoutOverlapping($lockKey))
                ->expireAfter(3600); // Lock expires after 1 hour
        }

        return $middleware;
    }

    /**
     * Execute the job.
     */
    public function handle(OsuApiService $osuApi, ForumParser $parser): void
    {
        Log::info('Processing forum topic', [
            'topic_id' => $this->topicId,
            'target_tournament_id' => $this->targetTournamentId,
        ]);

        try {
            // Fetch and parse outside the DB transaction so API latency and banner
            // validation do not hold row locks.
            $topicData = $osuApi->getForumTopic($this->topicId);

            if ($topicData === null) {
                Log::warning('Forum topic not found or inaccessible', [
                    'topic_id' => $this->topicId,
                ]);

                return;
            }

            $postContent = null;
            if (isset($topicData['posts'][0]['body']['raw'])) {
                $postContent = $topicData['posts'][0]['body']['raw'];
            } elseif (isset($topicData['posts'][0]['body']['html'])) {
                $postContent = strip_tags($topicData['posts'][0]['body']['html']);
            }

            $parsedData = $parser->parseForumTopicData($this->topicId, $topicData, $postContent, ! $this->forceReparse);

            if ($parsedData === null && ! $this->forceReparse) {
                Log::info('Forum topic is not a tournament post', [
                    'topic_id' => $this->topicId,
                    'title' => $topicData['title'] ?? ($topicData['topic']['title'] ?? 'Unknown'),
                ]);

                return;
            }

            if ($parsedData === null && $this->forceReparse) {
                Log::warning('Forcing workflow for non-tournament post', [
                    'topic_id' => $this->topicId,
                ]);

                $parsedData = [
                    'forum_topic_id' => $this->topicId,
                    'title' => $topicData['title'] ?? ($topicData['topic']['title'] ?? ''),
                    'host_osu_id' => $topicData['user_id'] ?? ($topicData['topic']['user_id'] ?? null),
                    'description' => $postContent ?? '',
                    'modes' => [],
                    'is_badge' => false,
                ];
            }

            if ($postContent === null) {
                $postContent = '';
            }

            $forumPostSha256 = hash('sha256', $postContent);

            // Use database transaction with pessimistic locking to prevent race conditions
            // This ensures atomic check-then-create operation to prevent duplicate tournaments
            DB::transaction(function () use ($parser, $topicData, $postContent, $parsedData, $forumPostSha256) {
                // ATOMIC CHECK: Acquire lock before checking for existing tournaments
                // This prevents concurrent jobs from both seeing empty and creating duplicates
                $query = Tournament::where('forum_topic_id', $this->topicId);

                // If target tournament ID is provided, ONLY update that specific tournament
                if ($this->targetTournamentId) {
                    $query->where('id', $this->targetTournamentId);
                }

                /** @var Collection<int, Tournament> $existingTournaments */
                $existingTournaments = $query->lockForUpdate()->get();

                if ($existingTournaments->isNotEmpty()) {
                    foreach ($existingTournaments as $existing) {
                        if ($existing->status !== Tournament::STATUS_PENDING && ! $this->forceReparse) {
                            Log::info('Skipping tournament update - not pending status', [
                                'topic_id' => $this->topicId,
                                'tournament_id' => $existing->id,
                                'status' => $existing->status,
                            ]);

                            continue;
                        }

                        if (! $this->forceReparse && $existing->forum_post_sha256 === $forumPostSha256) {
                            Log::info('Skipping tournament update - forum post unchanged', [
                                'topic_id' => $this->topicId,
                                'tournament_id' => $existing->id,
                                'status' => $existing->status,
                                'forum_post_sha256' => $forumPostSha256,
                            ]);

                            continue;
                        }

                        // Re-parse: update existing tournament
                        $logContext = [
                            'topic_id' => $this->topicId,
                            'tournament_id' => $existing->id,
                        ];

                        if ($this->forceReparse) {
                            $logContext['force_reparse'] = true;
                            $logContext['previous_status'] = $existing->status;
                        }

                        $this->updateTournament($existing, $parsedData, $topicData, $parser, $postContent, $forumPostSha256);

                        $this->recordMaintenanceTournament($existing, 'updated', [
                            'topic_id' => $this->topicId,
                            'force_reparse' => $this->forceReparse,
                        ]);

                        Log::info('Tournament updated from forum topic', [
                            'topic_id' => $this->topicId,
                            'tournament_id' => $existing->id,
                            'title' => $existing->title,
                        ]);
                    }
                } elseif ($this->targetTournamentId) {
                    Log::warning('Targeted forum parse found no matching tournament; skipping create fallback', [
                        'topic_id' => $this->topicId,
                        'target_tournament_id' => $this->targetTournamentId,
                    ]);
                } else {
                    // Create new tournament record
                    $tournament = $this->createTournament($parsedData, $topicData, $parser, $postContent, $forumPostSha256);

                    $this->recordMaintenanceTournament($tournament, 'created', [
                        'topic_id' => $this->topicId,
                        'is_badge' => $tournament->is_badge,
                    ]);

                    Log::info('Tournament created from forum topic', [
                        'topic_id' => $this->topicId,
                        'tournament_id' => $tournament->id,
                        'title' => $tournament->title,
                        'is_badge' => $tournament->is_badge,
                    ]);
                }
            }, 3); // 3 retry attempts on deadlock

        } catch (\Exception $e) {
            Log::error('Failed to process forum topic', [
                'topic_id' => $this->topicId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $maintenanceRunId = $this->maintenanceRunId();
            if ($maintenanceRunId !== null) {
                app(AdminMaintenanceRunRecorder::class)->recordError($maintenanceRunId, $e->getMessage(), [
                    'topic_id' => $this->topicId,
                ]);
            }

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordMaintenanceTournament(Tournament $tournament, string $action, array $metadata): void
    {
        $maintenanceRunId = $this->maintenanceRunId();

        if ($maintenanceRunId === null) {
            return;
        }

        app(AdminMaintenanceRunRecorder::class)->recordTournament(
            $maintenanceRunId,
            $tournament,
            $action,
            $metadata
        );
    }

    private function maintenanceRunId(): ?int
    {
        if ($this->transactionId === null) {
            return null;
        }

        return app(BatchTransactionService::class)->getAdminMaintenanceRunId($this->transactionId);
    }

    /**
     * Create tournament from parsed data
     *
     * @param  array<string, mixed>  $parsedData
     * @param  array<string, mixed>  $topicData
     */
    protected function createTournament(
        array $parsedData,
        array $topicData,
        ForumParser $parser,
        string $postContent,
        string $forumPostSha256
    ): Tournament {
        // Parse banner URL from BBCode
        $bannerUrl = $parser->parseBanner($postContent);

        // Validate banner URL (check if accessible and is an image)
        $validatedBannerUrl = $this->validateBannerUrl($bannerUrl);

        $tournament = Tournament::create([
            'forum_topic_id' => $this->topicId,
            'forum_post_sha256' => $forumPostSha256,
            // forum_post_url will be generated by accessor from forum_topic_id
            'title' => $parsedData['title'] ?: $topicData['title'] ?? 'Unknown Tournament',
            'description' => $parsedData['description'] ?: null,
            'host_osu_id' => $parsedData['host_osu_id'] ?: null,
            'host_username' => $parsedData['host_username'] ?: null,
            'status' => $this->bypassOverlapping ? 'approved' : 'pending_review', // OTR imports bypass overlapping → auto-approve as pre-verified
            'modes' => $parsedData['modes'] ?: [],
            'team_size_min' => $parsedData['team_size_min'] ?? null,
            'team_size_max' => $parsedData['team_size_max'] ?? null,
            'vs_size' => $parsedData['vs_size'] ?? null,
            'registration_start' => $parsedData['registration_start'] ?: null,
            'registration_end' => $parsedData['registration_end'] ?: null,
            'tournament_start' => $parsedData['tournament_start'] ?: null,
            'tournament_end' => $parsedData['tournament_end'] ?: null,
            'rank_range_min' => $parsedData['rank_range_min'] ?: null,
            'rank_range_max' => $parsedData['rank_range_max'] ?: null,
            'is_badge' => $parsedData['is_badge'] ?: false,
            'is_bws' => $parsedData['is_bws'] ?? false,
            'star_rating_min' => null,
            'star_rating_max' => null,
            'format' => null,
            'team_formation_style' => $parsedData['team_formation_style'] ?? Tournament::TEAM_FORMATION_STANDARD,
            'banner_url' => $validatedBannerUrl,
            'discord_url' => ! empty($parsedData['discord_url']) ? Str::limit($parsedData['discord_url'], 500) : null,
            'twitch_url' => ! empty($parsedData['twitch_url']) ? Str::limit($parsedData['twitch_url'], 500) : null,
            'spreadsheet_url' => ! empty($parsedData['spreadsheet_url']) ? Str::limit($parsedData['spreadsheet_url'], 500) : null,
            'bracket_url' => ! empty($parsedData['bracket_url']) ? Str::limit($parsedData['bracket_url'], 500) : null,
            'registration_url' => ! empty($parsedData['registration_url']) ? Str::limit($parsedData['registration_url'], 500) : null,
            'tcomm_url' => ! empty($parsedData['tcomm_url']) ? Str::limit($parsedData['tcomm_url'], 500) : null,
            'import_source' => 'forum',
            'parsed_at' => now(),
        ]);

        // Parse and store staff in cache for 3-stage chain
        $parsedStaff = $parser->parseStaffFromBBcode($postContent);

        // Ensure topic author is included as organizer if none found
        $parsedStaff = $this->ensureTopicAuthorAsOrganizer($parsedStaff, $topicData);

        // Ensure host user is included in staff list for BatchFetchUsersJob
        // This ensures we fetch the host's username from osu! API even if they're not listed in BBCode
        if ($tournament->host_osu_id !== null) {
            $parsedStaff = $this->ensureHostUserInStaff($parsedStaff, $tournament->host_osu_id);
        }

        // Store per-tournament staff data for AggregateStaffJob
        if ($this->transactionId) {
            app(BatchTransactionService::class)
                ->storeTournamentStaff($this->transactionId, $tournament->id, $parsedStaff);
        }

        Log::info('Tournament staff stored for batch processing', [
            'tournament_id' => $tournament->id,
            'staff_count' => count($parsedStaff),
            'transaction_id' => $this->transactionId,
        ]);

        return $tournament;
    }

    /**
     * Update existing tournament from parsed data
     *
     * @param  array<string, mixed>  $parsedData
     * @param  array<string, mixed>  $topicData
     */
    protected function updateTournament(
        Tournament $tournament,
        array $parsedData,
        array $topicData,
        ForumParser $parser,
        string $postContent,
        string $forumPostSha256
    ): void {
        // Filter out NULL host_username to prevent overwriting existing data
        // (The parser always returns host_username=null, but we want to preserve existing values)
        if (array_key_exists('host_username', $parsedData) && $parsedData['host_username'] === null) {
            unset($parsedData['host_username']);
        }

        // Truncate URL fields to 500 characters (database column limit)
        $urlFields = ['banner_url', 'discord_url', 'twitch_url', 'spreadsheet_url', 'bracket_url', 'registration_url', 'tcomm_url'];
        foreach ($urlFields as $field) {
            if (isset($parsedData[$field])) {
                $parsedData[$field] = Str::limit($parsedData[$field], 500);
            }
        }

        // Parse banner URL from BBCode
        $bannerUrl = $parser->parseBanner($postContent);
        if ($bannerUrl !== null) {
            // Validate banner URL before saving
            $parsedData['banner_url'] = $this->validateBannerUrl($bannerUrl);
        }

        // Update tournament fields with change tracking
        // PHASE 3: Pass null as user ID to indicate automated parse (not admin)
        $parsedData['forum_post_sha256'] = $forumPostSha256;
        $tournament->updateFromParsedData($parsedData, null);

        // Ensure host_username is populated if host_osu_id exists
        // (The parser might not have extracted the username from BBcode)
        if ($tournament->host_osu_id !== null && $tournament->host_username === null) {
            Log::info('updateTournament: Attempting to fetch host_username', [
                'tournament_id' => $tournament->id,
                'host_osu_id' => $tournament->host_osu_id,
            ]);

            $hostUser = User::where('osu_id', $tournament->host_osu_id)->first();
            if ($hostUser) {
                $tournament->update([
                    'host_username' => $hostUser->username,
                ]);
                Log::info('Updated host_username from users table', [
                    'tournament_id' => $tournament->id,
                    'host_osu_id' => $tournament->host_osu_id,
                    'host_username' => $hostUser->username,
                ]);
            } else {
                Log::warning('updateTournament: User not found in database', [
                    'tournament_id' => $tournament->id,
                    'host_osu_id' => $tournament->host_osu_id,
                ]);
            }
        } else {
            Log::info('updateTournament: Skipped host_username update', [
                'tournament_id' => $tournament->id,
                'reason' => $tournament->host_osu_id === null ? 'no host_osu_id' : 'host_username already set',
                'host_username' => $tournament->host_username,
            ]);
        }

        // Parse and store staff in cache for 3-stage chain
        $parsedStaff = $parser->parseStaffFromBBcode($postContent);

        // NOTE: Don't ensure topic author is organizer on re-parses
        // Topic author is only added as organizer on initial parse (in createTournament method)
        // This preserves manual staff changes made after initial approval

        // Ensure host user is included in staff list for BatchFetchUsersJob
        // This ensures we fetch the host's username from osu! API even if they're not listed in BBCode
        if ($tournament->host_osu_id !== null) {
            $parsedStaff = $this->ensureHostUserInStaff($parsedStaff, $tournament->host_osu_id);
        }

        // Store per-tournament staff data for AggregateStaffJob
        if ($this->transactionId) {
            app(BatchTransactionService::class)
                ->storeTournamentStaff($this->transactionId, $tournament->id, $parsedStaff);
        }

        Log::info('Tournament staff stored for batch processing', [
            'tournament_id' => $tournament->id,
            'staff_count' => count($parsedStaff),
            'transaction_id' => $this->transactionId,
        ]);
    }

    /**
     * Ensure topic author is included as organizer if no organizer found in parsed staff
     *
     * @param  array<int, array{osu_id: int, username: string, role: string}>  $parsedStaff
     * @param  array<string, mixed>  $topicData
     * @return array<int, array{osu_id: int, username: string, role: string}>
     */
    protected function ensureTopicAuthorAsOrganizer(array $parsedStaff, array $topicData): array
    {
        // DEBUG: Log topic data structure for diagnostics
        Log::debug('ensureTopicAuthorAsOrganizer: Input structure', [
            'topic_id' => $this->topicId,
            'has_user_id_root' => isset($topicData['user_id']),
            'has_topic_key' => isset($topicData['topic']),
            'has_user_id_nested' => isset($topicData['topic']['user_id']),
            'parsed_staff_count' => count($parsedStaff),
            'has_organizer' => ! empty(collect($parsedStaff)->where('role', 'organizer')->toArray()),
        ]);

        // Check if there's already an organizer in the parsed staff
        $hasOrganizer = false;
        foreach ($parsedStaff as $staff) {
            if ($staff['role'] === 'organizer') {
                $hasOrganizer = true;
                break;
            }
        }

        // Unwrap nested API response structure (same as ForumParser::parseForumTopic)
        // API returns: {topic: {id, user_id, ...}, posts: [...]}
        $topic = $topicData['topic'] ?? $topicData;

        // If no organizer found, add topic author as organizer
        if (! $hasOrganizer && isset($topic['user_id'])) {
            $topicAuthorOsuId = $topic['user_id'];

            // Find topic author in staff array and get their index
            $authorIndex = null;
            $authorRole = null;
            foreach ($parsedStaff as $index => $staff) {
                if ($staff['osu_id'] === $topicAuthorOsuId) {
                    $authorIndex = $index;
                    $authorRole = $staff['role'];
                    break;
                }
            }

            if ($authorIndex !== null) {
                // Topic author exists in staff
                if ($authorRole !== 'organizer') {
                    // Update their role to organizer
                    $parsedStaff[$authorIndex]['role'] = 'organizer';

                    Log::info('Updated topic author role to organizer', [
                        'topic_id' => $this->topicId,
                        'topic_author_osu_id' => $topicAuthorOsuId,
                        'previous_role' => $authorRole,
                    ]);
                }
            } else {
                // Topic author not in staff, add as organizer
                $parsedStaff[] = [
                    'osu_id' => $topicAuthorOsuId,
                    'username' => '', // Will be filled during Aggregate & Fetch stage
                    'role' => 'organizer',
                ];

                Log::info('Added topic author as organizer', [
                    'topic_id' => $this->topicId,
                    'topic_author_osu_id' => $topicAuthorOsuId,
                ]);
            }
        }

        return $parsedStaff;
    }

    /**
     * Validate banner URL before saving
     *
     * Checks if the banner URL is accessible and returns an image.
     * Uses HTTP HEAD request (fast, doesn't download full image).
     *
     * RETURN NULL (don't save) FOR:
     * - HTTP 404 (permanently gone)
     * - HTTP 403/401 (forbidden)
     * - HTTP 400/405 (client errors except not-an-image)
     *
     * RETURN URL (save anyway) FOR:
     * - Timeout/connection errors (might be temporary)
     * - HTTP 500+ (server errors, might be temporary)
     * - Content-Type is not an image (might be hotlinking protection)
     *
     * @param  string|null  $url  Banner URL to validate
     * @return string|null Valid URL or null if validation fails
     */
    protected function validateBannerUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        try {
            // Add small delay to respect rate limits (100ms between requests)
            usleep(100000); // 0.1 seconds

            $response = Http::timeout(5)->head($url);

            $statusCode = $response->status();

            // Client errors (4xx): Don't save - these are permanent failures
            if ($statusCode >= 400 && $statusCode < 500) {
                Log::warning('Banner URL rejected - client error', [
                    'topic_id' => $this->topicId,
                    'url' => substr($url, 0, 100),
                    'status_code' => $statusCode,
                ]);

                return null;
            }

            // Server errors (5xx): Save anyway - might be temporary
            if ($statusCode >= 500) {
                Log::info('Banner URL validated with server error - saving anyway', [
                    'topic_id' => $this->topicId,
                    'url' => substr($url, 0, 100),
                    'status_code' => $statusCode,
                ]);

                return $url;
            }

            // Check content type is image
            $contentType = $response->header('Content-Type');
            if ($contentType === null || ! str_starts_with($contentType, 'image/')) {
                // Not an image - SAVE anyway (might be hotlinking protection)
                Log::info('Banner URL not an image - saving anyway (might be hotlinking protection)', [
                    'topic_id' => $this->topicId,
                    'url' => substr($url, 0, 100),
                    'content_type' => $contentType,
                ]);

                return $url;
            }

            Log::info('Banner URL validated successfully', [
                'topic_id' => $this->topicId,
                'url' => substr($url, 0, 100),
                'content_type' => $contentType,
            ]);

            return $url;

        } catch (ConnectionException $e) {
            // Connection timeout or refused - save anyway (might be temporary)
            Log::info('Banner URL validated with connection error - saving anyway', [
                'topic_id' => $this->topicId,
                'url' => substr($url, 0, 100),
                'error' => 'Connection timeout or refused',
            ]);

            return $url;
        } catch (RequestException $e) {
            // Request failed - check if it's a timeout
            $errorMessage = $e->getMessage();
            if (stripos($errorMessage, 'timeout') !== false) {
                Log::info('Banner URL timed out - saving anyway', [
                    'topic_id' => $this->topicId,
                    'url' => substr($url, 0, 100),
                ]);

                return $url;
            }

            // Other request errors - don't save
            Log::warning('Banner URL rejected - request error', [
                'topic_id' => $this->topicId,
                'url' => substr($url, 0, 100),
                'error' => $errorMessage,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('Banner URL rejected - unknown error', [
                'topic_id' => $this->topicId,
                'url' => substr($url, 0, 100),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ensure host user is included in staff list
     *
     * This ensures BatchFetchUsersJob will fetch the host's username from osu! API,
     * even if the host is not explicitly listed in the forum post's BBCode staff section.
     *
     * @param  array<int, array{osu_id: int, username: string, role: string}>  $parsedStaff
     * @return array<int, array{osu_id: int, username: string, role: string}>
     */
    protected function ensureHostUserInStaff(array $parsedStaff, int $hostOsuId): array
    {
        // Check if host is already in staff list
        $hostExists = false;
        foreach ($parsedStaff as $staff) {
            if ($staff['osu_id'] === $hostOsuId) {
                $hostExists = true;
                break;
            }
        }

        if ($hostExists) {
            // Host already in staff, no action needed
            return $parsedStaff;
        }

        // Add host as organizer (they will be fetched by BatchFetchUsersJob)
        $parsedStaff[] = [
            'osu_id' => $hostOsuId,
            'username' => '', // Will be filled during BatchFetchUsersJob
            'role' => 'organizer', // Host is typically the organizer
        ];

        Log::info('Added host user to staff list for API fetch', [
            'topic_id' => $this->topicId,
            'host_osu_id' => $hostOsuId,
        ]);

        return $parsedStaff;
    }
}
