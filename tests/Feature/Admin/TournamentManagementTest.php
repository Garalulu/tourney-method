<?php

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Services\ParticipationPodiumBackfillService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Test 1: Queue data is passed to view
test('admin_pending_page_shows_queue_data', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    Tournament::factory()->count(3)->pending()->create();
    Tournament::factory()->count(2)->approved()->create();
    Tournament::factory()->count(1)->rejected()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending'));

    $response->assertStatus(200);
    $response->assertViewHas('tournaments');
    $response->assertViewHas('counts');
    $response->assertViewHas('activeTab', 'pending');

    expect($response->viewData('counts'))->toMatchArray([
        'pending' => 3,
        'approved' => 2,
        'rejected' => 1,
    ]);
});

// Test 2: Pending tournaments are sorted by registration start, with missing dates first
test('pending_tournaments_are_sorted_by_registration_start_with_nulls_first', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $later = Tournament::factory()->pending()->create(['registration_start' => now()->addDays(10)]);
    $missing = Tournament::factory()->pending()->create(['registration_start' => null]);
    $sooner = Tournament::factory()->pending()->create(['registration_start' => now()->addDays(2)]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending'));

    $tournaments = $response->viewData('tournaments');

    expect($tournaments->pluck('id')->all())->toBe([
        $missing->id,
        $sooner->id,
        $later->id,
    ]);
});

test('admin_can_sort_queue_by_allowed_date_and_updated_columns', function () {
    $admin = User::factory()->admin()->create();

    $first = Tournament::factory()->pending()->create([
        'registration_start' => '2026-02-01 00:00:00',
        'tournament_end' => '2026-05-01 00:00:00',
        'updated_at' => '2026-01-03 00:00:00',
    ]);
    $second = Tournament::factory()->pending()->create([
        'registration_start' => null,
        'tournament_end' => '2026-04-01 00:00:00',
        'updated_at' => '2026-01-01 00:00:00',
    ]);
    $third = Tournament::factory()->pending()->create([
        'registration_start' => '2026-03-01 00:00:00',
        'tournament_end' => null,
        'updated_at' => '2026-01-02 00:00:00',
    ]);

    $registrationStart = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['sort' => 'registration_start', 'direction' => 'asc']));
    $tournamentEnd = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['sort' => 'tournament_end', 'direction' => 'asc']));
    $updatedAt = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['sort' => 'updated_at', 'direction' => 'desc']));

    expect($registrationStart->viewData('tournaments')->pluck('id')->all())
        ->toBe([$second->id, $first->id, $third->id])
        ->and($tournamentEnd->viewData('tournaments')->pluck('id')->all())
        ->toBe([$third->id, $second->id, $first->id])
        ->and($updatedAt->viewData('tournaments')->pluck('id')->all())
        ->toBe([$first->id, $third->id, $second->id]);
});

// Test 3: Viewing tournament marks it as viewed
test('viewing_tournament_marks_it_as_viewed', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->pending()->create(['viewed_at' => null]);

    $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    expect($tournament->fresh()->viewed_at)->not->toBeNull();
});

// Test 4: Re-parsing with meaningful changes marks as unread
test('reparsing_with_meaningful_changes_marks_as_unread', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'viewed_at' => now(),
        'title' => 'Original Title',
        'description' => 'Original Description',
        'status' => 'pending_review',
    ]);

    // Simulate re-parse with title change (meaningful change)
    $parsedData = [
        'title' => 'Updated Title',
        'description' => 'Original Description', // Same
        'host_username' => $tournament->host_username,
        'modes' => $tournament->modes,
    ];

    $tournament->updateFromParsedData($parsedData, $admin->id);

    expect($tournament->fresh()->viewed_at)->toBeNull();
});

// Test 5: Re-parsing without meaningful changes keeps viewed status
test('reparsing_without_meaningful_changes_keeps_viewed_status', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'viewed_at' => now(),
        'title' => 'Same Title',
        'description' => 'Same Description',
        'status' => 'pending_review',
    ]);

    // Simulate re-parse with no meaningful changes
    $parsedData = [
        'title' => 'Same Title', // Same as current
        'description' => 'Same Description', // Same as current
        'host_username' => $tournament->host_username,
        'modes' => $tournament->modes,
    ];

    $tournament->updateFromParsedData($parsedData, $admin->id);

    expect($tournament->fresh()->viewed_at)->not->toBeNull();
});

