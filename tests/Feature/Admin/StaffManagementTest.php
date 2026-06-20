<?php

use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Services\TournamentParticipantSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can add registered user as staff', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $user = User::factory()->create(['username' => 'TestUser', 'osu_id' => 12345]);

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/staff", [
        'user_id' => $user->id,
        'roles' => ['organizer'],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);
});

test('admin can add an osu user who has not registered locally', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $resolvedUser = User::factory()->create([
        'username' => 'UnregisteredUser',
        'osu_id' => 987654,
    ]);
    $syncService = Mockery::mock(TournamentParticipantSyncService::class);
    $syncService->shouldReceive('resolveStaffUserByUsername')
        ->once()
        ->with('UnregisteredUser')
        ->andReturn($resolvedUser);
    app()->instance(TournamentParticipantSyncService::class, $syncService);

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/staff", [
        'user_id' => 'new:UnregisteredUser',
        'roles' => ['referee'],
    ]);

    $response->assertStatus(201);

    // Verify the resolved osu! user is retained locally.
    $this->assertDatabaseHas('users', [
        'username' => 'UnregisteredUser',
        'osu_id' => 987654,
        'role' => 'player',
    ]);

    // Verify staff record created
    $placeholderUser = User::where('username', 'UnregisteredUser')->first();
    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $tournament->id,
        'user_id' => $placeholderUser->id,
        'role' => 'referee',
        'status' => 'approved',
    ]);
});

test('adding unregistered staff logs was_pre_registered flag', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $resolvedUser = User::factory()->create(['username' => 'PlaceholderUser']);
    $syncService = Mockery::mock(TournamentParticipantSyncService::class);
    $syncService->shouldReceive('resolveStaffUserByUsername')
        ->once()
        ->with('PlaceholderUser')
        ->andReturn($resolvedUser);
    app()->instance(TournamentParticipantSyncService::class, $syncService);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/staff", [
        'user_id' => 'new:PlaceholderUser',
        'roles' => ['mapper'],
    ]);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'tournament.staff_added',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $auditLog = AdminAuditLog::where('action', 'tournament.staff_added')->first();
    expect($auditLog->details['was_pre_registered'])->toBeTrue();
});

test('duplicate roles are skipped gracefully when adding staff', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $user = User::factory()->create();

    // Add first staff with organizer role
    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/staff", [
        'user_id' => $user->id,
        'roles' => ['organizer'],
    ]);

    // Count staff records
    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->count())->toBe(1);

    // Try to add duplicate organizer role - should be skipped gracefully
    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/staff", [
        'user_id' => $user->id,
        'roles' => ['organizer', 'mapper'],  // organizer is duplicate, mapper is new
    ]);

    $response->assertStatus(201);

    // Should still have 2 roles (original organizer + new mapper)
    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->count())->toBe(2);

    // Verify mapper was added
    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
    ]);
});

test('admin can update staff role', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $staff = TournamentStaff::factory()->for($tournament)->create(['role' => 'organizer']);

    $response = $this->actingAs($admin)->patch("/admin/tournaments/{$tournament->id}/staff/{$staff->id}", [
        'role' => 'referee',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('tournament_staff', [
        'id' => $staff->id,
        'role' => 'referee',
    ]);
});

test('updating staff role logs old and new roles', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $staff = TournamentStaff::factory()->for($tournament)->create(['role' => 'organizer']);

    $this->actingAs($admin)->patch("/admin/tournaments/{$tournament->id}/staff/{$staff->id}", [
        'role' => 'referee',
    ]);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'tournament.staff_role_updated',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $auditLog = AdminAuditLog::where('action', 'tournament.staff_role_updated')->first();
    expect($auditLog->details['old_role'])->toBe('organizer');
    expect($auditLog->details['new_role'])->toBe('referee');
});

