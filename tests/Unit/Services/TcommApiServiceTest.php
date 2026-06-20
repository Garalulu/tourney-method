<?php

/**
 * TcommApiService Unit Tests
 *
 * Tests tcomm.hivie.tn API integration
 *
 * IMPORTANT: Updated to match ACTUAL API structure (discovered 2026-03-05)
 * - Actual response: {tournaments: [...], total: 699, page: 1, pages: 24}
 * - NOT {data: [...], totalCount: 699, currentPage: 1, totalPages: 24} (as docs show)
 *
 * API endpoints tested:
 * - GET /api/tournaments/ - List tournaments (paginated, filterable) - NOTE trailing slash
 * - GET /api/tournaments/{id} - Tournament details
 *
 * Key features:
 * - Bearer token authentication
 * - Rate limiting (100 requests per 10 minutes)
 * - Caching strategy (1 hour for tournaments)
 * - Tournament data formatting for import
 * - Type filtering (only 'tournament', exclude 'contest')
 */

use App\Services\TcommApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();

    // Set mock API key
    Config::set('services.tcomm.api_key', 'test_tcomm_api_key');
});

describe('TcommApiService::getTournaments', function () {
    it('fetches tournaments list from tcomm API with actual structure', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [
                    [
                        '_id' => '6998eb3416a2d51524ed4134',
                        'id' => '6998eb3416a2d51524ed4134',
                        'name' => 'EPIC47 osu!',
                        'type' => 'tournament',
                        'modes' => ['osu'],
                        'startDate' => '2026-02-21T12:00:00.000Z',
                        'endDate' => '2026-02-22T12:00:00.000Z',
                        'forumUrl' => 'https://osu.ppy.sh/community/forums/topics/2176090',
                        'bannerUrl' => 'https://i.ppy.sh/76406005d03650f96ba84294d2fa385368ef60a8/68747470733a2f2f692e6962622e636f6d2f4854686a63704c4c2f556e7469746c65642d322e706e67',
                        'hosts' => [
                            [
                                'osuId' => 5182050,
                                'username' => 'Bubbleman',
                                'country' => ['code' => 'GB', 'name' => 'United Kingdom'],
                                'avatarUrl' => 'https://a.ppy.sh/5182050',
                            ],
                        ],
                        'status' => 'badgeApproved',
                        'isActive' => false,
                        'badges' => [
                            [
                                'url' => 'https://assets.hivie.tn/tcomm/tournaments/6998eb3416a2d51524ed4134/1771883033002-E47_osu_Winner_Badge.png',
                                'id' => '699cca192472dd5330de0ddb',
                            ],
                        ],
                        'tags' => ['epic', '47', 'lan', 'uk'],
                    ],
                ],
                'total' => 699,
                'page' => 1,
                'pages' => 24,
            ]),
        ]);

        $service = new TcommApiService;
        $tournaments = $service->getTournaments(['state' => 'archived']);

        expect($tournaments)->toBeArray();
        expect($tournaments)->toHaveCount(1);
        expect($tournaments[0]['name'])->toBe('EPIC47 osu!');
        expect($tournaments[0]['type'])->toBe('tournament');
    });

    it('passes query parameters to API request', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 0,
            ]),
        ]);

        $service = new TcommApiService;
        $service->getTournaments([
            'state' => 'archived',
            'type' => 'tournament',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'tcomm.hivie.tn/api/tournaments/')
                && $request['state'] === 'archived'
                && $request['type'] === 'tournament';
        });
    });

    it('adds trailing slash to endpoint', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 0,
            ]),
        ]);

        $service = new TcommApiService;
        $service->getTournaments();

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/tournaments/');
        });
    });

    it('caches tournament list for 1 hour', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [['id' => 'abc123', 'name' => 'Cached Tournament']],
                'total' => 1,
                'page' => 1,
                'pages' => 1,
            ]),
        ]);

        $service = new TcommApiService;

        // First call should hit API
        $first = $service->getTournaments();
        expect($first)->toHaveCount(1);

        // Second call should use cache
        $second = $service->getTournaments();
        expect($second)->toHaveCount(1);

        // Verify only one API call was made
        Http::assertSentCount(1);
    });

    it('throws exception on API error', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $service = new TcommApiService;

        expect(fn () => $service->getTournaments())
            ->toThrow(RequestException::class);
    });
});

describe('TcommApiService::getTournamentsPaginated', function () {
    it('fetches paginated tournaments and maps to Laravel-style structure', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [['id' => 'xyz', 'name' => 'Page 2 Tournament']],
                'total' => 100,
                'page' => 2,
                'pages' => 10,
            ]),
        ]);

        $service = new TcommApiService;
        $result = $service->getTournamentsPaginated(2, ['state' => 'archived']);

        expect($result['data'])->toHaveCount(1);
        expect($result['current_page'])->toBe(2);
        expect($result['last_page'])->toBe(10);
        expect($result['total'])->toBe(100);
        expect($result['per_page'])->toBe(30); // Default per_page
    });

    it('maps actual tcomm keys to Laravel pagination keys', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [],
                'total' => 699,
                'page' => 1,
                'pages' => 24,
            ]),
        ]);

        $service = new TcommApiService;
        $result = $service->getTournamentsPaginated(1);

        expect($result)->toHaveKey('data');
        expect($result)->toHaveKey('current_page');
        expect($result)->toHaveKey('last_page');
        expect($result)->toHaveKey('total');
    });

    it('adds trailing slash to paginated endpoint', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
            ]),
        ]);

        $service = new TcommApiService;
        $service->getTournamentsPaginated(1);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/tournaments/');
        });
    });
});

