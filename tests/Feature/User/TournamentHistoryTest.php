<?php

use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tournament history tab shows player participation', function () {
    $user = User::factory()->create([
        'username' => 'playeruser',
        'main_mode' => 'osu',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Player Tournament',
        'status' => 'approved',
        'tournament_start' => now()->subDays(10),
        'tournament_end' => now()->subDays(5),
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'commentator',
        'status' => 'approved',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);
    $response->assertSee('Player Tournament');
});

test('tournament history shows staff roles', function () {
    $user = User::factory()->create([
        'username' => 'staffuser',
        'main_mode' => 'osu',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Staff Tournament',
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'referee',
        'status' => 'approved',
        'notes' => 'Grand finals',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);
    $response->assertSee('Staff Tournament');
    $response->assertSee('Referee');
});

test('history sorted by date most recent first', function () {
    $user = User::factory()->create([
        'username' => 'sortuser',
        'main_mode' => 'osu',
    ]);

    $oldTournament = Tournament::factory()->create([
        'title' => 'Old Tournament',
        'status' => 'approved',
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonths(2)->addDays(7),
    ]);

    $newTournament = Tournament::factory()->create([
        'title' => 'New Tournament',
        'status' => 'approved',
        'tournament_start' => now()->subWeek(),
        'tournament_end' => now()->subDays(3),
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $oldTournament->id,
        'role' => 'referee',
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $newTournament->id,
        'role' => 'commentator',
        'status' => 'approved',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);

    $content = $response->getContent();
    $newPos = strpos($content, 'New Tournament');
    $oldPos = strpos($content, 'Old Tournament');

    expect($newPos)->toBeLessThan($oldPos);
});

test('empty state: No tournament history yet', function () {
    $user = User::factory()->create([
        'username' => 'emptyuser',
        'main_mode' => 'osu',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);
    $response->assertSee('No Staff Roles');
});

test('pending staff roles are not displayed', function () {
    $user = User::factory()->create([
        'username' => 'staffuser2',
        'main_mode' => 'osu',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'mapper',
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'streamer',
        'status' => 'pending',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);
    $response->assertSee('Mapper');
    $response->assertDontSee('>Streamer</span>', false);
});

test('multiple roles in same tournament are grouped', function () {
    $user = User::factory()->create([
        'username' => 'multirole',
        'main_mode' => 'osu',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Multi Role Tournament',
        'status' => 'approved',
    ]);

    // Create multiple roles for same tournament
    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'commentator',
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'mappooler',
        'status' => 'approved',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);

    // Tournament title should appear only once
    $content = $response->getContent();
    $tournamentCount = substr_count($content, 'Multi Role Tournament');
    expect($tournamentCount)->toBe(1); // Not 3

    // Should show primary role (organizer has highest priority)
    $response->assertSee('Organizer');

    // Should show +2 more indicator
    $response->assertSee('+2');

    // Should show all roles in tooltip
    $response->assertSee('Organizer');
    $response->assertSee('Commentator');
    $response->assertSee('Mappooler');
});

test('role priority order is correct', function () {
    $user = User::factory()->create([
        'username' => 'prioritytest',
        'main_mode' => 'osu',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Priority Tournament',
        'status' => 'approved',
    ]);

    // Create roles with different priorities
    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'commentator', // Lower priority
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'organizer', // Highest priority
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'mapper', // Medium priority
        'status' => 'approved',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);

    // Organizer should be shown as primary role (highest priority)
    $content = $response->getContent();
    expect($content)->toContain('Organizer');
});

test('multiple staff roles render in canonical priority order', function () {
    $user = User::factory()->create([
        'username' => 'canonicalorder',
        'main_mode' => 'osu',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Canonical Order Tournament',
        'status' => 'approved',
    ]);

    $scrambledRoles = [
        'commentator',
        'referee',
        'other',
        'organizer',
        'streamer',
        'gfx',
        'mapper',
        'sheeter',
        'mappooler',
        'playtester',
    ];

    foreach ($scrambledRoles as $role) {
        TournamentStaff::factory()->create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'role' => $role,
            'status' => 'approved',
        ]);
    }

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);

    $content = $response->getContent();
    $section = substr($content, strpos($content, 'Canonical Order Tournament'));
    $expectedLabels = collect([
        'organizer',
        'mappooler',
        'playtester',
        'mapper',
        'gfx',
        'sheeter',
        'referee',
        'streamer',
        'commentator',
        'other',
    ])->map(fn (string $role): string => __('tournaments.staffs.'.$role))->all();

    $lastPosition = -1;
    foreach ($expectedLabels as $label) {
        $position = strpos($section, $label);

        expect($position)->not->toBeFalse();
        expect($position)->toBeGreaterThan($lastPosition);

        $lastPosition = $position;
    }
});

test('only approved tournaments with approved roles are shown', function () {
    $user = User::factory()->create([
        'username' => 'approvedonly',
        'main_mode' => 'osu',
    ]);

    $approvedTournament = Tournament::factory()->create([
        'title' => 'Approved Tournament',
        'status' => 'approved',
    ]);

    $pendingTournament = Tournament::factory()->create([
        'title' => 'Pending Tournament',
        'status' => 'pending',
    ]);

    // Approved role in approved tournament
    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $approvedTournament->id,
        'role' => 'referee',
        'status' => 'approved',
    ]);

    // Pending role in approved tournament (should not show)
    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $approvedTournament->id,
        'role' => 'streamer',
        'status' => 'pending',
    ]);

    // Approved role in pending tournament (should not show because tournament is pending)
    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $pendingTournament->id,
        'role' => 'mapper',
        'status' => 'approved',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);

    // Should show approved tournament
    $response->assertSee('Approved Tournament');
    $response->assertSee('Referee');

    // Should not show pending roles
    $response->assertDontSee('>Streamer</span>', false);

    // Should not show pending tournament
    $response->assertDontSee('Pending Tournament');
    $response->assertDontSee('>Custom Mapper</span>', false);
});
