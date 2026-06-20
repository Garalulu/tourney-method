<?php

use App\Jobs\ProcessBulkPodiumAddJob;
use App\Jobs\ProcessBulkStaffAddJob;
use App\Models\AdminAsyncOperation;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserRankHistory;
use App\Services\OsuApiService;
use App\Services\TournamentParticipantSyncService;
use App\Services\UserProfileSyncService;
use App\Support\QueueNames;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tournament = Tournament::factory()->create([
        'forum_topic_id' => 123456,
        'modes' => ['osu'],
        'registration_start' => now()->subMonths(4),
        'registration_end' => now()->subMonths(3),
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);
});

test('bulk staff add queues async operation instead of calling osu api inline', function () {
    Queue::fake();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'usernames' => 'alice,bob',
            'role' => 'organizer',
        ]);

    $response->assertAccepted()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['operation_id']);

    $operation = AdminAsyncOperation::query()->findOrFail($response->json('operation_id'));

    expect($operation->type)->toBe(AdminAsyncOperation::TYPE_STAFF_BULK_ADD)
        ->and($operation->total)->toBe(2)
        ->and($operation->status)->toBe(AdminAsyncOperation::STATUS_PENDING);

    Queue::assertPushed(
        ProcessBulkStaffAddJob::class,
        fn (ProcessBulkStaffAddJob $job) => $job->queue === QueueNames::OSU_ADMIN_PRIORITY
    );
});

test('bulk podium add queues async operation', function () {
    Queue::fake();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.podium.store', $this->tournament), [
            'placement' => 1,
            'usernames' => 'winner_one,winner_two',
        ]);

    $response->assertAccepted()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['operation_id']);

    $operation = AdminAsyncOperation::query()->findOrFail($response->json('operation_id'));

    expect($operation->type)->toBe(AdminAsyncOperation::TYPE_PODIUM_BULK_ADD)
        ->and($operation->total)->toBe(2);

    Queue::assertPushed(
        ProcessBulkPodiumAddJob::class,
        fn (ProcessBulkPodiumAddJob $job) => $job->queue === QueueNames::OSU_ADMIN_PRIORITY
    );
});

test('operation progress endpoint returns status and refreshed fragments', function () {
    $user = User::factory()->create(['username' => 'StaffUser']);
    TournamentStaff::query()->create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    $operation = AdminAsyncOperation::query()->create([
        'type' => AdminAsyncOperation::TYPE_STAFF_BULK_ADD,
        'tournament_id' => $this->tournament->id,
        'status' => AdminAsyncOperation::STATUS_COMPLETED,
        'total' => 1,
        'completed' => 1,
        'message' => 'Done',
        'result' => ['refresh' => ['staff']],
        'errors' => [],
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.operations.show', $operation));

    $response->assertOk()
        ->assertJsonPath('percent', 100)
        ->assertJsonPath('status', AdminAsyncOperation::STATUS_COMPLETED);

    expect($response->json('fragments.staff'))->toContain('StaffUser');
});

test('operation progress endpoint returns podium team cards in refreshed fragment', function () {
    $user = User::factory()->create(['username' => 'WinnerUser']);
    TournamentWinner::query()->create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'placement' => 1,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'gamemode' => 'osu',
    ]);

    $operation = AdminAsyncOperation::query()->create([
        'type' => AdminAsyncOperation::TYPE_PODIUM_BULK_ADD,
        'tournament_id' => $this->tournament->id,
        'status' => AdminAsyncOperation::STATUS_COMPLETED,
        'total' => 1,
        'completed' => 1,
        'message' => 'Done',
        'result' => ['refresh' => ['podium']],
        'errors' => [],
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.operations.show', $operation));

    $response->assertOk()
        ->assertJsonPath('fragments.podium', fn (string $html) => str_contains($html, 'data-podium-group-card')
            && str_contains($html, 'data-podium-new-group-target')
            && str_contains($html, 'WinnerUser'));
});

test('reparse json request returns operation id and transaction id', function () {
    Bus::fake();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.reparse', $this->tournament));

    $response->assertAccepted()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['operation_id', 'transaction_id']);

    $operation = AdminAsyncOperation::query()->findOrFail($response->json('operation_id'));

    expect($operation->type)->toBe(AdminAsyncOperation::TYPE_TOURNAMENT_REPARSE)
        ->and($operation->total)->toBe(4);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === "Stage 1: Parse Tournament ({$this->tournament->title})"
        && $batch->queue() === QueueNames::OSU_ADMIN_PRIORITY
        && $batch->jobs[0]->queue === QueueNames::OSU_ADMIN_PRIORITY
    );
});

