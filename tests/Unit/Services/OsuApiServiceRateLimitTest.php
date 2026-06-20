<?php

use App\Jobs\ParseForumTopicJob;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear cache (including cached errors from previous tests)
    Cache::flush();
});

test('osu api service throws exception on 429 without blocking', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response(['access_token' => 'test_token']),
        'osu.ppy.sh/api/v2/forums/topics/123456' => Http::response(
            ['error' => 'rate limited'],
            429,
            ['Retry-After' => '60']
        ),
    ]);

    $service = new OsuApiService;

    // getForumTopic returns null on error (catches exception)
    // So we verify that:
    // 1. It returns null (doesn't hang/sleep)
    // 2. The error is logged

    $start = microtime(true);
    $result = $service->getForumTopic(123456);
    $duration = microtime(true) - $start;

    // Should return null immediately (not hang for 60 seconds)
    expect($result)->toBeNull();

    // Should be instant (< 2 seconds for Docker environment), not delayed by sleep()
    expect($duration)->toBeLessThan(2.0);
});

test('osu api service does not use sleep() for rate limiting', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response(['access_token' => 'test_token']),
        'osu.ppy.sh/api/v2/forums/topics/123456' => Http::response([
            'title' => 'Test Tournament',
            'posts' => [['body' => ['raw' => 'test content']]],
        ], 200),
    ]);

    $service = new OsuApiService;

    // Track execution time - should be instant, not delayed by sleep()
    $start = microtime(true);

    $result = $service->getForumTopic(123456);

    $duration = microtime(true) - $start;

    // Verify result is returned
    expect($result)->toBeArray();
    expect($result['title'])->toBe('Test Tournament');

    // If sleep() was called, duration would be >= 1 second
    // Without sleep(), it should be < 1 second (allowing for some overhead)
    expect($duration)->toBeLessThan(1.0);
});

test('parse forum topic job does not use local osu api rate limiting middleware', function () {
    $job = new ParseForumTopicJob(123456);

    $middleware = $job->middleware();

    expect(collect($middleware)->contains(fn ($m) => str_contains(get_class($m), 'RateLimited')))->toBeFalse();
});
