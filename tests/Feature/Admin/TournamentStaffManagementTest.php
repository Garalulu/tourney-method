<?php

use App\Helpers\StaffRoleHelper;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tournament = Tournament::factory()->create();
});

/**
 * Test: Admin can add staff with single role
 */
test('admin can add staff with single role', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['organizer'],
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
    ]);
});

/**
 * Test: Admin can add staff with multiple roles
 */
test('admin can add staff with multiple roles', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['mapper', 'referee', 'playtester'],
        ]);

    $response->assertStatus(201);

    // Verify all three roles were created
    foreach (['mapper', 'referee', 'playtester'] as $role) {
        $this->assertDatabaseHas('tournament_staff', [
            'tournament_id' => $this->tournament->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'approved',
        ]);
    }

    // Verify count
    expect(TournamentStaff::where('tournament_id', $this->tournament->id)
        ->where('user_id', $user->id)
        ->count())->toBe(3);
});

test('admin add staff normalizes roles to priority order', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['commentator', 'organizer', 'gfx', 'referee'],
        ]);

    $response->assertStatus(201);

    expect(collect($response->json('staff'))->pluck('role')->all())
        ->toBe(['organizer', 'gfx', 'referee', 'commentator']);

    $roles = TournamentStaff::query()
        ->where('tournament_id', $this->tournament->id)
        ->where('user_id', $user->id)
        ->orderBy('id')
        ->pluck('role')
        ->all();

    expect($roles)->toBe(['organizer', 'gfx', 'referee', 'commentator']);
});

/**
 * Test: Admin cannot add staff without roles
 */
test('admin cannot add staff without roles', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => [],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['roles']);
});

/**
 * Test: Admin cannot add staff with invalid role
 */
test('admin cannot add staff with invalid role', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['invalid_role'],
        ]);

    $response->assertStatus(422);
    // Laravel validates array items as "roles.0", "roles.1", etc.
    $response->assertJsonValidationErrors(['roles.0']);
});

/**
 * Test: Admin can add staff with all 10 available roles
 */
test('admin can add staff with all ten_available_roles', function () {
    $user = User::factory()->create();

    $allRoles = array_keys(StaffRoleHelper::getAvailableRoles());

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => $allRoles,
        ]);

    $response->assertStatus(201);

    // Verify all roles were created
    foreach ($allRoles as $role) {
        $this->assertDatabaseHas('tournament_staff', [
            'tournament_id' => $this->tournament->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'approved',
        ]);
    }

    expect(TournamentStaff::where('tournament_id', $this->tournament->id)
        ->where('user_id', $user->id)
        ->count())->toBe(10);
});

/**
 * Test: Duplicate user and role combination is skipped gracefully
 */
test('duplicate_user_and_role_combination_is_skipped_gracefully', function () {
    $user = User::factory()->create();

    // Add first role
    TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
        'status' => 'approved',
    ]);

    // Try to add same role again (should be skipped)
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['mapper', 'referee'],  // mapper is duplicate
        ]);

    $response->assertStatus(201);

    // mapper should exist (only once)
    expect(TournamentStaff::where('tournament_id', $this->tournament->id)
        ->where('user_id', $user->id)
        ->where('role', 'mapper')
        ->count())->toBe(1);

    // referee should be created
    expect(TournamentStaff::where('tournament_id', $this->tournament->id)
        ->where('user_id', $user->id)
        ->where('role', 'referee')
        ->count())->toBe(1);
});

/**
 * Test: Admin can update staff role to any available role
 */
test('admin_can_update_staff_role_to_any_available_role', function () {
    $user = User::factory()->create();

    $staff = TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    $allRoles = array_keys(StaffRoleHelper::getAvailableRoles());

    foreach ($allRoles as $newRole) {
        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.tournaments.staff.update', [$this->tournament, $staff]), [
                'role' => $newRole,
            ]);

        $response->assertStatus(200);

        $staff->refresh();
        expect($staff->role)->toBe($newRole);
    }
});

test('admin_can_replace_all_roles_for_staff_user_in_one_request', function () {
    $user = User::factory()->create();

    TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
        'status' => 'approved',
        'source' => 'parsed',
    ]);

    $response = $this->actingAs($this->admin)
        ->patchJson(route('admin.tournaments.staff.roles.replace', [$this->tournament, $user]), [
            'roles' => ['organizer', 'referee', 'streamer'],
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'added' => ['referee', 'streamer'],
            'removed' => ['mapper'],
            'kept' => ['organizer'],
        ]);

    $roles = TournamentStaff::query()
        ->where('tournament_id', $this->tournament->id)
        ->where('user_id', $user->id)
        ->pluck('role')
        ->sort()
        ->values()
        ->all();

    expect($roles)->toBe(['organizer', 'referee', 'streamer']);

    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'tournament.staff_roles_replaced',
        'entity_type' => Tournament::class,
        'entity_id' => $this->tournament->id,
        'admin_id' => $this->admin->id,
    ]);
});

