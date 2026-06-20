<?php

use App\Models\Tournament;
use App\Models\User;

/**
 * TDD Test Suite: Tournament Header Component
 *
 * These tests verify the tournament header component displays correctly
 * including status badge, re-parse button, and navigation.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tournament = Tournament::factory()->create([
        'status' => 'pending_review',
        'forum_topic_id' => 12345,
    ]);
});

/**
 * Test: Component renders back button to tournaments list
 */
test('tournament_header_renders_back_button', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-header :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Review');
    $view->assertSee('Edit');
    $view->assertSee('Modify fields before approval');
});

/**
 * Test: Component shows pending review status badge
 */
test('tournament_header_shows_pending_review_status', function () {
    $this->tournament->update(['status' => 'pending_review']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-header :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Pending Review');
    $view->assertSee('bg-yellow-500'); // Status badge color
});

/**
 * Test: Component shows approved status badge
 */
test('tournament_header_shows_approved_status', function () {
    $this->tournament->update(['status' => 'approved']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-header :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Approved');
    $view->assertSee('bg-green-500'); // Status badge color
});

/**
 * Test: Component shows rejected status badge with reason
 */
test('tournament_header_shows_rejected_status_with_reason', function () {
    $this->tournament->update([
        'status' => 'rejected',
        'rejection_reason' => 'Incomplete information',
    ]);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-header :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Rejected');
    $view->assertSee('bg-red-500'); // Status badge color
    $view->assertSee('Incomplete information');
    $view->assertSee('Reason:');
});

/**
 * Test: Component renders re-parse button when forum_topic_id exists
 */
test('tournament_header_shows_reparse_button_when_forum_topic_exists', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-header :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Re-parse Tournament');
    $view->assertSee('/admin/tournaments/'.$this->tournament->id.'/reparse'); // Form action URL pattern
});

/**
 * Test: Component does not render re-parse button when no forum_topic_id
 */
test('tournament_header_hides_reparse_button_when_no_forum_topic', function () {
    $this->tournament->update(['forum_topic_id' => null]);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-header :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertDontSee('Re-parse Tournament');
});

/**
 * Test: Component includes link back to pending tournaments
 */
test('tournament_header_includes_back_link', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-header :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('/admin/tournaments/pending');
});
