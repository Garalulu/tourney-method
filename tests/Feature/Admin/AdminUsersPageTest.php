<?php

use App\Jobs\SyncSelectedUserProfilesJob;
use App\Models\AdminMaintenanceRun;
use App\Models\ParticipationInputLog;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

test('master can view users page with html response', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);

    $response = $this->actingAs($master)->get('/admin/users');

    $response->assertStatus(200);
    $response->assertViewIs('admin.users.index');
});

test('master can get users as json response with management fields', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master', 'last_login_at' => now()]);
    $user = User::factory()->create([
        'main_mode' => 'mania',
        'main_mode_source' => 'oauth_setup',
        'locale' => 'ko',
    ]);
    $user->rankHistory()->create([
        'mode' => 'mania',
        'rank' => 1234,
        'recorded_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($master)->getJson('/admin/users');

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'osu_id',
                'username',
                'avatar_url',
                'country_code',
                'main_mode',
                'main_mode_source',
                'role',
                'status',
                'locale',
                'last_login_at',
                'latest_rank_recorded_at',
                'created_at',
            ],
        ],
        'links' => ['first', 'last', 'prev', 'next'],
        'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
    ]);
    expect(collect($response->json('data'))->firstWhere('id', $user->id)['status'])->toBe('logged_in');
});

test('master can filter users by mode status and locale', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $match = User::factory()->create([
        'main_mode' => 'mania',
        'main_mode_source' => 'manual_update',
        'locale' => 'ko',
    ]);
    User::factory()->create([
        'main_mode' => 'taiko',
        'main_mode_source' => 'auto_detected',
        'locale' => 'en',
    ]);

    $response = $this->actingAs($master)->getJson('/admin/users?mode=mania&status=manual&locale=ko');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $match->id);
});

test('master can search users by username and osu id', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $user = User::factory()->create([
        'username' => 'NeedlePlayer',
        'osu_id' => 99887766,
    ]);
    User::factory()->create(['username' => 'OtherPlayer']);

    $usernameResponse = $this->actingAs($master)->getJson('/admin/users?search=Needle');
    $osuIdResponse = $this->actingAs($master)->getJson('/admin/users?search=8877');

    $usernameResponse->assertOk()->assertJsonPath('data.0.id', $user->id);
    $osuIdResponse->assertOk()->assertJsonPath('data.0.id', $user->id);
});

test('master can sort users by table headings', function () {
    $master = User::factory()->create([
        'username' => 'MiddleMaster',
        'osu_id' => 555555,
        'main_mode' => 'osu',
        'role' => 'master',
    ]);
    $alpha = User::factory()->create(['username' => 'AlphaPlayer', 'osu_id' => 111111]);
    $zulu = User::factory()->create(['username' => 'ZuluPlayer', 'osu_id' => 999999]);

    $ascending = $this->actingAs($master)->getJson('/admin/users?sort=user&direction=asc');
    $descending = $this->actingAs($master)->getJson('/admin/users?sort=user&direction=desc');
    $osuAscending = $this->actingAs($master)->getJson('/admin/users?sort=osu_id&direction=asc');

    $ascending->assertOk()->assertJsonPath('data.0.id', $alpha->id);
    $descending->assertOk()->assertJsonPath('data.0.id', $zulu->id);
    $osuAscending->assertOk()->assertJsonPath('data.0.id', $alpha->id);
});

test('users page renders sortable table headers with active direction icon', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);

    $response = $this->actingAs($master)->get('/admin/users?sort=user&direction=asc&mode=osu');

    $response->assertOk();
    $response->assertSee('sort=user', false);
    $response->assertSee('direction=desc', false);
    $response->assertSee('mode=osu', false);
    $response->assertSee('▲', false);
});

