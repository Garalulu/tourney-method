<?php

use App\Services\OsuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        // Mock OAuth token request
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
    ]);
    Cache::flush();
});

test('returns empty array when given empty array', function () {
    $service = new OsuApiService;

    $result = $service->getUsers([]);

    expect($result)->toBeEmpty();
});

test('fetches single user from osu! api', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response([
            [
                'id' => 12345,
                'username' => 'test_user',
                'avatar_url' => 'https://example.com/avatar.jpg',
                'country_code' => 'US',
            ],
        ], 200),
    ]);

    $service = new OsuApiService;

    $result = $service->getUsers([12345]);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[12345]['username'])->toBe('test_user');
});

test('fetches multiple users in single request', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response([
            ['id' => 12345, 'username' => 'user1'],
            ['id' => 67890, 'username' => 'user2'],
            ['id' => 11111, 'username' => 'user3'],
        ], 200),
    ]);

    $service = new OsuApiService;

    $result = $service->getUsers([12345, 67890, 11111]);

    expect($result)->toHaveCount(3);
    expect($result[12345]['username'])->toBe('user1');
    expect($result[67890]['username'])->toBe('user2');
    expect($result[11111]['username'])->toBe('user3');
});

test('splits requests into chunks of 50', function () {
    // Create 75 unique osu_ids (requires 2 API calls: 50 + 25)
    $osuIds = range(1, 75);

    $callCount = 0;

    Http::fake([
        'osu.ppy.sh/api/v2/users*' => function () use (&$callCount) {
            $callCount++;

            // Return array of users (just mock first 50 each time)
            return Http::response(
                array_map(fn ($id) => ['id' => $id, 'username' => "user{$id}"], range(1, 50)),
                200
            );
        },
    ]);

    $service = new OsuApiService;

    $result = $service->getUsers($osuIds);

    // Should make 2 API calls (50 + 25)
    expect($callCount)->toBe(2);
    // Should return at least 50 users (our fake only returns 50 each call)
    expect($result)->toHaveCount(50);
});

test('returns array keyed by osu_id', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response([
            ['id' => 99999, 'username' => 'last_user'],
        ], 200),
    ]);

    $service = new OsuApiService;

    $result = $service->getUsers([99999]);

    expect($result)->toHaveKey(99999);
    expect($result[99999]['id'])->toBe(99999);
});

test('handles api errors gracefully', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response(status: 500),
    ]);

    $service = new OsuApiService;

    $exceptionThrown = false;

    try {
        $service->getUsers([12345]);
    } catch (Exception $e) {
        $exceptionThrown = true;
    }

    expect($exceptionThrown)->toBeTrue();
});

test('handles rate limiting', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response(status: 429),
    ]);

    $service = new OsuApiService;

    $exceptionThrown = false;

    try {
        $service->getUsers([12345]);
    } catch (Exception $e) {
        $exceptionThrown = true;
        expect($e->getMessage())->toContain('429 Too Many Requests');
    }

    expect($exceptionThrown)->toBeTrue();
});

test('handles duplicate osu_ids without making duplicate requests', function () {
    $callCount = 0;
    Http::fake([
        'osu.ppy.sh/api/v2/users*' => function () use (&$callCount) {
            $callCount++;

            return Http::response([
                ['id' => 12345, 'username' => 'user1'],
            ], 200);
        },
    ]);

    $service = new OsuApiService;

    // Pass duplicate IDs
    $result = $service->getUsers([12345, 12345, 12345]);

    // Should only make 1 API call
    expect($callCount)->toBe(1);
    expect($result)->toHaveCount(1);
});

test('calculates users batch request interval by osu id count', function () {
    $service = new OsuApiService;
    $method = new ReflectionMethod($service, 'requestIntervalMicroseconds');
    $method->setAccessible(true);

    expect($method->invoke($service, '/users', ['ids' => range(1, 50)]))->toBe(50_000_000)
        ->and($method->invoke($service, '/users', ['ids' => range(1, 7)]))->toBe(7_000_000)
        ->and($method->invoke($service, '/users/12345', []))->toBe(1_000_000);
});
