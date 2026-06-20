<?php

use App\Jobs\AggregateStaffJob;
use App\Jobs\BatchFetchUsersJob;
use App\Jobs\BatchMergeStaffJob;
use App\Jobs\ChunkedForumParseJob;
use App\Jobs\FetchUsersChunkJob;
use App\Jobs\ParseForumTopicJob;
use App\Jobs\ProcessBulkPodiumAddJob;
use App\Jobs\ProcessBulkStaffAddJob;
use App\Jobs\SyncTournamentJob;
use App\Jobs\SyncUserFromOsu;
use App\Jobs\SyncUserProfilesJob;
use App\Models\User;
use App\Support\QueueNames;

test('horizon splits user and admin queues into dedicated one-process lanes', function () {
    expect(config('horizon.defaults.user-supervisor.queue'))->toBe([
        QueueNames::OSU_USER,
    ])
        ->and(config('horizon.defaults.user-supervisor.maxProcesses'))->toBe(1)
        ->and(config('horizon.defaults.admin-supervisor.queue'))->toBe([
            QueueNames::OSU_ADMIN_PRIORITY,
            QueueNames::OSU_ADMIN,
            'default',
            'imports',
        ])
        ->and(config('horizon.defaults.admin-supervisor.maxProcesses'))->toBe(1);
});

test('horizon trims recent metadata aggressively for railway cost control', function () {
    expect(config('horizon.trim.recent'))->toBe(15)
        ->and(config('horizon.trim.pending'))->toBe(15)
        ->and(config('horizon.trim.completed'))->toBe(15)
        ->and(config('horizon.trim.failed'))->toBe(1440)
        ->and(config('horizon.metrics.trim_snapshots.job'))->toBe(12)
        ->and(config('horizon.metrics.trim_snapshots.queue'))->toBe(12);
});

test('user initiated osu sync jobs use the user priority queue', function () {
    $job = new SyncUserFromOsu(User::factory()->make());

    expect($job->queue)->toBe(QueueNames::OSU_USER);
});

test('admin staff and podium mutations use the admin priority queue', function () {
    $staffJob = new ProcessBulkStaffAddJob(1, 2, ['staff'], 'organizer', 3);
    $podiumJob = new ProcessBulkPodiumAddJob(1, 2, ['winner'], 1, 3);

    expect($staffJob->queue)->toBe(QueueNames::OSU_ADMIN_PRIORITY)
        ->and($podiumJob->queue)->toBe(QueueNames::OSU_ADMIN_PRIORITY);
});

test('admin osu api pipeline jobs use the admin queue', function () {
    $jobs = [
        new ParseForumTopicJob(123456),
        new ChunkedForumParseJob([123456]),
        new AggregateStaffJob('txn'),
        new BatchFetchUsersJob('txn'),
        new FetchUsersChunkJob('txn', [123]),
        new BatchMergeStaffJob('txn'),
        new SyncTournamentJob(2025, 2026),
        new SyncUserProfilesJob('staff', 2025, 2026),
    ];

    foreach ($jobs as $job) {
        expect($job->queue)->toBe(QueueNames::OSU_ADMIN);
    }
});
