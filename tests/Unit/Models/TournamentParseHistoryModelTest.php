<?php

use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\User;

beforeEach(function () {
    // Fresh database for each test
});

test('tournament has parse histories relationship', function () {
    $tournament = Tournament::factory()->create();
    $history = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
    ]);

    expect($tournament->parseHistories)->toHaveCount(1);
    expect($tournament->parseHistories->first()->id)->toBe($history->id);
});

test('tournament has last parsed by relationship', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create([
        'last_parsed_by' => $user->id,
        'last_parsed_at' => now(),
    ]);

    expect($tournament->lastParsedBy->id)->toBe($user->id);
});

test('updateFromParsedData updates parsed fields', function () {
    $tournament = Tournament::factory()->create([
        'title' => 'Old Title',
        'description' => 'Old Description',
        'modes' => ['osu'],
        'status' => 'approved',
    ]);

    $parsedData = [
        'title' => 'New Title',
        'description' => 'New Description',
        'modes' => ['osu', 'taiko'],
        'is_badge' => true,
    ];

    $history = $tournament->updateFromParsedData($parsedData);

    // Verify fields were updated
    expect($tournament->fresh()->title)->toBe('New Title');
    expect($tournament->fresh()->description)->toBe('New Description');
    expect($tournament->fresh()->modes)->toBe([
        ['mode' => 'osu', 'key_count' => null],
        ['mode' => 'taiko', 'key_count' => null],
    ]);
    expect($tournament->fresh()->is_badge)->toBeTrue();

    // Verify status was preserved
    expect($tournament->fresh()->status)->toBe('approved');

    // Verify parse history was created
    expect($history->tournament_id)->toBe($tournament->id);
    expect($history->changes)->toHaveKey('title');
    expect($history->changes)->toHaveKey('description');
    expect($history->changes)->toHaveKey('modes');
});

test('updateFromParsedData preserves protected fields', function () {
    $admin = User::factory()->create();
    $tournament = Tournament::factory()->create([
        'status' => 'approved',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now()->subDay(),
        'rejection_reason' => 'Test rejection',
    ]);

    $parsedData = [
        'title' => 'New Title',
        'status' => 'pending_review', // Should be ignored
        'reviewed_by' => 999, // Should be ignored
        'rejection_reason' => 'New reason', // Should be ignored
    ];

    $tournament->updateFromParsedData($parsedData);

    // Verify protected fields were preserved
    expect($tournament->fresh()->status)->toBe('approved');
    expect($tournament->fresh()->reviewed_by)->toBe($admin->id);
    expect($tournament->fresh()->rejection_reason)->toBe('Test rejection');
});

test('updateFromParsedData increments parse count', function () {
    $tournament = Tournament::factory()->create([
        'parse_count' => 0,
    ]);

    $parsedData = ['title' => 'New Title'];

    $tournament->updateFromParsedData($parsedData);
    expect($tournament->fresh()->parse_count)->toBe(1);

    $tournament->updateFromParsedData($parsedData);
    expect($tournament->fresh()->parse_count)->toBe(2);
});

test('updateFromParsedData tracks last parsed by user', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    $parsedData = ['title' => 'New Title'];

    $tournament->updateFromParsedData($parsedData, $user->id);

    expect($tournament->fresh()->last_parsed_by)->toBe($user->id);
    expect($tournament->fresh()->last_parsed_at)->not->toBeNull();
});

test('updateFromParsedData creates parse history with changes', function () {
    $tournament = Tournament::factory()->create([
        'title' => 'Old Title',
        'modes' => ['osu'],
    ]);

    $parsedData = [
        'title' => 'New Title',
        'modes' => ['taiko'],
        'description' => 'New Description',
    ];

    $history = $tournament->updateFromParsedData($parsedData);

    expect($history->changes['title']['old'])->toBe('Old Title');
    expect($history->changes['title']['new'])->toBe('New Title');
    expect($history->changes['modes']['old'])->toBe([['mode' => 'osu', 'key_count' => null]]);
    expect($history->changes['modes']['new'])->toBe(['taiko']);
});

test('updateFromParsedData does not track unchanged fields', function () {
    $tournament = Tournament::factory()->create([
        'title' => 'Same Title',
        'is_badge' => false,
    ]);

    $parsedData = [
        'title' => 'Same Title', // Unchanged
        'is_badge' => false, // Unchanged
        'modes' => ['taiko'], // Changed
    ];

    $history = $tournament->updateFromParsedData($parsedData);

    expect($history->changes)->not->toHaveKey('title');
    expect($history->changes)->not->toHaveKey('is_badge');
    expect($history->changes)->toHaveKey('modes');
});

test('updateFromParsedData clears parsed rank bounds for explicit open rank', function () {
    $tournament = Tournament::factory()->create([
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
        'field_sources' => [
            'rank_range_min' => 'parsed',
            'rank_range_max' => 'parsed',
        ],
    ]);

    $history = $tournament->updateFromParsedData([
        'rank_range_min' => null,
        'rank_range_max' => null,
        'rank_range_is_open' => true,
    ]);

    $tournament->refresh();

    expect($tournament->rank_range_min)->toBeNull();
    expect($tournament->rank_range_max)->toBeNull();
    expect($history->changes)->toHaveKeys(['rank_range_min', 'rank_range_max']);
});

test('updateFromParsedData does not clear rank bounds for missing rank information', function () {
    $tournament = Tournament::factory()->create([
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
        'field_sources' => [
            'rank_range_min' => 'parsed',
            'rank_range_max' => 'parsed',
        ],
    ]);

    $history = $tournament->updateFromParsedData([
        'rank_range_min' => null,
        'rank_range_max' => null,
        'rank_range_is_open' => false,
    ]);

    $tournament->refresh();

    expect($tournament->rank_range_min)->toBe(1000);
    expect($tournament->rank_range_max)->toBe(10000);
    expect($history->changes)->not->toHaveKeys(['rank_range_min', 'rank_range_max']);
});