test('replace staff roles reports added and kept roles in priority order', function () {
    $user = User::factory()->create();

    foreach (['commentator', 'organizer'] as $role) {
        TournamentStaff::create([
            'tournament_id' => $this->tournament->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'approved',
            'source' => 'manual',
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->patchJson(route('admin.tournaments.staff.roles.replace', [$this->tournament, $user]), [
            'roles' => ['commentator', 'gfx', 'organizer', 'mappooler'],
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'added' => ['mappooler', 'gfx'],
            'removed' => [],
            'kept' => ['organizer', 'commentator'],
        ]);
});

test('admin_can_clear_all_roles_for_staff_user', function () {
    $user = User::factory()->create();

    TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($this->admin)
        ->patchJson(route('admin.tournaments.staff.roles.replace', [$this->tournament, $user]), [
            'roles' => [],
        ]);

    $response->assertStatus(200);

    expect(TournamentStaff::where('tournament_id', $this->tournament->id)
        ->where('user_id', $user->id)
        ->count())->toBe(0);
});

test('replace_staff_roles_rejects_invalid_roles', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->patchJson(route('admin.tournaments.staff.roles.replace', [$this->tournament, $user]), [
            'roles' => ['organizer', 'invalid'],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['roles.1']);
});

test('admin_can_bulk_delete_selected_staff_role_only', function () {
    $userWithMultipleRoles = User::factory()->create();
    $refereeOnlyUser = User::factory()->create();
    $otherTournament = Tournament::factory()->create();

    foreach ([
        [$this->tournament->id, $userWithMultipleRoles->id, 'referee'],
        [$this->tournament->id, $userWithMultipleRoles->id, 'mapper'],
        [$this->tournament->id, $refereeOnlyUser->id, 'referee'],
        [$otherTournament->id, $userWithMultipleRoles->id, 'referee'],
    ] as [$tournamentId, $userId, $role]) {
        TournamentStaff::create([
            'tournament_id' => $tournamentId,
            'user_id' => $userId,
            'role' => $role,
            'status' => 'approved',
            'source' => 'manual',
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->deleteJson(route('admin.tournaments.staff.roles.remove', [$this->tournament, 'referee']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'deleted_count' => 2,
            'role' => 'referee',
        ]);

    $this->assertDatabaseMissing('tournament_staff', [
        'tournament_id' => $this->tournament->id,
        'user_id' => $userWithMultipleRoles->id,
        'role' => 'referee',
    ]);
    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $this->tournament->id,
        'user_id' => $userWithMultipleRoles->id,
        'role' => 'mapper',
    ]);
    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $otherTournament->id,
        'user_id' => $userWithMultipleRoles->id,
        'role' => 'referee',
    ]);
    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'tournament.staff_role_bulk_removed',
        'entity_type' => Tournament::class,
        'entity_id' => $this->tournament->id,
        'admin_id' => $this->admin->id,
    ]);
});

test('bulk_delete_selected_staff_role_rejects_invalid_role', function () {
    $response = $this->actingAs($this->admin)
        ->deleteJson(route('admin.tournaments.staff.roles.remove', [$this->tournament, 'invalid']));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['role']);
});

/**
 * Test: Admin cannot update staff with invalid role
 */
test('admin_cannot_update_staff_with_invalid_role', function () {
    $user = User::factory()->create();

    $staff = TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($this->admin)
        ->patchJson(route('admin.tournaments.staff.update', [$this->tournament, $staff]), [
            'role' => 'invalid_role',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['role']);
});

/**
 * Test: New roles (mappooler, playtester, gfx, sheeter) are accepted
 */
test('new_roles_are_accepted', function () {
    $user = User::factory()->create();

    $newRoles = ['mappooler', 'playtester', 'gfx', 'sheeter'];

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => $newRoles,
        ]);

    $response->assertStatus(201);

    foreach ($newRoles as $role) {
        $this->assertDatabaseHas('tournament_staff', [
            'tournament_id' => $this->tournament->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
});

/**
 * Test: Typo "sheetor" is rejected by validation
 */
test('sheetor_typo_is_rejected', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['sheetor'], // WRONG - should be "sheeter"
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['roles.0']);

    // Verify "sheetor" was NOT saved to database
    $this->assertDatabaseMissing('tournament_staff', [
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'sheetor',
    ]);
});

/**
 * Test: Notes field is not required (removed from validation)
 */
test('notes_field_is_not_required', function () {
    $user = User::factory()->create();

    // Should work without notes field
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['organizer'],
            // notes field omitted
        ]);

    $response->assertStatus(201);
});

/**
 * Test: Audit log records multi-role additions correctly
 */
test('audit_log_records_multi_role_additions_correctly', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['mapper', 'referee'],
        ]);

    // Check audit log - use entity_type and entity_id
    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'tournament.staff_added',
        'entity_type' => Tournament::class,
        'entity_id' => $this->tournament->id,
        'admin_id' => $this->admin->id,
    ]);
});

/**
 * Test: Admin-added staff have source='manual' set
 * This ensures re-parse protection works correctly
 */
