<?php

use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Fresh database for each test
});

// Test 1: Unread detection - null viewed_at means unread
test('is_unread_returns_true_when_viewed_at_is_null', function () {
    $tournament = Tournament::factory()->create(['viewed_at' => null]);

    expect($tournament->isUnread())->toBeTrue();
});

// Test 2: Non-null viewed_at means read
test('is_unread_returns_false_when_viewed_at_is_set', function () {
    $tournament = Tournament::factory()->create(['viewed_at' => now()]);

    expect($tournament->isUnread())->toBeFalse();
});

// Test 3: Mark as viewed sets timestamp
test('mark_as_viewed_sets_viewed_at', function () {
    $tournament = Tournament::factory()->create(['viewed_at' => null]);

    $tournament->markAsViewed();

    expect($tournament->viewed_at)->not->toBeNull();
    expect($tournament->viewed_at)->toBeInstanceOf(Carbon::class);
});

test('mark_as_viewed_does_not_touch_updated_at', function () {
    $updatedAt = now()->subDays(3);
    $tournament = Tournament::factory()->create([
        'updated_at' => $updatedAt,
        'viewed_at' => null,
    ]);
    $originalUpdatedAt = $tournament->fresh()->updated_at;

    $tournament->markAsViewed();

    expect($tournament->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

// Test 4: Mark as unread clears timestamp
test('mark_as_unread_sets_viewed_at_to_null', function () {
    $tournament = Tournament::factory()->create(['viewed_at' => now()]);

    $tournament->markAsUnread();

    expect($tournament->viewed_at)->toBeNull();
});

test('mark_as_unread_does_not_touch_updated_at', function () {
    $updatedAt = now()->subDays(4);
    $tournament = Tournament::factory()->create([
        'updated_at' => $updatedAt,
        'viewed_at' => now(),
    ]);
    $originalUpdatedAt = $tournament->fresh()->updated_at;

    $tournament->markAsUnread();

    expect($tournament->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

test('progression summary describes qualifier group and bracket path', function () {
    $tournament = Tournament::factory()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'group_stage', 'advance_count' => 32],
                ['type' => 'bracket', 'start_round_size' => 32, 'entry_type' => 'winner_only', 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    expect($tournament->progression_summary)->toBe('Qualifier (Top 64) -> Group Stage (Top 32) -> Ro32 Double Elimination');
    expect($tournament->progression_chip)->toBe('Ro32 Double Elimination');
    expect($tournament->progression_steps->all())->toBe([
        'Qualifier (Top 64)',
        'Group Stage (Top 32)',
        'Ro32 Double Elimination',
    ]);
});

test('progression summary describes hybrid bracket entries', function () {
    $tournament = Tournament::factory()->create([
        'format_structure' => [
            'stages' => [[
                'type' => 'bracket',
                'start_round_size' => 16,
                'entry_type' => 'winner_loser_hybrid',
                'elimination_type' => 'double_elimination',
            ]],
        ],
    ]);

    expect($tournament->progression_summary)->toBe('Ro16 Double Elimination (Hybrid)');
    expect($tournament->progression_chip)->toBe('Ro16 Double Elimination');
});

test('progression summary describes swiss round stage', function () {
    $tournament = Tournament::factory()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'swiss_round', 'round_count' => 5, 'advance_count' => 16],
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    expect($tournament->progression_summary)->toBe('Swiss Round (5 Rounds, Top 16) -> Ro16 Double Elimination');
    expect($tournament->progression_chip)->toBe('Ro16 Double Elimination');
    expect($tournament->progression_steps->all())->toBe([
        'Swiss Round (5 Rounds, Top 16)',
        'Ro16 Double Elimination',
    ]);
});

test('progression summary describes multiple battle royale stages', function () {
    $tournament = Tournament::factory()->create([
        'format_tags' => ['battle_royale'],
        'format_structure' => [
            'stages' => [
                ['type' => 'battle_royale', 'lobby_count' => 4, 'players_per_lobby' => 8, 'advance_per_lobby' => 4],
            ],
        ],
    ]);

    expect($tournament->progression_summary)->toBe('Battle Royale (3 Rounds)');
    expect($tournament->progression_chip)->toBe('3 Rounds');
    expect($tournament->format_badges->all())->toBe(['Battle Royale']);
});

test('explicit empty format stages suppress legacy bracket fallback', function () {
    $tournament = Tournament::factory()->create([
        'format' => 'Double Elimination',
        'start_round_size' => 32,
        'format_structure' => ['stages' => []],
    ]);

    expect($tournament->formatStages()->all())->toBe([])
        ->and($tournament->progression_summary)->toBeNull()
        ->and($tournament->progression_chip)->toBeNull();
});

// Test 5: Meaningful changes detection with significant field change
test('has_meaningful_changes_detects_significant_field_changes', function () {
    $viewedAt = now()->subHour();
    $parsedAt = now()->subMinutes(30); // Between viewed_at and now

    $tournament = Tournament::factory()->create([
        'viewed_at' => $viewedAt,
        'title' => 'Old Title',
    ]);

    // Create parse history with title change (significant field)
    $history = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'parsed_at' => $parsedAt,
        'changes' => ['title' => ['old' => 'Old Title', 'new' => 'New Title']],
    ]);

    // Ensure parsed_at is set correctly
    $history->parsed_at = $parsedAt;
    $history->save();

    // Refresh tournament to clear any cached relationships
    $tournament->refresh();

    expect($tournament->hasMeaningfulChangesSinceLastView())->toBeTrue();
});

// Test 6: Insignificant changes are ignored
test('has_meaningful_changes_ignores_insignificant_changes', function () {
    $tournament = Tournament::factory()->create([
        'viewed_at' => now()->subHour(),
    ]);

    // Create parse history with only insignificant changes
    TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'parsed_at' => now(),
        'changes' => ['some_insignificant_field' => ['old' => 'value', 'new' => 'value2']],
    ]);

    expect($tournament->hasMeaningfulChangesSinceLastView())->toBeFalse();
});

// Test 7: Never viewed tournaments always have changes
test('has_meaningful_changes_returns_true_for_never_viewed', function () {
    $tournament = Tournament::factory()->create([
        'viewed_at' => null,
    ]);

    expect($tournament->hasMeaningfulChangesSinceLastView())->toBeTrue();
});

// Test 8: No new parses since last view means no changes
test('has_meaningful_changes_returns_false_when_no_new_parses', function () {
    $tournament = Tournament::factory()->create([
        'viewed_at' => now()->subHour(),
    ]);

    // Create parse history BEFORE viewed_at
    TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'parsed_at' => now()->subHours(2), // Before viewed_at
        'changes' => ['title' => ['old' => 'Old', 'new' => 'New']],
    ]);

    expect($tournament->hasMeaningfulChangesSinceLastView())->toBeFalse();
});

