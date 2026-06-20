<?php

/**
 * OsuApiService Unit Tests - Based on OpenAPI Contract
 *
 * Tests OsuApiService user sync functionality per contracts/openapi.yaml:
 *
 * User schema fields that should be synced:
 * - osu_id: integer
 * - username: string
 * - avatar_url: computed string
 * - country_code: string
 *
 * Badge schema:
 * - name: string
 * - image_url: string
 * - awarded_at: date-time
 * - is_bws_eligible: boolean
 *
 * The service should:
 * - Sync user profile data from osu! API
 * - Sync badges with BWS eligibility calculation
 * - Handle rate limiting (429 responses)
 * - Handle 404 (user not found)
 */

use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
});

describe('OsuApiService::syncUserData', function () {
    it('syncs user profile data matching OpenAPI User schema', function () {
        // Mock osu! API token endpoint
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/12345678/osu' => Http::response([
                'id' => 12345678,
                'username' => 'TestPlayer',
                'avatar_url' => 'https://a.ppy.sh/12345678',
                'country_code' => 'US',
                'badges' => [],
                'rank_history' => ['data' => []],
                'statistics' => [
                    'global_rank' => 5000,
                    'country_rank' => 100,
                    'pp' => 8500.5,
                ],
            ]),
            '*' => Http::response(['statistics' => []]),
        ]);

        $user = User::factory()->create([
            'osu_id' => 12345678,
            'main_mode' => 'osu',
        ]);
        $tournament = Tournament::factory()->create([
            'title' => 'OWC 2024',
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'approved',
        ]);
        TournamentWinner::factory()->create([
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'tournament_id' => $tournament->id,
            'placement' => 1,
            'gamemode' => 'osu',
            'badge_description' => 'OWC 2024 Winner',
            'badge_image_url' => 'https://assets.ppy.sh/badges/owc2024.png',
        ]);

        $service = new OsuApiService;
        $service->syncUserData($user);

        // Verify user data matches OpenAPI User schema
        $user->refresh();
        expect($user->username)->toBe('TestPlayer');
        expect($user->avatar_url)->toBe('https://a.ppy.sh/12345678');
        expect($user->country_code)->toBe('US');
        expect($user->osu_data_synced_at)->not->toBeNull();
    });

    it('syncs badges with BWS eligibility per OpenAPI Badge schema', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/12345678/osu' => Http::response([
                'id' => 12345678,
                'username' => 'BadgePlayer',
                'avatar_url' => 'https://a.ppy.sh/12345678',
                'country_code' => 'JP',
                'badges' => [
                    [
                        'description' => 'OWC 2024 Winner',
                        'image_url' => 'https://assets.ppy.sh/badges/owc2024.png',
                        'awarded_at' => '2024-12-15T00:00:00Z',
                    ],
                    [
                        'description' => 'Elite Mapper',
                        'image_url' => 'https://assets.ppy.sh/badges/mapper.png',
                        'awarded_at' => '2023-06-01T00:00:00Z',
                    ],
                    [
                        'description' => 'Community Contributor',
                        'image_url' => 'https://assets.ppy.sh/badges/contributor.png',
                        'awarded_at' => '2022-01-01T00:00:00Z',
                    ],
                ],
                'rank_history' => ['data' => []],
                'statistics' => [],
            ]),
            '*' => Http::response(['statistics' => []]),
        ]);

        $user = User::factory()->create([
            'osu_id' => 12345678,
            'main_mode' => 'osu',
        ]);
        $tournament = Tournament::factory()->create([
            'title' => 'OWC 2024',
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'approved',
        ]);
        TournamentWinner::factory()->create([
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'tournament_id' => $tournament->id,
            'placement' => 1,
            'gamemode' => 'osu',
            'badge_description' => 'OWC 2024 Winner',
            'badge_image_url' => 'https://assets.ppy.sh/badges/owc2024.png',
        ]);

        $service = new OsuApiService;
        $service->syncUserData($user);

        // Per OpenAPI Badge schema: name, image_url, awarded_at, is_bws_eligible
        $badges = $user->badges()->get();
        expect($badges)->toHaveCount(3);

        // Tournament badge should be BWS eligible
        $tournamentBadge = $badges->firstWhere('name', 'OWC 2024 Winner');
        expect($tournamentBadge)->not->toBeNull();
        expect($tournamentBadge->image_url)->toBe('https://assets.ppy.sh/badges/owc2024.png');
        expect($tournamentBadge->is_bws_eligible)->toBeTrue();

        // Mapper badge should NOT be BWS eligible because it is not linked to a tournament winner.
        $mapperBadge = $badges->firstWhere('name', 'Elite Mapper');
        expect($mapperBadge)->not->toBeNull();
        expect($mapperBadge->is_bws_eligible)->toBeFalse();

        // Contributor badge should NOT be BWS eligible because it is not linked to a tournament winner.
        $contributorBadge = $badges->firstWhere('name', 'Community Contributor');
        expect($contributorBadge)->not->toBeNull();
        expect($contributorBadge->is_bws_eligible)->toBeFalse();
    });

    it('replaces existing badges on sync', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/12345678/osu' => Http::response([
                'id' => 12345678,
                'username' => 'SyncPlayer',
                'avatar_url' => 'https://a.ppy.sh/12345678',
                'country_code' => 'KR',
                'badges' => [
                    [
                        'description' => 'New Badge',
                        'image_url' => 'https://assets.ppy.sh/badges/new.png',
                        'awarded_at' => '2024-01-01T00:00:00Z',
                    ],
                ],
                'rank_history' => ['data' => []],
                'statistics' => [],
            ]),
            '*' => Http::response(['statistics' => []]),
        ]);

        $user = User::factory()->create([
            'osu_id' => 12345678,
            'main_mode' => 'osu',
        ]);

        // Create existing badge
        UserBadge::factory()->create([
            'user_id' => $user->id,
            'name' => 'Old Badge',
            'image_url' => 'https://old.badge.png',
        ]);

        expect($user->badges()->count())->toBe(1);

        $service = new OsuApiService;
        $service->syncUserData($user);

        // Old badge should be replaced
        expect($user->badges()->count())->toBe(1);
        expect($user->badges()->first()->name)->toBe('New Badge');
    });

    it('matches duplicate forum topic badges by tournament badge image', function () {
        $user = User::factory()->create([
            'osu_id' => 12345678,
            'main_mode' => 'osu',
        ]);

        $wrongTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'approved',
            'forum_topic_id' => 456789,
            'badge_urls' => [
                '1' => ['https://assets.ppy.sh/badges/series-x1-third.png'],
            ],
        ]);

        $correctTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'approved',
            'forum_topic_id' => 456789,
            'badge_urls' => [
                '1' => ['https://assets.ppy.sh/badges/series-x2-first.png'],
            ],
        ]);

        TournamentWinner::factory()->create([
            'tournament_id' => $wrongTournament->id,
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'placement' => 3,
            'badge_description' => 'Series Cup X-1 Third Place',
        ]);

        $correctWinner = TournamentWinner::factory()->create([
            'tournament_id' => $correctTournament->id,
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'placement' => 1,
            'badge_description' => 'Series Cup X-2 Champion',
        ]);

        $winners = TournamentWinner::query()
            ->where('user_id', $user->id)
            ->whereNotNull('badge_description')
            ->with('tournament')
            ->get()
            ->keyBy('tournament_id');

        $method = new ReflectionMethod(OsuApiService::class, 'matchBadgeToTournamentWinner');
        $matchedWinner = $method->invoke(new OsuApiService, [
            'description' => 'Series Cup X-2 Champion',
            'url' => 'https://osu.ppy.sh/community/forums/topics/456789',
            'image_url' => 'https://assets.ppy.sh/badges/series-x2-first.png',
        ], $winners);

        expect($matchedWinner)->not->toBeNull();
        expect($matchedWinner->id)->toBe($correctWinner->id);
    });
});