test('master can update main mode role including master and locale', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $user = User::factory()->create([
        'main_mode' => 'osu',
        'main_mode_source' => 'auto_detected',
        'role' => 'player',
        'locale' => 'en',
    ]);

    $response = $this->actingAs($master)->patchJson("/admin/users/{$user->id}", [
        'main_mode' => 'mania',
        'role' => 'master',
        'locale' => 'ru',
    ]);

    $response->assertOk();
    $response->assertJsonPath('main_mode', 'mania');
    $response->assertJsonPath('role', 'master');
    $response->assertJsonPath('locale', 'ru');
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'main_mode' => 'mania',
        'main_mode_source' => 'manual_update',
        'role' => 'master',
        'locale' => 'ru',
    ]);
});

test('master can delete selected users but not self', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $first = User::factory()->create();
    $second = User::factory()->create();

    $deleteSelf = $this->actingAs($master)->deleteJson('/admin/users', [
        'user_ids' => [$master->id, $first->id],
    ]);

    $deleteSelf->assertStatus(400);

    $response = $this->actingAs($master)->deleteJson('/admin/users', [
        'user_ids' => [$first->id, $second->id],
    ]);

    $response->assertOk();
    $response->assertJsonPath('deleted', 2);
    $this->assertSoftDeleted('users', ['id' => $first->id]);
    $this->assertSoftDeleted('users', ['id' => $second->id]);
});

test('deleting user removes only their shared participation presence and repairs root metadata', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $deletedUser = User::factory()->withSetup()->create(['username' => 'DeletedRoot']);
    $firstTeammate = User::factory()->withSetup()->create(['username' => 'FirstMate']);
    $secondTeammate = User::factory()->withSetup()->create(['username' => 'SecondMate']);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 3]);

    $root = TournamentParticipationRecord::query()->create([
        'user_id' => $deletedUser->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'team_name' => 'Still Standing',
        'placement' => 2,
    ]);
    $firstRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $firstTeammate->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'team_name' => 'Still Standing',
        'placement' => 2,
        'metadata' => ['shared_from_record_id' => $root->id],
    ]);
    $secondRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $secondTeammate->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'team_name' => 'Still Standing',
        'placement' => 2,
        'metadata' => ['shared_from_record_id' => $root->id],
    ]);
    $firstRecord->syncParticipationMatches([
        ['stage' => 'F', 'score_for' => 7, 'score_against' => 5, 'mp_link' => '123456'],
    ]);

    $root->teammates()->attach([$firstTeammate->id, $secondTeammate->id]);
    $firstRecord->teammates()->attach([$deletedUser->id, $secondTeammate->id]);
    $secondRecord->teammates()->attach([$deletedUser->id, $firstTeammate->id]);

    $this->actingAs($master)
        ->deleteJson("/admin/users/{$deletedUser->id}")
        ->assertStatus(409)
        ->assertJsonPath('requires_confirmation', true)
        ->assertJsonPath('connections.participation_records', 1);

    $this->actingAs($master)
        ->deleteJson("/admin/users/{$deletedUser->id}", ['confirmed' => true])
        ->assertOk();

    $this->assertSoftDeleted('users', ['id' => $deletedUser->id]);
    $this->assertDatabaseMissing('tournament_participation_records', ['id' => $root->id]);
    $this->assertDatabaseHas('tournament_participation_records', ['id' => $firstRecord->id]);
    $this->assertDatabaseHas('tournament_participation_records', ['id' => $secondRecord->id]);
    $this->assertDatabaseMissing('participation_record_teammates', ['user_id' => $deletedUser->id]);

    $firstRecord->refresh();
    $secondRecord->refresh();

    expect($firstRecord->teammates()->pluck('users.id')->all())->toBe([$secondTeammate->id])
        ->and($secondRecord->teammates()->pluck('users.id')->all())->toBe([$firstTeammate->id])
        ->and(data_get($firstRecord->metadata, 'shared_from_record_id'))->toBeNull()
        ->and(data_get($secondRecord->metadata, 'shared_from_record_id'))->toBe($firstRecord->id)
        ->and($firstRecord->matches[0]['mp_id'])->toBe(123456);
});

