<?php

use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\User;

/**
 * TDD Test Suite: Parse History Diff Viewer Component
 *
 * These tests verify the parse history diff component works correctly
 * and displays changes from forum post parsing.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tournament = Tournament::factory()->create([
        'status' => 'pending_review',
        'forum_topic_id' => 12345,
    ]);
});

/**
 * Test: Component renders when no parse history exists
 */
test('parse_history_diff_shows_empty_state_when_no_history', function () {
    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    // Component should not render anything when no parse history exists
    $content = (string) $view;
    expect($content)->toBeEmpty();
});

/**
 * Test: Component renders parse history with changes
 */
test('parse_history_diff_renders_parse_history_with_changes', function () {
    // Create parse history with changes
    TournamentParseHistory::factory()->create([
        'tournament_id' => $this->tournament->id,
        'changes' => [
            'title' => [
                'old' => 'Old Title',
                'new' => 'New Title',
            ],
        ],
        'parsed_at' => now(),
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Latest Parse Changes');
    $view->assertSee('Old Title');
    $view->assertSee('New Title');
});

/**
 * Test: Component displays staff changes correctly
 */
test('parse_history_diff_displays_staff_changes', function () {
    TournamentParseHistory::factory()->create([
        'tournament_id' => $this->tournament->id,
        'changes' => [
            'staff' => [
                'added' => [
                    ['username' => 'NewUser', 'role' => 'mapper', 'osu_id' => 12345],
                ],
                'removed' => [
                    ['username' => 'OldUser', 'role' => 'referee', 'osu_id' => 67890],
                ],
                'role_changed' => [
                    [
                        'username' => 'ChangedUser',
                        'old_role' => 'playtester',
                        'new_role' => 'mapper',
                        'osu_id' => 11111,
                    ],
                ],
            ],
        ],
        'parsed_at' => now(),
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Staff Added');
    $view->assertSee('Staff Removed');
    $view->assertSee('Role Changes');
    $view->assertSee('NewUser');
    $view->assertSee('OldUser');
    $view->assertSee('ChangedUser');
});

/**
 * Test: Component shows statistics before/after
 */
test('parse_history_diff_shows_statistics_before_after', function () {
    TournamentParseHistory::factory()->create([
        'tournament_id' => $this->tournament->id,
        'changes' => [
            'staff' => [
                'stats' => [
                    'before' => [
                        'total' => 5,
                        'organizer' => 1,
                        'mapper' => 2,
                    ],
                    'after' => [
                        'total' => 7,
                        'organizer' => 1,
                        'mapper' => 3,
                    ],
                ],
            ],
        ],
        'parsed_at' => now(),
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Statistics');
    $view->assertSee('Before');
    $view->assertSee('After');
    $view->assertSee('5 staff');
    $view->assertSee('7 staff');
});

/**
 * Test: Component displays field-level changes
 */
test('parse_history_diff_displays_field_level_changes', function () {
    TournamentParseHistory::factory()->create([
        'tournament_id' => $this->tournament->id,
        'changes' => [
            'title' => [
                'old' => 'Old Tournament Name',
                'new' => 'New Tournament Name',
            ],
            'modes' => [
                'old' => ['osu'],
                'new' => ['osu', 'taiko'],
            ],
        ],
        'parsed_at' => now(),
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Title');
    $view->assertSee('Game Modes');
    $view->assertSee('Old Tournament Name');
    $view->assertSee('New Tournament Name');
});

/**
 * Test: Component shows "no changes" message when parse had no changes
 */
test('parse_history_diff_shows_no_changes_message', function () {
    $this->tournament->update(['parse_count' => 1]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('No changes in latest parse');
    $view->assertSee('Tournament was re-parsed but all values remained the same');
});

/**
 * Test: Component links to full parse history
 */
test('parse_history_diff_links_to_full_history', function () {
    TournamentParseHistory::factory()->create([
        'tournament_id' => $this->tournament->id,
        'changes' => ['title' => ['old' => 'Old', 'new' => 'New']],
        'parsed_at' => now(),
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    // Check that the component renders and contains the link text
    $view->assertSee('View All History');
    $view->assertSee('/admin/tournaments/'.$this->tournament->id.'/parse-history');
});

/**
 * Test: Component displays parsed time in human-readable format
 */
test('parse_history_displays_time_in_human_format', function () {
    $parsedAt = now()->subMinutes(5);

    TournamentParseHistory::factory()->create([
        'tournament_id' => $this->tournament->id,
        'changes' => ['title' => ['old' => 'Old', 'new' => 'New']],
        'parsed_at' => $parsedAt,
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Parsed');
    $view->assertSee('ago'); // Carbon's diffForHumans() adds "ago"
});

/**
 * Test: Component handles empty changes array
 */
test('parse_history_diff_handles_empty_changes_array', function () {
    TournamentParseHistory::factory()->create([
        'tournament_id' => $this->tournament->id,
        'changes' => [],
        'parsed_at' => now(),
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    // Empty changes array means no changes, so component should render empty
    $html = (string) $view;
    expect($html)->toBeEmpty();
});

test('parse_history_diff_marks_compacted_latest_history', function () {
    TournamentParseHistory::factory()->create([
        'tournament_id' => $this->tournament->id,
        'changes' => ['title' => ['old' => 'Old', 'new' => 'New']],
        'compacted_at' => now(),
        'parsed_at' => now(),
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.parse-history-diff :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('compacted');
});
