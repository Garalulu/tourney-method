<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup for each test
});

test('tournament scope with staff sorted by role orders correctly', function () {
    $tournament = Tournament::factory()->create();

    // Create staff with different roles
    $commentator = User::factory()->create(['osu_id' => 1]);
    $organizer = User::factory()->create(['osu_id' => 2]);
    $mapper = User::factory()->create(['osu_id' => 3]);
    $mappooler = User::factory()->create(['osu_id' => 4]);

    $tournament->staff()->attach($commentator->id, ['role' => 'commentator', 'status' => 'approved']);
    $tournament->staff()->attach($organizer->id, ['role' => 'organizer', 'status' => 'approved']);
    $tournament->staff()->attach($mapper->id, ['role' => 'mapper', 'status' => 'approved']);
    $tournament->staff()->attach($mappooler->id, ['role' => 'mappooler', 'status' => 'approved']);

    // Load with sorted scope
    $tournamentWithSorted = Tournament::withStaffSortedByRole()->find($tournament->id);

    $staff = $tournamentWithSorted->staff;

    // Should be in priority order: organizer (2), mappooler (4), mapper (3), commentator (1)
    expect($staff->pluck('id')->toArray())->toBe([
        $organizer->id,
        $mappooler->id,
        $mapper->id,
        $commentator->id,
    ]);
});

test('scope with staff sorted by role includes all staff', function () {
    $tournament = Tournament::factory()->create();

    $user1 = User::factory()->create(['osu_id' => 1]);
    $user2 = User::factory()->create(['osu_id' => 2]);

    $tournament->staff()->attach($user1->id, ['role' => 'referee', 'status' => 'approved']);
    $tournament->staff()->attach($user2->id, ['role' => 'other', 'status' => 'approved']);

    $tournamentWithSorted = Tournament::withStaffSortedByRole()->find($tournament->id);

    expect($tournamentWithSorted->staff)->toHaveCount(2);
});

test('scope with staff sorted by role handles empty staff', function () {
    $tournament = Tournament::factory()->create();

    $tournamentWithSorted = Tournament::withStaffSortedByRole()->find($tournament->id);

    expect($tournamentWithSorted->staff)->toHaveCount(0);
});

test('scope with staff sorted by role handles lowest priority role', function () {
    $tournament = Tournament::factory()->create();

    $user = User::factory()->create(['osu_id' => 1]);

    // Attach with lowest priority role (other)
    $tournament->staff()->attach($user->id, ['role' => 'other', 'status' => 'approved']);

    $tournamentWithSorted = Tournament::withStaffSortedByRole()->find($tournament->id);

    // Should include the staff
    expect($tournamentWithSorted->staff)->toHaveCount(1);
    expect($tournamentWithSorted->staff->first()->pivot->role)->toBe('other');
});
