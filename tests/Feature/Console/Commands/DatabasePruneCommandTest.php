<?php

use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $overrides
 */
function createParseHistory(Tournament $tournament, int $daysAgo, array $overrides = []): TournamentParseHistory
{
    return TournamentParseHistory::factory()->create(array_merge([
        'tournament_id' => $tournament->id,
        'created_at' => now()->subDays($daysAgo),
        'updated_at' => now()->subDays($daysAgo),
        'parsed_at' => now()->subDays($daysAgo),
        'changes' => [
            'description' => [
                'old' => str_repeat('old description ', 100),
                'new' => str_repeat('new description ', 100),
                'diff_html' => '<del>old</del><ins>new</ins>',
            ],
            'title' => [
                'old' => 'Old title',
                'new' => 'New title',
                'diff_html' => '<del>Old</del><ins>New</ins>',
            ],
        ],
        'parsed_data' => [
            'title' => 'New title',
            'description' => str_repeat('full parsed description ', 100),
            'modes' => ['osu'],
        ],
    ], $overrides));
}

test('database prune dry run reports counts without changing data', function () {
    $tournament = Tournament::factory()->create();

    for ($i = 0; $i < 7; $i++) {
        createParseHistory($tournament, 40 + $i);
    }

    DB::table('job_batches')->insert([
        'id' => 'completed-old',
        'name' => 'Old completed batch',
        'total_jobs' => 1,
        'pending_jobs' => 0,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => null,
        'created_at' => now()->subDays(20)->getTimestamp(),
        'finished_at' => now()->subDays(15)->getTimestamp(),
        'cancelled_at' => null,
    ]);

    AdminAuditLog::factory()->create([
        'created_at' => now()->subDays(200),
    ]);

    $this->artisan('maintenance:database-prune --dry-run')
        ->expectsOutput('Database prune summary')
        ->expectsOutput('Parse histories to delete: 2')
        ->expectsOutput('Old kept parse histories to compact: 5')
        ->expectsOutput('Completed job batches to prune: 1')
        ->expectsOutput('Admin audit logs older than 180 days retained for now: 1')
        ->expectsOutput('No data changed.')
        ->assertSuccessful();

    expect(TournamentParseHistory::query()->count())->toBe(7);
    expect(TournamentParseHistory::query()->whereNotNull('compacted_at')->count())->toBe(0);
    expect(DB::table('job_batches')->count())->toBe(1);
});

test('database prune force deletes old histories outside latest five and prunes completed batches', function () {
    $tournament = Tournament::factory()->create();
    $kept = [];

    for ($i = 0; $i < 7; $i++) {
        $history = createParseHistory($tournament, 40 + $i);

        if ($i < 4) {
            $kept[] = $history->id;
        }
    }

    $recent = createParseHistory($tournament, 5);

    DB::table('job_batches')->insert([
        [
            'id' => 'completed-old',
            'name' => 'Old completed batch',
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => null,
            'created_at' => now()->subDays(20)->getTimestamp(),
            'finished_at' => now()->subDays(15)->getTimestamp(),
            'cancelled_at' => null,
        ],
        [
            'id' => 'unfinished-old',
            'name' => 'Old unfinished batch',
            'total_jobs' => 1,
            'pending_jobs' => 1,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => null,
            'created_at' => now()->subDays(20)->getTimestamp(),
            'finished_at' => null,
            'cancelled_at' => null,
        ],
    ]);

    $this->artisan('maintenance:database-prune --force')
        ->expectsOutput('Parse histories to delete: 3')
        ->expectsOutput('Old kept parse histories to compact: 4')
        ->expectsOutput('Deleted parse histories: 3')
        ->expectsOutput('Compacted parse histories: 4')
        ->assertSuccessful();

    expect(TournamentParseHistory::query()->pluck('id')->all())
        ->toContain($recent->id)
        ->toContain(...$kept);
    expect(TournamentParseHistory::query()->count())->toBe(5);
    expect(TournamentParseHistory::query()->whereNotNull('compacted_at')->count())->toBe(4);
    expect(DB::table('job_batches')->where('id', 'completed-old')->exists())->toBeFalse();
    expect(DB::table('job_batches')->where('id', 'unfinished-old')->exists())->toBeTrue();
});

test('database prune compacts old kept histories idempotently', function () {
    $tournament = Tournament::factory()->create();

    createParseHistory($tournament, 40);

    $this->artisan('maintenance:database-prune --force')->assertSuccessful();

    $history = TournamentParseHistory::query()->firstOrFail();
    expect($history->compacted_at)->not->toBeNull();
    expect($history->parsed_data['compacted'])->toBeTrue();
    expect($history->parsed_data)->toHaveKey('original_sha256');
    expect($history->changes['description'])->not->toHaveKey('old');
    expect($history->changes['description'])->not->toHaveKey('new');
    expect($history->changes['description'])->not->toHaveKey('diff_html');
    expect($history->changes['description'])->toHaveKey('old_sha256');
    expect($history->changes['title'])->not->toHaveKey('diff_html');

    $this->travel(1)->hour();

    $this->artisan('maintenance:database-prune --force')
        ->expectsOutput('Old kept parse histories to compact: 0')
        ->expectsOutput('Compacted parse histories: 0')
        ->assertSuccessful();

    expect($history->fresh()->compacted_at->equalTo($history->compacted_at))->toBeTrue();
});

test('database prune requires dry run or force but not both', function () {
    $this->artisan('maintenance:database-prune')
        ->expectsOutput('Specify exactly one of --dry-run or --force.')
        ->assertFailed();

    $this->artisan('maintenance:database-prune --dry-run --force')
        ->expectsOutput('Specify exactly one of --dry-run or --force.')
        ->assertFailed();
});