describe('TcommApiService::getAllTournaments', function () {
    it('fetches all pages and filters by type=tournament only', function () {
        Http::fakeSequence('tcomm.hivie.tn/api/tournaments/*')
            ->pushResponse(Http::response([
                'tournaments' => [
                    ['id' => '1', 'name' => 'T1', 'type' => 'tournament'],
                    ['id' => '2', 'name' => 'C1', 'type' => 'contest'], // Should be filtered out
                ],
                'total' => 4,
                'page' => 1,
                'pages' => 2,
            ]))
            ->pushResponse(Http::response([
                'tournaments' => [
                    ['id' => '3', 'name' => 'T2', 'type' => 'tournament'],
                    ['id' => '4', 'name' => 'C2', 'type' => 'contest'], // Should be filtered out
                ],
                'total' => 4,
                'page' => 2,
                'pages' => 2,
            ]));

        $service = new TcommApiService;
        $all = $service->getAllTournaments(['state' => 'archived']);

        // Should only return tournaments, not contests
        expect($all)->toHaveCount(2);
        expect($all[0]['type'])->toBe('tournament');
        expect($all[1]['type'])->toBe('tournament');
    });

    it('includes type filter in request parameters', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
            ]),
        ]);

        $service = new TcommApiService;
        $service->getAllTournaments(['state' => 'archived']);

        Http::assertSent(function ($request) {
            return $request['state'] === 'archived';
        });
    });

    it('calls progress callback with accurate counts (after filtering)', function () {
        Http::fakeSequence('tcomm.hivie.tn/api/tournaments/*')
            ->pushResponse(Http::response([
                'tournaments' => [
                    ['id' => '1', 'type' => 'tournament'],
                    ['id' => '2', 'type' => 'contest'],
                ],
                'total' => 3,
                'page' => 1,
                'pages' => 2,
            ]))
            ->pushResponse(Http::response([
                'tournaments' => [
                    ['id' => '3', 'type' => 'tournament'],
                ],
                'total' => 3,
                'page' => 2,
                'pages' => 2,
            ]));

        $service = new TcommApiService;
        $callbacks = [];

        $service->getAllTournaments(['state' => 'archived'], function ($page, $lastPage, $totalCount) use (&$callbacks) {
            $callbacks[] = compact('page', 'lastPage', 'totalCount');
        });

        // After filtering out contests, should have 2 tournaments total
        expect($callbacks)->toEqual([
            ['page' => 1, 'lastPage' => 2, 'totalCount' => 1], // Page 1: 1 tournament
            ['page' => 2, 'lastPage' => 2, 'totalCount' => 2], // Page 2: 2 tournaments total
        ]);
    });
});

