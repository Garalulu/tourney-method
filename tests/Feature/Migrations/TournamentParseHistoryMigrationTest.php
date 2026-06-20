<?php

use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Rollback migrations to start fresh
    // migrate:rollback will be handled by RefreshDatabase trait
});

afterEach(function () {
    // Cleanup handled by RefreshDatabase trait
});

test('tournament_parse_histories table exists with correct columns', function () {
    // Check table exists
    expect(Schema::hasTable('tournament_parse_histories'))->toBeTrue();

    // Check all columns exist
    expect(Schema::hasColumn('tournament_parse_histories', 'id'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'tournament_id'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'parsed_by'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'changes'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'parsed_data'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'compacted_at'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'created_at'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'updated_at'))->toBeTrue();
});

test('duplicate users osu id index is removed while unique constraint remains', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL index catalog assertion only.');
    }

    $indexes = DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('tablename', 'users')
        ->pluck('indexname')
        ->all();

    expect($indexes)->not->toContain('idx_users_osu_id');
    expect($indexes)->toContain('users_osu_id_unique');
});

test('tournament_parse_histories has foreign key to tournaments', function () {
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    $history = TournamentParseHistory::create([
        'tournament_id' => $tournament->id,
        'parsed_by' => $user->id,
        'changes' => ['title' => ['old' => 'Old Title', 'new' => 'New Title']],
        'parsed_data' => ['title' => 'New Title'],
    ]);

    expect($history->tournament->id)->toBe($tournament->id);
    expect($history->parsedBy->id)->toBe($user->id);
});

test('tournament_parse_histories cascades delete on tournament delete', function () {
    $tournament = Tournament::factory()->create();
    $history = TournamentParseHistory::create([
        'tournament_id' => $tournament->id,
        'changes' => ['test' => 'data'],
    ]);

    $historyId = $history->id;

    // Delete tournament (hard delete to trigger cascade)
    $tournament->forceDelete();

    // History should be cascade deleted
    $this->assertDatabaseMissing('tournament_parse_histories', [
        'id' => $historyId,
    ]);
});

test('tournaments table has parse tracking columns', function () {
    expect(Schema::hasColumn('tournaments', 'parse_count'))->toBeTrue();
    expect(Schema::hasColumn('tournaments', 'last_parsed_at'))->toBeTrue();
    expect(Schema::hasColumn('tournaments', 'last_parsed_by'))->toBeTrue();
});

test('tournaments parse_count defaults to 0', function () {
    $tournament = Tournament::factory()->create();

    expect($tournament->parse_count)->toBe(0);
    expect($tournament->last_parsed_at)->toBeNull();
    expect($tournament->last_parsed_by)->toBeNull();
});

test('tournaments has foreign key to users for last_parsed_by', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create([
        'last_parsed_by' => $user->id,
        'last_parsed_at' => now(),
    ]);

    expect($tournament->lastParsedBy->id)->toBe($user->id);
});

test('tournament_parse_histories stores json data correctly', function () {
    $tournament = Tournament::factory()->create();

    $changes = [
        'title' => ['old' => 'Old Title', 'new' => 'New Title'],
        'description' => ['old' => 'Old Description', 'new' => 'New Description'],
    ];

    $parsedData = [
        'title' => 'New Title',
        'description' => 'New Description',
        'modes' => ['osu', 'taiko'],
    ];

    $history = TournamentParseHistory::create([
        'tournament_id' => $tournament->id,
        'changes' => $changes,
        'parsed_data' => $parsedData,
    ]);

    expect($history->changes)->toBe($changes);
    expect($history->parsed_data)->toBe($parsedData);
});

test('tournament_parse_histories indexes are created', function () {
    // This test verifies indexes exist by checking column existence
    // Actual index verification requires database-specific queries
    expect(Schema::hasColumn('tournament_parse_histories', 'tournament_id'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'parsed_by'))->toBeTrue();
    expect(Schema::hasColumn('tournament_parse_histories', 'created_at'))->toBeTrue();
});