describe('OsuApiService::getUser', function () {
    it('fetches and caches user data from osu! API', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/99999999' => Http::response([
                'id' => 99999999,
                'username' => 'CachedPlayer',
                'avatar_url' => 'https://a.ppy.sh/99999999',
                'country_code' => 'CA',
                'badges' => [],
            ]),
        ]);

        $service = new OsuApiService;

        // First call should hit API
        $userData = $service->getUser(99999999);
        expect($userData['username'])->toBe('CachedPlayer');

        // Second call should use cache (won't hit API again)
        $cachedData = $service->getUser(99999999);
        expect($cachedData['username'])->toBe('CachedPlayer');

        // Verify cache key exists
        expect(Cache::has('osu_user_99999999'))->toBeTrue();
    });
});

describe('OsuApiService::getUserRankStats', function () {
    it('fetches rank statistics for specific game mode', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/12345678/osu' => Http::response([
                'id' => 12345678,
                'username' => 'RankedPlayer',
                'statistics' => [
                    'global_rank' => 1234,
                    'country_rank' => 50,
                    'pp' => 12345.67,
                ],
            ]),
        ]);

        $service = new OsuApiService;
        $rankStats = $service->getUserRankStats(12345678, 'osu');

        expect($rankStats)->toHaveKey('global_rank');
        expect($rankStats)->toHaveKey('country_rank');
        expect($rankStats)->toHaveKey('pp');
        expect($rankStats['global_rank'])->toBe(1234);
        expect($rankStats['country_rank'])->toBe(50);
        expect($rankStats['pp'])->toBe(12345.67);
    });

    it('returns null values for unranked player', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/12345678/taiko' => Http::response([
                'id' => 12345678,
                'username' => 'UnrankedPlayer',
                'statistics' => [
                    'global_rank' => null,
                    'country_rank' => null,
                    'pp' => null,
                ],
            ]),
        ]);

        $service = new OsuApiService;
        $rankStats = $service->getUserRankStats(12345678, 'taiko');

        expect($rankStats['global_rank'])->toBeNull();
        expect($rankStats['country_rank'])->toBeNull();
        expect($rankStats['pp'])->toBeNull();
    });
});