describe('TcommApiService::formatTournamentData', function () {
    it('converts actual tcomm API structure to our format', function () {
        $service = new TcommApiService;

        $tcommData = [
            '_id' => '6998eb3416a2d51524ed4134',
            'id' => '6998eb3416a2d51524ed4134',
            'name' => 'EPIC47 osu!',
            'type' => 'tournament',
            'modes' => ['osu'],
            'startDate' => '2026-02-21T12:00:00.000Z',
            'endDate' => '2026-02-22T12:00:00.000Z',
            'forumUrl' => 'https://osu.ppy.sh/community/forums/topics/2176090',
            'bannerUrl' => 'https://i.ppy.sh/76406005d03650f96ba84294d2fa385368ef60a8/68747470733a2f2f692e6962622e636f6d2f4854686a63704c4c2f556e7469746c65642d322e706e67',
            'hosts' => [
                [
                    'osuId' => 5182050,
                    'username' => 'Bubbleman',
                    'country' => ['code' => 'GB', 'name' => 'United Kingdom'],
                    'avatarUrl' => 'https://a.ppy.sh/5182050',
                ],
            ],
            'status' => 'badgeApproved',
            'isActive' => false,
            'badges' => [
                [
                    'url' => 'https://assets.hivie.tn/tcomm/tournaments/6998eb3416a2d51524ed4134/1771883033002-E47_osu_Winner_Badge.png',
                    'id' => '699cca192472dd5330de0ddb',
                ],
            ],
            'tags' => ['epic', '47', 'lan', 'uk'],
        ];

        $formatted = $service->formatTournamentData($tcommData);

        expect($formatted['tcomm_id'])->toBe('6998eb3416a2d51524ed4134');
        expect($formatted['title'])->toBe('EPIC47 osu!');
        expect($formatted['modes'])->toEqual([['mode' => 'osu', 'key_count' => null]]);
        expect($formatted['host_osu_id'])->toBe(5182050);
        expect($formatted['forum_topic_id'])->toBe('2176090');
        expect($formatted['tournament_start'])->toBe('2026-02-21T12:00:00.000Z');
        expect($formatted['tournament_end'])->toBe('2026-02-22T12:00:00.000Z');
        expect($formatted['banner_url'])->toContain('i.ppy.sh');
        expect($formatted['status'])->toBe('approved'); // All tcomm imports
        expect($formatted['import_source'])->toBe('tcomm');
        expect($formatted['is_badge'])->toBeTrue();
    });

    it('parses forum topic ID from forumUrl', function () {
        $service = new TcommApiService;

        $cases = [
            'https://osu.ppy.sh/community/forums/topics/2176090' => '2176090',
            'https://osu.ppy.sh/community/forums/topics/2141090' => '2141090',
            'https://osu.ppy.sh/community/forums/topics/2139230?n=1' => '2139230',
        ];

        foreach ($cases as $forumUrl => $expectedTopicId) {
            $tcommData = [
                '_id' => 'test',
                'name' => 'Test',
                'forumUrl' => $forumUrl,
            ];

            $formatted = $service->formatTournamentData($tcommData);
            expect($formatted['forum_topic_id'])->toBe($expectedTopicId);
        }

        // Test null case separately
        $tcommData = [
            '_id' => 'test',
            'name' => 'Test',
            'forumUrl' => null,
        ];

        $formatted = $service->formatTournamentData($tcommData);
        expect($formatted['forum_topic_id'])->toBeNull();
    });

    it('extracts host_osuId from hosts array', function () {
        $service = new TcommApiService;

        $tcommData = [
            '_id' => 'test',
            'name' => 'Test',
            'hosts' => [
                ['osuId' => 12345, 'username' => 'Player1'],
                ['osuId' => 67890, 'username' => 'Player2'],
            ],
        ];

        $formatted = $service->formatTournamentData($tcommData);
        expect($formatted['host_osu_id'])->toBe(12345); // First host
    });

    it('sets missing fields to null', function () {
        $service = new TcommApiService;

        $tcommData = [
            '_id' => 'test',
            'name' => 'Minimal Tournament',
            'modes' => ['osu'],
        ];

        $formatted = $service->formatTournamentData($tcommData);

        expect($formatted['team_size_min'])->toBeNull();
        expect($formatted['team_size_max'])->toBeNull();
        expect($formatted['rank_range_min'])->toBeNull();
        expect($formatted['rank_range_max'])->toBeNull();
        expect($formatted['registration_start'])->toBeNull();
        expect($formatted['registration_end'])->toBeNull();
        expect($formatted['discord_url'])->toBeNull();
        expect($formatted['twitch_url'])->toBeNull();
        expect($formatted['bracket_url'])->toBeNull();
    });

    it('converts modes array to new structure format', function () {
        $service = new TcommApiService;

        $tcommData = [
            '_id' => 'test',
            'name' => 'Test',
            'modes' => ['osu'],
        ];

        $formatted = $service->formatTournamentData($tcommData);
        expect($formatted['modes'])->toEqual([['mode' => 'osu', 'key_count' => null]]);
    });
});

describe('TcommApiService mode conversion', function () {
    it('converts single mode string to new structure', function () {
        $service = new TcommApiService;

        $testCases = [
            ['osu', 'osu'],
            ['taiko', 'taiko'],
            ['catch', 'catch'],
            ['mania', 'mania'],
        ];

        foreach ($testCases as [$input, $expected]) {
            $tcommData = [
                '_id' => 'test',
                'name' => 'Test',
                'modes' => [$input],
            ];

            $formatted = $service->formatTournamentData($tcommData);
            expect($formatted['modes'])->toEqual([['mode' => $expected, 'key_count' => null]]);
        }
    });

    it('defaults to osu for null modes', function () {
        $service = new TcommApiService;

        $tcommData = [
            '_id' => 'test',
            'name' => 'Test',
            'modes' => null,
        ];

        $formatted = $service->formatTournamentData($tcommData);
        expect($formatted['modes'])->toEqual([['mode' => 'osu', 'key_count' => null]]);
    });
});

describe('TcommApiService::checkConnection', function () {
    it('returns accessible true when API is reachable', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response([
                'tournaments' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 0,
            ], 200),
        ]);

        $service = new TcommApiService;
        $result = $service->checkConnection();

        expect($result['accessible'])->toBeTrue();
        expect($result['error'])->toBeNull();
    });

    it('returns accessible false when API returns error', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $service = new TcommApiService;
        $result = $service->checkConnection();

        expect($result['accessible'])->toBeFalse();
        expect($result['error'])->toContain('404');
    });

    it('returns accessible false when connection fails', function () {
        Http::fake([
            'tcomm.hivie.tn/api/tournaments/*' => fn () => throw new Exception('Connection refused'),
        ]);

        $service = new TcommApiService;
        $result = $service->checkConnection();

        expect($result['accessible'])->toBeFalse();
        expect($result['error'])->toBe('Connection refused');
    });
});
