<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\ForumParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// TDD Phase 1: RED - Write Failing Tests First
// These tests WILL FAIL because the current code doesn't support multi-role
// ============================================================================

beforeEach(function () {
    // Create test tournament and users
    $this->tournament = Tournament::factory()->create();
    $this->user1 = User::factory()->create(['osu_id' => 12345, 'username' => 'MultiRoleUser']);
    $this->user2 = User::factory()->create(['osu_id' => 67890, 'username' => 'OtherUser']);
});

// ============================================================================
// ForumParser Tests - Multi-Role Extraction
// ============================================================================

test('multi_role_extraction_from_bbcode_returns_all_roles_for_same_user', function () {
    $bbcode = '[b][color=#cf68dd]Organizer:[/color][/b] [url=https://osu.ppy.sh/users/12345]MultiRoleUser[/url]
[b][color=#cf68dd]Mappooler:[/color][/b] [url=https://osu.ppy.sh/users/12345]MultiRoleUser[/url]
[b][color=#cf68dd]Referee:[/color][/b] [url=https://osu.ppy.sh/users/67890]OtherUser[/url]';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    // EXPECTED: 3 entries - user1 appears twice with different roles
    expect($result)->toHaveCount(3);

    // User 12345 should have BOTH organizer AND mappooler roles
    $user1Entries = array_filter($result, fn ($entry) => $entry['osu_id'] === 12345);
    expect($user1Entries)->toHaveCount(2);

    $roles = array_column(array_values($user1Entries), 'role');
    expect($roles)->toContain('organizer');
    expect($roles)->toContain('mappooler');
});

test('deduplicate_groups_roles_by_user_not_just_first_occurrence', function () {
    // This test verifies the NEW deduplication behavior
    // OLD behavior: keeps only first occurrence (organizer)
    // NEW behavior: groups all roles by user

    $inputStaff = [
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'organizer'],
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'mappooler'],
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'referee'],
        ['osu_id' => 67890, 'username' => 'OtherUser', 'role' => 'streamer'],
    ];

    $parser = app(ForumParser::class);

    // Use reflection to access private method
    $reflection = new ReflectionClass($parser);
    $method = $reflection->getMethod('deduplicateByOsuId');
    $method->setAccessible(true);

    $result = $method->invoke($parser, $inputStaff);

    // EXPECTED: Returns all entries (no deduplication by role, only by exact duplicates)
    // Each unique (osu_id, role) combination should be preserved
    expect($result)->toHaveCount(4);

    // All three roles for user 12345 should be present
    $user1Entries = array_filter($result, fn ($entry) => $entry['osu_id'] === 12345);
    expect($user1Entries)->toHaveCount(3);

    $roles = array_column(array_values($user1Entries), 'role');
    expect($roles)->toContain('organizer');
    expect($roles)->toContain('mappooler');
    expect($roles)->toContain('referee');
});

test('plain text staff without numeric identity is ignored', function () {
    $bbcode = '[b]Organizer:[/b] MultiRoleUser
[b]Mapper:[/b] MultiRoleUser
[b]Referee:[/b] OtherUser';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBe([]);
});

// ============================================================================
// Tournament Model Tests - Multi-Role Merge
// ============================================================================

test('merge_staff_adds_second_role_to_existing_staff', function () {
    // Setup: User already has organizer role
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    // Act: Merge staff with same user having mappooler role
    $parsedStaff = [
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'organizer'],
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'mappooler'],
    ];

    $result = $this->tournament->mergeStaff($parsedStaff);

    // Assert: User should now have BOTH roles
    $staffCount = $this->tournament->staff()->where('user_id', $this->user1->id)->count();
    expect($staffCount)->toBe(2);

    // Check both roles exist
    $roles = $this->tournament->staff()
        ->where('user_id', $this->user1->id)
        ->get()
        ->pluck('pivot.role')
        ->toArray();

    expect($roles)->toContain('organizer');
    expect($roles)->toContain('mappooler');

    // mergeStaff should report added=1 (only mappooler was new)
    expect($result['added'])->toBe(1);
    expect($result['updated'])->toBe(0);
});

test('merge_staff_removes_individual_role_while_keeping_others', function () {
    // Setup: User has both organizer and mappooler roles (from parsing)
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'parsed', // Mark as parsed so it can be removed during re-parse
    ]);
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'mappooler',
        'status' => 'approved',
        'source' => 'parsed', // Mark as parsed so it can be removed during re-parse
    ]);

    // Act: Parse returns only organizer role (mappooler removed)
    $parsedStaff = [
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'organizer'],
    ];

    $result = $this->tournament->mergeStaff($parsedStaff);

    // Assert: User should still have organizer role
    $staffCount = $this->tournament->staff()->where('user_id', $this->user1->id)->count();
    expect($staffCount)->toBe(1);

    $role = $this->tournament->staff()
        ->where('user_id', $this->user1->id)
        ->first()
        ->pivot->role;

    expect($role)->toBe('organizer');

    // Note: Current mergeStaff doesn't remove, so we'll need to update implementation
    // This test will drive the new behavior
});