test('deleting user removes staff and podium presence while preserving remaining podium group', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $deletedUser = User::factory()->withSetup()->create(['username' => 'PodiumGone']);
    $teammate = User::factory()->withSetup()->create(['username' => 'PodiumStays']);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    $groupId = 'podium-cleanup-group';

    TournamentStaff::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $deletedUser->id,
        'role' => 'referee',
        'status' => 'approved',
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $deletedUser->id,
        'placement' => 1,
        'username' => $deletedUser->username,
        'osu_id' => $deletedUser->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => $groupId, 'podium_group_manual' => true],
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $teammate->id,
        'placement' => 1,
        'username' => $teammate->username,
        'osu_id' => $teammate->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => $groupId, 'podium_group_manual' => true],
    ]);

    $deletedRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $deletedUser->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners', 'podium_group_id' => $groupId],
    ]);
    $teammateRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners', 'podium_group_id' => $groupId, 'shared_from_record_id' => $deletedRecord->id],
    ]);
    $deletedRecord->teammates()->attach($teammate);
    $teammateRecord->teammates()->attach($deletedUser);

    $this->actingAs($master)
        ->deleteJson("/admin/users/{$deletedUser->id}")
        ->assertStatus(409)
        ->assertJsonPath('requires_confirmation', true)
        ->assertJsonPath('connections.staff_roles', 1)
        ->assertJsonPath('connections.podium_rows', 1);

    $this->actingAs($master)
        ->deleteJson("/admin/users/{$deletedUser->id}", ['confirmed' => true])
        ->assertOk();

    $this->assertDatabaseMissing('tournament_staff', ['user_id' => $deletedUser->id]);
    $this->assertDatabaseMissing('tournament_winners', ['user_id' => $deletedUser->id]);
    $this->assertDatabaseHas('tournament_winners', ['user_id' => $teammate->id, 'tournament_id' => $tournament->id, 'placement' => 1]);

    $teammateRecord->refresh();

    expect($teammateRecord->teammates()->pluck('users.id')->all())->toBe([])
        ->and(data_get($teammateRecord->metadata, 'shared_from_record_id'))->toBeNull();
});

test('participation moderation renders soft deleted users as archived identities', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $archivedUser = User::factory()->withSetup()->create(['username' => 'ArchivedParticipant']);

    ParticipationInputLog::query()->create([
        'user_id' => $archivedUser->id,
        'action' => 'participation_updated',
        'changed_fields' => ['team_name' => 'Old Team'],
    ]);
    $archivedUser->delete();

    $response = $this->actingAs($master)->get(route('admin.participation-moderation.index'));

    $response->assertOk();
    $response->assertSee('ArchivedParticipant');
    $response->assertDontSee(route('users.show', $archivedUser), false);
});

test('master must confirm before deleting users with tournament contributions', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $contributor = User::factory()->withSetup()->create(['username' => 'HelpfulEditor']);
    $tournament = Tournament::factory()->approved()->create();

    TournamentCorrection::query()->create([
        'tournament_id' => $tournament->id,
        'submitted_by' => $contributor->id,
        'status' => TournamentCorrection::STATUS_APPROVED,
        'payload' => ['changes' => ['title' => 'Better title']],
        'current_snapshot' => ['title' => $tournament->title],
    ]);

    $this->actingAs($master)
        ->deleteJson("/admin/users/{$contributor->id}")
        ->assertStatus(409)
        ->assertJsonPath('requires_confirmation', true)
        ->assertJsonPath('connections.contributions', 1);

    $this->assertDatabaseHas('users', [
        'id' => $contributor->id,
        'deleted_at' => null,
    ]);

    $this->actingAs($master)
        ->deleteJson("/admin/users/{$contributor->id}", ['confirmed' => true])
        ->assertOk();

    $this->assertSoftDeleted('users', ['id' => $contributor->id]);
    $this->assertDatabaseHas('tournament_corrections', [
        'id' => TournamentCorrection::query()->firstOrFail()->id,
        'submitted_by' => $contributor->id,
    ]);
});