// Test 9: Multiple significant fields detected
test('has_meaningful_changes_detects_multiple_significant_fields', function () {
    $viewedAt = now()->subHour();
    $parsedAt = now()->subMinutes(30); // Between viewed_at and now

    $tournament = Tournament::factory()->create([
        'viewed_at' => $viewedAt,
    ]);

    // Create parse history with changes to multiple significant fields
    $history = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'parsed_at' => $parsedAt,
        'changes' => [
            'description' => ['old' => 'Old Desc', 'new' => 'New Desc'],
            'host_username' => ['old' => 'old_host', 'new' => 'new_host'],
        ],
    ]);

    // Ensure parsed_at is set correctly
    $history->parsed_at = $parsedAt;
    $history->save();

    // Refresh tournament to clear any cached relationships
    $tournament->refresh();

    expect($tournament->hasMeaningfulChangesSinceLastView())->toBeTrue();
});

// Test 10: Scope unread filters correctly
test('scope_unread_filters_unviewed_tournaments', function () {
    Tournament::factory()->create(['viewed_at' => null]);
    Tournament::factory()->create(['viewed_at' => now()]);
    Tournament::factory()->create(['viewed_at' => null]);

    $unread = Tournament::unread()->get();

    expect($unread)->toHaveCount(2);
});

