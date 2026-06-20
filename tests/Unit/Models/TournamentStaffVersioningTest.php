<?php

use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fresh database for each test - RefreshDatabase trait handles this
});

// ==================== STAFF DIFF CALCULATION ====================

test('calculate staff diffs detects added staff', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    // Start with 1 staff member
    $tournament->staff()->attach($user1->id, ['role' => 'organizer', 'status' => 'approved']);

    // Parse shows 2 staff members
    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
        ['osu_id' => 456, 'username' => 'user2', 'role' => 'referee'],
    ];

    $changes = $tournament->calculateStaffDiffs($parsedStaff);

    expect($changes['added'])->toHaveCount(1);
    expect($changes['added'][0]['osu_id'])->toBe(456);
    expect($changes['removed'])->toBeEmpty();
    expect($changes['role_changed'])->toBeEmpty();
});

test('calculate staff diffs detects removed staff', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    // Start with 2 staff members
    $tournament->staff()->attach($user1->id, ['role' => 'organizer', 'status' => 'approved']);
    $tournament->staff()->attach($user2->id, ['role' => 'referee', 'status' => 'approved']);

    // Parse shows only 1 staff member
    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
    ];

    $changes = $tournament->calculateStaffDiffs($parsedStaff);

    expect($changes['removed'])->toHaveCount(1);
    expect($changes['removed'][0]['osu_id'])->toBe(456);
    expect($changes['removed'][0]['role'])->toBe('referee');
    expect($changes['removed'][0]['reason'])->toBe('not_in_parsed_data');
    expect($changes['added'])->toBeEmpty();
    expect($changes['role_changed'])->toBeEmpty();
});

test('calculate staff diffs detects role changes', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);

    // Start with organizer
    $tournament->staff()->attach($user1->id, ['role' => 'organizer', 'status' => 'approved']);

    // Parse shows referee (role change in multi-role model = remove old, add new)
    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'referee'],
    ];

    $changes = $tournament->calculateStaffDiffs($parsedStaff);

    // Multi-role model: organizer removed, referee added
    expect($changes['removed'])->toHaveCount(1);
    expect($changes['removed'][0]['osu_id'])->toBe(123);
    expect($changes['removed'][0]['role'])->toBe('organizer');
    expect($changes['added'])->toHaveCount(1);
    expect($changes['added'][0]['osu_id'])->toBe(123);
    expect($changes['added'][0]['role'])->toBe('referee');
});

test('calculate staff diffs handles multiple changes', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);
    $user3 = User::factory()->create(['osu_id' => 789]);

    // Start with 2 staff
    $tournament->staff()->attach($user1->id, ['role' => 'organizer', 'status' => 'approved']);
    $tournament->staff()->attach($user2->id, ['role' => 'mapper', 'status' => 'approved']);

    // Parse shows different set
    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'referee'], // Role changed (organizer removed, referee added)
        ['osu_id' => 456, 'username' => 'user2', 'role' => 'mapper'],    // Unchanged
        ['osu_id' => 789, 'username' => 'user3', 'role' => 'commentator'], // Added
    ];

    $changes = $tournament->calculateStaffDiffs($parsedStaff);

    expect($changes['added'])->toHaveCount(2); // referee + commentator
    expect($changes['removed'])->toHaveCount(1); // organizer removed

    // Verify added items
    $addedOsuIds = array_column($changes['added'], 'osu_id');
    expect($addedOsuIds)->toContain(789); // user3 added

    // Verify removed items
    expect($changes['removed'][0]['osu_id'])->toBe(123);
    expect($changes['removed'][0]['role'])->toBe('organizer');
});

test('calculate staff diffs includes stats', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    // Start with 1 organizer
    $tournament->staff()->attach($user1->id, ['role' => 'organizer', 'status' => 'approved']);

    // Parse shows 2 staff
    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'referee'], // Role changed
        ['osu_id' => 456, 'username' => 'user2', 'role' => 'organizer'], // Added
    ];

    $changes = $tournament->calculateStaffDiffs($parsedStaff);

    expect($changes['stats'])->toBeArray();
    expect($changes['stats']['before']['total'])->toBe(1);
    expect($changes['stats']['before']['organizer'])->toBe(1);
    expect($changes['stats']['after']['total'])->toBe(2);
    expect($changes['stats']['after']['organizer'])->toBe(1);
    expect($changes['stats']['after']['referee'])->toBe(1);
});

test('calculate staff diffs handles empty staff', function () {
    $tournament = Tournament::factory()->create();

    $parsedStaff = [];

    $changes = $tournament->calculateStaffDiffs($parsedStaff);

    expect($changes['added'])->toBeEmpty();
    expect($changes['removed'])->toBeEmpty();
    expect($changes['role_changed'])->toBeEmpty();
    expect($changes['stats']['before']['total'])->toBe(0);
    expect($changes['stats']['after']['total'])->toBe(0);
});

// ==================== MERGE STAFF WITH HISTORY ====================

test('mergeStaffWithHistory creates parse history', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
        ['osu_id' => 456, 'username' => 'user2', 'role' => 'referee'],
    ];

    $result = $tournament->mergeStaffWithHistory($parsedStaff, 'test_source');

    expect($result['added'])->toBe(2);
    expect($result['updated'])->toBe(0);

    // Verify parse history was created
    $history = TournamentParseHistory::where('tournament_id', $tournament->id)->first();
    expect($history)->not->toBeNull();
    expect($history->parse_source)->toBe('test_source');
    expect($history->changes['staff'])->toHaveKey('added');
    expect($history->changes['staff']['added'])->toHaveCount(2);
});

