<?php

use App\Models\TournamentParseBatch;
use App\Services\BatchTransactionService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('generates unique transaction IDs', function () {
    $service = new BatchTransactionService;

    $id1 = $service->generateTransactionId();
    $id2 = $service->generateTransactionId();

    expect($id1)->not->toBe($id2);
    expect($id1)->toBeString();
    expect($id2)->toBeString();
    expect($id1)->toStartWith('txn_');
    expect($id2)->toStartWith('txn_');
});

test('stores and retrieves tournament IDs', function () {
    $service = new BatchTransactionService;
    $transactionId = 'test_txn_123';
    $tournamentIds = [1, 2, 3];

    $service->storeTournamentIds($transactionId, $tournamentIds);

    $retrieved = $service->getTournamentIds($transactionId);

    expect($retrieved)->toBeArray();
    expect($retrieved)->toEqual($tournamentIds);
});

test('returns empty array when transaction has no tournaments', function () {
    $service = new BatchTransactionService;

    $retrieved = $service->getTournamentIds('nonexistent_txn');

    expect($retrieved)->toBeArray();
    expect($retrieved)->toBeEmpty();
});

test('adds tournament ID to transaction', function () {
    $service = new BatchTransactionService;
    $transactionId = 'test_txn_add';

    $service->storeTournamentIds($transactionId, [1, 2]);
    $service->addTournamentId($transactionId, 3);

    $retrieved = $service->getTournamentIds($transactionId);

    expect($retrieved)->toEqual([1, 2, 3]);
});

test('does not add duplicate tournament IDs', function () {
    $service = new BatchTransactionService;
    $transactionId = 'test_txn_dedupe';

    $service->storeTournamentIds($transactionId, [1, 2]);
    $service->addTournamentId($transactionId, 2); // Duplicate
    $service->addTournamentId($transactionId, 3);

    $retrieved = $service->getTournamentIds($transactionId);

    expect($retrieved)->toEqual([1, 2, 3]);
});

test('checks if transaction exists', function () {
    $service = new BatchTransactionService;

    expect($service->hasTransaction('nonexistent_txn'))->toBeFalse();

    $service->storeTournamentIds('existing_txn', [1, 2]);

    expect($service->hasTransaction('existing_txn'))->toBeTrue();
});

test('cleanup marks parse batch completed without deleting handoff state', function () {
    $service = new BatchTransactionService;
    $transactionId = 'test_txn_cleanup';

    $service->storeTournamentIds($transactionId, [1, 2]);

    $service->cleanupTransaction($transactionId);

    $batch = TournamentParseBatch::query()
        ->where('transaction_id', $transactionId)
        ->first();

    expect($batch)->not->toBeNull();
    expect($batch->status)->toBe('completed');
    expect($batch->stage)->toBe('completed');
    expect($batch->completed_at)->not->toBeNull();
    expect($service->getTournamentIds($transactionId))->toEqual([1, 2]);
});

test('handles cleanup of non-existent transaction gracefully', function () {
    $service = new BatchTransactionService;

    // Should not throw exception
    $service->cleanupTransaction('nonexistent_txn');

    expect(true)->toBeTrue();
});

test('can overwrite existing tournament IDs', function () {
    $service = new BatchTransactionService;
    $transactionId = 'test_txn_overwrite';

    $service->storeTournamentIds($transactionId, [1, 2, 3]);
    $service->storeTournamentIds($transactionId, [4, 5, 6]);

    $retrieved = $service->getTournamentIds($transactionId);

    expect($retrieved)->toEqual([4, 5, 6]);
});
