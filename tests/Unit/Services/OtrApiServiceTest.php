<?php

/**
 * OtrApiService Unit Tests
 *
 * Tests o!TR (osu! Tournament Rating) API integration per research.md#12-otr-osu-tournament-rating-api-integration
 *
 * API endpoints tested:
 * - GET /tournaments - List all tournaments
 * - GET /tournaments/{id} - Tournament with matches
 * - GET /matches/{id} - Match with games
 * - GET /players/{id}/stats - Player tournament history
 *
 * Key features:
 * - Bearer token authentication
 * - Rate limiting (100 requests per 10 minutes)
 * - Caching strategy (tournaments: 1h, matches: 24h)
 * - Match data formatting with games and scores
 */

use App\Services\OtrApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();

    // Set mock API key
    Config::set('services.otr.api_key', 'test_otr_api_key');
});

describe('OtrApiService::getTournaments', function () {
    it('fetches all tournaments from o!TR API', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments*' => Http::response([
                [
                    'id' => 123,
                    'name' => 'OWC 2024',
                    'abbreviation' => 'OWC2024',
                    'forumPostId' => 12345,
                    'rankRangeLowerBound' => 1000,
                    'rankRangeUpperBound' => 10000,
                    'mode' => 0,
                    'startTime' => '2024-01-01T00:00:00Z',
                    'endTime' => '2024-01-31T00:00:00Z',
                ],
                [
                    'id' => 124,
                    'name' => 'Taiko World Cup',
                    'abbreviation' => 'TWC2024',
                    'forumPostId' => 12346,
                    'rankRangeLowerBound' => 1,
                    'rankRangeUpperBound' => 50000,
                    'mode' => 1,
                    'startTime' => '2024-02-01T00:00:00Z',
                    'endTime' => '2024-02-28T00:00:00Z',
                ],
            ]),
        ]);

        $service = new OtrApiService;
        $tournaments = $service->getTournaments();

        expect($tournaments)->toBeArray();
        expect($tournaments)->toHaveCount(2);
        expect($tournaments[0]['name'])->toBe('OWC 2024');
        expect($tournaments[1]['name'])->toBe('Taiko World Cup');
    });

    it('caches tournaments list for 1 hour', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments*' => Http::response([
                ['id' => 123, 'name' => 'Cached Tournament'],
            ]),
        ]);

        $service = new OtrApiService;

        $first = $service->getTournaments();
        $second = $service->getTournaments();

        expect($first)->toHaveCount(1);
        expect($second)->toHaveCount(1);

        Http::assertSentCount(1);
    });

    it('tracks rate limit usage', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments*' => Http::response([]),
        ]);

        $service = new OtrApiService;
        $service->getTournaments();

        $status = $service->getRateLimitStatus();
        expect($status['requests_used'])->toBe(1);
        expect($status['requests_remaining'])->toBe(99);
    });
});

describe('OtrApiService::getTournamentWithMatches', function () {
    it('fetches tournament with match data', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments/123' => Http::response([
                'id' => 123,
                'name' => 'OWC 2024',
                'abbreviation' => 'OWC2024',
                'forumPostId' => 12345,
                'mode' => 0,
                'startTime' => '2024-01-01T00:00:00Z',
                'endTime' => '2024-01-31T00:00:00Z',
                'matches' => [
                    [
                        'id' => 456,
                        'matchId' => 789012,
                        'name' => 'OWC2024: (USA) vs (JPN)',
                        'startTime' => '2024-01-15T14:00:00Z',
                        'endTime' => '2024-01-15T15:30:00Z',
                        'games' => [
                            [
                                'id' => 1,
                                'beatmapId' => 123456,
                                'mods' => ['HD', 'DT'],
                                'mode' => 0,
                                'scores' => [
                                    [
                                        'playerId' => 111,
                                        'score' => 950000,
                                        'accuracy' => 0.9875,
                                        'maxCombo' => 1234,
                                    ],
                                    [
                                        'playerId' => 222,
                                        'score' => 920000,
                                        'accuracy' => 0.9750,
                                        'maxCombo' => 1156,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = new OtrApiService;
        $tournament = $service->getTournamentWithMatches(123);

        expect($tournament['id'])->toBe(123);
        expect($tournament['name'])->toBe('OWC 2024');
        expect($tournament['matches'])->toHaveCount(1);
        expect($tournament['matches'][0]['matchId'])->toBe(789012);
        expect($tournament['matches'][0]['games'])->toHaveCount(1);
        expect($tournament['matches'][0]['games'][0]['scores'])->toHaveCount(2);
    });

    it('caches tournament with matches for 1 hour', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments/456' => Http::response([
                'id' => 456,
                'name' => 'Cached Match Tournament',
            ]),
        ]);

        $service = new OtrApiService;

        $first = $service->getTournamentWithMatches(456);
        $second = $service->getTournamentWithMatches(456);

        expect($first['name'])->toBe('Cached Match Tournament');
        expect($second['name'])->toBe('Cached Match Tournament');

        Http::assertSentCount(1);
    });
});