// Test 6: Approved tab shows approved tournaments
test('approved_tab_shows_approved_tournaments', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    Tournament::factory()->count(2)->approved()->create();
    Tournament::factory()->count(3)->pending()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['tab' => 'approved']));

    $approved = $response->viewData('tournaments');

    expect($response->viewData('activeTab'))->toBe('approved');
    expect($approved)->toHaveCount(2);
});

// Test 7: Rejected tab shows rejected tournaments
test('rejected_tab_shows_rejected_tournaments', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    Tournament::factory()->count(1)->rejected()->create();
    Tournament::factory()->count(4)->pending()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['tab' => 'rejected']));

    $rejected = $response->viewData('tournaments');

    expect($response->viewData('activeTab'))->toBe('rejected');
    expect($rejected)->toHaveCount(1);
});

// Test 8: Non-admin users cannot access pending page
test('non_admin_cannot_access_pending_page', function () {
    $user = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.tournaments.pending'));

    $response->assertStatus(403);
});

// Test 9: Multiple admins share viewed status (global tracking)
test('multiple_admins_share_viewed_status', function () {
    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();

    $tournament = Tournament::factory()->pending()->create(['viewed_at' => null]);

    // Admin1 views tournament
    $this->actingAs($admin1)
        ->get(route('admin.tournaments.show', $tournament));

    expect($tournament->fresh()->viewed_at)->not->toBeNull();

    // Admin2 should see it as already viewed
    expect($tournament->fresh()->isUnread())->toBeFalse();
});

// Test 10: Pagination works with tab and page parameters
test('pagination_works_with_tab_and_page_parameters', function () {
    $admin = User::factory()->admin()->create();

    // Create 25 tournaments in each status (more than default 20 per page)
    Tournament::factory()->count(25)->pending()->create();
    Tournament::factory()->count(25)->approved()->create();
    Tournament::factory()->count(25)->rejected()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['tab' => 'approved', 'page' => 2]));

    $tournaments = $response->viewData('tournaments');

    expect($response->viewData('activeTab'))->toBe('approved');
    expect($tournaments)->toHaveCount(5);
    expect($tournaments->total())->toBe(25);
    expect($tournaments->currentPage())->toBe(2);
});

test('legacy_page_parameters_select_the_matching_queue_tab', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->count(25)->approved()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['approved_page' => 2]));

    $tournaments = $response->viewData('tournaments');

    expect($response->viewData('activeTab'))->toBe('approved');
    expect($tournaments->currentPage())->toBe(2);
    expect($tournaments)->toHaveCount(5);
});

// ============================================================================
// NEW TESTS: UI/UX Improvements (TDD Phase 1 - RED)
// ============================================================================

// Test 11: Pending tournament detail page shows both approve and reject buttons
test('pending tournament detail page_shows_both_approve_and_reject_buttons', function () {
    $tournament = Tournament::factory()->pending()->create();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertStatus(200);
    $response->assertSee('Approve Tournament');
    $response->assertSee('Reject Tournament');
});

// Test 12: Approved tournament detail page does not show delete button
test('approved_tournament_detail_page_does_not_show_delete_button', function () {
    $tournament = Tournament::factory()->approved()->create();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertStatus(200);
    $response->assertDontSee('Delete Tournament?'); // Tournament delete action should not be present
});

// Test 13: Approved tournament detail page shows return to pending button
test('approved_tournament_detail_page_shows_return_to_pending_button', function () {
    $tournament = Tournament::factory()->approved()->create();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertStatus(200);
    $response->assertSee('Return to Pending Review');
});

// Test 14: Admin can return approved tournament to pending status
test('admin_can_return_approved_tournament_to_pending_status', function () {
    $tournament = Tournament::factory()->approved()->create();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.tournaments.restore', $tournament));

    $response->assertStatus(200);
    expect($tournament->fresh()->status)->toBe('pending_review');
    expect($tournament->fresh()->reviewed_by)->toBe($admin->id);
});