test('participant service syncs staff user through shared profile sync service', function () {
    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    /** @var UserProfileSyncService&MockInterface $profileSync */
    $profileSync = Mockery::mock(UserProfileSyncService::class);

    $osuApi->shouldReceive('getUserByUsername')
        ->once()
        ->with('SomeUser')
        ->andReturn([
            'id' => 998877,
            'username' => 'SomeUser',
            'avatar_url' => 'https://a.ppy.sh/998877',
            'country_code' => 'KR',
            'previous_usernames' => ['OldSomeUser'],
        ]);

    $profileSync->shouldReceive('syncBasicProfileFromApiData')
        ->once()
        ->andReturnUsing(function (User $user, array $apiUser) {
            $user->update([
                'username' => $apiUser['username'],
                'country_code' => $apiUser['country_code'],
                'previous_usernames' => $apiUser['previous_usernames'],
                'osu_data_synced_at' => now(),
            ]);

            return $user->fresh();
        });

    $profileSync->shouldReceive('syncStaffProfileMetadata')->once();

    $service = new TournamentParticipantSyncService($osuApi, $profileSync);
    $staff = $service->addStaffByUsername($this->tournament, 'SomeUser', 'organizer', $this->admin->id);

    expect($staff->user->osu_id)->toBe(998877)
        ->and($staff->role)->toBe('organizer');

    $this->assertDatabaseHas('users', [
        'osu_id' => 998877,
        'username' => 'SomeUser',
        'country_code' => 'KR',
    ]);
});

