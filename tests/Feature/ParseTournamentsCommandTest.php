<?php

use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Support\QueueNames;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
    ]);
});

test('tournaments parse queues forum topic parse jobs with unchanged signature', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 200001, 'title' => 'New Tournament 2024'],
            ],
        ], 200),
    ]);

    Bus::fake();

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->expectsOutput('Starting tournament parser...')
        ->assertExitCode(0);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === 'Stage 1: Parse Tournaments'
        && $batch->queue() === QueueNames::OSU_ADMIN
        && count($batch->jobs) === 1
        && $batch->jobs[0] instanceof ParseForumTopicJob
        && $batch->jobs[0]->queue === QueueNames::OSU_ADMIN
    );
});

test('tournaments parse queues existing topics for staff refresh without duplicating synchronously', function () {
    Tournament::factory()->create([
        'forum_topic_id' => 200002,
        'title' => 'Existing Tournament',
        'status' => 'approved',
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 200002, 'title' => 'Existing Tournament'],
            ],
        ], 200),
    ]);

    Bus::fake();

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertExitCode(0);

    expect(Tournament::where('forum_topic_id', 200002)->count())->toBe(1);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === 'Stage 1: Parse Tournaments');
});

test('tournaments parse dry run does not dispatch jobs', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 200003, 'title' => 'Dry Run Tournament'],
            ],
        ], 200),
    ]);

    Bus::fake();

    $this->artisan('tournaments:parse --dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('[DRY RUN] Would parse topic: Dry Run Tournament');

    Bus::assertNothingBatched();
});

test('tournaments parse requires confirm flag for data modification', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 200004, 'title' => 'Test'],
            ],
        ], 200),
    ]);

    $this->artisan('tournaments:parse')
        ->expectsOutputToContain('requires explicit confirmation.')
        ->assertExitCode(1);
});

test('tournaments parse logs summary after queueing', function () {
    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics*' => Http::response([
            'topics' => [
                ['id' => 200005, 'title' => 'Test'],
            ],
        ], 200),
    ]);

    Bus::fake();

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertExitCode(0)
        ->expectsOutput('=== Parsing Summary ===');
});