test('admin can remove staff', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $staff = TournamentStaff::factory()->for($tournament)->create(['role' => 'organizer']);

    $response = $this->actingAs($admin)->deleteJson("/admin/tournaments/{$tournament->id}/staff/{$staff->user_id}", [
        'role' => $staff->role,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseMissing('tournament_staff', [
        'id' => $staff->id,
    ]);
});

test('removing staff logs action in audit', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $staff = TournamentStaff::factory()->for($tournament)->create(['role' => 'streamer']);

    $this->actingAs($admin)->deleteJson("/admin/tournaments/{$tournament->id}/staff/{$staff->user_id}", [
        'role' => $staff->role,
    ]);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'tournament.staff_removed',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $auditLog = AdminAuditLog::where('action', 'tournament.staff_removed')->first();
    expect($auditLog->details['staff_id'])->toBe($staff->id);
    expect($auditLog->details['role'])->toBe('streamer');
});

test('cannot update staff from different tournament', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament1 = Tournament::factory()->create();
    $tournament2 = Tournament::factory()->create();
    $staff = TournamentStaff::factory()->for($tournament1)->create(['role' => 'organizer']);

    $response = $this->actingAs($admin)->patch("/admin/tournaments/{$tournament2->id}/staff/{$staff->id}", [
        'role' => 'referee',
    ]);

    $response->assertStatus(404);
});

test('cannot remove staff from different tournament', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament1 = Tournament::factory()->create();
    $tournament2 = Tournament::factory()->create();
    $staff = TournamentStaff::factory()->for($tournament1)->create(['role' => 'organizer']);

    $response = $this->actingAs($admin)->deleteJson("/admin/tournaments/{$tournament2->id}/staff/{$staff->user_id}", [
        'role' => $staff->role,
    ]);

    $response->assertStatus(404);
});

test('race condition: user registers between search and add', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    // Simulate race condition: user exists now
    User::factory()->create(['username' => 'ExistingUser', 'osu_id' => 99999]);

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/staff", [
        'user_id' => 'new:ExistingUser',
        'roles' => ['commentator'],
    ]);

    $response->assertStatus(201);

    // Should use existing user, not create duplicate
    $this->assertDatabaseCount('users', 2); // admin + existing user
    $existingUser = User::where('username', 'ExistingUser')->first();

    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $tournament->id,
        'user_id' => $existingUser->id,
        'role' => 'commentator',
    ]);
});

test('admin tournament review page renders with csrf token', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    $response = $this->actingAs($admin)->get("/admin/tournaments/{$tournament->id}");

    $response->assertStatus(200);
    $response->assertSee('<meta name="csrf-token"', false);
});

test('staff modal renders with tournament id data attribute', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    $response = $this->actingAs($admin)->get("/admin/tournaments/{$tournament->id}");

    $response->assertStatus(200);
    $response->assertSee("data-tournament-id=\"{$tournament->id}\"", false);
});

test('fetching user roles uses correct tournament id endpoint', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $user = User::factory()->create();

    // Add user as staff with organizer role
    TournamentStaff::factory()->for($tournament)->for($user)->create(['role' => 'organizer']);

    $response = $this->actingAs($admin)
        ->getJson("/admin/tournaments/{$tournament->id}/staff?user_id={$user->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'staff' => [
            '*' => ['id', 'role', 'source'],
        ],
    ]);

    $response->assertJsonFragment([
        'role' => 'organizer',
    ]);
});

test('multi-role staff removal uses correct endpoint', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $user = User::factory()->create();

    // Add user with two roles
    TournamentStaff::factory()->for($tournament)->for($user)->create(['role' => 'organizer']);
    TournamentStaff::factory()->for($tournament)->for($user)->create(['role' => 'mapper']);

    $response = $this->actingAs($admin)
        ->deleteJson("/admin/tournaments/{$tournament->id}/staff/{$user->id}", [
            'role' => 'organizer',
        ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    // Verify only organizer role was removed, mapper still exists
    $this->assertDatabaseMissing('tournament_staff', [
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
    ]);

    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
    ]);
});

test('multi-role staff addition uses correct endpoint', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);
    $user = User::factory()->create();

    $response = $this->actingAs($admin)
        ->postJson("/admin/tournaments/{$tournament->id}/staff", [
            'user_id' => $user->id,
            'roles' => ['organizer', 'mapper'],
            'notes' => 'Test staff',
        ]);

    $response->assertStatus(201);

    // Verify both roles were created
    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
    ]);

    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
    ]);
});
