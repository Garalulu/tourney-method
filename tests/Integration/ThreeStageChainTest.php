<?php

use App\Jobs\AggregateStaffJob;
use App\Jobs\BatchFetchUsersJob;
use App\Jobs\BatchMergeStaffJob;
use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BatchTransactionService;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('full 3-stage chain processes tournaments end-to-end', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/100001' => Http::response([
            'title' => 'Test Tournament 1',
            'posts' => [['body' => ['raw' => 'Tournament post']]],
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/100002' => Http::response([
            'title' => 'Test Tournament 2',
            'posts' => [['body' => ['raw' => 'Another tournament']]],
        ], 200),
        'osu.ppy.sh/api/v2/users*' => function () {
            // Return only the requested users (1001, 1002 from staff data)
            return Http::response([
                ['id' => 1001, 'username' => 'organizer_user'],
                ['id' => 1002, 'username' => 'mapper_user'],
            ], 200);
        },
    ]);
    Cache::flush();

    $this->mock(ForumParser::class, function ($mock) {
        $mock->shouldReceive('parseBanner')->andReturnNull();
        $mock->shouldReceive('parseForumTopicData')->andReturn([
            'title' => 'Test Tournament',
            'description' => 'Test',
            'host_osu_id' => null,
            'host_username' => null,
            'modes' => ['osu'],
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
        ]);

        $mock->shouldReceive('parseStaffFromBBcode')->andReturn([
            ['osu_id' => 1001, 'role' => 'organizer'],
            ['osu_id' => 1002, 'role' => 'mapper'],
        ]);
    });

    // Generate transaction ID
    $transactionId = 'test-batch-integration';
    $transaction = app(BatchTransactionService::class);

    // Stage 1: Parse tournaments and cache staff
    $job1 = new ParseForumTopicJob(100001, $transactionId);
    $job1->handle(app(OsuApiService::class), app(ForumParser::class));

    $job2 = new ParseForumTopicJob(100002, $transactionId);
    $job2->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify Stage 1: Tournaments created, staff cached
    $tournament1 = Tournament::where('forum_topic_id', 100001)->first();
    $tournament2 = Tournament::where('forum_topic_id', 100002)->first();

    expect($tournament1)->not->toBeNull();
    expect($tournament2)->not->toBeNull();
    expect($tournament1->staff()->count())->toBe(0);
    expect($tournament2->staff()->count())->toBe(0);

    // Stage 2a: Aggregate staff
    $aggregateJob = new AggregateStaffJob($transactionId);
    $aggregateJob->handle($transaction);

    // Verify aggregation
    $aggregatedStaff = $transaction->getStaffPayloads($transactionId);
    expect($aggregatedStaff)->toBeArray();
    expect($aggregatedStaff)->toHaveCount(2);

    // Stage 2b: Fetch users
    $fetchJob = new BatchFetchUsersJob($transactionId);
    $fetchJob->handle(app(OsuApiService::class));

    // Verify Stage 2: Users created, mapping cached
    expect(User::where('osu_id', 1001)->first()->username)->toBe('organizer_user');
    expect(User::where('osu_id', 1002)->first()->username)->toBe('mapper_user');

    $userMapping = $transaction->getUserMapping($transactionId);
    expect($userMapping)->toBeArray();
    expect($userMapping)->toHaveCount(2);

    // Stage 3: Merge staff
    $mergeJob = new BatchMergeStaffJob($transactionId);
    $mergeJob->handle();

    // Verify Stage 3: Staff attached to tournaments
    $tournament1->refresh();
    $tournament2->refresh();

    expect($tournament1->staff()->count())->toBe(2);
    expect($tournament2->staff()->count())->toBe(2);

    // Verify roles
    $organizer = $tournament1->staff()->where('user_id', $userMapping[1001])->first();
    expect($organizer->pivot->role)->toBe('organizer');

    // Verify host updated from organizer
    expect($tournament1->host_osu_id)->toBe(1001);
    expect($tournament1->host_username)->toBe('organizer_user');

    // Persisted handoff state remains available for audit and recovery.
    expect($transaction->hasTransaction($transactionId))->toBeTrue();
});

test('3-stage chain handles empty tournaments gracefully', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/400001' => Http::response([
            'title' => 'Empty Tournament',
            'posts' => [['body' => ['raw' => 'Post']]],
        ], 200),
    ]);
    Cache::flush();

    $this->mock(ForumParser::class, function ($mock) {
        $mock->shouldReceive('parseBanner')->andReturnNull();
        $mock->shouldReceive('parseForumTopicData')->andReturn([
            'title' => 'Empty',
            'description' => 'Empty',
            'host_osu_id' => null,
            'host_username' => null,
            'modes' => ['osu'],
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
        ]);

        $mock->shouldReceive('parseStaffFromBBcode')->andReturn([]);
    });

    // Generate transaction ID
    $transactionId = 'test-empty';
    $transaction = app(BatchTransactionService::class);

    // Stage 1
    $job = new ParseForumTopicJob(400001, $transactionId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', 400001)->first();
    expect($tournament)->not->toBeNull();

    // Stage 2a: Aggregate staff
    $aggregateJob = new AggregateStaffJob($transactionId);
    $aggregateJob->handle($transaction);

    // Stage 2b: Fetch users
    $fetchJob = new BatchFetchUsersJob($transactionId);
    $fetchJob->handle(app(OsuApiService::class));

    // Stage 3
    $mergeJob = new BatchMergeStaffJob($transactionId);
    $mergeJob->handle();

    // Verify no staff attached, no errors
    $tournament->refresh();
    expect($tournament->staff()->count())->toBe(0);
    expect($tournament->host_osu_id)->toBeNull();
    expect($tournament->host_username)->toBeNull();

    expect($transaction->hasTransaction($transactionId))->toBeTrue();
});