// Test 15: Admin can then reject the returned tournament (two-step workflow)
test('admin_can_then_reject_the_returned_tournament', function () {
    $tournament = Tournament::factory()->approved()->create();
    $admin = User::factory()->admin()->create();

    // Step 1: Return to pending
    $this->actingAs($admin)
        ->post(route('admin.tournaments.restore', $tournament));
    expect($tournament->fresh()->status)->toBe('pending_review');

    // Step 2: Reject
    $response = $this->actingAs($admin)
        ->post(route('admin.tournaments.reject', $tournament), [
            'reason' => 'No longer needed',
        ]);

    $response->assertStatus(200);
    expect($tournament->fresh()->status)->toBe('rejected');
    expect($tournament->fresh()->rejection_reason)->toBe('No longer needed');
});

test('ended badge tournament review page shows badge configuration without podium winners', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'is_badge' => true,
        'badge_status' => null,
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertOk();
    $response->assertSee('Badge Configuration');
    $response->assertSee('Badge Approval Status');
    $response->assertSee('value="pending" selected', false);
    $response->assertDontSee('-- Not a badge tournament --');
});

test('ended non badge tournament review page renders badge configuration for instant toggle', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'is_badge' => false,
        'badge_status' => null,
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertOk();
    $response->assertSee('Badge Configuration');
    $response->assertSee('@admin-badge-toggle.window', false);
    $response->assertSee(':disabled="!visible"', false);
});

test('ended tournament review page groups legacy same placement podium winners with controls', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);
    $winnerOne = User::factory()->create(['username' => 'LegacyWinnerOne']);
    $winnerTwo = User::factory()->create(['username' => 'LegacyWinnerTwo']);

    foreach ([[$winnerOne, 'stale-group-one'], [$winnerTwo, 'stale-group-two']] as [$winner, $staleGroupId]) {
        TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winner->id,
            'placement' => 1,
            'username' => $winner->username,
            'osu_id' => $winner->osu_id,
            'gamemode' => 'osu',
            'metadata' => ['podium_group_id' => $staleGroupId],
        ]);
    }

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertOk();
    $response->assertSee('Team 1');
    $response->assertDontSee('Group 2');
    $response->assertDontSee('Group selected');
    $response->assertDontSee('Use data');
    $response->assertDontSee('Split');
    $response->assertSee('data-podium-group-card', false);
    $response->assertSee('draggable="true"', false);
    $response->assertSee('ondrop="dropPodiumWinner(event)"', false);
    $response->assertSee('onclick="togglePodiumSelection(this)"', false);
    $response->assertSee('onclick="moveSelectedPodiumWinnersToNewGroup(this)"', false);
    $response->assertSee('data-podium-new-group-target', false);
    $response->assertSee('LegacyWinnerOne');
    $response->assertSee('LegacyWinnerTwo');
});

test('admin podium group save updates team name and returns refreshed fragment', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);
    $winnerOne = User::factory()->create(['username' => 'PodiumSaveOne']);
    $winnerTwo = User::factory()->create(['username' => 'PodiumSaveTwo']);
    $groupId = 'podium-save-group';

    $winnerRows = collect([$winnerOne, $winnerTwo])->map(fn (User $user) => TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'placement' => 1,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => $groupId, 'podium_group_manual' => true],
    ]));

    $this->actingAs($admin)
        ->postJson(route('admin.tournaments.podium.groups.store', $tournament), [
            'winner_ids' => $winnerRows->pluck('id')->all(),
            'team_name' => 'Saved Team Name',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('podium_group_id', $groupId)
        ->assertJson(fn ($json) => $json->whereType('fragment', 'string')->etc());

    expect(TournamentParticipationRecord::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('user_id', [$winnerOne->id, $winnerTwo->id])
        ->pluck('team_name')
        ->all())->toBe(['Saved Team Name', 'Saved Team Name']);
});

