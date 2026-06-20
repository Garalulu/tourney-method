<?php

namespace App\Services;

use App\Jobs\AggregateStaffJob;
use App\Jobs\BatchFetchUsersJob;
use App\Jobs\ParseForumTopicJob;
use App\Models\AdminAsyncOperation;
use App\Models\Tournament;
use App\Support\QueueNames;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class AdminTournamentParseService
{
    private const QUEUE = QueueNames::OSU_ADMIN_PRIORITY;

    public function __construct(
        private readonly BatchTransactionService $transactions,
    ) {}

    public function queueFullParse(Tournament $tournament, string $context, ?int $operationId = null): string
    {
        $transactionId = $this->transactions->generateTransactionId();
        $title = $tournament->title;

        if ($operationId !== null) {
            AdminAsyncOperation::query()
                ->find($operationId)
                ?->start('Queued forum parse...');
        }

        Bus::batch([
            new ParseForumTopicJob(
                $tournament->forum_topic_id,
                $transactionId,
                true,
                false,
                $tournament->id,
                self::QUEUE
            ),
        ])
            ->finally(function (Batch $batch) use ($transactionId, $tournament, $context, $operationId) {
                if ($batch->cancelled()) {
                    Log::warning('Stage 1 batch cancelled, stopping pipeline', [
                        'context' => $context,
                        'transaction_id' => $transactionId,
                        'tournament_id' => $tournament->id,
                        'batch_id' => $batch->id,
                    ]);

                    return;
                }

                if ($batch->hasFailures()) {
                    Log::warning('Stage 1 batch finished with failures, continuing pipeline', [
                        'context' => $context,
                        'transaction_id' => $transactionId,
                        'tournament_id' => $tournament->id,
                        'batch_id' => $batch->id,
                    ]);
                }

                Log::info('Stage 1 finished: Tournament parsing finished', [
                    'context' => $context,
                    'transaction_id' => $transactionId,
                    'tournament_id' => $tournament->id,
                    'batch_id' => $batch->id,
                ]);

                if ($operationId !== null) {
                    AdminAsyncOperation::query()
                        ->find($operationId)
                        ?->advance('Forum parse finished');
                }

                Bus::batch([
                    new AggregateStaffJob($transactionId, self::QUEUE),
                ])
                    ->finally(function (Batch $aggregateBatch) use ($transactionId, $tournament, $context, $operationId) {
                        if ($aggregateBatch->cancelled()) {
                            Log::warning('Stage 2a batch cancelled, stopping pipeline', [
                                'context' => $context,
                                'transaction_id' => $transactionId,
                                'tournament_id' => $tournament->id,
                                'batch_id' => $aggregateBatch->id,
                            ]);

                            return;
                        }

                        if ($aggregateBatch->hasFailures()) {
                            Log::warning('Stage 2a batch finished with failures, continuing pipeline', [
                                'context' => $context,
                                'transaction_id' => $transactionId,
                                'tournament_id' => $tournament->id,
                                'batch_id' => $aggregateBatch->id,
                            ]);
                        }

                        Log::info('Stage 2a finished: Staff aggregation finished', [
                            'context' => $context,
                            'transaction_id' => $transactionId,
                            'tournament_id' => $tournament->id,
                            'batch_id' => $aggregateBatch->id,
                        ]);

                        if ($operationId !== null) {
                            AdminAsyncOperation::query()
                                ->find($operationId)
                                ?->advance('Staff aggregation finished');
                        }

                        Bus::batch([
                            new BatchFetchUsersJob($transactionId, operationId: $operationId, queueName: self::QUEUE),
                        ])
                            ->catch(function (Batch $fetchBatch, \Throwable $e) use ($transactionId, $tournament, $context) {
                                Log::error('Stage 2b failed: User fetch coordinator failed', [
                                    'context' => $context,
                                    'transaction_id' => $transactionId,
                                    'tournament_id' => $tournament->id,
                                    'batch_id' => $fetchBatch->id,
                                    'error' => $e->getMessage(),
                                ]);

                                app(BatchTransactionService::class)
                                    ->recordError($transactionId, 'users_fetching', $e->getMessage());
                            })
                            ->finally(function (Batch $fetchBatch) use ($transactionId, $tournament, $context) {
                                Log::info('Stage 2b coordinator finished', [
                                    'context' => $context,
                                    'transaction_id' => $transactionId,
                                    'tournament_id' => $tournament->id,
                                    'batch_id' => $fetchBatch->id,
                                ]);
                            })
                            ->name('Stage 2b: Fetch Users Coordinator')
                            ->onQueue(self::QUEUE)
                            ->dispatch();
                    })
                    ->catch(function (Batch $aggregateBatch, \Throwable $e) use ($transactionId, $tournament, $context, $operationId) {
                        Log::error('Stage 2a failed: Staff aggregation failed', [
                            'context' => $context,
                            'transaction_id' => $transactionId,
                            'tournament_id' => $tournament->id,
                            'batch_id' => $aggregateBatch->id,
                            'error' => $e->getMessage(),
                        ]);
                        app(BatchTransactionService::class)
                            ->recordError($transactionId, 'staff_aggregation', $e->getMessage());

                        if ($operationId !== null) {
                            AdminAsyncOperation::query()
                                ->find($operationId)
                                ?->fail('Staff aggregation failed', $e);
                        }
                    })
                    ->name('Stage 2a: Aggregate Staff')
                    ->onQueue(self::QUEUE)
                    ->dispatch();
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($transactionId, $tournament, $context, $operationId) {
                Log::error('Stage 1 failed: Tournament parsing failed', [
                    'context' => $context,
                    'transaction_id' => $transactionId,
                    'tournament_id' => $tournament->id,
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
                app(BatchTransactionService::class)->cleanupTransaction($transactionId);

                if ($operationId !== null) {
                    AdminAsyncOperation::query()
                        ->find($operationId)
                        ?->fail('Tournament parsing failed', $e);
                }
            })
            ->name("Stage 1: Parse Tournament ({$title})")
            ->onQueue(self::QUEUE)
            ->dispatch();

        return $transactionId;
    }
}
