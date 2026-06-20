<?php

namespace App\Services;

use App\Integrations\Otr\LegacyModDecoder;
use App\Models\ImportJob;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Service for interacting with o!TR (osu! Tournament Rating) API
 *
 * API Documentation: https://otr.stagec.net/api
 */
class OtrApiService
{
    private const BASE_URL = 'https://otr.stagec.net/api';

    private const CACHE_TTL_TOURNAMENTS = 3600; // 1 hour

    private const CACHE_TTL_TOURNAMENT_WITH_MATCHES = 3600; // 1 hour

    private const CACHE_TTL_MATCH = 86400; // 24 hours - matches are historical

    private ?string $apiKey;

    private int $requestCount = 0;

    private int $requestWindowStart;

    private ?ImportJob $importJob = null;

    private const MAX_REQUESTS_PER_10MIN = 100;

    private const WINDOW_SIZE = 600; // 10 minutes in seconds

    /**
     * Create a new OtrApiService instance
     */
    public function __construct()
    {
        $this->apiKey = config('services.otr.api_key');
        $this->requestWindowStart = time();
    }

    /**
     * Set the import job for persistent rate limit state
     */
    public function setImportJob(ImportJob $job): void
    {
        $this->importJob = $job;

        // Restore rate limit state from job if available
        if ($job->rate_limit_window_start !== null) {
            $this->requestCount = $job->rate_limit_count ?? 0;
            $this->requestWindowStart = $job->rate_limit_window_start->timestamp;
        }
    }