test('admin podium drag style move regroups same placement member without page reload', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 3,
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);
    $users = User::factory()->count(3)->create();
    [$teamOneA, $teamOneB, $teamTwoA] = $users;

    $winnerOneA = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $teamOneA->id,
        'placement' => 1,
        'username' => $teamOneA->username,
        'osu_id' => $teamOneA->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => 'group-one', 'podium_group_manual' => true],
    ]);
    $winnerOneB = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $teamOneB->id,
        'placement' => 1,
        'username' => $teamOneB->username,
        'osu_id' => $teamOneB->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => 'group-one', 'podium_group_manual' => true],
    ]);
    $winnerTwoA = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $teamTwoA->id,
        'placement' => 1,
        'username' => $teamTwoA->username,
        'osu_id' => $teamTwoA->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => 'group-two', 'podium_group_manual' => true],
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.tournaments.podium.groups.store', $tournament), [
            'winner_ids' => [$winnerTwoA->id, $winnerOneB->id],
            'source_winner_id' => $winnerTwoA->id,
            'team_name' => 'Moved Team',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('podium_group_id', 'group-two')
        ->assertJson(fn ($json) => $json->whereType('fragment', 'string')->etc());

    expect(data_get($winnerOneB->fresh()->metadata, 'podium_group_id'))->toBe('group-two')
        ->and(data_get($winnerOneA->fresh()->metadata, 'podium_group_id'))->toBe('group-one')
        ->and(TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $teamOneB->id)
            ->firstOrFail()
            ->teammates()
            ->pluck('users.id')
            ->all())->toBe([$teamTwoA->id]);
});

test('admin podium grouping rejects members from different placements', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $first = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $firstUser->id,
        'placement' => 1,
        'username' => $firstUser->username,
        'osu_id' => $firstUser->osu_id,
        'gamemode' => 'osu',
    ]);
    $second = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $secondUser->id,
        'placement' => 2,
        'username' => $secondUser->username,
        'osu_id' => $secondUser->osu_id,
        'gamemode' => 'osu',
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.tournaments.podium.groups.store', $tournament), [
            'winner_ids' => [$first->id, $second->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('winner_ids');
});

test('admin can split selected podium members into a new same placement group', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 3,
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);
    $users = User::factory()->count(3)->create();
    [$userOne, $userTwo, $userThree] = $users;
    $groupId = 'original-team';

    $winnerOne = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $userOne->id,
        'placement' => 1,
        'username' => $userOne->username,
        'osu_id' => $userOne->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => $groupId, 'podium_group_manual' => true],
    ]);
    $winnerTwo = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $userTwo->id,
        'placement' => 1,
        'username' => $userTwo->username,
        'osu_id' => $userTwo->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => $groupId, 'podium_group_manual' => true],
    ]);
    $winnerThree = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $userThree->id,
        'placement' => 1,
        'username' => $userThree->username,
        'osu_id' => $userThree->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => $groupId, 'podium_group_manual' => true],
    ]);

    app(ParticipationPodiumBackfillService::class)->backfillTournamentGroup($tournament->id, $groupId);
    TournamentParticipationRecord::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('user_id', [$userOne->id, $userTwo->id, $userThree->id])
        ->update([
            'team_name' => 'Original Team Name',
            'seed' => 4,
        ]);
    /** @var Collection<int, TournamentParticipationRecord> $participationRecords */
    $participationRecords = TournamentParticipationRecord::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('user_id', [$userOne->id, $userTwo->id, $userThree->id])
        ->get();

    foreach ($participationRecords as $record) {
        $record->syncParticipationMatches([
            ['stage' => 'SF', 'result' => '6-3'],
        ]);
    }

    $this->actingAs($admin)
        ->postJson(route('admin.tournaments.podium.groups.new', $tournament), [
            'winner_ids' => [$winnerTwo->id, $winnerThree->id],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJson(fn ($json) => $json->whereType('podium_group_id', 'string')->whereType('fragment', 'string')->etc());

    $newGroupId = data_get($winnerTwo->fresh()->metadata, 'podium_group_id');

    expect($newGroupId)->not->toBe($groupId)
        ->and(data_get($winnerThree->fresh()->metadata, 'podium_group_id'))->toBe($newGroupId)
        ->and(data_get($winnerOne->fresh()->metadata, 'podium_group_id'))->toBe($groupId)
        ->and(TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $userTwo->id)
            ->firstOrFail()
            ->teammates()
            ->pluck('users.id')
            ->sort()
            ->values()
            ->all())->toBe([$userThree->id])
        ->and(TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $userTwo->id)
            ->firstOrFail()
            ->team_name)->toBeNull()
        ->and(TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $userTwo->id)
            ->firstOrFail()
            ->matches)->toBe([])
        ->and(TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $userTwo->id)
            ->firstOrFail()
            ->seed)->toBeNull();
});