test('updateFromParsedData preserves manually cleared rank bounds', function () {
    $tournament = Tournament::factory()->create([
        'rank_range_min' => null,
        'rank_range_max' => null,
        'field_sources' => [
            'rank_range_min' => 'manual',
            'rank_range_max' => 'manual',
        ],
    ]);

    $tournament->updateFromParsedData([
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
    ]);

    $tournament->refresh();

    expect($tournament->rank_range_min)->toBeNull();
    expect($tournament->rank_range_max)->toBeNull();
    expect($tournament->field_sources['rank_range_min'])->toBe('manual');
    expect($tournament->field_sources['rank_range_max'])->toBe('manual');
});

test('updateFromParsedData preserves manual rank bounds during open rank reparse', function () {
    $tournament = Tournament::factory()->create([
        'rank_range_min' => 5000,
        'rank_range_max' => 50000,
        'field_sources' => [
            'rank_range_min' => 'manual',
            'rank_range_max' => 'manual',
        ],
    ]);

    $tournament->updateFromParsedData([
        'rank_range_min' => null,
        'rank_range_max' => null,
        'rank_range_is_open' => true,
    ]);

    $tournament->refresh();

    expect($tournament->rank_range_min)->toBe(5000);
    expect($tournament->rank_range_max)->toBe(50000);
    expect($tournament->field_sources['rank_range_min'])->toBe('manual');
    expect($tournament->field_sources['rank_range_max'])->toBe('manual');
});

test('mergeStaff adds new staff members', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'organizer'],
        ['osu_id' => 456, 'username' => 'user2', 'role' => 'referee'],
    ];

    $result = $tournament->mergeStaff($parsedStaff);

    expect($result['added'])->toBe(2);
    expect($result['updated'])->toBe(0);
    expect($tournament->staff)->toHaveCount(2);
});

test('mergeStaff updates existing staff roles', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create(['osu_id' => 123]);

    // Add staff as organizer (from parsing, so source='parsed')
    $tournament->staff()->attach($user->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'parsed', // Mark as parsed so it can be removed during re-parse
    ]);

    // Update to referee
    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user', 'role' => 'referee'],
    ];

    $result = $tournament->mergeStaff($parsedStaff);

    // Multi-role support: Role change = remove old + add new
    expect($result['added'])->toBe(1);
    expect($result['removed'])->toBe(1);

    $tournament->refresh();
    $staffPivot = $tournament->staff()->where('user_id', $user->id)->first()->pivot;
    expect($staffPivot->role)->toBe('referee');
});

test('mergeStaff removes parsed staff but protects manual staff', function () {
    $tournament = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    // Add staff as parsed (simulating previous parse)
    $tournament->staff()->attach($user1->id, ['role' => 'organizer', 'status' => 'approved', 'source' => 'parsed']);
    $tournament->staff()->attach($user2->id, ['role' => 'referee', 'status' => 'approved', 'source' => 'parsed']);

    // Parse only user1 with updated role
    $parsedStaff = [
        ['osu_id' => 123, 'username' => 'user1', 'role' => 'mapper'],
    ];

    $result = $tournament->mergeStaff($parsedStaff);

    // Multi-role support: user1 role changed (removed organizer, added mapper), user2 removed
    expect($result['added'])->toBe(1); // mapper added
    expect($result['removed'])->toBe(2); // organizer and referee removed

    // Note: In the new multi-role model, staff not in parsed data are removed
    // This is expected behavior - mergeStaff() replaces all staff with parsed data
    expect($tournament->staff)->toHaveCount(1);

    $tournament->refresh();
    $user1Staff = $tournament->staff()->where('user_id', $user1->id)->first();
    expect($user1Staff->pivot->role)->toBe('mapper');
});

test('mergeStaff skips users not found in database', function () {
    $tournament = Tournament::factory()->create();

    $parsedStaff = [
        ['osu_id' => 999, 'username' => 'nonexistent', 'role' => 'organizer'],
    ];

    $result = $tournament->mergeStaff($parsedStaff);

    expect($result['added'])->toBe(0);
    expect($result['updated'])->toBe(0);
    expect($tournament->staff)->toHaveCount(0);
});

test('mergeStaff handles empty staff array', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create(['osu_id' => 123]);

    // Add staff from parsing (not manual)
    $tournament->staff()->attach($user->id, ['role' => 'organizer', 'status' => 'approved', 'source' => 'parsed']);

    // Merge empty array - removes all parsed staff (but not manual)
    $result = $tournament->mergeStaff([]);

    expect($result['added'])->toBe(0);
    expect($result['removed'])->toBe(1); // Staff removed
    expect($tournament->staff)->toHaveCount(0); // All staff removed
});

test('parseHistories orders by created_at desc', function () {
    $tournament = Tournament::factory()->create();

    $history1 = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'created_at' => now()->subDays(2),
    ]);

    $history2 = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'created_at' => now()->subDay(),
    ]);

    $history3 = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'created_at' => now(),
    ]);

    $histories = $tournament->parseHistories;

    expect($histories->first()->id)->toBe($history3->id);
    expect($histories->last()->id)->toBe($history1->id);
});

test('tournament parse count defaults to 0', function () {
    $tournament = Tournament::factory()->create();

    expect($tournament->parse_count)->toBe(0);
    expect($tournament->last_parsed_at)->toBeNull();
    expect($tournament->last_parsed_by)->toBeNull();
});
