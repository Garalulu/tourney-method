<?php

use App\Helpers\StaffRoleHelper;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tournament = Tournament::factory()->create();
    // Add some test staff
    $user1 = User::factory()->create(['username' => 'TestUser1', 'osu_id' => 12345]);
    $user2 = User::factory()->create(['username' => 'TestUser2', 'osu_id' => 67890]);

    // Add staff with single role
    $this->tournament->staff()->attach($user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    // Add staff with multiple roles
    $this->tournament->staff()->attach($user2->id, [
        'role' => 'mappooler',
        'status' => 'approved',
        'source' => 'manual',
    ]);
    $this->tournament->staff()->attach($user2->id, [
        'role' => 'playtester',
        'status' => 'approved',
        'source' => 'manual',
    ]);
});

/**
 * Test: TournamentStaffManagement component renders staff list grouped by user
 */
test('tournament_staff_management_component_renders_staff_list_grouped_by_user', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load(['staff' => function ($query) {
            $query->orderBy('pivot_role');
        }]),
    ]);

    // Check that users are grouped (should have 2 user cards, not 3 staff records)
    $userCardCount = substr_count($view->render(), 'data-user-id="');
    expect($userCardCount)->toBe(2); // TestUser1 and TestUser2

    // Each user card should have proper structure
    $content = $view->render();
    expect($content)->toContain('data-user-id="'); // New attribute name
});

/**
 * Test: Staff are grouped by user correctly with role chips
 */
test('staff_are_grouped_by_user_with_role_chips', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load(['staff' => function ($query) {
            $query->orderBy('pivot_role');
        }]),
    ]);

    $content = $view->render();

    // Check usernames appear correctly
    expect(substr_count($content, 'TestUser1'))->toBeGreaterThan(0);
    expect(substr_count($content, 'TestUser2'))->toBeGreaterThan(0);

    // Check for role labels shown as compact chips
    expect($content)->toContain('Organizer');
    expect($content)->toContain('Mappooler');
    expect($content)->toContain('Playtester');

    // Check that avatar <img> tags are rendered
    $imgCount = substr_count($content, '<img');
    expect($imgCount)->toBeGreaterThan(0);

    // Check that user cards have data-user-id attributes (not data-staff-id)
    expect(substr_count($content, 'data-user-id='))->toBe(2);
});

/**
 * Test: Users are sorted by role count then username
 */
test('users_are_sorted_by_role_count_then_username', function () {
    // Create additional users to test sorting
    $user3 = User::factory()->create(['username' => 'AAAUser', 'osu_id' => 11111]);
    $user4 = User::factory()->create(['username' => 'ZZZUser', 'osu_id' => 99999]);

    // User3 gets 3 roles
    $this->tournament->staff()->attach($user3->id, ['role' => 'mapper', 'status' => 'approved', 'source' => 'manual']);
    $this->tournament->staff()->attach($user3->id, ['role' => 'referee', 'status' => 'approved', 'source' => 'manual']);
    $this->tournament->staff()->attach($user3->id, ['role' => 'gfx', 'status' => 'approved', 'source' => 'manual']);

    // User4 gets 1 role
    $this->tournament->staff()->attach($user4->id, ['role' => 'other', 'status' => 'approved', 'source' => 'manual']);

    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load(['staff' => function ($query) {
            $query->orderBy('pivot_role');
        }]),
    ]);

    $content = $view->render();

    // Find order of user cards
    $aaaPos = strpos($content, 'AAAUser');
    $zzzPos = strpos($content, 'ZZZUser');
    $test1Pos = strpos($content, 'TestUser1');
    $test2Pos = strpos($content, 'TestUser2');

    // AAAUser (3 roles) should come first
    expect($aaaPos)->toBeLessThan($test2Pos); // TestUser2 has 2 roles
    expect($aaaPos)->toBeLessThan($test1Pos); // TestUser1 has 1 role
    expect($aaaPos)->toBeLessThan($zzzPos); // ZZZUser has 1 role

    // Among 1-role users, should be sorted alphabetically
    expect($test1Pos)->toBeLessThan($zzzPos); // TestUser1 < ZZZUser alphabetically
});

/**
 * Test: Each role badge has proper colors from StaffRoleHelper
 */
test('each_role_badge_has_proper_colors', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load('staff'),
    ]);

    $content = $view->render();

    // Check that organizer has purple colors
    expect($content)->toContain('bg-purple-500/20');
    expect($content)->toContain('text-purple-400');
    expect($content)->toContain('border-purple-500/30');

    // Check that mappooler has blue colors (from StaffRoleHelper)
    expect($content)->toContain('bg-blue-500/20');
    expect($content)->toContain('text-blue-400');
    expect($content)->toContain('border-blue-500/30');

    // Check that playtester has green colors (from StaffRoleHelper)
    expect($content)->toContain('bg-green-500/20');
    expect($content)->toContain('text-green-400');
    expect($content)->toContain('border-green-500/30');
});

