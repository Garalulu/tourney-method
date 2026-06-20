<?php

use App\Jobs\BatchFetchUsersJob;
use App\Jobs\BatchMergeStaffJob;
use App\Jobs\FetchUsersChunkJob;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BatchTransactionService;
use App\Services\OsuApiService;
use App\Support\QueueNames;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
    ]);
    Cache::flush();
});

test('coordinator dispatches user fetch chunks from db backed staff payloads', function () {
    Bus::fake();

    $transactionId = 'test-txn-chunks';
    $tournament = Tournament::factory()->create();
    $staff = [];

    for ($i = 1; $i <= 75; $i++) {
        $staff[] = ['osu_id' => $i, 'role' => 'mapper'];
    }

    app(BatchTransactionService::class)->storeTournamentStaff($transactionId, $tournament->id, $staff);

    (new BatchFetchUsersJob($transactionId))->handle();

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === 'Stage 2b: Fetch Users'
        && $batch->queue() === QueueNames::OSU_ADMIN
        && count($batch->jobs) === 2
        && $batch->jobs[0] instanceof FetchUsersChunkJob
        && $batch->jobs[0]->queue === QueueNames::OSU_ADMIN
    );
});

test('coordinator maps locally flagged staff before dispatching user fetch chunks', function () {
    Bus::fake();

    $transactionId = 'test-txn-local-staff-map';
    $tournament = Tournament::factory()->create();
    $localUser = User::factory()->create([
        'osu_id' => 100,
        'country_code' => 'KR',
    ]);

    app(BatchTransactionService::class)->storeTournamentStaff($transactionId, $tournament->id, [
        ['osu_id' => 100, 'role' => 'organizer'],
        ['osu_id' => 200, 'role' => 'mapper'],
    ]);

    (new BatchFetchUsersJob($transactionId))->handle();

    $mapping = app(BatchTransactionService::class)->getUserMapping($transactionId);
    expect($mapping[100])->toBe($localUser->id);

    Bus::assertBatched(function (PendingBatch $batch) {
        if ($batch->name !== 'Stage 2b: Fetch Users' || count($batch->jobs) !== 1) {
            return false;
        }

        $job = $batch->jobs[0];
        if (! $job instanceof FetchUsersChunkJob) {
            return false;
        }

        $property = new ReflectionProperty($job, 'osuIds');
        $property->setAccessible(true);

        return $property->getValue($job) === [200];
    });
});

test('coordinator dispatches merge when no users need fetching', function () {
    Bus::fake();

    $transactionId = app(BatchTransactionService::class)->generateTransactionId();

    (new BatchFetchUsersJob($transactionId))->handle();

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === 'Stage 3: Merge Staff'
        && $batch->queue() === QueueNames::OSU_ADMIN
        && count($batch->jobs) === 1
        && $batch->jobs[0] instanceof BatchMergeStaffJob
        && $batch->jobs[0]->queue === QueueNames::OSU_ADMIN
    );
});

test('coordinator preserves priority queue for fetch chunks and merge stage', function () {
    Bus::fake();

    $transactionId = 'test-txn-priority';
    $tournament = Tournament::factory()->create();

    app(BatchTransactionService::class)->storeTournamentStaff($transactionId, $tournament->id, [
        ['osu_id' => 123, 'role' => 'organizer'],
    ]);

    (new BatchFetchUsersJob($transactionId, queueName: QueueNames::OSU_ADMIN_PRIORITY))->handle();

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === 'Stage 2b: Fetch Users'
        && $batch->queue() === QueueNames::OSU_ADMIN_PRIORITY
        && $batch->jobs[0] instanceof FetchUsersChunkJob
        && $batch->jobs[0]->queue === QueueNames::OSU_ADMIN_PRIORITY
    );

    $emptyTransactionId = app(BatchTransactionService::class)->generateTransactionId();

    (new BatchFetchUsersJob($emptyTransactionId, queueName: QueueNames::OSU_ADMIN_PRIORITY))->handle();

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === 'Stage 3: Merge Staff'
        && $batch->queue() === QueueNames::OSU_ADMIN_PRIORITY
        && $batch->jobs[0] instanceof BatchMergeStaffJob
        && $batch->jobs[0]->queue === QueueNames::OSU_ADMIN_PRIORITY
    );
});

test('coordinator throws when parse batch is missing', function () {
    expect(fn () => (new BatchFetchUsersJob('missing-txn'))->handle())
        ->toThrow(Exception::class, 'Staff data not found for transaction');
});