test('merge_staff_removes_all_roles_for_user_when_not_in_parsed_data', function () {
    // Setup: User has multiple roles (from parsing)
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'parsed', // Mark as parsed so it can be removed during re-parse
    ]);
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'mappooler',
        'status' => 'approved',
        'source' => 'parsed', // Mark as parsed so it can be removed during re-parse
    ]);

    // Act: Parse data doesn't include user at all
    $parsedStaff = [
        ['osu_id' => 67890, 'username' => 'OtherUser', 'role' => 'referee'],
    ];

    $result = $this->tournament->mergeStaff($parsedStaff);

    // Assert: User should have NO roles (completely removed)
    $staffCount = $this->tournament->staff()->where('user_id', $this->user1->id)->count();
    expect($staffCount)->toBe(0);
});

test('merge_staff_prevents_duplicate_role_assignments', function () {
    // Setup: User already has organizer role
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    // Act: Try to add same role again
    $parsedStaff = [
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'organizer'],
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'mappooler'],
    ];

    $result = $this->tournament->mergeStaff($parsedStaff);

    // Assert: Should not create duplicate organizer role
    $organizerCount = $this->tournament->staff()
        ->where('user_id', $this->user1->id)
        ->wherePivot('role', 'organizer')
        ->count();

    expect($organizerCount)->toBe(1);
    expect($result['added'])->toBe(1); // Only mappooler added
});

// ============================================================================
// Tournament Model Tests - Multi-Role Diff Calculation
// ============================================================================

test('calculate_diffs_tracks_role_additions_for_multi_role_user', function () {
    // Setup: User has organizer role
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    // Act: Parse returns organizer + mappooler
    $parsedStaff = [
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'organizer'],
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'mappooler'],
    ];

    $diffs = $this->tournament->calculateStaffDiffs($parsedStaff);

    // Assert: Should show mappooler as added
    expect($diffs['added'])->toHaveCount(1);
    expect($diffs['added'][0]['role'])->toBe('mappooler');
    expect($diffs['added'][0]['osu_id'])->toBe(12345);
});

test('calculate_diffs_tracks_individual_role_removals', function () {
    // Setup: User has organizer and mappooler roles
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
    ]);
    $this->tournament->staff()->attach($this->user1->id, [
        'role' => 'mappooler',
        'status' => 'approved',
    ]);

    // Act: Parse returns only organizer
    $parsedStaff = [
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'organizer'],
    ];

    $diffs = $this->tournament->calculateStaffDiffs($parsedStaff);

    // Assert: Should show mappooler as removed
    expect($diffs['removed'])->toHaveCount(1);
    expect($diffs['removed'][0]['role'])->toBe('mappooler');
    expect($diffs['removed'][0]['osu_id'])->toBe(12345);
});

test('calculate_diffs_stats_correctly_counts_multi_role_users', function () {
    // Setup: User1 has 2 roles, User2 has 1 role
    $this->tournament->staff()->attach($this->user1->id, ['role' => 'organizer', 'status' => 'approved']);
    $this->tournament->staff()->attach($this->user1->id, ['role' => 'mappooler', 'status' => 'approved']);
    $this->tournament->staff()->attach($this->user2->id, ['role' => 'referee', 'status' => 'approved']);

    // Act: Parse returns User1 with 3 roles, User2 with 1 role
    $parsedStaff = [
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'organizer'],
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'mappooler'],
        ['osu_id' => 12345, 'username' => 'MultiRoleUser', 'role' => 'referee'], // NEW
        ['osu_id' => 67890, 'username' => 'OtherUser', 'role' => 'referee'],
    ];

    $diffs = $this->tournament->calculateStaffDiffs($parsedStaff);

    // Assert: Stats should count individual role assignments
    expect($diffs['stats']['before']['total'])->toBe(3);
    expect($diffs['stats']['after']['total'])->toBe(4);

    // Count by role
    expect($diffs['stats']['before']['organizer'])->toBe(1);
    expect($diffs['stats']['before']['mappooler'])->toBe(1);
    expect($diffs['stats']['before']['referee'])->toBe(1);

    expect($diffs['stats']['after']['organizer'])->toBe(1);
    expect($diffs['stats']['after']['mappooler'])->toBe(1);
    expect($diffs['stats']['after']['referee'])->toBe(2);
});
