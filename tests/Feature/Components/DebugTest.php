<?php

use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;

test('debug relationships', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create(['username' => 'TestUser', 'osu_id' => 12345]);

    $staff = TournamentStaff::factory()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    // Load relationships - staff() returns User models directly, not TournamentStaff
    $loadedTournament = $tournament->load(['staff' => function ($query) {
        $query->orderBy('user_id');
    }]);

    // Check if staff exists
    expect($loadedTournament->staff)->toHaveCount(1);

    // Check if user is loaded (staff relationship returns User models)
    expect($loadedTournament->staff->first())->not->toBeNull();
    expect($loadedTournament->staff->first()->username)->toBe('TestUser');
});
