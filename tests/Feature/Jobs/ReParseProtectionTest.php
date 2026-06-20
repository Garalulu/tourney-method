<?php

use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('re-parse preserves fields marked as manual', function () {
    // Create tournament with some fields
    $tournament = Tournament::factory()->create([
        'title' => 'Original Title',
        'description' => 'Original Description',
        'host_osu_id' => 12345,
        'rank_range_min' => 1000,
        'rank_range_max' => 50000,
    ]);

    // Mark some fields as manual (admin edited)
    $tournament->update([
        'field_sources' => [
            'title' => 'manual',
            'description' => 'manual',
        ],
    ]);

    // Simulate re-parse with different data
    $parsedData = [
        'title' => 'Parsed Title', // Should NOT update (manual)
        'description' => 'Parsed Description', // Should NOT update (manual)
        'host_osu_id' => 99999, // Should update (not marked as manual)
        'rank_range_min' => 5000, // Should update (not marked as manual)
        'rank_range_max' => 100000, // Should update (not marked as manual)
        'forum_topic_id' => $tournament->forum_topic_id,
        'forum_post_url' => $tournament->forum_post_url,
        'modes' => $tournament->modes,
    ];

    $tournament->updateFromParsedData($parsedData);

    // Refresh from database
    $tournament->refresh();

    // Verify manual fields are preserved
    expect($tournament->title)->toBe('Original Title');
    expect($tournament->description)->toBe('Original Description');

    // Verify non-manual fields are updated
    expect($tournament->host_osu_id)->toBe(99999);
    expect($tournament->rank_range_min)->toBe(5000);
    expect($tournament->rank_range_max)->toBe(100000);

    // Verify field_sources tracks what was parsed
    expect($tournament->field_sources['title'])->toBe('manual'); // Still manual
    expect($tournament->field_sources['description'])->toBe('manual'); // Still manual
    expect($tournament->field_sources['host_osu_id'])->toBe('parsed'); // Now marked as parsed
    expect($tournament->field_sources['rank_range_min'])->toBe('parsed');
    expect($tournament->field_sources['rank_range_max'])->toBe('parsed');
});

test('re-parse skips staff with source=manual', function () {
    $tournament = Tournament::factory()->create();

    // Create users
    $manualUser = User::factory()->create(['username' => 'ManualUser', 'osu_id' => 10000]);
    $parsedUser = User::factory()->create(['username' => 'ParsedUser', 'osu_id' => 20000]);
    $manualRoleUser = User::factory()->create(['username' => 'MixedUser', 'osu_id' => 30000]);
    $newUser = User::factory()->create(['username' => 'NewUser', 'osu_id' => 40000]); // FIX: Create user first

    // Add staff with different sources
    TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $manualUser->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual', // Admin added manually - should be preserved
    ]);

    TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $parsedUser->id,
        'role' => 'mapper',
        'status' => 'approved',
        'source' => 'parsed', // From parser - can be removed
    ]);

    // User with mixed roles - one manual, one parsed
    TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $manualRoleUser->id,
        'role' => 'referee',
        'status' => 'approved',
        'source' => 'manual', // This role should be preserved
    ]);

    TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $manualRoleUser->id,
        'role' => 'streamer',
        'status' => 'approved',
        'source' => 'parsed', // This role can be removed
    ]);

    // Simulate re-parse with different staff
    $parsedStaff = [
        ['osu_id' => 20000, 'username' => 'ParsedUser', 'role' => 'mapper'], // Exists, should stay
        ['osu_id' => 30000, 'username' => 'MixedUser', 'role' => 'streamer'], // Exists, should stay
        ['osu_id' => 40000, 'username' => 'NewUser', 'role' => 'commentator'], // New, should be added
        // Note: ManualUser (organizer) and MixedUser (referee) NOT in parsed data
        // They should NOT be removed
    ];

    $result = $tournament->mergeStaff($parsedStaff);

    // Verify manual staff were NOT removed
    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $manualUser->id)
        ->where('role', 'organizer')
        ->where('source', 'manual')
        ->exists())->toBeTrue();

    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $manualRoleUser->id)
        ->where('role', 'referee')
        ->where('source', 'manual')
        ->exists())->toBeTrue();

    // Verify parsed staff were updated/added as normal
    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $parsedUser->id)
        ->where('role', 'mapper')
        ->exists())->toBeTrue();

    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $manualRoleUser->id)
        ->where('role', 'streamer')
        ->exists())->toBeTrue();

    // Verify new staff was added
    expect($newUser)->not->toBeNull();
    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $newUser->id)
        ->where('role', 'commentator')
        ->exists())->toBeTrue();
});

test('detect_reparse_conflicts identifies manual fields that would change', function () {
    $tournament = Tournament::factory()->create([
        'title' => 'Manual Title',
        'description' => 'Manual Description',
        'rank_range_min' => 1000,
    ]);

    // Mark fields as manual
    $tournament->update([
        'field_sources' => [
            'title' => 'manual',
            'description' => 'manual',
        ],
    ]);

    // Simulate parsed data that would conflict
    $parsedData = [
        'title' => 'Different Title', // Conflict!
        'description' => 'Different Description', // Conflict!
        'rank_range_min' => 2000, // No conflict (not manual)
    ];

    $conflicts = $tournament->detectReparseConflicts($parsedData);

    // Should detect 2 conflicts (title and description)
    expect($conflicts)->toHaveCount(2);

    // Verify conflict structure
    expect($conflicts['title'])->not->toBeNull();
    expect($conflicts['title']['current'])->toBe('Manual Title');
    expect($conflicts['title']['parsed'])->toBe('Different Title');
    expect($conflicts['title']['source'])->toBe('manual');

    expect($conflicts['description'])->not->toBeNull();
    expect($conflicts['description']['current'])->toBe('Manual Description');
    expect($conflicts['description']['parsed'])->toBe('Different Description');
    expect($conflicts['description']['source'])->toBe('manual');

    // rank_range_min should not be in conflicts
    expect($conflicts['rank_range_min'] ?? null)->toBeNull();
});

