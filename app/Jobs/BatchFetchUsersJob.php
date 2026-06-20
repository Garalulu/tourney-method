<?php

namespace App\Jobs;

use App\Models\AdminAsyncOperation;
use App\Models\User;
use App\Services\BatchTransactionService;
use App\Support\QueueNames;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class BatchFetchUsersJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int<0, max>>
     */
    public array $backoff = [60, 120, 240];

    /**
     * @param  array<int, int>|null  $nextChunkTopics
     */
    public function __construct(
        private string $parseBatchId,
        private ?array $nextChunkTopics = null,
        private ?int $chunkSize = null,
        private ?int $nextBatchNumber = null,
        private bool $forceReparse = false,
        private ?string $dumpVersion = null,
        private ?int $operationId = null,
        private string $queueName = QueueNames::OSU_ADMIN
    ) {
        $this->onQueue($this->queueName);
    }

    /**
     * Execute the coordinator job.
     */
    public function handle(mixed $transaction = null): void
    {
        if (! $transaction instanceof BatchTransactionService) {
            $transaction = null;
        }

        $transaction ??= app(BatchTransactionService::class);

        Log::info('BatchFetchUsersJob: Coordinating user fetch chunks', [
            'transaction_id' => $this->parseBatchId,
        ]);

        $staffData = $transaction->getStaffPayloads($this->parseBatchId);

        if (empty($staffData) && ! $transaction->hasTransaction($this->parseBatchId)) {
            Log::error('BatchFetchUsersJob: Staff data not found in parse batch', [
                'transaction_id' => $this->parseBatchId,
            ]);

            throw new \Exception("Staff data not found for transaction: {$this->parseBatchId}");
        }

        $osuIds = $transaction->getUniqueStaffOsuIds($this->parseBatchId);
        $localUserMapping = $this->mapLocallyCompleteStaffUsers($osuIds);

        if (! empty($localUserMapping)) {
            $transaction->mergeUserMapping($this->parseBatchId, $localUserMapping);
            $osuIds = array_values(array_diff($osuIds, array_keys($localUserMapping)));

            Log::info('BatchFetchUsersJob: Reused locally complete staff users', [
                'transaction_id' => $this->parseBatchId,
                'local_user_count' => count($localUserMapping),
                'remaining_osu_ids' => count($osuIds),
            ]);
        }

        if (empty($osuIds)) {
            Log::info('BatchFetchUsersJob: No osu_ids to fetch', [
                'transaction_id' => $this->parseBatchId,
                'tournament_count' => count($staffData),
            ]);

            $transaction->mergeUserMapping($this->parseBatchId, []);
            self::dispatchMergeStage(
                $this->parseBatchId,
                $this->nextChunkTopics,
                $this->chunkSize,
                $this->nextBatchNumber,
                $this->forceReparse,
                $this->dumpVersion,
                $this->operationId,
                $this->queueName
            );

            return;
        }

        $chunks = array_chunk($osuIds, FetchUsersChunkJob::CHUNK_SIZE);

        Log::info('BatchFetchUsersJob: Dispatching user fetch chunks', [
            'transaction_id' => $this->parseBatchId,
            'unique_osu_ids' => count($osuIds),
            'chunk_count' => count($chunks),
        ]);

        $jobs = array_map(
            fn (array $chunk) => new FetchUsersChunkJob($this->parseBatchId, $chunk, $this->queueName),
            $chunks
        );

        $parseBatchId = $this->parseBatchId;
        $nextChunkTopics = $this->nextChunkTopics;
        $chunkSize = $this->chunkSize;
        $nextBatchNumber = $this->nextBatchNumber;
        $forceReparse = $this->forceReparse;
        $dumpVersion = $this->dumpVersion;
        $operationId = $this->operationId;
        $queueName = $this->queueName;

        Bus::batch($jobs)
            ->then(function (Batch $batch) use ($parseBatchId, $nextChunkTopics, $chunkSize, $nextBatchNumber, $forceReparse, $dumpVersion, $operationId, $queueName) {
                Log::info('User fetch chunks complete; dispatching staff merge', [
                    'transaction_id' => $parseBatchId,
                    'batch_id' => $batch->id,
                    'total_jobs' => $batch->totalJobs,
                    'failed_jobs' => $batch->failedJobs,
                ]);

                if ($operationId !== null) {
                    AdminAsyncOperation::query()
                        ->find($operationId)
                        ?->advance('osu! users fetched');
                }

                self::dispatchMergeStage(
                    $parseBatchId,
                    $nextChunkTopics,
                    $chunkSize,
                    $nextBatchNumber,
                    $forceReparse,
                    $dumpVersion,
                    $operationId,
                    $queueName
                );
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($parseBatchId, $operationId) {
                Log::error('User fetch chunks failed', [
                    'transaction_id' => $parseBatchId,
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);

                app(BatchTransactionService::class)
                    ->recordError($parseBatchId, 'users_fetching', $e->getMessage());

                if ($operationId !== null) {
                    AdminAsyncOperation::query()
                        ->find($operationId)
                        ?->fail('osu! user fetching failed', $e);
                }
            })
            ->name('Stage 2b: Fetch Users')
            ->onQueue($this->queueName)
            ->dispatch();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BatchFetchUsersJob failed permanently', [
            'transaction_id' => $this->parseBatchId,
            'error' => $exception->getMessage(),
        ]);

        app(BatchTransactionService::class)
            ->recordError($this->parseBatchId, 'users_fetching', $exception->getMessage());

        if ($this->operationId !== null) {
            AdminAsyncOperation::query()
                ->find($this->operationId)
                ?->fail('osu! user fetch coordinator failed', $exception);
        }
    }

    /**
     * @param  array<int, int>|null  $nextChunkTopics
     */
    private static function dispatchMergeStage(
        string $parseBatchId,
        ?array $nextChunkTopics = null,
        ?int $chunkSize = null,
        ?int $nextBatchNumber = null,
        bool $forceReparse = false,
        ?string $dumpVersion = null,
        ?int $operationId = null,
        string $queueName = QueueNames::OSU_ADMIN
    ): void {
        Bus::batch([
            new BatchMergeStaffJob($parseBatchId, $operationId, $queueName),
        ])
            ->then(function (Batch $batch) use ($parseBatchId, $nextChunkTopics, $chunkSize, $nextBatchNumber, $forceReparse, $dumpVersion) {
                Log::info('Stage 3 complete: Staff merging completed', [
                    'transaction_id' => $parseBatchId,
                    'batch_id' => $batch->id,
                ]);

                app(BatchTransactionService::class)->cleanupTransaction($parseBatchId);

                self::dispatchContinuation($nextChunkTopics, $chunkSize, $nextBatchNumber, $forceReparse, $dumpVersion);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($parseBatchId) {
                Log::error('Stage 3 failed: Staff merging failed', [
                    'transaction_id' => $parseBatchId,
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);

                app(BatchTransactionService::class)
                    ->recordError($parseBatchId, 'staff_merging', $e->getMessage());
            })
            ->name('Stage 3: Merge Staff')
            ->onQueue($queueName)
            ->dispatch();
    }

    /**
     * @param  array<int, int>|null  $nextChunkTopics
     */
    private static function dispatchContinuation(
        ?array $nextChunkTopics,
        ?int $chunkSize,
        ?int $nextBatchNumber,
        bool $forceReparse,
        ?string $dumpVersion
    ): void {
        if ($nextChunkTopics === null || $chunkSize === null || $nextBatchNumber === null) {
            return;
        }

        if (empty($nextChunkTopics)) {
            Log::info('All forum parse chunks completed', [
                'last_batch' => $nextBatchNumber - 1,
            ]);

            if ($dumpVersion !== null) {
                \DB::table('otr_import_history')
                    ->where('dump_version', $dumpVersion)
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                Log::info('OTR dump import history updated to completed', [
                    'dump_version' => $dumpVersion,
                ]);
            }

            return;
        }

        Log::info('Dispatching next forum parse chunk', [
            'remaining_topics' => count($nextChunkTopics),
            'next_batch_number' => $nextBatchNumber,
            'force_reparse' => $forceReparse,
        ]);

        ChunkedForumParseJob::dispatch(
            $nextChunkTopics,
            $chunkSize,
            $nextBatchNumber,
            $forceReparse,
            $dumpVersion
        );
    }

    /**
     * Staff profile fetches only need osu! API when the local user is missing a country flag.
     *
     * @param  array<int, int>  $osuIds
     * @return array<int, int>
     */
    private function mapLocallyCompleteStaffUsers(array $osuIds): array
    {
        if (empty($osuIds)) {
            return [];
        }

        $mapping = [];

        /** @var Collection<int, User> $users */
        $users = User::withTrashed()
            ->whereIn('osu_id', $osuIds)
            ->whereNotNull('country_code')
            ->get();

        foreach ($users as $user) {
            if ($user->trashed()) {
                $user->restore();
                $user = $user->fresh();
            }

            $mapping[(int) $user->osu_id] = $user->id;
        }

        return $mapping;
    }
}