test('fetch chunk syncs users and stores db user mapping', function () {
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();

    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response([
            [
                'id' => 100,
                'username' => 'user100',
                'avatar_url' => 'https://a.ppy.sh/100',
                'country_code' => 'KR',
                'previous_usernames' => ['old100'],
            ],
            [
                'id' => 200,
                'username' => 'user200',
                'avatar_url' => 'https://a.ppy.sh/200',
                'country_code' => 'US',
                'previous_usernames' => [],
            ],
        ], 200),
    ]);

    (new FetchUsersChunkJob($transactionId, [100, 200]))->handle(
        app(OsuApiService::class),
        app(BatchTransactionService::class)
    );

    expect(User::where('osu_id', 100)->first()->username)->toBe('user100');
    expect(User::where('osu_id', 200)->first()->username)->toBe('user200');

    $user = User::where('osu_id', 100)->first();

    expect($user->avatar_url)->toBe('https://a.ppy.sh/100');
    expect($user->country_code)->toBe('KR');
    expect($user->previous_usernames)->toBe(['old100']);
    expect($user->osu_data_synced_at)->not->toBeNull();

    $mapping = app(BatchTransactionService::class)->getUserMapping($transactionId);

    expect($mapping)->toHaveKeys([100, 200]);
});

test('parse staff pipeline syncs same profile fields as staff user profile sync', function () {
    $transactionId = 'test-txn-staff-profile-equivalence';
    $tournament = Tournament::factory()->create([
        'modes' => ['taiko'],
    ]);

    app(BatchTransactionService::class)->storeTournamentStaff($transactionId, $tournament->id, [
        ['osu_id' => 777, 'role' => 'mapper'],
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response([
            [
                'id' => 777,
                'username' => 'parsed_staff',
                'avatar_url' => 'https://a.ppy.sh/777',
                'country_code' => 'KR',
                'previous_usernames' => ['old_parsed_staff'],
            ],
        ], 200),
    ]);

    (new FetchUsersChunkJob($transactionId, [777]))->handle(
        app(OsuApiService::class),
        app(BatchTransactionService::class)
    );

    (new BatchMergeStaffJob($transactionId))->handle();

    $user = User::where('osu_id', 777)->firstOrFail();

    expect($user->username)->toBe('parsed_staff');
    expect($user->avatar_url)->toBe('https://a.ppy.sh/777');
    expect($user->country_code)->toBe('KR');
    expect($user->previous_usernames)->toBe(['old_parsed_staff']);
    expect($user->osu_data_synced_at)->not->toBeNull();
    expect($user->main_mode)->toBe('taiko');
    expect($user->main_mode_source)->toBe('auto_detected');
});

test('fetch chunk restores soft deleted users and updates username', function () {
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();

    $user = User::factory()->create([
        'osu_id' => 999,
        'username' => 'old_username',
    ]);
    $user->delete();

    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response([
            [
                'id' => 999,
                'username' => 'new_username',
                'avatar_url' => 'https://a.ppy.sh/999',
                'country_code' => 'JP',
                'previous_usernames' => ['old_username'],
            ],
        ], 200),
    ]);

    (new FetchUsersChunkJob($transactionId, [999]))->handle(
        app(OsuApiService::class),
        app(BatchTransactionService::class)
    );

    $user = User::where('osu_id', 999)->first();

    expect($user)->not->toBeNull();
    expect($user->username)->toBe('new_username');
    expect($user->avatar_url)->toBe('https://a.ppy.sh/999');
    expect($user->country_code)->toBe('JP');
    expect($user->previous_usernames)->toBe(['old_username']);
});

test('fetch chunk does not write tournament staff directly', function () {
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();

    Http::fake([
        'osu.ppy.sh/api/v2/users*' => Http::response([
            ['id' => 123, 'username' => 'user123'],
        ], 200),
    ]);

    (new FetchUsersChunkJob($transactionId, [123]))->handle(
        app(OsuApiService::class),
        app(BatchTransactionService::class)
    );

    expect(DB::table('tournament_staff')->count())->toBe(0);
});

test('fetch jobs use explicit retry and timeout settings', function () {
    $coordinator = new BatchFetchUsersJob('test-txn-retry');
    $chunk = new FetchUsersChunkJob('test-txn-retry', [123]);

    expect($coordinator->tries)->toBe(3);
    expect($coordinator->backoff)->toBe([60, 120, 240]);
    expect($chunk->tries)->toBe(3);
    expect($chunk->backoff)->toBe([60, 120, 240]);
    expect($chunk->timeout)->toBe(120);
});