test('re-parse respects field_sources when updating', function () {
    $tournament = Tournament::factory()->create([
        'title' => 'Original',
        'description' => 'Original Desc',
        'host_osu_id' => 11111,
    ]);

    // Mix of manual and non-manual fields
    $tournament->update([
        'field_sources' => [
            'title' => 'manual',
            'description' => 'manual',
        ],
    ]);

    $parsedData = [
        'title' => 'New Title', // Should be skipped
        'description' => 'New Desc', // Should be skipped
        'host_osu_id' => 22222, // Should be updated
    ];

    $tournament->updateFromParsedData($parsedData);
    $tournament->refresh();

    expect($tournament->title)->toBe('Original'); // Preserved
    expect($tournament->description)->toBe('Original Desc'); // Preserved
    expect($tournament->host_osu_id)->toBe(22222); // Updated

    // Verify field_sources
    expect($tournament->field_sources['title'])->toBe('manual'); // Still manual
    expect($tournament->field_sources['description'])->toBe('manual'); // Still manual
    expect($tournament->field_sources['host_osu_id'])->toBe('parsed'); // Marked as parsed
});

test('merge_staff_with_history respects source=manual', function () {
    $tournament = Tournament::factory()->create();
    $adminUser = User::factory()->create(['username' => 'AdminUser', 'osu_id' => 9999]); // Create valid user for $parsedBy

    $manualUser = User::factory()->create(['username' => 'ManualUser', 'osu_id' => 5000]);
    $otherUser = User::factory()->create(['username' => 'OtherUser', 'osu_id' => 6000]); // FIX: Create user in factory

    // Add staff with source=manual
    TournamentStaff::create([
        'tournament_id' => $tournament->id,
        'user_id' => $manualUser->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    // Parsed data includes otherUser
    $parsedStaff = [
        ['osu_id' => 6000, 'username' => 'OtherUser', 'role' => 'mapper'],
    ];

    // Merge with history - pass valid user ID for $parsedBy
    $result = $tournament->mergeStaffWithHistory($parsedStaff, 'forum', [], $adminUser->id);

    // Verify manual staff was NOT removed
    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $manualUser->id)
        ->where('role', 'organizer')
        ->where('source', 'manual')
        ->exists())->toBeTrue();

    // Verify new staff was added
    expect($otherUser)->not->toBeNull();
    expect(TournamentStaff::where('tournament_id', $tournament->id)
        ->where('user_id', $otherUser->id)
        ->where('role', 'mapper')
        ->exists())->toBeTrue();
});

test('empty field_sources allows all updates', function () {
    $tournament = Tournament::factory()->create([
        'title' => 'Original Title',
        'description' => 'Original Desc',
    ]);

    // No field_sources set (null or empty)
    expect($tournament->field_sources)->toBeEmpty();

    $parsedData = [
        'title' => 'New Title',
        'description' => 'New Desc',
    ];

    $tournament->updateFromParsedData($parsedData);
    $tournament->refresh();

    // Both should be updated (nothing protected)
    expect($tournament->title)->toBe('New Title');
    expect($tournament->description)->toBe('New Desc');

    // field_sources should track what was parsed
    expect($tournament->field_sources['title'])->toBe('parsed');
    expect($tournament->field_sources['description'])->toBe('parsed');
});

test('parser owned fields fill blank create values while preserving manually cleared ranks', function () {
    $tournament = Tournament::factory()->create([
        'title' => 'Admin seed title',
        'forum_topic_id' => 904607,
        'vs_size' => null,
        'team_size_min' => null,
        'team_size_max' => null,
        'rank_range_min' => null,
        'rank_range_max' => null,
        'is_badge' => false,
        'is_bws' => false,
        'discord_url' => null,
        'field_sources' => [
            'title' => 'manual',
            'modes' => 'manual',
            'forum_post_url' => 'manual',
            'vs_size' => 'manual',
            'team_size_min' => 'manual',
            'team_size_max' => 'manual',
            'rank_range_min' => 'manual',
            'rank_range_max' => 'manual',
            'is_badge' => 'manual',
            'is_bws' => 'manual',
            'discord_url' => 'manual',
        ],
    ]);

    $tournament->updateFromParsedData([
        'title' => 'Parsed title',
        'vs_size' => 2,
        'team_size_min' => 3,
        'team_size_max' => 4,
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
        'is_badge' => true,
        'is_bws' => true,
        'discord_url' => 'https://discord.gg/cjUkraA',
    ]);

    $tournament->refresh();

    expect($tournament->title)->toBe('Admin seed title');
    expect($tournament->vs_size)->toBe(2);
    expect($tournament->team_size_min)->toBe(3);
    expect($tournament->team_size_max)->toBe(4);
    expect($tournament->rank_range_min)->toBeNull();
    expect($tournament->rank_range_max)->toBeNull();
    expect($tournament->is_badge)->toBeTrue();
    expect($tournament->is_bws)->toBeTrue();
    expect($tournament->discord_url)->toBe('https://discord.gg/cjUkraA');
    expect($tournament->field_sources['title'])->toBe('manual');
    expect($tournament->field_sources['vs_size'])->toBe('parsed');
    expect($tournament->field_sources['rank_range_min'])->toBe('manual');
    expect($tournament->field_sources['rank_range_max'])->toBe('manual');
    expect($tournament->field_sources['is_bws'])->toBe('parsed');
});