// Test 11: NULL changes are handled gracefully (edge case for database corruption)
test('has_meaningful_changes_handles_null_changes_gracefully', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create([
        'viewed_at' => now()->subHour(),
    ]);

    // Create parse history with NULL changes (simulating database corruption or edge case)
    TournamentParseHistory::create([
        'tournament_id' => $tournament->id,
        'parsed_by' => $user->id,
        'changes' => null, // Explicitly NULL to test edge case
        'parsed_data' => ['test' => 'data'],
        'parsed_at' => now()->subMinute(),
    ]);

    // Should not throw error, should return false (no meaningful changes)
    expect($tournament->hasMeaningfulChangesSinceLastView())->toBeFalse();
});

// Test 12: Boolean false changes are handled gracefully
test('has_meaningful_changes_handles_boolean_false_changes', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create([
        'viewed_at' => now()->subHour(),
    ]);

    // Directly insert a record with boolean false in changes column
    // This simulates what happens when getAttribute() returns false
    DB::table('tournament_parse_histories')->insert([
        'tournament_id' => $tournament->id,
        'parsed_by' => $user->id,
        'changes' => false, // Boolean false (edge case)
        'parsed_data' => json_encode(['test' => 'data']),
        'parsed_at' => now()->subMinute(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Should not throw error, should return false (no meaningful changes)
    expect($tournament->hasMeaningfulChangesSinceLastView())->toBeFalse();
});

// Test 13: Scope latestFirst sorts by parsed_at DESC
test('scope_latest_first_sorts_by_parsed_at_desc', function () {
    $old = Tournament::factory()->create(['parsed_at' => now()->subDays(2)]);
    $new = Tournament::factory()->create(['parsed_at' => now()->subDay()]);
    $newest = Tournament::factory()->create(['parsed_at' => now()]);

    $tournaments = Tournament::latestFirst()->get();

    expect($tournaments->first()->id)->toBe($newest->id);
    expect($tournaments->last()->id)->toBe($old->id);
});

test('scope_latest_edited_first_sorts_by_updated_at_desc_then_id_desc', function () {
    $old = Tournament::factory()->create(['updated_at' => now()->subDays(2)]);
    $new = Tournament::factory()->create(['updated_at' => now()]);
    $sameTimeOlderId = Tournament::factory()->create(['updated_at' => now()->subDay()]);
    $sameTimeNewerId = Tournament::factory()->create(['updated_at' => $sameTimeOlderId->updated_at]);

    $tournaments = Tournament::latestEditedFirst()->pluck('id')->all();

    expect($tournaments)->toBe([
        $new->id,
        $sameTimeNewerId->id,
        $sameTimeOlderId->id,
        $old->id,
    ]);
});

// Test 14: Scope unread can be combined with other scopes
test('scope_unread_can_be_combined_with_status_scope', function () {
    Tournament::factory()->pending()->create(['viewed_at' => null]);
    Tournament::factory()->approved()->create(['viewed_at' => null]);
    Tournament::factory()->pending()->create(['viewed_at' => now()]);
    Tournament::factory()->approved()->create(['viewed_at' => now()]);

    $unreadPending = Tournament::pending()->unread()->get();

    expect($unreadPending)->toHaveCount(1);
});

// ============================================================================
// NEW TESTS: Tournament Status Transition Enhancement (TDD Phase 1 - RED)
// ============================================================================

// Test 15: Approved tournament can be restored to pending
test('approved tournament can be restored to pending', function () {
    $tournament = Tournament::factory()->approved()->create();
    $admin = User::factory()->admin()->create();

    $tournament->restoreToPending($admin);

    expect($tournament->status)->toBe('pending_review');
    expect($tournament->reviewed_by)->toBe($admin->id);
    expect($tournament->fresh()->rejection_reason)->toBeNull();
});

// Test 16: restoreToPending throws exception for pending tournaments
test('restoreToPending throws exception for pending tournaments', function () {
    $tournament = Tournament::factory()->pending()->create();
    $admin = User::factory()->admin()->create();

    expect(fn () => $tournament->restoreToPending($admin))
        ->toThrow(Exception::class, 'Only rejected or approved tournaments can be restored to pending');
});

// Test 17: Rejected tournament can still be restored to pending (existing behavior)
test('rejected tournament can be restored_to_pending', function () {
    $tournament = Tournament::factory()->rejected()->create([
        'rejection_reason' => 'Invalid information',
    ]);
    $admin = User::factory()->admin()->create();

    $tournament->restoreToPending($admin);

    expect($tournament->status)->toBe('pending_review');
    expect($tournament->reviewed_by)->toBe($admin->id);
    expect($tournament->fresh()->rejection_reason)->toBeNull();
});

// ============================================================================
// NEW TESTS: Search and Filter Scopes (TDD Phase 1 - RED)
// ============================================================================

// Test 18: search scope filters by tournament title
test('search scope filters by tournament title', function () {
    Tournament::factory()->create(['title' => 'Awesome Tournament 2024']);
    Tournament::factory()->create(['title' => 'Another Tournament']);

    $results = Tournament::search('awesome')->get();

    expect($results->count())->toBe(1);
    expect($results->first()->title)->toBe('Awesome Tournament 2024');
});

// Test 19: search scope filters by host username
test('search scope filters by host username', function () {
    Tournament::factory()->create(['host_username' => 'best_host']);
    Tournament::factory()->create(['host_username' => 'other_host']);

    $results = Tournament::search('best_host')->get();

    expect($results->count())->toBe(1);
    expect($results->first()->host_username)->toBe('best_host');
});

// Test 20: search scope returns all when search term is empty
test('search scope returns all when search term is empty', function () {
    Tournament::factory()->count(3)->create();

    $results = Tournament::search('')->get();

    expect($results->count())->toBe(3);
});

// Test 21: search scope is case insensitive
test('search scope is case_insensitive', function () {
    Tournament::factory()->create(['title' => 'Summer Championship 2024']);
    Tournament::factory()->create(['title' => 'Winter Cup']);

    $results = Tournament::search('SUMMER')->get();

    expect($results->count())->toBe(1);
    expect($results->first()->title)->toBe('Summer Championship 2024');
});

// Test 22: filterModes scope filters by single mode
test('filterModes scope filters by single mode', function () {
    Tournament::factory()->create(['modes' => ['osu', 'taiko']]);
    Tournament::factory()->create(['modes' => ['mania']]);

    $results = Tournament::filterModes(['osu'])->get();

    expect($results->count())->toBe(1);
    expect($results->first()->modes_with_details->pluck('mode')->all())->toContain('osu');
});

// Test 23: filterModes scope filters by multiple modes (OR logic)
test('filterModes scope filters by multiple modes', function () {
    Tournament::factory()->create(['modes' => ['osu', 'taiko']]);
    Tournament::factory()->create(['modes' => ['osu']]);
    Tournament::factory()->create(['modes' => ['mania']]);

    $results = Tournament::filterModes(['osu', 'taiko'])->get();

    expect($results->count())->toBe(2);
});

// Test 24: filterModes scope returns all when modes array is empty
test('filterModes scope returns all when modes array is empty', function () {
    Tournament::factory()->count(3)->create(['modes' => ['osu']]);

    $results = Tournament::filterModes([])->get();

    expect($results->count())->toBe(3);
});

// Test 25: filterModes scope can be combined with search scope
test('filterModes scope can be combined with search scope', function () {
    Tournament::factory()->create([
        'title' => 'Summer osu! Tournament',
        'modes' => ['osu'],
    ]);
    Tournament::factory()->create([
        'title' => 'Summer mania Tournament',
        'modes' => ['mania'],
    ]);
    Tournament::factory()->create([
        'title' => 'Winter osu! Cup',
        'modes' => ['osu'],
    ]);

    $results = Tournament::search('summer')->filterModes(['osu'])->get();

    expect($results->count())->toBe(1);
    expect($results->first()->title)->toBe('Summer osu! Tournament');
});