test('orphan cleanup does not delete users with tournament contributions', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $contributor = User::factory()->create([
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);
    $tournament = Tournament::factory()->approved()->create();

    TournamentCorrection::query()->create([
        'tournament_id' => $tournament->id,
        'submitted_by' => $contributor->id,
        'status' => TournamentCorrection::STATUS_APPROVED,
        'payload' => ['changes' => ['title' => 'Better title']],
        'current_snapshot' => ['title' => $tournament->title],
    ]);

    $this->actingAs($master)
        ->postJson('/admin/users/cleanup-orphans')
        ->assertOk()
        ->assertJsonPath('deleted', 0);

    $this->assertDatabaseHas('users', [
        'id' => $contributor->id,
        'deleted_at' => null,
    ]);
});

test('master can queue selected user sync and maintenance run', function () {
    Bus::fake();

    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $users = User::factory()->count(2)->create();

    $response = $this->actingAs($master)->postJson('/admin/users/sync-selected', [
        'user_ids' => $users->pluck('id')->all(),
    ]);

    $response->assertOk();
    $response->assertJsonPath('selected', 2);
    $this->assertDatabaseHas('admin_maintenance_runs', [
        'id' => $response->json('maintenance_run_id'),
        'command' => AdminMaintenanceRun::COMMAND_SYNC_USER_PROFILES,
        'source' => 'web',
        'status' => AdminMaintenanceRun::STATUS_RUNNING,
    ]);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === 'Sync selected user profiles'
        && collect($batch->jobs)->contains(fn ($job) => $job instanceof SyncSelectedUserProfilesJob));
});

test('master can cleanup orphan users through admin page', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    $orphan = User::factory()->create([
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);
    $oauthUser = User::factory()->create([
        'role' => 'player',
        'main_mode_source' => 'oauth_setup',
    ]);

    $response = $this->actingAs($master)->postJson('/admin/users/cleanup-orphans');

    $response->assertOk();
    $response->assertJsonPath('deleted', 1);
    $this->assertSoftDeleted('users', ['id' => $orphan->id]);
    $this->assertDatabaseHas('users', [
        'id' => $oauthUser->id,
        'deleted_at' => null,
    ]);
});

test('admin and guests cannot access user management endpoints', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $target = User::factory()->create();

    $this->actingAs($admin)->get('/admin/users')->assertForbidden();
    $this->actingAs($admin)->patchJson("/admin/users/{$target->id}", ['role' => 'master'])->assertForbidden();
    $this->actingAs($admin)->deleteJson("/admin/users/{$target->id}")->assertForbidden();
    $this->actingAs($admin)->postJson('/admin/users/sync-selected', ['user_ids' => [$target->id]])->assertForbidden();
    $this->actingAs($admin)->postJson('/admin/users/cleanup-orphans')->assertForbidden();

    auth()->logout();

    $this->get('/admin/users')->assertStatus(401);
    $this->patchJson("/admin/users/{$target->id}", ['role' => 'master'])->assertStatus(401);
    $this->deleteJson("/admin/users/{$target->id}")->assertStatus(401);
    $this->postJson('/admin/users/sync-selected', ['user_ids' => [$target->id]])->assertStatus(401);
    $this->postJson('/admin/users/cleanup-orphans')->assertStatus(401);
});

test('users are paginated', function () {
    $master = User::factory()->create(['main_mode' => 'osu', 'role' => 'master']);
    User::factory()->count(25)->create(['main_mode' => 'osu']);

    $response = $this->actingAs($master)->getJson('/admin/users?per_page=10');

    $response->assertStatus(200);
    $response->assertJsonCount(10, 'data');
    $response->assertJsonPath('meta.total', 26);
    $response->assertJsonPath('meta.per_page', 10);
});