test('3-stage chain handles duplicate staff across tournaments', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'title' => 'Tournament',
            'posts' => [['body' => ['raw' => 'Post']]],
        ], 200),
        'osu.ppy.sh/api/v2/users*' => function () {
            // Same user (1001) appears as staff in both tournaments
            return Http::response([
                ['id' => 1001, 'username' => 'multi_role_user'],
                ['id' => 1002, 'username' => 'secondary_user'],
            ], 200);
        },
    ]);
    Cache::flush();

    $this->mock(ForumParser::class, function ($mock) {
        $mock->shouldReceive('parseBanner')->andReturnNull();
        $mock->shouldReceive('parseForumTopicData')->andReturn([
            'title' => 'Tournament',
            'description' => 'Test',
            'host_osu_id' => null,
            'host_username' => null,
            'modes' => ['osu'],
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
        ]);

        // Same user (1001) with different roles in different tournaments
        $mock->shouldReceive('parseStaffFromBBcode')->andReturn([
            ['osu_id' => 1001, 'role' => 'organizer'],
            ['osu_id' => 1002, 'role' => 'mapper'],
        ]);
    });

    // Generate transaction ID
    $transactionId = 'test-duplicate-staff';
    $transaction = app(BatchTransactionService::class);

    // Stage 1: Create two tournaments
    $job1 = new ParseForumTopicJob(200001, $transactionId);
    $job1->handle(app(OsuApiService::class), app(ForumParser::class));

    $job2 = new ParseForumTopicJob(200002, $transactionId);
    $job2->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament1 = Tournament::where('forum_topic_id', 200001)->first();
    $tournament2 = Tournament::where('forum_topic_id', 200002)->first();

    // Stage 2a: Aggregate staff
    $aggregateJob = new AggregateStaffJob($transactionId);
    $aggregateJob->handle($transaction);

    // Stage 2b: Fetch users (should deduplicate)
    $fetchJob = new BatchFetchUsersJob($transactionId);
    $fetchJob->handle(app(OsuApiService::class));

    $userMapping = $transaction->getUserMapping($transactionId);
    expect($userMapping)->toHaveCount(2); // Only 2 unique users

    // Stage 3: Merge staff
    $mergeJob = new BatchMergeStaffJob($transactionId);
    $mergeJob->handle();

    // Verify both tournaments have the same users attached
    $tournament1->refresh();
    $tournament2->refresh();

    expect($tournament1->staff()->count())->toBe(2);
    expect($tournament2->staff()->count())->toBe(2);

    // Verify same user_id in both tournaments
    $staff1Ids = $tournament1->staff->pluck('id')->sort()->values();
    $staff2Ids = $tournament2->staff->pluck('id')->sort()->values();
    expect($staff1Ids)->toEqual($staff2Ids);
});

test('3-stage chain updates host from first organizer role', function () {
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 86400,
        ], 200),
        'osu.ppy.sh/api/v2/forums/topics/300001' => Http::response([
            'title' => 'Tournament',
            'posts' => [['body' => ['raw' => 'Post']]],
        ], 200),
        'osu.ppy.sh/api/v2/users*' => function () {
            return Http::response([
                ['id' => 9999, 'username' => 'tournament_host'],
            ], 200);
        },
    ]);
    Cache::flush();

    $this->mock(ForumParser::class, function ($mock) {
        $mock->shouldReceive('parseBanner')->andReturnNull();
        $mock->shouldReceive('parseForumTopicData')->andReturn([
            'title' => 'Tournament',
            'description' => 'Test',
            'host_osu_id' => null,
            'host_username' => null,
            'modes' => ['osu'],
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
        ]);

        $mock->shouldReceive('parseStaffFromBBcode')->andReturn([
            ['osu_id' => 9999, 'role' => 'organizer'],
        ]);
    });

    // Generate transaction ID
    $transactionId = 'test-host-update';
    $transaction = app(BatchTransactionService::class);

    // Stage 1
    $job = new ParseForumTopicJob(300001, $transactionId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', 300001)->first();
    expect($tournament->host_osu_id)->toBeNull();
    expect($tournament->host_username)->toBeNull();

    // Stage 2a: Aggregate staff
    $aggregateJob = new AggregateStaffJob($transactionId);
    $aggregateJob->handle($transaction);

    // Stage 2b: Fetch users
    $fetchJob = new BatchFetchUsersJob($transactionId);
    $fetchJob->handle(app(OsuApiService::class));

    // Stage 3
    $mergeJob = new BatchMergeStaffJob($transactionId);
    $mergeJob->handle();

    // Verify host was updated from organizer
    $tournament->refresh();
    expect($tournament->host_osu_id)->toBe(9999);
    expect($tournament->host_username)->toBe('tournament_host');

    // Verify organizer is attached as staff
    expect($tournament->staff()->count())->toBe(1);
    expect($tournament->staff()->first()->pivot->role)->toBe('organizer');
});