describe('OsuApiService rate limiting', function () {
    it('throws exception on upstream 429 response', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/12345678' => Http::response(null, 429, ['Retry-After' => '1']),
        ]);

        $service = new OsuApiService;

        expect(fn () => $service->getUser(12345678))
            ->toThrow(Exception::class, '429 Too Many Requests');
    });
});

describe('OsuApiService::syncUser', function () {
    it('creates or updates user from osu! API data', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/55555555' => Http::response([
                'id' => 55555555,
                'username' => 'NewSyncPlayer',
                'avatar_url' => 'https://a.ppy.sh/55555555',
                'country_code' => 'DE',
                'badges' => [],
            ]),
            'osu.ppy.sh/api/v2/users/55555555/osu' => Http::response([
                'statistics' => ['global_rank' => 100, 'country_rank' => 5, 'pp' => 15000],
            ]),
            'osu.ppy.sh/api/v2/users/55555555/taiko' => Http::response([
                'statistics' => ['global_rank' => null, 'country_rank' => null, 'pp' => null],
            ]),
            'osu.ppy.sh/api/v2/users/55555555/fruits' => Http::response([
                'statistics' => ['global_rank' => null, 'country_rank' => null, 'pp' => null],
            ]),
            'osu.ppy.sh/api/v2/users/55555555/mania' => Http::response([
                'statistics' => ['global_rank' => null, 'country_rank' => null, 'pp' => null],
            ]),
        ]);

        $service = new OsuApiService;
        $user = $service->syncUser(55555555);

        // Per OpenAPI User schema
        expect($user->osu_id)->toBe(55555555);
        expect($user->username)->toBe('NewSyncPlayer');
        expect($user->avatar_url)->toBe('https://a.ppy.sh/55555555');
        expect($user->country_code)->toBe('DE');
        expect($user->osu_data_synced_at)->not->toBeNull();
    });

    it('updates existing user data on sync', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/users/66666666' => Http::response([
                'id' => 66666666,
                'username' => 'UpdatedUsername',
                'avatar_url' => 'https://a.ppy.sh/66666666_new',
                'country_code' => 'FR',
                'badges' => [],
            ]),
            'osu.ppy.sh/api/v2/users/66666666/*' => Http::response([
                'statistics' => ['global_rank' => null, 'country_rank' => null, 'pp' => null],
            ]),
        ]);

        // Create existing user with old data
        $existingUser = User::factory()->create([
            'osu_id' => 66666666,
            'username' => 'OldUsername',
            'country_code' => 'US',
        ]);

        $service = new OsuApiService;
        $updatedUser = $service->syncUser(66666666);

        // Verify data updated
        expect($updatedUser->id)->toBe($existingUser->id);
        expect($updatedUser->username)->toBe('UpdatedUsername');
        expect($updatedUser->avatar_url)->toBe('https://a.ppy.sh/66666666');
        expect($updatedUser->country_code)->toBe('FR');
    });
});

describe('OsuApiService::getMatch', function () {
    it('fetches match data from osu! API', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/matches/12345' => Http::response([
                'match' => [
                    'id' => 12345,
                    'name' => 'OWC2024: Team A vs Team B',
                    'start_time' => '2024-12-01T15:00:00Z',
                    'end_time' => '2024-12-01T16:30:00Z',
                ],
                'events' => [],
                'users' => [],
            ]),
        ]);

        $service = new OsuApiService;
        $matchData = $service->getMatch(12345);

        expect($matchData['match']['id'])->toBe(12345);
        expect($matchData['match']['name'])->toBe('OWC2024: Team A vs Team B');
    });

    it('throws exception for non-existent match (404)', function () {
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ]),
            'osu.ppy.sh/api/v2/matches/99999' => Http::response(null, 404),
        ]);

        $service = new OsuApiService;

        expect(fn () => $service->getMatch(99999))
            ->toThrow(Exception::class);
    });
});