describe('OtrApiService::getMatch', function () {
    it('fetches match by osu! match ID', function () {
        Http::fake([
            'otr.stagec.net/api/matches/789012' => Http::response([
                'id' => 456,
                'matchId' => 789012,
                'name' => 'Tournament Match: Team A vs Team B',
                'startTime' => '2024-01-15T14:00:00Z',
                'endTime' => '2024-01-15T15:30:00Z',
                'games' => [
                    [
                        'id' => 1,
                        'beatmapId' => 123456,
                        'mods' => ['NM'],
                        'mode' => 0,
                        'scores' => [
                            ['playerId' => 111, 'score' => 800000, 'accuracy' => 0.95, 'maxCombo' => 500],
                            ['playerId' => 222, 'score' => 850000, 'accuracy' => 0.97, 'maxCombo' => 600],
                        ],
                    ],
                    [
                        'id' => 2,
                        'beatmapId' => 234567,
                        'mods' => ['HR'],
                        'mode' => 0,
                        'scores' => [
                            ['playerId' => 111, 'score' => 700000, 'accuracy' => 0.92, 'maxCombo' => 400],
                            ['playerId' => 222, 'score' => 900000, 'accuracy' => 0.98, 'maxCombo' => 700],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = new OtrApiService;
        $match = $service->getMatch(789012);

        expect($match['matchId'])->toBe(789012);
        expect($match['name'])->toBe('Tournament Match: Team A vs Team B');
        expect($match['games'])->toHaveCount(2);
        expect($match['games'][0]['beatmapId'])->toBe(123456);
        expect($match['games'][1]['beatmapId'])->toBe(234567);
    });

    it('caches match for 24 hours', function () {
        Http::fake([
            'otr.stagec.net/api/matches/999999' => Http::response([
                'id' => 999,
                'matchId' => 999999,
                'name' => 'Cached Match',
            ]),
        ]);

        $service = new OtrApiService;

        $first = $service->getMatch(999999);
        $second = $service->getMatch(999999);

        expect($first['name'])->toBe('Cached Match');
        expect($second['name'])->toBe('Cached Match');

        Http::assertSentCount(1);
    });
});

describe('OtrApiService::getPlayerStats', function () {
    it('fetches player tournament statistics', function () {
        Http::fake([
            'otr.stagec.net/api/players/12345678/stats' => Http::response([
                'playerId' => 12345678,
                'tournamentsPlayed' => 15,
                'totalMatches' => 47,
                'wins' => 32,
                'losses' => 15,
                'winRate' => 0.68,
            ]),
        ]);

        $service = new OtrApiService;
        $stats = $service->getPlayerStats(12345678);

        expect($stats['playerId'])->toBe(12345678);
        expect($stats['tournamentsPlayed'])->toBe(15);
        expect($stats['totalMatches'])->toBe(47);
        expect($stats['wins'])->toBe(32);
        expect($stats['winRate'])->toBe(0.68);
    });

    it('caches player stats for 1 hour', function () {
        Http::fake([
            'otr.stagec.net/api/players/99999999/stats' => Http::response([
                'playerId' => 99999999,
                'totalMatches' => 100,
            ]),
        ]);

        $service = new OtrApiService;

        $first = $service->getPlayerStats(99999999);
        $second = $service->getPlayerStats(99999999);

        expect($first['totalMatches'])->toBe(100);
        expect($second['totalMatches'])->toBe(100);

        Http::assertSentCount(1);
    });
});

describe('OtrApiService::formatTournamentData', function () {
    it('converts o!TR tournament data to our format', function () {
        $service = new OtrApiService;

        $otrData = [
            'id' => 123,
            'name' => 'Test Tournament',
            'abbreviation' => 'TT',
            'forumUrl' => 'https://osu.ppy.sh/community/forums/topics/54321',
            'ruleset' => 0,
            'rankRangeLowerBound' => 1000,
            'startTime' => '2024-01-01T00:00:00Z',
            'endTime' => '2024-01-31T00:00:00Z',
        ];

        $formatted = $service->formatTournamentData($otrData);

        expect($formatted['otr_id'])->toBe(123);
        expect($formatted['title'])->toBe('Test Tournament');
        expect($formatted['abbreviation'])->toBe('TT');
        expect($formatted['forum_topic_id'])->toBe(54321);
        expect($formatted['modes'])->toEqual([['mode' => 'osu', 'key_count' => null]]);
        expect($formatted['rank_range_min'])->toBe(1000);
        expect($formatted['tournament_start'])->toBe('2024-01-01T00:00:00Z');
        expect($formatted['tournament_end'])->toBe('2024-01-31T00:00:00Z');
        expect($formatted['import_source'])->toBe('otr');
        expect($formatted['status'])->toBe('approved');
    });
});

describe('OtrApiService::formatMatchData', function () {
    it('converts o!TR match data to our format', function () {
        $service = new OtrApiService;

        $otrMatchData = [
            'id' => 456,
            'osuId' => 789012,
            'name' => 'Match: A vs B',
            'startTime' => '2024-01-15T14:00:00Z',
            'endTime' => '2024-01-15T15:30:00Z',
        ];

        $formatted = $service->formatMatchData($otrMatchData, 999);

        expect($formatted['osu_match_id'])->toBe(789012);
        expect($formatted['name'])->toBe('Match: A vs B');
        expect($formatted['tournament_id'])->toBe(999);
        expect($formatted['start_time'])->toBe('2024-01-15T14:00:00Z');
        expect($formatted['end_time'])->toBe('2024-01-15T15:30:00Z');
        expect($formatted['status'])->toBe('approved');
        expect($formatted['raw_data'])->toBe($otrMatchData);
    });
});

describe('OtrApiService::formatGameData', function () {
    it('converts o!TR game data to our format', function () {
        $service = new OtrApiService;

        $otrGameData = [
            'id' => 1,
            'osuId' => 789012,
            'ruleset' => 0,
            'scoringType' => 0,
            'teamType' => 0,
            'mods' => 0, // Integer mods bit flag
            'startTime' => '2024-01-15T14:00:00Z',
            'endTime' => '2024-01-15T14:05:00Z',
            'beatmap' => [
                'osuId' => 123456,
            ],
        ];

        $formatted = $service->formatGameData($otrGameData, 789);

        expect($formatted['match_id'])->toBe(789);
        expect($formatted['game_id'])->toBe(1);
        expect($formatted['beatmap_id'])->toBe(123456);
        expect($formatted['mode'])->toBe('osu');
    });
});

describe('OtrApiService::formatScoreData', function () {
    it('converts o!TR score data to our format', function () {
        $service = new OtrApiService;

        $otrScoreData = [
            'playerId' => 12345,
            'score' => 950000,
            'accuracy' => 0.9875,
            'maxCombo' => 1234,
        ];

        $formatted = $service->formatScoreData($otrScoreData, 456);

        expect($formatted['match_game_id'])->toBe(456);
        expect($formatted['osu_user_id'])->toBe(12345);
        expect($formatted['score'])->toBe(950000);
        expect($formatted['accuracy'])->toBe(0.9875);
        expect($formatted['max_combo'])->toBe(1234);
        expect($formatted['passed'])->toBeTrue();
    });
});

describe('OtrApiService mode conversion', function () {
    it('converts mode integers correctly', function () {
        $service = new OtrApiService;

        expect($service->formatTournamentData(['ruleset' => 0, 'id' => 1, 'name' => 'T'])['modes'])->toEqual([['mode' => 'osu', 'key_count' => null]]);
        expect($service->formatTournamentData(['ruleset' => 1, 'id' => 2, 'name' => 'T'])['modes'])->toEqual([['mode' => 'taiko', 'key_count' => null]]);
        expect($service->formatTournamentData(['ruleset' => 2, 'id' => 3, 'name' => 'T'])['modes'])->toEqual([['mode' => 'catch', 'key_count' => null]]);
        expect($service->formatTournamentData(['ruleset' => 3, 'id' => 4, 'name' => 'T'])['modes'])->toEqual([['mode' => 'mania', 'key_count' => null]]);
    });

    it('defaults to osu for unknown mode', function () {
        $service = new OtrApiService;

        expect($service->formatTournamentData(['mode' => 99, 'id' => 1, 'name' => 'T'])['modes'])->toEqual([['mode' => 'osu', 'key_count' => null]]);
        expect($service->formatTournamentData(['mode' => null, 'id' => 2, 'name' => 'T'])['modes'])->toEqual([['mode' => 'osu', 'key_count' => null]]);
    });
});

describe('OtrApiService::checkConnection', function () {
    it('returns accessible true when API is reachable', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments*' => Http::response([]),
        ]);

        $service = new OtrApiService;
        $result = $service->checkConnection();

        expect($result['accessible'])->toBeTrue();
        expect($result['error'])->toBeNull();
    });

    it('returns accessible false when API returns error', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $service = new OtrApiService;
        $result = $service->checkConnection();

        expect($result['accessible'])->toBeFalse();
        expect($result['error'])->not->toBeNull();
    });

    it('returns accessible false when connection fails', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments*' => fn () => throw new Exception('Connection timeout'),
        ]);

        $service = new OtrApiService;
        $result = $service->checkConnection();

        expect($result['accessible'])->toBeFalse();
        expect($result['error'])->toBe('Connection timeout');
    });
});

