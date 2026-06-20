<?php

use App\Models\Tournament;
use App\Support\QueueNames;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('dispatches 3-stage chain after parsing batch completes', function () {
    // Test with empty topics list
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response(['topics' => []], 200),
    ]);

    $exitCode = $this->artisan('tournaments:parse --confirm --no-backup')
        ->execute();

    // Command should succeed
    expect($exitCode)->toBe(0);
});

test('chain dispatches BatchFetchUsersJob after Stage 1 completes', function () {
    // Single Http::fake call with all necessary URLs
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 789012, 'title' => 'Test Tournament'],
            ],
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/789012' => Http::response([
            'title' => 'Test Tournament',
            'posts' => [['body' => ['raw' => 'test']]],
        ], 200),
        'osu.ppy.sh/api/v2/users*' => Http::response([
            ['id' => 123, 'username' => 'test_user'],
        ], 200),
    ]);

    // Run the command
    Bus::fake();
    $this->artisan('tournaments:parse --confirm --no-backup')
        ->assertExitCode(0);

    // Verify job was batched
    Bus::assertBatched(function (PendingBatch $batch) {
        return $batch->jobs->count() === 1
            && $batch->queue() === QueueNames::OSU_ADMIN;
    });
});

test('handles dry-run option without dispatching jobs', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 555555, 'title' => 'Dry Run Tournament'],
            ],
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/555555' => Http::response([
            'title' => 'Dry Run Tournament',
            'posts' => [['body' => ['raw' => 'test']]],
        ], 200),
    ]);

    Bus::fake();

    $this->artisan('tournaments:parse --dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('[DRY RUN] Would parse topic: Dry Run Tournament');

    // No jobs should be dispatched in dry-run mode
    Bus::assertNothingBatched();
});

test('skips already imported topics unless force option is used', function () {
    // Create existing tournament
    Tournament::factory()->create([
        'forum_topic_id' => 999999,
        'title' => 'Existing Tournament',
    ]);

    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 999999, 'title' => 'Existing Tournament'],
            ],
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/999999' => Http::response([
            'title' => 'Existing Tournament',
            'posts' => [['body' => ['raw' => 'test']]],
        ], 200),
        'osu.ppy.sh/api/v2/users*' => Http::response([
            ['id' => 123, 'username' => 'test_user'],
        ], 200),
    ]);

    Bus::fake();

    $this->artisan('tournaments:parse --confirm --no-backup')
        ->assertExitCode(0);

    Bus::assertBatched(function (PendingBatch $batch) {
        return $batch->jobs->count() === 1;
    });

    // With --force, job should be dispatched
    $this->artisan('tournaments:parse --force --confirm --no-backup')
        ->assertExitCode(0);

    Bus::assertBatched(function (PendingBatch $batch) {
        return $batch->jobs->count() === 1;
    });
});

test('logs summary after completion', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 111111, 'title' => 'Test'],
            ],
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/111111' => Http::response([
            'title' => 'Test',
            'posts' => [['body' => ['raw' => 'test']]],
        ], 200),
        'osu.ppy.sh/api/v2/users*' => Http::response([], 200),
    ]);

    $this->artisan('tournaments:parse --confirm --no-backup')
        ->assertExitCode(0)
        ->expectsOutput('=== Parsing Summary ===');
});
