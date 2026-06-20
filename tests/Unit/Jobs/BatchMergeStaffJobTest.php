<?php

use App\Jobs\BatchMergeStaffJob;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BatchTransactionService;

/**
 * @param  array<int, array<string, mixed>>  $staffData
 * @param  array<int, int>  $userMapping
 */
function storeParseStaff(string $parseBatchId, Tournament $tournament, array $staffData, array $userMapping): void
{
    $transaction = app(BatchTransactionService::class);
    $transaction->storeTournamentStaff($parseBatchId, $tournament->id, $staffData);
    $transaction->mergeUserMapping($parseBatchId, $userMapping);
}

test('merges staff to tournaments from db backed parse state', function () {
    $tournament1 = Tournament::factory()->create();
    $tournament2 = Tournament::factory()->create();
    $user1 = User::factory()->create(['osu_id' => 100]);
    $user2 = User::factory()->create(['osu_id' => 200]);

    $parseBatchId = 'test-batch-merge';
    storeParseStaff($parseBatchId, $tournament1, [
        ['osu_id' => 100, 'role' => 'organizer'],
        ['osu_id' => 200, 'role' => 'mapper'],
    ], [100 => $user1->id, 200 => $user2->id]);
    storeParseStaff($parseBatchId, $tournament2, [
        ['osu_id' => 200, 'role' => 'referee'],
    ], [100 => $user1->id, 200 => $user2->id]);

    (new BatchMergeStaffJob($parseBatchId))->handle();

    expect($tournament1->staff()->count())->toBe(2);
    expect($tournament2->staff()->count())->toBe(1);
    expect($tournament1->staff()->where('user_id', $user1->id)->first()->pivot->role)->toBe('organizer');
});

test('updates tournament host from organizer role', function () {
    $tournament = Tournament::factory()->create([
        'host_osu_id' => null,
        'host_username' => null,
    ]);
    $user = User::factory()->create(['osu_id' => 999, 'username' => 'host_user']);

    $parseBatchId = 'test-batch-host';
    storeParseStaff($parseBatchId, $tournament, [
        ['osu_id' => 999, 'role' => 'organizer'],
    ], [999 => $user->id]);

    (new BatchMergeStaffJob($parseBatchId))->handle();

    $tournament->refresh();

    expect($tournament->host_osu_id)->toBe(999);
    expect($tournament->host_username)->toBe('host_user');
});

test('refreshes staff profile metadata after merge', function () {
    $tournament = Tournament::factory()->create([
        'modes' => ['taiko'],
    ]);
    $user = User::factory()->create([
        'osu_id' => 321,
        'main_mode' => null,
        'main_mode_source' => null,
    ]);

    $parseBatchId = 'test-batch-profile-metadata';
    storeParseStaff($parseBatchId, $tournament, [
        ['osu_id' => 321, 'role' => 'mapper'],
    ], [321 => $user->id]);

    (new BatchMergeStaffJob($parseBatchId))->handle();

    $user->refresh();

    expect($user->main_mode)->toBe('taiko');
    expect($user->main_mode_source)->toBe('auto_detected');
});

test('preserves existing manual staff roles during merge', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create(['osu_id' => 500]);

    $tournament->staff()->attach($user, [
        'role' => 'mapper',
        'status' => 'approved',
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'source' => 'manual',
    ]);

    $parseBatchId = 'test-batch-update';
    storeParseStaff($parseBatchId, $tournament, [
        ['osu_id' => 500, 'role' => 'organizer'],
    ], [500 => $user->id]);

    (new BatchMergeStaffJob($parseBatchId))->handle();

    $roles = $tournament->staff()->where('user_id', $user->id)->get()->pluck('pivot.role')->toArray();

    expect($roles)->toContain('mapper');
    expect($roles)->toContain('organizer');
});

test('skips staff if user_id not found in mapping', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create(['osu_id' => 888]);

    $parseBatchId = 'test-batch-skip';
    storeParseStaff($parseBatchId, $tournament, [
        ['osu_id' => 999, 'role' => 'organizer'],
        ['osu_id' => 888, 'role' => 'mapper'],
    ], [888 => $user->id]);

    (new BatchMergeStaffJob($parseBatchId))->handle();

    expect($tournament->staff()->count())->toBe(1);
    expect($tournament->staff()->first()->pivot->role)->toBe('mapper');
});

test('throws when parse batch is missing', function () {
    expect(fn () => (new BatchMergeStaffJob('missing-batch'))->handle())
        ->toThrow(Exception::class, 'Parse batch not found');
});

test('handles empty parse batch gracefully', function () {
    $parseBatchId = app(BatchTransactionService::class)->generateTransactionId();

    (new BatchMergeStaffJob($parseBatchId))->handle();

    expect(true)->toBeTrue();
});