/**
 * Test: Expanded staff details do not show legacy per-role delete controls
 */
test('expanded_staff_details_use_checklist_entry_point_not_role_delete_buttons', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load('staff'),
    ]);

    $content = $view->render();

    expect($content)->not->toContain('onclick="removeStaff(');
    expect($content)->not->toContain('Remove Organizer role');
    expect($content)->toContain("Edit this user's roles together with the checklist.");
    expect($content)->toContain('Save Roles');
});

/**
 * Test: Component has expandable role sections
 */
test('component_has_expandable_role_sections_for_multi_role_users', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load('staff'),
    ]);

    $content = $view->render();

    // Check for Alpine.js expand functionality
    expect($content)->toContain('x-data="{ expanded: false }');
    expect($content)->toContain('@click="expanded = !expanded"');
    expect($content)->toContain('x-show="expanded"');

    // Check for role sections container
    expect($content)->toContain('id="userRoles-');

    // Check for expand/collapse icons
    expect($content)->toContain(':class="{ \'rotate-180\': expanded }"');
});

test('component_has_staff_search_role_filter_scroll_and_checklist_editor', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load('staff'),
    ]);

    $content = $view->render();

    expect($content)->toContain('x-model="staffSearch"');
    expect($content)->toContain('x-model="roleFilter"');
    expect($content)->toContain('max-h-[28rem]');
    expect($content)->toContain('Edit Roles');
    expect($content)->toContain('Save Roles');
    expect($content)->toContain('/staff/users/');
});

/**
 * Test: Empty staff list renders correctly
 */
test('empty_staff_list_renders_correctly', function () {
    $emptyTournament = Tournament::factory()->create();
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $emptyTournament->load(['staff' => function ($query) {
            $query->with('user');
        }]),
    ]);

    $content = $view->render();

    // Should not contain user cards
    expect(substr_count($content, 'data-user-id="'))->toBe(0);
    expect($content)->not->toContain('TestUser1');
    expect($content)->not->toContain('TestUser2');

    // Should show empty message
    expect($content)->toContain('No staff assigned yet');

    // Should still render the container
    expect($content)->toContain('data-tournament-staff-list');
});

/**
 * Test: Component handles staff with derived avatar URLs
 */
test('component_handles_staff_with_derived_avatar_urls', function () {
    $user = User::factory()->create(['username' => 'AvatarUser', 'osu_id' => 11111]);

    TournamentStaff::factory()->create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $user->id,
        'role' => 'gfx',
        'status' => 'approved',
    ]);

    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load('staff'),
    ]);

    $content = $view->render();

    // Should use derived osu! avatar URL
    expect($content)->toContain('https://a.ppy.sh/11111');
});

/**
 * Test: Role management uses checklist replacement, not legacy per-role update/delete controls
 */
test('javascript_controls_use_role_replacement_not_legacy_update_or_delete', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load('staff'),
    ]);

    $content = $view->render();

    // Check that legacy per-role controls do NOT exist in the component
    expect($content)->not->toContain('removeStaff(');
    expect($content)->not->toContain('updateStaffRole(');

    // Role replacement should be routed through the checklist save action
    expect($content)->toContain('saveRoles(');
    expect($content)->toContain('/staff/users/');
});

/**
 * Test: Role labels use StaffRoleHelper
 */
test('role_labels_use_staff_role_helper', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load('staff'),
    ]);

    $content = $view->render();

    // Check that role labels match StaffRoleHelper output
    $availableRoles = array_keys(StaffRoleHelper::getAvailableRoles());

    // Verify some known role labels
    expect($content)->toContain(StaffRoleHelper::getRoleLabel('organizer'));
    expect($content)->toContain(StaffRoleHelper::getRoleLabel('mappooler'));
    expect($content)->toContain(StaffRoleHelper::getRoleLabel('playtester'));
});

/**
 * Test: User cards display osu! IDs
 */
test('user_cards_display_osu_ids', function () {
    $view = view('components.admin.tournaments.tournament-staff-management', [
        'tournament' => $this->tournament->load('staff'),
    ]);

    $content = $view->render();

    // Check for osu! IDs with # prefix
    expect($content)->toContain('#12345'); // TestUser1
    expect($content)->toContain('#67890'); // TestUser2
});