test('admin_added_staff_have_source_manual_set', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.tournaments.staff.add', $this->tournament), [
            'user_id' => $user->id,
            'roles' => ['organizer', 'mapper'],
        ]);

    // Verify all created staff records have source='manual'
    $staffRecords = TournamentStaff::where('tournament_id', $this->tournament->id)
        ->where('user_id', $user->id)
        ->get();

    expect($staffRecords)->toHaveCount(2);

    foreach ($staffRecords as $staff) {
        expect($staff->source)->toBe('manual');
    }

    // Verify in database directly
    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'source' => 'manual',
    ]);

    $this->assertDatabaseHas('tournament_staff', [
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
        'source' => 'manual',
    ]);
});

/**
 * Test: Admin can fetch user's existing roles for tournament
 * This prevents duplicate role assignments in UI
 */
test('admin_can_fetch_user_existing_roles_for_tournament', function () {
    $user = User::factory()->create();

    // Add user with multiple roles
    TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
        'status' => 'approved',
        'source' => 'parsed',
    ]);

    // Add role for different tournament (should not be included)
    $otherTournament = Tournament::factory()->create();
    TournamentStaff::create([
        'tournament_id' => $otherTournament->id,
        'user_id' => $user->id,
        'role' => 'referee',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    // Fetch user's roles for this tournament
    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.tournaments.staff.fetch', $this->tournament).'?user_id='.$user->id);

    $response->assertStatus(200);

    // Verify count (should be 2, not 3 - excluding roles from other tournaments)
    expect($response->json('staff'))->toHaveCount(2);

    // Verify both roles are present (order doesn't matter)
    $staffData = collect($response->json('staff'));

    expect($staffData->where('role', 'organizer')->first())->not->toBeNull();
    expect($staffData->where('role', 'organizer')->first()['source'])->toBe('manual');

    expect($staffData->where('role', 'mapper')->first())->not->toBeNull();
    expect($staffData->where('role', 'mapper')->first()['source'])->toBe('parsed');
});

test('fetch user roles returns roles in priority order', function () {
    $user = User::factory()->create();

    foreach (['commentator', 'organizer', 'gfx', 'referee'] as $role) {
        TournamentStaff::create([
            'tournament_id' => $this->tournament->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'approved',
            'source' => 'manual',
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.tournaments.staff.fetch', $this->tournament).'?user_id='.$user->id);

    $response->assertStatus(200);

    expect(collect($response->json('staff'))->pluck('role')->all())
        ->toBe(['organizer', 'gfx', 'referee', 'commentator']);
});

/**
 * Test: fetchUserRoles handles invalid user_id values correctly
 * Malformed values return empty array, invalid numeric values fail validation
 */
test('fetch_user_roles_handles_invalid_user_id_values', function () {
    // Malformed values - should return empty array (graceful degradation)
    $malformedValues = [
        'null',       // String "null"
        'undefined',  // String "undefined"
        'abc',        // Non-numeric string
        '<script>',   // XSS attempt
    ];

    foreach ($malformedValues as $malformedUserId) {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.tournaments.staff.fetch', $this->tournament).'?user_id='.urlencode($malformedUserId));

        // Should return 200 OK with empty staff array (graceful degradation)
        $response->assertStatus(200);
        expect($response->json('staff'))->toBe([]);
    }

    // Invalid numeric values - should fail validation
    $invalidNumericValues = [
        '-1',         // Negative number
        '0',          // Zero
        '9999999999', // Non-existent user ID
    ];

    foreach ($invalidNumericValues as $invalidUserId) {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.tournaments.staff.fetch', $this->tournament).'?user_id='.$invalidUserId);

        // Should return 422 Validation Error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }
});

/**
 * Test: fetchUserRoles properly validates user_id parameter
 * Ensures type safety and prevents SQL injection
 */
test('fetch_user_roles_validates_user_id_parameter', function () {
    $user = User::factory()->create();

    // Add some staff for the user
    TournamentStaff::create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    // Valid user_id should return staff
    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.tournaments.staff.fetch', $this->tournament).'?user_id='.$user->id);

    $response->assertStatus(200);
    expect($response->json('staff'))->toHaveCount(1);

    // Invalid user_id formats should return empty array
    $malformedInputs = [
        '<script>alert("xss")</script>',
        "' OR '1'='1",
        '../../etc/passwd',
        '${7*7}',
        '1; DROP TABLE users--',
    ];

    foreach ($malformedInputs as $malformedInput) {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.tournaments.staff.fetch', $this->tournament).'?user_id='.urlencode($malformedInput));

        // Should return 200 OK with empty staff array (graceful degradation)
        $response->assertStatus(200);
        expect($response->json('staff'))->toBe([]);
    }
});

/**
 * Test: User search returns a derived avatar URL
 * This prevents the browser from requesting /admin/tournaments/null as an image
 */
test('user_search_returns_derived_avatar_url', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/users/search?q='.$user->username);

    $response->assertStatus(200);

    $users = $response->json();
    expect($users)->toHaveCount(1);

    // Verify avatar_url is NOT null in the response
    expect($users[0]['avatar_url'])->not->toBeNull();
    expect($users[0]['avatar_url'])->toBe('https://a.ppy.sh/'.$user->osu_id);

    // Verify other fields are correct
    expect($users[0]['id'])->toBe($user->id);
    expect($users[0]['username'])->toBe($user->username);
});
