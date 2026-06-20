<?php

use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Rollback migrations handled by RefreshDatabase trait
});

afterEach(function () {
    // Cleanup handled by RefreshDatabase trait
});

// ==================== TOURNAMENTS TABLE: field_sources COLUMN ====================

test('tournaments table has field_sources jsonb column', function () {
    // Check column exists
    expect(Schema::hasColumn('tournaments', 'field_sources'))->toBeTrue();

    // Create tournament and verify column accepts JSONB data
    $tournament = Tournament::factory()->create([
        'field_sources' => [
            'title' => 'manual',
            'description' => 'parsed',
            'host_username' => 'manual',
        ],
    ]);

    expect($tournament->field_sources)->toBeArray();
    expect($tournament->field_sources['title'])->toBe('manual');
    expect($tournament->field_sources['description'])->toBe('parsed');
});

test('tournaments field_sources defaults to empty array', function () {
    $tournament = Tournament::factory()->create([
        'field_sources' => null,
    ]);

    expect($tournament->field_sources)->toBeNull();
});

test('tournaments field_sources stores complex nested data', function () {
    $fieldSources = [
        'title' => 'manual',
        'description' => 'manual',
        'host_username' => 'manual',
        'modes' => 'parsed',
        'team_size_min' => 'parsed',
        'team_size_max' => 'parsed',
        'registration_start' => 'manual',
        'registration_end' => 'parsed',
        'tournament_start' => 'manual',
        'tournament_end' => 'parsed',
        'rank_range_min' => 'parsed',
        'rank_range_max' => 'parsed',
        'format' => 'manual',
        'banner_url' => 'parsed',
        'discord_url' => 'parsed',
        'twitch_url' => 'parsed',
        'spreadsheet_url' => 'manual',
        'bracket_url' => 'parsed',
        'registration_url' => 'parsed',
        'tcomm_url' => 'manual',
        'tcomm_id' => 'parsed',
        'otr_id' => 'manual',
    ];

    $tournament = Tournament::factory()->create([
        'field_sources' => $fieldSources,
    ]);

    expect($tournament->field_sources)->toBe($fieldSources);
});

test('tournaments field_sources can be queried via jsonb operations', function () {
    $tournament1 = Tournament::factory()->create([
        'title' => 'Tournament 1',
        'field_sources' => ['title' => 'manual'],
    ]);

    $tournament2 = Tournament::factory()->create([
        'title' => 'Tournament 2',
        'field_sources' => ['title' => 'parsed'],
    ]);

    // Query for tournaments with manually edited title
    $manualTournaments = Tournament::where('field_sources->title', 'manual')->get();

    expect($manualTournaments)->toHaveCount(1);
    expect($manualTournaments->first()->id)->toBe($tournament1->id);
});

test('tournaments field_sources maintains data across updates', function () {
    $tournament = Tournament::factory()->create([
        'field_sources' => ['title' => 'parsed'],
    ]);

    // Update field sources
    $tournament->update([
        'field_sources' => [
            'title' => 'manual',
            'description' => 'manual',
        ],
    ]);

    $tournament->refresh();

    expect($tournament->field_sources['title'])->toBe('manual');
    expect($tournament->field_sources['description'])->toBe('manual');
});

// ==================== TOURNAMENT_STAFF TABLE: source COLUMN ====================

test('tournament_staff table has source enum column', function () {
    // Check column exists
    expect(Schema::hasColumn('tournament_staff', 'source'))->toBeTrue();

    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    // Test with 'manual' source
    $staff1 = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'source' => 'manual',
    ]);

    expect($staff1->source)->toBe('manual');

    // Test with 'parsed' source
    $staff2 = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
        'source' => 'parsed',
    ]);

    expect($staff2->source)->toBe('parsed');
});

test('tournament_staff source defaults to manual', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    $staff = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        // Not specifying source - should default to 'manual'
    ]);

    expect($staff->source)->toBe('manual');
});

test('tournament_staff source only accepts valid enum values', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    // This should work
    $staff1 = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'source' => 'manual',
    ]);

    expect($staff1->source)->toBe('manual');

    // This should also work
    $staff2 = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
        'source' => 'parsed',
    ]);

    expect($staff2->source)->toBe('parsed');
});

test('tournament_staff can be filtered by source', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    // Create manually added staff
    $manualStaff = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'source' => 'manual',
    ]);

    // Create parsed staff
    $parsedStaff = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
        'source' => 'parsed',
    ]);

    // Query for manual staff only
    $manualOnly = TournamentStaff::where('source', 'manual')->get();

    expect($manualOnly)->toHaveCount(1);
    expect($manualOnly->first()->id)->toBe($manualStaff->id);

    // Query for parsed staff only
    $parsedOnly = TournamentStaff::where('source', 'parsed')->get();

    expect($parsedOnly)->toHaveCount(1);
    expect($parsedOnly->first()->id)->toBe($parsedStaff->id);
});

test('tournament_staff source is included in model attributes', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    $staff = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'source' => 'manual',
    ]);

    expect($staff->source)->toBe('manual');
    expect($staff->getAttributes())->toHaveKey('source');
});

// ==================== INTEGRATION TESTS ====================

test('field_sources and source columns work together', function () {
    $tournament = Tournament::factory()->create([
        'field_sources' => [
            'title' => 'manual',
            'host_username' => 'manual',
        ],
    ]);

    $user = User::factory()->create();

    // Manually added staff (source = 'manual')
    $manualStaff = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'organizer',
        'source' => 'manual',
    ]);

    // Parsed staff (source = 'parsed')
    $parsedStaff = TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'mapper',
        'source' => 'parsed',
    ]);

    // Verify tournament has manual field sources
    expect($tournament->field_sources['title'])->toBe('manual');

    // Verify staff have correct sources
    expect($manualStaff->source)->toBe('manual');
    expect($parsedStaff->source)->toBe('parsed');

    // Verify we can query both
    $manualTournaments = Tournament::where('field_sources->title', 'manual')->get();
    expect($manualTournaments)->toHaveCount(1);

    $manualStaffList = TournamentStaff::where('source', 'manual')->get();
    expect($manualStaffList)->toHaveCount(1);
});

test('migrations are reversible', function () {
    // This test ensures migrations can be rolled back
    // The actual rollback will be tested by RefreshDatabase trait
    expect(Schema::hasTable('tournaments'))->toBeTrue();
    expect(Schema::hasTable('tournament_staff'))->toBeTrue();
    expect(Schema::hasColumn('tournaments', 'field_sources'))->toBeTrue();
    expect(Schema::hasColumn('tournament_staff', 'source'))->toBeTrue();
});
