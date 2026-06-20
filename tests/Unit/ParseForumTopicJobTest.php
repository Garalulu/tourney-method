<?php

use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear any existing tournaments for clean test state
    Tournament::where('forum_topic_id', '>=', 123000)->where('forum_topic_id', '<=', 129999)->delete();

    // Clear ALL cache (including OsuApiService cache)
    Cache::flush();

    // Clear Http::fake() from previous tests
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
    ]);
});

afterEach(function () {
    // Cleanup
    Tournament::where('forum_topic_id', '>=', 123000)->where('forum_topic_id', '<=', 129999)->delete();

    // Clear cache between tests
    Cache::flush();
});

test('prevents duplicate tournaments when job runs twice', function () {
    // Arrange: Mock osu! API response
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'topic' => [
                'id' => 123456,
                'title' => 'Test Tournament',
            ],
            'posts' => [
                [
                    'body' => [
                        'raw' => 'Test Tournament. Modes: osu!',
                    ],
                ],
            ],
        ]),
    ]);

    // Act: Run the same job twice sequentially
    $job1 = new ParseForumTopicJob(123456);
    app()->call([$job1, 'handle']);

    $job2 = new ParseForumTopicJob(123456);
    app()->call([$job2, 'handle']);

    // Assert: Only ONE tournament should exist (no duplicate)
    $tournaments = Tournament::where('forum_topic_id', 123456)->get();
    expect($tournaments)->toHaveCount(1);

    // Assert: Tournament was created on first run
    $tournament = $tournaments->first();
    expect($tournament->forum_topic_id)->toBe(123456);
    expect($tournament->title)->toBe('Test Tournament');
});

test('updates existing tournament when re-parsed', function () {
    // Arrange: Create existing tournament
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => 123456,
        'title' => 'Old Title',
        'description' => 'Old description',
        'field_sources' => ['title' => 'parsed', 'description' => 'parsed'],
    ]);

    // Clear the OsuApiService cache for this topic to ensure fresh API call
    Cache::forget('forum_topic_123456');

    // Mock osu! API to return updated data
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'topic' => [
                'id' => 123456,
                'title' => 'Updated Title',
            ],
            'posts' => [
                [
                    'body' => [
                        'raw' => 'Updated Title. Updated description. Modes: osu!',
                    ],
                ],
            ],
        ]),
    ]);

    // Act: Parse same topic again
    $job = new ParseForumTopicJob(123456, forceReparse: true);
    app()->call([$job, 'handle']);

    // Assert: Tournament was updated, not duplicated
    $tournaments = Tournament::where('forum_topic_id', 123456)->get();
    expect($tournaments)->toHaveCount(1);

    $tournament->refresh();
    expect($tournament->title)->toBe('Updated Title');
    // Note: description contains the entire raw BBCode, not just "Updated description"
    expect($tournament->description)->toContain('Updated description');
});

test('stores parsed team formation style when creating tournament', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'topic' => [
                'id' => 123457,
                'title' => '[osu!] Draft Cup',
            ],
            'posts' => [
                [
                    'body' => [
                        'raw' => 'Draft tournament. Modes: osu! Registration open.',
                    ],
                ],
            ],
        ]),
    ]);

    $job = new ParseForumTopicJob(123457);
    app()->call([$job, 'handle']);

    $tournament = Tournament::where('forum_topic_id', 123457)->first();

    expect($tournament)->not->toBeNull();
    expect($tournament->team_formation_style)->toBe(Tournament::TEAM_FORMATION_DRAFT);
});

test('preserves manual edits during re-parse', function () {
    // Arrange: Create tournament with manually edited title
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => 123456,
        'title' => 'Manual Title (Admin Edited)',
        'description' => 'Original description',
        'field_sources' => [
            'title' => 'manual', // Protected from updates
            'description' => 'parsed', // Can be updated
        ],
    ]);

    // Clear the OsuApiService cache for this topic
    Cache::forget('forum_topic_123456');

    // Mock osu! API to return different data
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'topic' => [
                'id' => 123456,
                'title' => 'Parsed Title',
            ],
            'posts' => [
                [
                    'body' => [
                        'raw' => 'Parsed Title. New description. Modes: osu!',
                    ],
                ],
            ],
        ]),
    ]);

    // Act: Parse the topic
    $job = new ParseForumTopicJob(123456, forceReparse: true);
    app()->call([$job, 'handle']);

    // Assert: Manual title was preserved, parsed description was updated
    $tournament->refresh();
    expect($tournament->title)->toBe('Manual Title (Admin Edited)'); // Unchanged
    expect($tournament->description)->toContain('New description'); // Updated
});

test('increments parse_count on each parse', function () {
    // Arrange: Create existing tournament
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => 123456,
        'parse_count' => 1,
    ]);

    // Clear the OsuApiService cache for this topic
    Cache::forget('forum_topic_123456');

    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'topic' => ['id' => 123456, 'title' => 'Test'],
            'posts' => [['body' => ['raw' => 'Test. Modes: osu!']]],
        ]),
    ]);

    // Act: Parse the topic
    $job = new ParseForumTopicJob(123456, forceReparse: true);
    app()->call([$job, 'handle']);

    // Assert: Parse count incremented
    $tournament->refresh();
    expect($tournament->parse_count)->toBe(2);
    expect($tournament->last_parsed_at)->not->toBeNull();
});
