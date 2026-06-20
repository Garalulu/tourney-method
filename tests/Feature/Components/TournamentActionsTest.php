<?php

use App\Models\Tournament;
use App\Models\User;

/**
 * TDD Test Suite: Tournament Actions Component
 *
 * These tests verify the tournament actions component displays correctly
 * including restore and delete buttons for rejected/approved tournaments.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);
});

/**
 * Test: Component does not render for pending_review tournaments
 */
test('tournament_actions_does_not_render_for_pending_review', function () {
    $this->tournament->update(['status' => 'pending_review']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    // Component should render empty for pending_review
    $content = (string) $view;
    expect(trim($content))->toBeEmpty();
});

/**
 * Test: Component renders for rejected tournaments
 */
test('tournament_actions_renders_for_rejected_tournament', function () {
    $this->tournament->update(['status' => 'rejected']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('This tournament was rejected');
    $view->assertSee('Restore');
});

/**
 * Test: Component does not render for approved tournaments
 * Updated: tournament-actions only shows for rejected tournaments now
 */
test('tournament_actions_does_not_render_for_approved_tournament', function () {
    $this->tournament->update(['status' => 'approved']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    // Component should render empty for approved tournaments
    $content = (string) $view;
    expect(trim($content))->toBeEmpty();
});

/**
 * Test: Delete button is present for rejected tournaments
 * Updated: Changed from approved to rejected
 */
test('tournament_actions_shows_delete_button_for_rejected', function () {
    $this->tournament->update(['status' => 'rejected']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Delete');
});

/**
 * Test: Restore button is present for rejected tournaments
 */
test('tournament_actions_shows_restore_button_for_rejected', function () {
    $this->tournament->update(['status' => 'rejected']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Restore');
    $view->assertSee('restoreTournament('); // JavaScript function
});

/**
 * Test: Component does not render for approved tournaments
 * Updated: Tests that approved tournaments don't show the component
 */
test('tournament_actions_hides_all_actions_for_approved', function () {
    $this->tournament->update(['status' => 'approved']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $content = (string) $view;
    expect(trim($content))->toBeEmpty();
});

/**
 * Test: Delete confirmation modal is present for rejected tournaments
 * Updated: Changed from approved to rejected
 */
test('tournament_actions_shows_delete_confirmation_modal_for_rejected', function () {
    $this->tournament->update(['status' => 'rejected']);

    $view = $this->blade(
        '<x-admin.tournaments.tournament-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Delete Tournament?');
    $view->assertSee('This will soft delete the tournament');
    $view->assertSee('deleteTournament('); // JavaScript function
});
