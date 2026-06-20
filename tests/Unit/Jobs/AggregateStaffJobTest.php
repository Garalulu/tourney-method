<?php

use App\Jobs\AggregateStaffJob;
use App\Models\Tournament;
use App\Models\TournamentParseBatch;
use App\Services\BatchTransactionService;

test('marks db backed staff payloads as aggregated', function () {
    $transaction = app(BatchTransactionService::class);
    $transactionId = 'test_txn_aggregate';
    $tournament1 = Tournament::factory()->create();
    $tournament2 = Tournament::factory()->create();

    $transaction->storeTournamentStaff($transactionId, $tournament1->id, [
        ['osu_id' => 100, 'role' => 'organizer'],
        ['osu_id' => 101, 'role' => 'mapper'],
    ]);
    $transaction->storeTournamentStaff($transactionId, $tournament2->id, [
        ['osu_id' => 200, 'role' => 'referee'],
    ]);

    (new AggregateStaffJob($transactionId))->handle($transaction);

    $payloads = $transaction->getStaffPayloads($transactionId);
    $batch = TournamentParseBatch::query()->where('transaction_id', $transactionId)->first();

    expect($payloads)->toHaveCount(2);
    expect($payloads[$tournament1->id])->toHaveCount(2);
    expect($payloads[$tournament2->id][0]['osu_id'])->toBe(200);
    expect($batch->stage)->toBe('staff_aggregated');
});

test('handles transactions with no tournaments gracefully', function () {
    $transaction = app(BatchTransactionService::class);
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();

    (new AggregateStaffJob($transactionId))->handle($transaction);

    $batch = TournamentParseBatch::query()->where('transaction_id', $transactionId)->first();

    expect($transaction->getStaffPayloads($transactionId))->toBeEmpty();
    expect($batch->stage)->toBe('staff_aggregated');
});

test('preserves staff structure for each tournament', function () {
    $transaction = app(BatchTransactionService::class);
    $transactionId = 'test_txn_structure';
    $tournament = Tournament::factory()->create();
    $staffData = [
        ['osu_id' => 100, 'role' => 'organizer'],
        ['osu_id' => 101, 'role' => 'mapper'],
        ['osu_id' => 102, 'role' => 'referee'],
        ['osu_id' => 100, 'role' => 'playtester'],
    ];

    $transaction->storeTournamentStaff($transactionId, $tournament->id, $staffData);

    (new AggregateStaffJob($transactionId))->handle($transaction);

    expect($transaction->getStaffPayloads($transactionId)[$tournament->id])->toEqual($staffData);
});

test('retries on failure', function () {
    $job = new AggregateStaffJob('test_txn_retry');

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([60, 120, 240]);
});