test('non ended tournament review page does not render badge configuration', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'is_badge' => false,
        'tournament_start' => now()->addWeek(),
        'tournament_end' => now()->addMonth(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertOk();
    $response->assertDontSee('Badge Configuration');
});

test('stage editor renders drag handles and draggable cards', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertOk();
    $response->assertSee('data-format-stage-card', false);
    $response->assertSee('data-stage-drag-handle', false);
    $response->assertSee('draggable="true"', false);
    $response->assertSee('@dragstart.stop="startDrag(index, $event)"', false);
    $response->assertSee("!event.target.closest('[data-stage-drag-handle]')", false);
});

test('review page renders split date and optional time inputs for tournament dates', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->pending()->create([
        'registration_start' => '2026-02-01 12:00:00',
        'registration_end' => '2026-03-01 18:30:00',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertOk();
    $response->assertSee('data-admin-datetime-field="registration_start"', false);
    $response->assertSee('id="registration_start_date"', false);
    $response->assertSee('type="date"', false);
    $response->assertSee('type="time"', false);
    $response->assertSee('name="registration_start" value="2026-02-01T12:00"', false);
    $response->assertSee('timeInput.value ? `${dateInput.value}T${timeInput.value}` : dateInput.value', false);
    $response->assertSee('value="2026-03-01"', false);
    $response->assertSee('value="18:30"', false);
    $response->assertSee('normalizeAdminTournamentDateFields(form)', false);
});

test('badge status select only exposes approval states', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'is_badge' => true,
        'badge_status' => 'approved',
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertOk();
    $response->assertSee('value="approved"', false);
    $response->assertSee('value="pending"', false);
    $response->assertSee('value="rejected"', false);
    $response->assertDontSee('-- Not a badge tournament --');
});

// Test 16: Pending, approved and rejected tables use consistent styling
test('pending_approved_and_rejected_tables_use_consistent_styling', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->pending()->create();
    Tournament::factory()->approved()->create();
    Tournament::factory()->rejected()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending'));

    $response->assertStatus(200);
    // Verify neutral theme CSS classes are used (not emerald/red specific)
    $response->assertSee('admin-border'); // Neutral border
    $response->assertSee('admin-muted');  // Neutral text
});

test('queue_renders_sortable_date_headers_and_preserves_filters_in_sort_links', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->pending()->create([
        'title' => 'Sortable Header Cup',
        'modes' => ['osu'],
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', [
            'tab' => 'pending',
            'search' => 'Sortable',
            'modes' => ['osu'],
        ]));

    $response->assertOk();
    $response->assertSee('Reg Start');
    $response->assertSee('Tourney End');
    $response->assertSee('Last Edited');
    $response->assertDontSee('>Dates<', false);
    $response->assertDontSee('>Action<', false);
    $response->assertSee('sort=registration_start', false);
    $response->assertSee('sort=tournament_end', false);
    $response->assertSee('sort=updated_at', false);
    $response->assertSee('search=Sortable', false);
    $response->assertSee('modes%5B0%5D=osu', false);
    $response->assertDontSee('>Sort<', false);
});

test('queue_active_sort_header_uses_arrow_indicator', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->pending()->create();

    $ascending = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['sort' => 'registration_start', 'direction' => 'asc']));
    $descending = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['sort' => 'registration_start', 'direction' => 'desc']));

    $ascending->assertOk();
    $ascending->assertSee('↑', false);
    $descending->assertOk();
    $descending->assertSee('↓', false);
});

test('queue_title_replaces_action_button_and_host_links_to_public_profile', function () {
    $admin = User::factory()->admin()->create();
    $host = User::factory()->create([
        'osu_id' => 123456,
        'username' => 'QueueHost',
    ]);
    $tournament = Tournament::factory()->pending()->create([
        'title' => 'Linked Queue Cup',
        'host_osu_id' => $host->osu_id,
        'host_username' => $host->username,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending'));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.tournaments.show', $tournament).'"', false);
    $response->assertSee('Linked Queue Cup');
    $response->assertSee('href="'.route('users.show', $host).'"', false);
    $response->assertSee('target="_blank"', false);
    $response->assertSee('rel="noopener"', false);
    $response->assertDontSee('>Review<', false);
    $response->assertDontSee('>Action<', false);
});

