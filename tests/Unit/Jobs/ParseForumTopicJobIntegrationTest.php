<?php

use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Services\BatchTransactionService;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
    ]);
    Cache::flush();

    // Default mock for ForumParser
    $this->mock(ForumParser::class, function ($mock) {
        $mock->shouldReceive('parseForumTopicData')
            ->andReturnUsing(fn (int $topicId) => parsedForumTopicJobIntegrationData([
                'forum_topic_id' => $topicId,
                'title' => 'Test Tournament 2024',
                'description' => 'Test description',
                'modes' => ['osu'],
            ]));

        $mock->shouldReceive('parseBanner')
            ->andReturn(null);

        $mock->shouldReceive('parseStaffFromBBcode')
            ->andReturn([
                ['osu_id' => 123, 'role' => 'organizer'],
                ['osu_id' => 456, 'role' => 'mapper'],
            ]);
    });
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function parsedForumTopicJobIntegrationData(array $overrides = []): array
{
    return array_merge([
        'forum_topic_id' => 12345,
        'title' => 'Test Tournament',
        'description' => null,
        'host_osu_id' => null,
        'host_username' => null,
        'modes' => [],
        'registration_start' => null,
        'registration_end' => null,
        'tournament_start' => null,
        'tournament_end' => null,
        'rank_range_min' => null,
        'rank_range_max' => null,
        'is_badge' => false,
        'discord_url' => null,
        'twitch_url' => null,
        'spreadsheet_url' => null,
        'bracket_url' => null,
        'registration_url' => null,
        'tcomm_url' => null,
    ], $overrides);
}

test('stores parsed staff in cache instead of merging immediately', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/123456' => Http::response([
            'title' => 'Test Tournament 2024',
            'posts' => [['body' => ['raw' => 'test content']]],
        ], 200),
    ]);

    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $job = new ParseForumTopicJob(123456, $transactionId);
    $job->handle(app()->make(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', 123456)->first();
    expect($tournament)->not->toBeNull();
    expect($tournament->title)->toBe('Test Tournament 2024');

    // Staff should be in cache, not database
    $staffData = app(BatchTransactionService::class)->getStaffPayloads($transactionId)[$tournament->id] ?? null;
    expect($staffData)->not->toBeNull();
    expect($staffData)->toHaveCount(2);
    expect($staffData[0]['osu_id'])->toBe(123);
    expect($staffData[0]['role'])->toBe('organizer');

    // No staff should be attached yet
    expect($tournament->staff()->count())->toBe(0);
});

test('stores staff in cache for existing tournaments during update', function () {
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => 789012,
        'title' => 'Old Tournament Title',
        'status' => Tournament::STATUS_PENDING,
        'host_osu_id' => null,
        'host_username' => null,
    ]);

    $this->mock(ForumParser::class, function ($mock) {
        $mock->shouldReceive('parseForumTopicData')
            ->andReturnUsing(fn (int $topicId) => parsedForumTopicJobIntegrationData([
                'forum_topic_id' => $topicId,
                'title' => 'Updated Tournament 2024',
                'description' => 'Updated',
                'modes' => ['osu'],
            ]));

        $mock->shouldReceive('parseBanner')
            ->andReturn(null);

        $mock->shouldReceive('parseStaffFromBBcode')
            ->andReturn([
                ['osu_id' => 999, 'role' => 'referee'],
            ]);
    });

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/789012' => Http::response([
            'title' => 'Updated Tournament 2024',
            'posts' => [['body' => ['raw' => 'test']]],
        ], 200),
    ]);

    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $job = new ParseForumTopicJob(789012, $transactionId);
    $job->handle(app()->make(OsuApiService::class), app(ForumParser::class));

    $tournament->refresh();
    expect($tournament->title)->toBe('Updated Tournament 2024');

    $staffData = app(BatchTransactionService::class)->getStaffPayloads($transactionId)[$tournament->id] ?? null;
    expect($staffData)->not->toBeNull();
    expect($staffData)->toHaveCount(1);
    expect($staffData[0]['osu_id'])->toBe(999);

    expect($tournament->staff()->count())->toBe(0);
});

test('uses tournament id as batch key for cache', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/999999' => Http::response([
            'title' => 'Batch Key Test',
            'posts' => [['body' => ['raw' => 'test']]],
        ], 200),
    ]);

    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $job = new ParseForumTopicJob(999999, $transactionId);
    $job->handle(app()->make(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', 999999)->first();

    $staffData = app(BatchTransactionService::class)->getStaffPayloads($transactionId);
    expect($staffData)->toHaveKey($tournament->id);
});

test('handles empty staff list gracefully', function () {
    $this->mock(ForumParser::class, function ($mock) {
        $mock->shouldReceive('parseForumTopicData')
            ->andReturnUsing(fn (int $topicId) => parsedForumTopicJobIntegrationData([
                'forum_topic_id' => $topicId,
                'title' => 'No Staff Tournament',
                'description' => 'Test',
                'modes' => ['osu'],
            ]));

        $mock->shouldReceive('parseBanner')
            ->andReturn(null);

        $mock->shouldReceive('parseStaffFromBBcode')
            ->andReturn([]);
    });

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/555555' => Http::response([
            'title' => 'No Staff Tournament',
            'posts' => [['body' => ['raw' => 'test']]],
        ], 200),
    ]);

    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $job = new ParseForumTopicJob(555555, $transactionId);
    $job->handle(app()->make(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', 555555)->first();

    $staffData = app(BatchTransactionService::class)->getStaffPayloads($transactionId)[$tournament->id] ?? null;
    expect($staffData)->toBeArray();
    expect($staffData)->toBeEmpty();
});

test('does not attach staff to tournament immediately', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/333333' => Http::response([
            'title' => 'No Immediate Attach',
            'posts' => [['body' => ['raw' => 'test']]],
        ], 200),
    ]);

    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $job = new ParseForumTopicJob(333333, $transactionId);
    $job->handle(app()->make(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', 333333)->first();

    expect($tournament->staff()->count())->toBe(0);
    expect(app(BatchTransactionService::class)->getStaffPayloads($transactionId))->toHaveKey($tournament->id);
});