test('mergeStaffWithHistory tracks role changes', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create(['osu_id' => 123]);

    $tournament->staff()->attach($user->id, ['role' => 'organizer', 'status' => 'approved']);

    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user', 'role' => 'referee'],
    ];

    $result = $tournament->mergeStaffWithHistory($parsedStaff, 'test_source');

    // Multi-role model: organizer removed, referee added
    expect($result['removed'])->toBe(1);
    expect($result['added'])->toBe(1);

    $history = TournamentParseHistory::where('tournament_id', $tournament->id)->first();
    expect($history->changes['staff']['removed'])->toHaveCount(1);
    expect($history->changes['staff']['removed'][0]['role'])->toBe('organizer');
    expect($history->changes['staff']['added'])->toHaveCount(1);
    expect($history->changes['staff']['added'][0]['role'])->toBe('referee');
});

test('mergeStaffWithHistory tracks removed staff', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    // Add staff from parsing (so they can be removed on re-parse)
    $tournament->staff()->attach($user1->id, ['role' => 'organizer', 'status' => 'approved', 'source' => 'parsed']);
    $tournament->staff()->attach($user2->id, ['role' => 'referee', 'status' => 'approved', 'source' => 'parsed']);

    // Parse shows only user1
    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
    ];

    $result = $tournament->mergeStaffWithHistory($parsedStaff, 'reparse_command');

    expect($result['removed'])->toBe(1);

    $history = TournamentParseHistory::where('tournament_id', $tournament->id)->first();
    expect($history->changes['staff']['removed'])->toHaveCount(1);
    expect($history->changes['staff']['removed'][0]['osu_id'])->toBe(456);
    expect($history->changes['staff']['removed'][0]['reason'])->toBe('not_in_parsed_data');

    // Verify user2 is removed from database (multi-role model removes staff)
    expect($tournament->staff()->where('user_id', $user2->id)->exists())->toBeFalse();
});

test('mergeStaffWithHistory includes parse metadata', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create(['osu_id' => 123]);

    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
    ];

    $result = $tournament->mergeStaffWithHistory($parsedStaff, 'test_source', [
        'format_detected' => 'profile_tag',
        'parser_version' => '2.0',
    ]);

    $history = TournamentParseHistory::where('tournament_id', $tournament->id)->first();
    expect($history->parsed_data['format_detected'])->toBe('profile_tag');
    expect($history->parsed_data['parser_version'])->toBe('2.0');
    expect($history->parsed_data['staff'])->toHaveCount(1);
});

test('mergeStaffWithHistory increments tournament parse count', function () {
    $tournament = Tournament::factory()->create(['parse_count' => 0]);
    $user = User::factory()->create(['osu_id' => 123]);

    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
    ];

    $tournament->mergeStaffWithHistory($parsedStaff, 'test_source');

    expect($tournament->fresh()->parse_count)->toBe(1);
    expect($tournament->fresh()->last_parsed_at)->not->toBeNull();
});

test('mergeStaffWithHistory skips history and parse count when staff is unchanged', function () {
    $tournament = Tournament::factory()->create(['parse_count' => 3]);
    $user1 = User::factory()->create(['osu_id' => 123, 'username' => 'user1']);
    $user2 = User::factory()->create(['osu_id' => 456, 'username' => 'user2']);

    $tournament->staff()->attach($user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'parsed',
    ]);
    $tournament->staff()->attach($user2->id, [
        'role' => 'mapper',
        'status' => 'approved',
        'source' => 'parsed',
    ]);

    $result = $tournament->mergeStaffWithHistory([
        ['osu_id' => 456, 'username' => 'user2', 'role' => 'mapper'],
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
    ], 'test_source');

    expect($result)->toBe([
        'added' => 0,
        'updated' => 0,
        'removed' => 0,
    ]);
    expect($tournament->fresh()->parse_count)->toBe(3);
    expect(TournamentParseHistory::where('tournament_id', $tournament->id)->count())->toBe(0);
    expect($tournament->fresh()->staff()->count())->toBe(2);
});

test('mergeStaffWithHistory accepts parsed_by user', function () {
    $admin = User::factory()->create();
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create(['osu_id' => 123]);

    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
    ];

    $tournament->mergeStaffWithHistory($parsedStaff, 'manual_update', [], $admin->id);

    $history = TournamentParseHistory::where('tournament_id', $tournament->id)->first();
    expect($history->parsed_by)->toBe($admin->id);
    expect($tournament->fresh()->last_parsed_by)->toBe($admin->id);
});

test('mergeStaffWithHistory prevents data loss when parser returns empty results', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    // Start with 2 staff members
    $tournament->staff()->attach($user1->id, ['role' => 'organizer', 'status' => 'approved']);
    $tournament->staff()->attach($user2->id, ['role' => 'mapper', 'status' => 'approved']);

    expect($tournament->staff()->count())->toBe(2);

    // Parser returns empty results (simulating API failure)
    $result = $tournament->mergeStaffWithHistory([], 'test_source');

    // Should NOT delete any staff
    expect($tournament->fresh()->staff()->count())->toBe(2);
    expect($result['added'])->toBe(0);
    expect($result['removed'])->toBe(0);
    expect($result['updated'])->toBe(0);

    // Parse history should record the safety skip
    $history = TournamentParseHistory::where('tournament_id', $tournament->id)
        ->where('parse_source', 'test_source')
        ->first();

    expect($history)->not->toBeNull();
    expect($history->parse_notes)->toContain('SAFETY: Skipped merge');
    expect($history->changes['staff']['warning'])->toBe('Parser returned empty results - merge skipped to prevent data loss');
});