    /**
     * Check if O!TR API is configured
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * Get the HTTP client with authentication
     */
    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('O!TR API key is not configured. Please set OTR_API_KEY environment variable.');
        }

        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Check and enforce rate limiting
     * Returns true if request should proceed, false if should wait
     */
    private function checkRateLimit(): bool
    {
        $now = time();
        $windowElapsed = $now - $this->requestWindowStart;

        // Reset counter if window has expired
        if ($windowElapsed >= self::WINDOW_SIZE) {
            $this->requestCount = 0;
            $this->requestWindowStart = $now;

            return true;
        }

        // Check if we've hit the rate limit
        if ($this->requestCount >= self::MAX_REQUESTS_PER_10MIN) {
            // Calculate wait time
            $waitTime = self::WINDOW_SIZE - $windowElapsed;
            sleep($waitTime);

            // Reset after waiting
            $this->requestCount = 0;
            $this->requestWindowStart = time();
        }

        return true;
    }

    /**
     * Increment request counter after successful request
     * Saves state to import job if available for resume capability
     */
    private function incrementRequestCount(): void
    {
        $this->requestCount++;

        // Save state to import job for persistence across resume
        if ($this->importJob) {
            $this->importJob->update([
                'rate_limit_count' => $this->requestCount,
                'rate_limit_window_start' => Carbon::createFromTimestamp($this->requestWindowStart),
            ]);
        }
    }

    /**
     * Fetch all tournaments from o!TR API with pagination
     *
     * @return array<int, array<string, mixed>> List of tournaments
     *
     * @throws RequestException
     */
    /**
     * Fetch all tournaments from o!TR API with pagination
     *
     * Always fetches and caches ALL tournaments. Limiting should be done in calling code.
     *
     * @return array<int, array<string, mixed>> List of all tournaments
     *
     * @throws RequestException
     */
    public function getTournaments(): array
    {
        $this->checkRateLimit();

        $cacheKey = 'otr.tournaments.all';

        $result = Cache::remember($cacheKey, self::CACHE_TTL_TOURNAMENTS, function () {
            $allTournaments = [];
            $page = 1;

            while (true) {
                // Check rate limit BEFORE making request
                $this->checkRateLimit();

                $response = $this->client()->get(self::BASE_URL.'/tournaments', ['page' => $page]);
                $response->throw();
                $this->incrementRequestCount();

                $tournaments = $response->json();

                // If we get an empty array or non-array response, we've reached the end
                if (! is_array($tournaments) || count($tournaments) === 0) {
                    break;
                }

                $allTournaments = array_merge($allTournaments, $tournaments);

                // If we got less than 30 tournaments, we're on the last page
                if (count($tournaments) < 30) {
                    break;
                }

                $page++;
            }

            return $allTournaments;
        });

        return $result;
    }

    /**
     * Fetch a specific tournament with matches by o!TR ID
     *
     * @param  int  $id  Tournament ID from o!TR
     * @return array<string, mixed> Tournament details with matches
     *
     * @throws RequestException
     */
    public function getTournamentWithMatches(int $id): array
    {
        $this->checkRateLimit();

        $cacheKey = 'otr.tournament.'.$id;

        $result = Cache::remember($cacheKey, self::CACHE_TTL_TOURNAMENT_WITH_MATCHES, function () use ($id) {
            $response = $this->client()->get(self::BASE_URL.'/tournaments/'.$id);
            $response->throw();
            $this->incrementRequestCount();

            $data = $response->json();

            // Ensure we got a valid array response
            if (! is_array($data)) {
                throw new \RuntimeException("Invalid response from o!TR API for tournament {$id}: expected array, got ".gettype($data));
            }

            return $data;
        });

        return $result;
    }

    /**
     * Fetch a specific match by osu! match ID
     *
     * @param  int  $matchId  osu! match ID
     * @return array<string, mixed> Match details with games
     *
     * @throws RequestException
     */
    public function getMatch(int $matchId): array
    {
        $this->checkRateLimit();

        $cacheKey = 'otr.match.'.$matchId;

        $result = Cache::remember($cacheKey, self::CACHE_TTL_MATCH, function () use ($matchId) {
            $response = $this->client()->get(self::BASE_URL.'/matches/'.$matchId);
            $response->throw();
            $this->incrementRequestCount();

            return $response->json();
        });

        return $result;
    }

    /**
     * Fetch player tournament history/stats
     *
     * @param  int  $playerId  osu! user ID
     * @return array<string, mixed> Player stats
     *
     * @throws RequestException
     */
    public function getPlayerStats(int $playerId): array
    {
        $this->checkRateLimit();

        $cacheKey = 'otr.player.'.$playerId.'.stats';

        $result = Cache::remember($cacheKey, self::CACHE_TTL_TOURNAMENTS, function () use ($playerId) {
            $response = $this->client()->get(self::BASE_URL.'/players/'.$playerId.'/stats');
            $response->throw();
            $this->incrementRequestCount();

            return $response->json();
        });

        return $result;
    }

    /**
     * Check if the API is accessible
     *
     * @return array{accessible: bool, error: ?string}
     */
    public function checkConnection(): array
    {
        try {
            $response = $this->client()->get(self::BASE_URL.'/tournaments');
            $response->throw();
            $this->incrementRequestCount();

            return [
                'accessible' => true,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'accessible' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert o!TR tournament data to our tournament format
     *
     * @param  array<string, mixed>  $otrData  Raw o!TR tournament data
     * @return array<string, mixed> Formatted tournament data
     */
    public function formatTournamentData(array $otrData): array
    {
        // Extract forum_topic_id from forumUrl if present
        $forumTopicId = null;
        if (isset($otrData['forumUrl']) && preg_match('/\/topics\/(\d+)/', $otrData['forumUrl'], $matches)) {
            $forumTopicId = (int) $matches[1];
        }

        return [
            'otr_id' => $otrData['id'] ?? null,
            'title' => $otrData['name'] ?? null,
            'abbreviation' => $otrData['abbreviation'] ?? null,
            'forum_topic_id' => $forumTopicId,
            'modes' => $this->convertMode($otrData['ruleset'] ?? null),
            'rank_range_min' => $otrData['rankRangeLowerBound'] ?? null,
            'rank_range_max' => null, // OTR API only provides lower bound
            'vs_size' => $otrData['lobbySize'] ?? null,
            'tournament_start' => $otrData['startTime'] ?? null,
            'tournament_end' => $otrData['endTime'] ?? null,
            'import_source' => 'otr',
            'status' => 'approved', // o!TR tournaments are verified
            'reviewed_at' => now(), // Set review date to import time for OTR tournaments
        ];
    }

    /**
     * Convert o!TR match data to our match format
     *
     * @param  array<string, mixed>  $otrMatchData  Raw o!TR match data
     * @param  int|null  $tournamentId  Local tournament ID
     * @return array<string, mixed> Formatted match data
     */
    public function formatMatchData(array $otrMatchData, ?int $tournamentId = null): array
    {
        return [
            'osu_match_id' => $otrMatchData['osuId'] ?? null,
            'name' => $otrMatchData['name'] ?? null,
            'tournament_id' => $tournamentId,
            'start_time' => $otrMatchData['startTime'] ?? null,
            'end_time' => $otrMatchData['endTime'] ?? null,
            'status' => 'approved', // o!TR matches are verified
            'raw_data' => $otrMatchData, // Store full response for future processing
        ];
    }

    /**
     * Convert o!TR game data to our match game format
     *
     * @param  array<string, mixed>  $otrGameData  Raw o!TR game data
     * @param  int  $matchId  Local match ID
     * @return array<string, mixed> Formatted game data
     */
    public function formatGameData(array $otrGameData, int $matchId): array
    {
        $beatmap = $otrGameData['beatmap'] ?? null;
        $beatmapset = $beatmap['beatmapset'] ?? null;

        return [
            'match_id' => $matchId,
            'game_id' => $otrGameData['id'] ?? null,
            'beatmap_id' => $beatmap['osuId'] ?? null,
            'beatmap_title' => $beatmapset['title'] ?? null,
            'beatmap_version' => $beatmap['diffName'] ?? null,
            'mods' => $this->convertModsIntegerToArray($otrGameData['mods'] ?? 0),
            'mode' => data_get($this->convertMode($otrGameData['ruleset'] ?? null), '0.mode', 'osu'),
            'scoring_type' => $this->convertScoringType($otrGameData['scoringType'] ?? null),
            'team_type' => $this->convertTeamType($otrGameData['teamType'] ?? null),
            'start_time' => $otrGameData['startTime'] ?? null,
            'end_time' => $otrGameData['endTime'] ?? null,
        ];
    }

    /**
     * Convert o!TR score data to our match score format
     *
     * @param  array<string, mixed>  $otrScoreData  Raw o!TR score data
     * @param  int  $matchGameId  Local match game ID
     * @return array<string, mixed> Formatted score data
     */
    public function formatScoreData(array $otrScoreData, int $matchGameId): array
    {
        return [
            'match_game_id' => $matchGameId,
            'osu_user_id' => $otrScoreData['playerId'] ?? null,
            'username' => null, // Will be resolved from users table
            'team' => null,
            'score' => $otrScoreData['score'] ?? 0,
            'accuracy' => $otrScoreData['accuracy'] ?? 0,
            'max_combo' => $otrScoreData['maxCombo'] ?? 0,
            'count_300' => null,
            'count_100' => null,
            'count_50' => null,
            'count_miss' => null,
            'count_geki' => null,
            'count_katu' => null,
            'perfect' => false,
            'passed' => true, // Assume passed if score exists
            'mods' => [],
        ];
    }

    /**
     * Convert o!TR mode integer to our mode array format
     *
     * o!TR mode values: 0 = osu!, 1 = taiko, 2 = catch, 3 = mania
     *
     * @return array<int, array{mode: string, key_count: ?int}>
     */
    /**
     * Convert o!TR ruleset to our mode format
     * OTR Ruleset: 0=Osu, 1=Taiko, 2=Catch, 3=ManiaOther, 4=Mania4k, 5=Mania7k
     *
     * @param  int|null  $mode  OTR ruleset value
     * @return array<array<string, mixed>> Mode structure for tournament
     */
    private function convertMode(?int $mode): array
    {
        $modeData = match ($mode) {
            0 => ['mode' => 'osu', 'key_count' => null],
            1 => ['mode' => 'taiko', 'key_count' => null],
            2 => ['mode' => 'catch', 'key_count' => null],
            3 => ['mode' => 'mania', 'key_count' => null], // ManiaOther
            4 => ['mode' => 'mania', 'key_count' => 4],    // Mania4k
            5 => ['mode' => 'mania', 'key_count' => 7],    // Mania7k
            default => ['mode' => 'osu', 'key_count' => null],
        };

        // Return as array structure
        return [$modeData];
    }

    /**
     * Get current rate limit status
     *
     * @return array{requests_used: int, requests_remaining: int, window_reset_in: int}
     */
    public function getRateLimitStatus(): array
    {
        $now = time();
        $windowElapsed = $now - $this->requestWindowStart;
        $windowRemaining = max(0, self::WINDOW_SIZE - $windowElapsed);

        return [
            'requests_used' => $this->requestCount,
            'requests_remaining' => max(0, self::MAX_REQUESTS_PER_10MIN - $this->requestCount),
            'window_reset_in' => $windowRemaining,
        ];
    }

    /**
     * Convert the osu! legacy mod bitmask to acronyms.
     *
     * @return array<int, string>
     */
    private function convertModsIntegerToArray(int $mods): array
    {
        return app(LegacyModDecoder::class)->decode($mods);
    }

    /**
     * Convert o!TR scoring type to our format
     *
     * o!TR scoring types: 0=Score, 1=Accuracy, 2=Combo, 3=ScoreV2
     */
    private function convertScoringType(?int $scoringType): ?string
    {
        return match ($scoringType) {
            0 => 'score',
            1 => 'accuracy',
            2 => 'combo',
            3 => 'scorev2',
            default => null,
        };
    }

    /**
     * Convert o!TR team type to our format
     *
     * o!TR team types: 0=HeadToHead, 1=TagCoop, 2=TeamVs, 3=TagTeamVs
     */
    private function convertTeamType(?int $teamType): ?string
    {
        return match ($teamType) {
            0 => 'head_to_head',
            1 => 'tag_coop',
            2 => 'team_vs',
            3 => 'tag_team_vs',
            default => null,
        };
    }
}