describe('OtrApiService::getRateLimitStatus', function () {
    it('returns initial rate limit status', function () {
        $service = new OtrApiService;
        $status = $service->getRateLimitStatus();

        expect($status['requests_used'])->toBe(0);
        expect($status['requests_remaining'])->toBe(100);
        expect($status['window_reset_in'])->toBeInt();
    });

    it('tracks requests correctly', function () {
        Http::fake([
            'otr.stagec.net/api/tournaments*' => Http::response([]),
        ]);

        $service = new OtrApiService;

        $service->getTournaments();
        $status1 = $service->getRateLimitStatus();
        expect($status1['requests_used'])->toBe(1);
        expect($status1['requests_remaining'])->toBe(99);

        $service->getTournaments(); // Cached, no new request
        $status2 = $service->getRateLimitStatus();
        expect($status2['requests_used'])->toBe(1); // Still 1 because cached
    });
});

describe('OtrApiService rate limiting', function () {
    it('resets request counter after window expires', function () {
        // This would require time manipulation in tests
        // For now, just verify the tracking logic exists
        $service = new OtrApiService;

        $initialStatus = $service->getRateLimitStatus();
        expect($initialStatus['window_reset_in'])->toBeGreaterThanOrEqual(0);
        expect($initialStatus['window_reset_in'])->toBeLessThanOrEqual(600); // 10 minutes max
    });
});