test('queue_unresolved_host_name_remains_plain_text', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->pending()->create([
        'host_osu_id' => 987654,
        'host_username' => 'UnresolvedHost',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending'));

    $response->assertOk();
    $response->assertSee('UnresolvedHost');
    $response->assertDontSee('href="'.route('users.show', 987654).'"', false);
});

test('approved_and_rejected_tournaments_are_sorted_latest_edited_first', function () {
    $admin = User::factory()->admin()->create();

    $oldApproved = Tournament::factory()->approved()->create(['updated_at' => now()->subDays(4)]);
    $newApproved = Tournament::factory()->approved()->create(['updated_at' => now()->subDay()]);
    $oldRejected = Tournament::factory()->rejected()->create(['updated_at' => now()->subDays(3)]);
    $newRejected = Tournament::factory()->rejected()->create(['updated_at' => now()]);

    $approvedResponse = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['tab' => 'approved']));
    $rejectedResponse = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['tab' => 'rejected']));

    expect($approvedResponse->viewData('tournaments')->pluck('id')->all())
        ->toBe([$newApproved->id, $oldApproved->id]);
    expect($rejectedResponse->viewData('tournaments')->pluck('id')->all())
        ->toBe([$newRejected->id, $oldRejected->id]);
});

test('viewing_a_tournament_does_not_change_queue_order', function () {
    $admin = User::factory()->admin()->create();
    $laterUnread = Tournament::factory()->pending()->create([
        'registration_start' => now()->addDays(3),
        'updated_at' => now()->subDays(2),
        'viewed_at' => null,
    ]);
    $sooner = Tournament::factory()->pending()->create([
        'registration_start' => now()->addDay(),
        'updated_at' => now()->subDay(),
        'viewed_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $laterUnread));

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending'));

    expect($response->viewData('tournaments')->pluck('id')->all())
        ->toBe([$sooner->id, $laterUnread->id]);
});

// ============================================================================
// NEW TESTS: Search and Filter Functionality (TDD Phase 1 - RED)
// ============================================================================

// Test 17: Admin can search tournaments by name
test('admin_can_search_tournaments_by_name', function () {
    $admin = User::factory()->admin()->create();
    Tournament::factory()->create([
        'title' => 'Summer Tournament 2024',
        'status' => 'pending_review',
    ]);
    Tournament::factory()->create([
        'title' => 'Winter Cup',
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['search' => 'summer']));

    $response->assertStatus(200);
    $response->assertSee('Summer Tournament 2024');
    $response->assertDontSee('Winter Cup');

    expect($response->viewData('counts')['pending'])->toBe(1);
});

// Test 18: Admin can search tournaments by host username
test('admin_can_search_tournaments_by_host_username', function () {
    $admin = User::factory()->admin()->create();
    Tournament::factory()->create([
        'host_username' => 'best_host_ever',
        'status' => 'pending_review',
    ]);
    Tournament::factory()->create([
        'host_username' => 'another_host',
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['search' => 'best_host']));

    $response->assertStatus(200);
    $response->assertSee('best_host_ever');
    $response->assertDontSee('another_host');
});

// Test 19: Admin can filter tournaments by game mode
test('admin_can_filter_tournaments_by_game_mode', function () {
    $admin = User::factory()->admin()->create();
    Tournament::factory()->create([
        'modes' => ['osu'],
        'status' => 'pending_review',
    ]);
    Tournament::factory()->create([
        'modes' => ['mania'],
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['modes' => ['osu']]));

    $response->assertStatus(200);
    // Verify osu mode tournament is shown
    $response->assertSee('osu!');
    expect($response->viewData('counts')['pending'])->toBe(1);
});

test('admin_can_filter_queue_to_badge_tournaments_only', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->pending()->create([
        'title' => 'Badge Filter Cup',
        'is_badge' => true,
    ]);
    Tournament::factory()->pending()->create([
        'title' => 'Plain Filter Cup',
        'is_badge' => false,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['badge' => '1']));

    $response->assertOk();
    $response->assertSee('Badge Filter Cup');
    $response->assertDontSee('Plain Filter Cup');
    $response->assertSee('name="badge"', false);
    expect($response->viewData('badgeOnly'))->toBeTrue()
        ->and($response->viewData('counts')['pending'])->toBe(1);
});

test('approved_queue_can_filter_to_updated_tournaments_only', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->approved()->create([
        'title' => 'Updated Approved Cup',
        'viewed_at' => null,
    ]);
    Tournament::factory()->approved()->create([
        'title' => 'Viewed Approved Cup',
        'viewed_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', [
            'tab' => 'approved',
            'updated' => '1',
        ]));

    $response->assertOk();
    $response->assertSee('Updated Approved Cup');
    $response->assertDontSee('Viewed Approved Cup');
    $response->assertSee('name="updated"', false);
    expect($response->viewData('updatedOnly'))->toBeTrue()
        ->and($response->viewData('counts')['approved'])->toBe(1);
});

