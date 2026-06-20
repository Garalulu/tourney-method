<?php

namespace App\Jobs;

use App\Support\QueueNames;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Chunked Forum Parse Job
 *
 * Processes forum topic parsing in chunks to prevent timeout issues.
 * Each chunk processes 50 topics via Bus::batch(), then triggers Stage 2 jobs
 * (AggregateStaffJob, then BatchFetchUsersJob) before dispatching the next chunk.
 *
 * WHY: ParseForumTopicJob has 60s timeout. With 2571 jobs, processing all at once causes timeouts.
 * This job processes chunks sequentially (50 jobs per batch), ensuring no batch exceeds timeout.
 *
 * 3-STAGE PIPELINE:
 * 1. Stage 1: ParseForumTopicJob (50 jobs per chunk)
 *    - Parses forum topic data
 *    - Stores staff payloads in the parse batch tables
 *    - Adds tournament ID to transaction: `$transaction->addTournamentId($transactionId, $tournamentId)`
 * 2. Stage 2: AggregateStaffJob, then BatchFetchUsersJob
 *    - AggregateStaffJob: Marks DB-backed staff payloads ready
 *    - BatchFetchUsersJob: Dispatches user detail fetch chunks and stores user mapping
 * 3. Stage 3: BatchMergeStaffJob (1 job per chunk)
 *    - Merges staff into tournament_staff table with status='approved'
 *    - Uses Tournament::mergeStaffWithHistory() for proper staff approval
 *    - Marks the persisted transaction complete
 *    - Dispatches next chunk (repeat Stage 1)
 *
 * FLOW:
 * 1. Generate unique transaction ID for this chunk (UUID)
 * 2. Take first 50 topic IDs from array
 * 3. Create ParseForumTopicJob instances for each (with transaction ID)
 * 4. Dispatch as Bus::batch()
 * 5. When Stage 1 batch completes → `then()` callback fires
 * 6. Dispatch Stage 2 batch (AggregateStaffJob + BatchFetchUsersJob)
 * 7. When Stage 2 completes → dispatch Stage 3 batch (BatchMergeStaffJob)
 * 8. When Stage 3 completes → complete transaction + dispatch next chunk (repeat from step 1)
 * 9. Repeat until all topics processed
 *
 * TIMING:
 * - Stage 1: 50 jobs × ~1 second/job (with delays) = ~50 seconds per chunk
 * - Stage 2: 2 jobs (aggregation is fast, user fetching with rate limiting)
 * - Stage 3: 1 job (staff merge is fast, bulk operations)
 * - With rate limiting (60 req/min), Stage 2 takes ~1-2 minutes for user fetching
 * - 2571 jobs ÷ 50 = ~52 chunks
 * - Total time: ~52 chunks × ~2-3 minutes = ~2-3 hours (within safe limits)
 *
 * @see ParseForumTopicJob
 * @see AggregateStaffJob
 * @see BatchFetchUsersJob
 * @see BatchMergeStaffJob
 * @see OtrDumpService
 */
class ChunkedForumParseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     * Set to 0 (no limit) since chunk processing can take 2+ minutes.
     */
    public int $timeout = 0;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  array<int, int>  $topicIds  Array of forum topic IDs to process
     * @param  int  $chunkSize  Number of jobs per batch (default: 50)
     * @param  int  $batchNumber  Current batch number (for logging)
     * @param  bool  $forceReparse  Force update even if tournament is approved (for OTR imports)
     * @param  string|null  $dumpVersion  OTR dump version (to update history on completion)
     */
    public function __construct(
        public array $topicIds,
        public int $chunkSize = 50,
        public int $batchNumber = 1,
        public bool $forceReparse = false,
        public ?string $dumpVersion = null
    ) {
        $this->onQueue(QueueNames::OSU_ADMIN);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $totalTopics = count($this->topicIds);
        $chunk = array_slice($this->topicIds, 0, $this->chunkSize);

        // Create transaction ID for staff aggregation (3-stage pipeline)
        $transactionId = (string) Str::uuid();

        // Extract dump version for passing to closures (cannot use $this in serialized closures)
        $dumpVersion = $this->dumpVersion;

        Log::info('Processing forum parse chunk', [
            'batch_number' => $this->batchNumber,
            'chunk_size' => count($chunk),
            'remaining_after' => count($this->topicIds) - count($chunk),
            'total_topics' => $totalTopics,
            'transaction_id' => $transactionId,
            'first_5_topic_ids' => array_slice($this->topicIds, 0, 5),
            'chunk_first_5' => array_slice($chunk, 0, 5),
        ]);

        // Create ParseForumTopicJob instances for this chunk
        $jobs = [];
        foreach ($chunk as $index => $topicId) {
            $job = new ParseForumTopicJob($topicId, $transactionId, $this->forceReparse, true);

            // Add 1-second delay to each job to space out API calls
            $job->delay(now()->addSeconds($index));

            $jobs[] = $job;
        }

        // Pass data explicitly to closure (cannot use $this in batch callbacks)
        $remainingTopicIds = $this->topicIds;
        $currentChunkSize = $this->chunkSize;
        $currentBatchNumber = $this->batchNumber;
        $forceReparse = $this->forceReparse;
        $currentTransactionId = $transactionId;

        Bus::batch($jobs)
            ->name("Forum Parse Chunk #{$this->batchNumber}")
            ->onQueue(QueueNames::OSU_ADMIN)
            ->allowFailures() // Don't fail entire batch if some jobs fail
            ->finally(function (Batch $batch) use ($remainingTopicIds, $currentChunkSize, $currentBatchNumber, $forceReparse, $currentTransactionId, $dumpVersion) {
                if ($batch->cancelled()) {
                    Log::warning('Forum parse batch cancelled, stopping pipeline', [
                        'batch_id' => $batch->id,
                        'chunk_number' => $currentBatchNumber,
                    ]);

                    return;
                }

                if ($batch->hasFailures()) {
                    Log::warning('Forum parse batch finished with failures, continuing pipeline', [
                        'batch_id' => $batch->id,
                        'chunk_number' => $currentBatchNumber,
                        'failed_jobs' => $batch->failedJobs,
                    ]);
                }

                // Calculate remaining topics (remove processed chunk)
                $nextChunkTopics = array_slice($remainingTopicIds, $currentChunkSize);

                // Dispatch Stage 2a: Staff aggregation before user fetching.
                Log::info('Dispatching Stage 2a job (AggregateStaffJob)', [
                    'transaction_id' => $currentTransactionId,
                    'chunk_number' => $currentBatchNumber,
                ]);

                Bus::batch([
                    new AggregateStaffJob($currentTransactionId),
                ])
                    ->finally(function (Batch $batch) use ($currentTransactionId, $currentBatchNumber, $remainingTopicIds, $currentChunkSize, $forceReparse, $dumpVersion) {
                        if ($batch->cancelled()) {
                            Log::warning('Stage 2a batch cancelled, stopping pipeline', [
                                'batch_id' => $batch->id,
                                'transaction_id' => $currentTransactionId,
                            ]);

                            return;
                        }

                        if ($batch->hasFailures()) {
                            Log::warning('Stage 2a batch finished with failures, continuing pipeline', [
                                'batch_id' => $batch->id,
                                'transaction_id' => $currentTransactionId,
                            ]);
                        }

                        Log::info('Stage 2a finished: Staff aggregation finished', [
                            'transaction_id' => $currentTransactionId,
                            'chunk_number' => $currentBatchNumber,
                        ]);

                        $nextChunkTopics = array_slice($remainingTopicIds, $currentChunkSize);

                        Bus::batch([
                            new BatchFetchUsersJob(
                                $currentTransactionId,
                                $nextChunkTopics,
                                $currentChunkSize,
                                $currentBatchNumber + 1,
                                $forceReparse,
                                $dumpVersion
                            ),
                        ])
                            ->finally(function (Batch $fetchBatch) use ($currentTransactionId, $currentBatchNumber) {
                                if ($fetchBatch->cancelled()) {
                                    Log::warning('Stage 2b coordinator batch cancelled, stopping pipeline', [
                                        'batch_id' => $fetchBatch->id,
                                        'transaction_id' => $currentTransactionId,
                                    ]);

                                    return;
                                }

                                if ($fetchBatch->hasFailures()) {
                                    Log::warning('Stage 2b coordinator batch finished with failures, continuing pipeline', [
                                        'batch_id' => $fetchBatch->id,
                                        'transaction_id' => $currentTransactionId,
                                    ]);
                                }

                                Log::info('Stage 2b coordinator finished: User fetch chunks dispatched', [
                                    'transaction_id' => $currentTransactionId,
                                    'chunk_number' => $currentBatchNumber,
                                ]);
                            })
                            ->catch(function (Batch $fetchBatch, \Throwable $e) use ($currentTransactionId, $currentBatchNumber) {
                                Log::error('Stage 2b error: User fetch coordinator encountered an error', [
                                    'transaction_id' => $currentTransactionId,
                                    'chunk_number' => $currentBatchNumber,
                                    'error' => $e->getMessage(),
                                ]);
                            })
                            ->name('Stage 2b: Fetch Users Coordinator')
                            ->onQueue(QueueNames::OSU_ADMIN)
                            ->dispatch();
                    })
                    ->catch(function (Batch $batch, \Throwable $e) use ($currentTransactionId, $currentBatchNumber) {
                        Log::error('Stage 2a error: Staff aggregation encountered an error', [
                            'transaction_id' => $currentTransactionId,
                            'chunk_number' => $currentBatchNumber,
                            'error' => $e->getMessage(),
                        ]);
                    })
                    ->name('Stage 2a: Aggregate Staff')
                    ->onQueue(QueueNames::OSU_ADMIN)
                    ->dispatch();

            })
            ->dispatch();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Chunked forum parse job failed permanently', [
            'batch_number' => $this->batchNumber,
            'remaining_topics' => count($this->topicIds),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
