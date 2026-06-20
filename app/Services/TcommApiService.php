<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Service for interacting with tcomm.hivie.tn API
 *
 * API Documentation: https://github.com/Hiviexd/tournament-tracker/wiki/API-Documentation
 */
class TcommApiService
{
    private const BASE_URL = 'https://tcomm.hivie.tn/api';

    private const CACHE_TTL_TOURNAMENTS = 3600; // 1 hour

    private const CACHE_TTL_TOURNAMENT_DETAIL = 3600; // 1 hour

    private ?string $apiKey;

    private int $rateLimitRemaining;

    private ?string $rateLimitReset;

    /**
     * Create a new TcommApiService instance
     */
    public function __construct()
    {
        $this->apiKey = config('services.tcomm.api_key');
        $this->rateLimitRemaining = 100;
        $this->rateLimitReset = null;
    }

    /**
     * Get the HTTP client with authentication
     */
    private function client(): PendingRequest
    {
        if ($this->apiKey === null) {
            throw new \RuntimeException('Tcomm API key is not configured. Please set SERVICES_TCOMM_API_KEY in your .env file.');
        }

        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Fetch list of tournaments from tcomm API
     *
     * @param  array<string, mixed>  $params  Query parameters for filtering
     * @return array<int, array<string, mixed>> List of tournaments
     *
     * @throws RequestException
     */
    public function getTournaments(array $params = []): array
    {
        $cacheKey = 'tcomm.tournaments.'.md5(json_encode($params));

        return Cache::remember($cacheKey, self::CACHE_TTL_TOURNAMENTS, function () use ($params) {
            $response = $this->client()->get(self::BASE_URL.'/tournaments/', $params);
            $response->throw();

            // Update rate limit info from headers
            $this->updateRateLimitInfo($response);

            // Parse actual tcomm response structure
            $data = $response->json();

            return $data['tournaments'] ?? [];
        });
    }

    /**
     * Fetch paginated tournaments from tcomm API
     *
     * @param  int  $page  Page number
     * @param  array<string, mixed>  $params  Additional query parameters
     * @return array{data: array<int, array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int}|array<string, mixed>
     *
     * @throws RequestException
     */
    public function getTournamentsPaginated(int $page = 1, array $params = []): array
    {
        $params['page'] = $page;

        $response = $this->client()->get(self::BASE_URL.'/tournaments/', $params);
        $response->throw();

        $this->updateRateLimitInfo($response);

        // Map actual tcomm structure to Laravel-style for compatibility
        $data = $response->json();

        return [
            'data' => $data['tournaments'] ?? [],
            'current_page' => $data['page'] ?? 1,
            'last_page' => $data['pages'] ?? 1,
            'per_page' => $params['per_page'] ?? 30,
            'total' => $data['total'] ?? 0,
        ];
    }

    /**
     * Fetch a specific tournament by tcomm ID
     *
     * @param  string  $id  Tournament ID from tcomm
     * @return array<string, mixed> Tournament details
     *
     * @throws RequestException
     */
    public function getTournament(string $id): array
    {
        $cacheKey = 'tcomm.tournament.'.$id;

        return Cache::remember($cacheKey, self::CACHE_TTL_TOURNAMENT_DETAIL, function () use ($id) {
            $response = $this->client()->get(self::BASE_URL.'/tournaments/'.$id);
            $response->throw();

            $this->updateRateLimitInfo($response);

            return $response->json();
        });
    }

    /**
     * Fetch all tournaments using cursor-based pagination
     * This method handles pagination automatically and returns all tournaments
     *
     * @param  array<string, mixed>  $params  Query parameters for filtering
     * @param  callable(int, int, int): void  $progressCallback  Optional callback for progress updates ($page, $lastPage, $totalCount)
     * @return array<int, array<string, mixed>> All tournaments
     *
     * @throws RequestException
     */
    public function getAllTournaments(array $params = [], ?callable $progressCallback = null): array
    {
        $allTournaments = [];
        $page = 1;
        $lastPage = 1;

        do {
            $response = $this->getTournamentsPaginated($page, $params);
            $tournaments = $response['data'] ?? [];

            // Filter by type='tournament' only (exclude contests)
            foreach ($tournaments as $tournament) {
                if (($tournament['type'] ?? null) === 'tournament') {
                    $allTournaments[] = $tournament;
                }
            }

            $lastPage = $response['last_page'] ?? $page;

            if ($progressCallback !== null) {
                $progressCallback($page, $lastPage, count($allTournaments));
            }

            // Rate limiting: sleep if approaching limit
            if ($this->rateLimitRemaining < 10) {
                $this->waitForRateLimit();
            }

            $page++;

            // Small delay between pages to be safe (100ms)
            usleep(100000);
        } while ($page <= $lastPage);

        return $allTournaments;
    }

    /**
     * Update rate limit information from response headers
     */
    private function updateRateLimitInfo(Response $response): void
    {
        $remaining = $response->header('X-RateLimit-Remaining');
        $reset = $response->header('X-RateLimit-Reset');

        if ($remaining !== null) {
            $this->rateLimitRemaining = (int) $remaining;
        }

        if ($reset !== null) {
            $this->rateLimitReset = $reset;
        }
    }

    /**
     * Wait for rate limit to reset if necessary
     */
    private function waitForRateLimit(): void
    {
        if ($this->rateLimitReset !== null) {
            $resetTime = strtotime($this->rateLimitReset);
            $waitTime = $resetTime - time();

            if ($waitTime > 0) {
                sleep($waitTime);
            }

            $this->rateLimitRemaining = 100;
        }
    }

    /**
     * Check if the API is accessible
     *
     * @return array{accessible: bool, error: ?string}
     */
    public function checkConnection(): array
    {
        try {
            $response = $this->client()->get(self::BASE_URL.'/tournaments/', ['per_page' => 1]);

            if ($response->successful()) {
                return [
                    'accessible' => true,
                    'error' => null,
                ];
            }

            return [
                'accessible' => false,
                'error' => 'HTTP '.$response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'accessible' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert tcomm tournament data to our tournament format
     *
     * @param  array<string, mixed>  $tcommData  Raw tcomm tournament data
     * @return array<string, mixed> Formatted tournament data
     */
    public function formatTournamentData(array $tcommData): array
    {
        // Parse forum topic ID from forumUrl
        $forumTopicId = null;
        if (isset($tcommData['forumUrl'])) {
            preg_match('/\/topics\/(\d+)(?:\?|$)/', $tcommData['forumUrl'], $matches);
            $forumTopicId = $matches[1] ?? null;
        }

        return [
            'tcomm_id' => $tcommData['_id'] ?? $tcommData['id'] ?? null,
            'title' => $tcommData['name'] ?? null,
            'abbreviation' => null, // Not in list API
            'modes' => $this->convertModes($tcommData['modes'] ?? null),
            'team_size_min' => null, // Not in list API
            'team_size_max' => null, // Not in list API
            'rank_range_min' => null, // Not in list API
            'rank_range_max' => null, // Not in list API
            'host_osu_id' => $tcommData['hosts'][0]['osuId'] ?? null,
            'host_username' => $tcommData['hosts'][0]['username'] ?? null,
            'forum_topic_id' => $forumTopicId,
            'registration_start' => null, // Not in list API
            'registration_end' => null, // Not in list API
            'tournament_start' => $tcommData['startDate'] ?? null,
            'tournament_end' => $tcommData['endDate'] ?? null,
            'discord_url' => null,
            'twitch_url' => null,
            'bracket_url' => null,
            'banner_url' => $tcommData['bannerUrl'] ?? null,
            'status' => 'approved', // All tcomm imports start as approved
            'import_source' => 'tcomm',
            'is_badge' => ! empty($tcommData['badges']),
            'badge_status' => $this->mapBadgeStatus($tcommData['status'] ?? null),
            'badge_urls' => $this->extractBadgeUrls($tcommData['badges'] ?? []),
            'parsed_at' => $tcommData['startDate'] ?? now(), // Use tournament start date for parsed_at
        ];
    }

    /**
     * Convert tcomm modes to our mode format (new structure)
     *
     * @param  null|array<string>  $modes  Array of mode strings from tcomm API
     * @return array<int, array{mode: string, key_count: ?int}>
     */
    private function convertModes(?array $modes): array
    {
        // Default to osu if no modes specified
        if ($modes === null || empty($modes)) {
            return [['mode' => 'osu', 'key_count' => null]];
        }

        // Convert each mode to our format
        $convertedModes = [];
        foreach ($modes as $mode) {
            $modeString = match ($mode) {
                'osu', 'std', 'standard' => 'osu',
                'taiko' => 'taiko',
                'catch', 'ctb', 'fruits' => 'catch',
                'mania' => 'mania',
                default => 'osu', // Default to osu
            };

            $convertedModes[] = ['mode' => $modeString, 'key_count' => null];
        }

        return $convertedModes;
    }

    /**
     * Map tcomm status to badge_status
     *
     * @param  ?string  $tcommStatus  Status from tcomm API
     * @return ?string 'approved', 'rejected', 'pending', or null
     */
    private function mapBadgeStatus(?string $tcommStatus): ?string
    {
        return match ($tcommStatus) {
            'badgeApproved' => 'approved',
            'badgeRejected' => 'rejected',
            'pending', 'underReview' => 'pending',
            default => null, // Not a badge tournament
        };
    }

    /**
     * Extract badge URLs from badges array
     *
     * @param  array<int, array<string, mixed>>  $badges  Badges array from tcomm API
     * @return ?array<int, string> Array of badge URLs or null
     */
    private function extractBadgeUrls(array $badges): ?array
    {
        if (empty($badges)) {
            return null;
        }

        return array_map(fn ($badge) => $badge['url'] ?? null, $badges);
    }

    /**
     * Get current rate limit status
     *
     * @return array{remaining: int, reset_at: ?string}
     */
    public function getRateLimitStatus(): array
    {
        return [
            'remaining' => $this->rateLimitRemaining,
            'reset_at' => $this->rateLimitReset,
        ];
    }
}