test('updated_filter_is_only_available_on_approved_queue_tab', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->pending()->create([
        'title' => 'Pending Updated Param Cup',
        'viewed_at' => null,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['updated' => '1']));

    $response->assertOk();
    $response->assertSee('Pending Updated Param Cup');
    $response->assertDontSee('name="updated"', false);
    expect($response->viewData('updatedOnly'))->toBeFalse();
});

test('approved_queue_can_combine_badge_and_updated_filters', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->approved()->create([
        'title' => 'Badge Updated Cup',
        'is_badge' => true,
        'viewed_at' => null,
    ]);
    Tournament::factory()->approved()->create([
        'title' => 'Badge Viewed Cup',
        'is_badge' => true,
        'viewed_at' => now(),
    ]);
    Tournament::factory()->approved()->create([
        'title' => 'Plain Updated Cup',
        'is_badge' => false,
        'viewed_at' => null,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', [
            'tab' => 'approved',
            'badge' => '1',
            'updated' => '1',
        ]));

    $response->assertOk();
    $response->assertSee('Badge Updated Cup');
    $response->assertDontSee('Badge Viewed Cup');
    $response->assertDontSee('Plain Updated Cup');
    $response->assertSee('badge=1', false);
    $response->assertSee('updated=1', false);
    expect($response->viewData('badgeOnly'))->toBeTrue()
        ->and($response->viewData('updatedOnly'))->toBeTrue()
        ->and($response->viewData('counts')['approved'])->toBe(1);
});

// Test 20: Admin can combine search and mode filters
test('admin_can_combine_search_and_mode_filters', function () {
    $admin = User::factory()->admin()->create();
    Tournament::factory()->create([
        'title' => 'Summer osu! Tournament',
        'modes' => ['osu'],
        'status' => 'pending_review',
    ]);
    Tournament::factory()->create([
        'title' => 'Summer mania Tournament',
        'modes' => ['mania'],
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', [
            'search' => 'summer',
            'modes' => ['osu'],
        ]));

    $response->assertStatus(200);
    $response->assertSee('Summer osu! Tournament');
    $response->assertDontSee('Summer mania Tournament');
    expect($response->viewData('counts')['pending'])->toBe(1);
});

// Test 21: Search is case insensitive
test('search_is_case_insensitive', function () {
    $admin = User::factory()->admin()->create();
    Tournament::factory()->create([
        'title' => 'Summer Championship 2024',
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['search' => 'SUMMER']));

    $response->assertStatus(200);
    $response->assertSee('Summer Championship 2024');
});

test('queue_filter_accepts_legacy_fruits_mode_alias', function () {
    $admin = User::factory()->admin()->create();

    Tournament::factory()->pending()->create([
        'title' => 'Catch Tournament',
        'modes' => ['catch'],
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.pending', ['modes' => ['fruits']]));

    $response->assertStatus(200);
    $response->assertSee('Catch Tournament');
    expect($response->viewData('selectedModes'))->toBe(['catch']);
});

test('reject_modal_posts_reason_field', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament));

    $response->assertOk();
    $response->assertSee('name="reason"', false);
    $response->assertDontSee('name="rejection_reason"', false);
});
