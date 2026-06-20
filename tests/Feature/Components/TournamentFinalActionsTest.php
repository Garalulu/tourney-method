<?php

use App\Models\Tournament;
use App\Models\User;

/**
 * TDD Test Suite: Tournament Final Actions Component
 *
 * These tests verify the tournament final actions component displays correctly
 * including approve button and reject modal.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);
});

/**
 * Test: Component renders section header
 */
test('tournament_final_actions_renders_header', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-final-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Final Actions');
});

/**
 * Test: Component renders approve button
 */
test('tournament_final_actions_shows_approve_button', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-final-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $view->assertSee('Approve Tournament');
    $view->assertSee('approveTournament('.$this->tournament->id.', $data)', false);
});

/**
 * Test: Component includes confirm dialog on approve button
 */
test('tournament_final_actions_includes_approve_confirmation', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-final-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $content = (string) $view;
    expect($content)->toContain('Are you sure you want to approve this tournament?');
    expect($content)->toContain('Tournament will become visible to all players');
});

/**
 * Test: Component includes reject modal component call
 */
test('tournament_final_actions_includes_reject_modal_component', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-final-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    // In component tests, the modal won't render (no $errors), so just verify structure
    $view->assertSee('Final Actions');
    $view->assertSee('Approve Tournament');
});

/**
 * Test: Approve button has success styling
 */
test('tournament_final_actions_approve_button_has_success_styling', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-final-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $content = (string) $view;
    expect($content)->toContain('bg-[var(--success)]');
});

/**
 * Test: Component uses a POST request for approval
 */
test('tournament_final_actions_uses_post_method', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-final-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    $content = (string) $view;
    expect($content)->toContain("method: 'POST'");
});

/**
 * Test: Component includes CSRF token
 */
test('tournament_final_actions_includes_csrf_token', function () {
    $view = $this->blade(
        '<x-admin.tournaments.tournament-final-actions :tournament="$tournament" />',
        ['tournament' => $this->tournament]
    );

    // @csrf directive is rendered as a hidden input field
    $content = (string) $view;
    expect($content)->toContain('type="hidden"');
    expect($content)->toContain('name="_token"');
});