test('participant service adds locally flagged staff without osu api lookup', function () {
    $user = User::factory()->create([
        'username' => 'CurrentStaff',
        'country_code' => 'KR',
        'previous_usernames' => ['OldStaff'],
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    /** @var UserProfileSyncService&MockInterface $profileSync */
    $profileSync = Mockery::mock(UserProfileSyncService::class);

    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserByUsername')->never();
    $profileSync->shouldReceive('syncBasicProfileFromApiData')->never();
    $profileSync->shouldReceive('syncStaffProfileMetadata')->once();

    $service = new TournamentParticipantSyncService($osuApi, $profileSync);
    $staff = $service->addStaffByUsername($this->tournament, 'oldstaff', 'organizer', $this->admin->id);

    expect($staff->user_id)->toBe($user->id)
        ->and($staff->role)->toBe('organizer');
});

test('participant service gives current usernames priority over previous usernames', function () {
    $current = User::factory()->create([
        'username' => 'PriorityName',
        'country_code' => 'KR',
    ]);
    User::factory()->create([
        'username' => 'FormerOwner',
        'country_code' => 'US',
        'previous_usernames' => ['PriorityName'],
        'updated_at' => now()->addMinute(),
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    /** @var UserProfileSyncService&MockInterface $profileSync */
    $profileSync = Mockery::mock(UserProfileSyncService::class);

    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserByUsername')->never();
    $profileSync->shouldReceive('syncBasicProfileFromApiData')->never();
    $profileSync->shouldReceive('syncStaffProfileMetadata')->once();

    $service = new TournamentParticipantSyncService($osuApi, $profileSync);
    $staff = $service->addStaffByUsername($this->tournament, 'priorityname', 'organizer', $this->admin->id);

    expect($staff->user_id)->toBe($current->id);
});

test('participant service uses the most recently updated previous username match', function () {
    User::factory()->create([
        'username' => 'OlderOwner',
        'country_code' => 'US',
        'previous_usernames' => ['HistoricalStaff'],
        'updated_at' => now()->subDay(),
    ]);
    $newer = User::factory()->create([
        'username' => 'NewerOwner',
        'country_code' => 'KR',
        'previous_usernames' => ['HistoricalStaff'],
        'updated_at' => now(),
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    /** @var UserProfileSyncService&MockInterface $profileSync */
    $profileSync = Mockery::mock(UserProfileSyncService::class);

    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserByUsername')->never();
    $profileSync->shouldReceive('syncBasicProfileFromApiData')->never();
    $profileSync->shouldReceive('syncStaffProfileMetadata')->once();

    $service = new TournamentParticipantSyncService($osuApi, $profileSync);
    $staff = $service->addStaffByUsername($this->tournament, 'historicalstaff', 'mapper', $this->admin->id);

    expect($staff->user_id)->toBe($newer->id);
});

test('participant service fetches staff from osu api when local user lacks flag', function () {
    $user = User::factory()->create([
        'osu_id' => 112233,
        'username' => 'NeedsFlag',
        'country_code' => null,
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    /** @var UserProfileSyncService&MockInterface $profileSync */
    $profileSync = Mockery::mock(UserProfileSyncService::class);

    $osuApi->shouldReceive('getUser')
        ->once()
        ->with(112233)
        ->andReturn([
            'id' => 112233,
            'username' => 'NeedsFlag',
            'avatar_url' => 'https://a.ppy.sh/112233',
            'country_code' => 'JP',
        ]);
    $osuApi->shouldReceive('getUserByUsername')->never();

    $profileSync->shouldReceive('syncBasicProfileFromApiData')
        ->once()
        ->andReturnUsing(function (User $user, array $apiUser) {
            $user->update([
                'username' => $apiUser['username'],
                'country_code' => $apiUser['country_code'],
                'osu_data_synced_at' => now(),
            ]);

            return $user->fresh();
        });
    $profileSync->shouldReceive('syncStaffProfileMetadata')->once();

    $service = new TournamentParticipantSyncService($osuApi, $profileSync);
    $staff = $service->addStaffByUsername($this->tournament, 'NeedsFlag', 'mapper', $this->admin->id);

    expect($staff->user_id)->toBe($user->id)
        ->and($staff->user->country_code)->toBe('JP');
});

test('participant service adds locally ranked podium user without osu api lookup', function () {
    $user = User::factory()->create([
        'username' => 'CurrentWinner',
        'previous_usernames' => ['OldWinner'],
        'main_mode' => 'osu',
    ]);
    UserRankHistory::query()->create([
        'user_id' => $user->id,
        'mode' => 'osu',
        'rank' => 1234,
        'recorded_at' => now(),
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    /** @var UserProfileSyncService&MockInterface $profileSync */
    $profileSync = Mockery::mock(UserProfileSyncService::class);

    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserByUsername')->never();
    $osuApi->shouldReceive('getUserForMode')->never();
    $profileSync->shouldReceive('syncBasicProfileFromApiData')->never();
    $profileSync->shouldReceive('syncWinnerProfile')->never();

    $service = new TournamentParticipantSyncService($osuApi, $profileSync);
    $winner = $service->addPodiumByUsername($this->tournament, 'oldwinner', 1);

    expect($winner->user_id)->toBe($user->id)
        ->and($winner->placement)->toBe(1)
        ->and($winner->gamemode)->toBe('osu');
});

test('participant service adds podium user to largest same placement group by default', function () {
    $existingUsers = User::factory()->count(2)->create();
    $newUser = User::factory()->create([
        'username' => 'DefaultGroupWinner',
        'main_mode' => 'osu',
    ]);

    foreach ($existingUsers as $existingUser) {
        TournamentWinner::query()->create([
            'tournament_id' => $this->tournament->id,
            'user_id' => $existingUser->id,
            'placement' => 1,
            'username' => $existingUser->username,
            'osu_id' => $existingUser->osu_id,
            'gamemode' => 'osu',
            'metadata' => ['podium_group_id' => 'largest-group', 'podium_group_manual' => true],
        ]);
    }

    UserRankHistory::query()->create([
        'user_id' => $newUser->id,
        'mode' => 'osu',
        'rank' => 3456,
        'recorded_at' => now(),
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    /** @var UserProfileSyncService&MockInterface $profileSync */
    $profileSync = Mockery::mock(UserProfileSyncService::class);
    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserByUsername')->never();
    $osuApi->shouldReceive('getUserForMode')->never();
    $profileSync->shouldReceive('syncBasicProfileFromApiData')->never();
    $profileSync->shouldReceive('syncWinnerProfile')->never();

    $service = new TournamentParticipantSyncService($osuApi, $profileSync);
    $winner = $service->addPodiumByUsername($this->tournament, 'DefaultGroupWinner', 1);

    expect(data_get($winner->metadata, 'podium_group_id'))->toBe('largest-group')
        ->and($winner->user_id)->toBe($newUser->id);
});

test('participant service syncs podium user when mania variant rank is missing', function (
    int $keyCount,
    string $rankColumn,
    int $syncedRank,
) {
    $this->tournament->update([
        'modes' => [['mode' => 'mania', 'key_count' => $keyCount]],
        'tournament_end' => '2025-08-01',
    ]);

    $user = User::factory()->create([
        'osu_id' => 445566,
        'username' => 'VariantWinner',
        'main_mode' => 'mania',
        'rank_mania_4k' => null,
        'rank_mania_7k' => null,
    ]);

    UserRankHistory::query()->create([
        'user_id' => $user->id,
        'mode' => 'mania',
        'rank' => 12345,
        'recorded_at' => now(),
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    /** @var UserProfileSyncService&MockInterface $profileSync */
    $profileSync = Mockery::mock(UserProfileSyncService::class);

    $osuApi->shouldReceive('getUserForMode')
        ->once()
        ->with(445566, 'mania')
        ->andReturn([
            'id' => 445566,
            'username' => 'VariantWinner',
            'avatar_url' => 'https://a.ppy.sh/445566',
            'country_code' => 'KR',
        ]);
    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserByUsername')->never();

    $profileSync->shouldReceive('syncBasicProfileFromApiData')
        ->once()
        ->andReturnUsing(function (User $user, array $apiUser) {
            $user->update([
                'username' => $apiUser['username'],
                'country_code' => $apiUser['country_code'],
                'osu_data_synced_at' => now(),
            ]);

            return $user->fresh();
        });

    $profileSync->shouldReceive('syncWinnerProfile')
        ->once()
        ->andReturnUsing(function (User $user) use ($rankColumn, $syncedRank) {
            $user->update([$rankColumn => $syncedRank]);

            return [
                'username' => $user->username,
                'main_mode' => $user->main_mode,
            ];
        });

    $service = new TournamentParticipantSyncService($osuApi, $profileSync);
    $winner = $service->addPodiumByUsername($this->tournament->fresh(), 'VariantWinner', 1);

    expect($winner->user_id)->toBe($user->id)
        ->and($winner->gamemode)->toBe('mania')
        ->and($user->fresh()->{$rankColumn})->toBe($syncedRank);
})->with([
    '4k' => [4, 'rank_mania_4k', 4567],
    '7k' => [7, 'rank_mania_7k', 7654],
]);

test('bulk staff job records duplicate role errors without failing operation', function () {
    $user = User::factory()->create(['osu_id' => 12345, 'username' => 'DuplicateUser']);
    TournamentStaff::query()->create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    $operation = AdminAsyncOperation::query()->create([
        'type' => AdminAsyncOperation::TYPE_STAFF_BULK_ADD,
        'tournament_id' => $this->tournament->id,
        'total' => 1,
        'errors' => [],
        'result' => [],
    ]);

    /** @var TournamentParticipantSyncService&MockInterface $syncService */
    $syncService = Mockery::mock(TournamentParticipantSyncService::class);
    $syncService->shouldReceive('addStaffByUsername')
        ->once()
        ->andThrow(new RuntimeException("DuplicateUser already has role 'organizer'"));

    $job = new ProcessBulkStaffAddJob($operation->id, $this->tournament->id, ['DuplicateUser'], 'organizer', $this->admin->id);
    $job->handle($syncService);

    $operation->refresh();

    expect($operation->status)->toBe(AdminAsyncOperation::STATUS_COMPLETED)
        ->and($operation->completed)->toBe(1)
        ->and($operation->failed)->toBe(1)
        ->and($operation->errors[0]['username'])->toBe('DuplicateUser');
});

test('bulk staff job writes one consolidated audit log entry', function () {
    $successUser = User::factory()->create(['username' => 'AddedStaff']);
    $staff = TournamentStaff::query()->create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $successUser->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);
    $staff->load('user');

    $operation = AdminAsyncOperation::query()->create([
        'type' => AdminAsyncOperation::TYPE_STAFF_BULK_ADD,
        'tournament_id' => $this->tournament->id,
        'total' => 2,
        'errors' => [],
        'result' => [],
    ]);

    /** @var TournamentParticipantSyncService&MockInterface $syncService */
    $syncService = Mockery::mock(TournamentParticipantSyncService::class);
    $syncService->shouldReceive('addStaffByUsername')
        ->once()
        ->with(Mockery::type(Tournament::class), 'AddedStaff', 'organizer', $this->admin->id)
        ->andReturn($staff);
    $syncService->shouldReceive('addStaffByUsername')
        ->once()
        ->with(Mockery::type(Tournament::class), 'MissingStaff', 'organizer', $this->admin->id)
        ->andThrow(new RuntimeException('User not found'));

    $job = new ProcessBulkStaffAddJob($operation->id, $this->tournament->id, ['AddedStaff', 'MissingStaff'], 'organizer', $this->admin->id);
    $job->handle($syncService);

    $logs = AdminAuditLog::query()->where('action', 'tournament.staff_bulk_added')->get();

    expect($logs)->toHaveCount(1);
    expect($logs->first()->details)
        ->toMatchArray([
            'operation_id' => $operation->id,
            'role' => 'organizer',
            'requested_usernames' => ['AddedStaff', 'MissingStaff'],
            'success_count' => 1,
            'failed_count' => 1,
        ]);
    expect($logs->first()->details['successes'][0]['username'])->toBe('AddedStaff');
    expect($logs->first()->details['failures'][0]['username'])->toBe('MissingStaff');
});

test('bulk podium job writes one consolidated audit log entry', function () {
    $winner = TournamentWinner::factory()->create([
        'tournament_id' => $this->tournament->id,
        'username' => 'WinnerOne',
        'placement' => 1,
    ]);

    $operation = AdminAsyncOperation::query()->create([
        'type' => AdminAsyncOperation::TYPE_PODIUM_BULK_ADD,
        'tournament_id' => $this->tournament->id,
        'total' => 2,
        'errors' => [],
        'result' => [],
    ]);

    /** @var TournamentParticipantSyncService&MockInterface $syncService */
    $syncService = Mockery::mock(TournamentParticipantSyncService::class);
    $syncService->shouldReceive('addPodiumByUsername')
        ->once()
        ->with(Mockery::type(Tournament::class), 'WinnerOne', 1, Mockery::type('string'))
        ->andReturn($winner);
    $syncService->shouldReceive('addPodiumByUsername')
        ->once()
        ->with(Mockery::type(Tournament::class), 'MissingWinner', 1, Mockery::type('string'))
        ->andThrow(new RuntimeException('User not found'));

    $job = new ProcessBulkPodiumAddJob($operation->id, $this->tournament->id, ['WinnerOne', 'MissingWinner'], 1, $this->admin->id);
    $job->handle($syncService);

    $logs = AdminAuditLog::query()->where('action', 'tournament.podium_winners_bulk_added')->get();

    expect($logs)->toHaveCount(1);
    expect($logs->first()->details)
        ->toMatchArray([
            'operation_id' => $operation->id,
            'placement' => 1,
            'requested_usernames' => ['WinnerOne', 'MissingWinner'],
            'success_count' => 1,
            'failed_count' => 1,
        ]);
    expect($logs->first()->details['successes'][0]['username'])->toBe('WinnerOne');
    expect($logs->first()->details['failures'][0]['username'])->toBe('MissingWinner');
});

test('bulk podium job adds new winners to largest same placement group', function () {
    $bigGroupUsers = User::factory()->count(2)->create();
    $smallGroupUser = User::factory()->create();

    foreach ($bigGroupUsers as $user) {
        TournamentWinner::query()->create([
            'tournament_id' => $this->tournament->id,
            'user_id' => $user->id,
            'placement' => 1,
            'username' => $user->username,
            'osu_id' => $user->osu_id,
            'gamemode' => 'osu',
            'metadata' => ['podium_group_id' => 'big-group', 'podium_group_manual' => true],
        ]);
    }

    TournamentWinner::query()->create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $smallGroupUser->id,
        'placement' => 1,
        'username' => $smallGroupUser->username,
        'osu_id' => $smallGroupUser->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => 'small-group', 'podium_group_manual' => true],
    ]);

    $winner = TournamentWinner::factory()->make([
        'tournament_id' => $this->tournament->id,
        'username' => 'NewWinner',
        'placement' => 1,
    ]);

    $operation = AdminAsyncOperation::query()->create([
        'type' => AdminAsyncOperation::TYPE_PODIUM_BULK_ADD,
        'tournament_id' => $this->tournament->id,
        'total' => 1,
        'errors' => [],
        'result' => [],
    ]);

    /** @var TournamentParticipantSyncService&MockInterface $syncService */
    $syncService = Mockery::mock(TournamentParticipantSyncService::class);
    $syncService->shouldReceive('addPodiumByUsername')
        ->once()
        ->with(Mockery::type(Tournament::class), 'NewWinner', 1, 'big-group')
        ->andReturn($winner);

    $job = new ProcessBulkPodiumAddJob($operation->id, $this->tournament->id, ['NewWinner'], 1, $this->admin->id);
    $job->handle($syncService);

    expect($operation->fresh()->status)->toBe(AdminAsyncOperation::STATUS_COMPLETED);
});
