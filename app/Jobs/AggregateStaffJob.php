<?php

namespace App\Jobs;

use App\Services\BatchTransactionService;
use App\Support\QueueNames;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AggregateStaffJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int|array<int, int>
     */
    public int|array $backoff = [60, 120, 240];

    public function __construct(
        private string $transactionId,
        string $queueName = QueueNames::OSU_ADMIN
    ) {
        $this->onQueue($queueName);
    }

    /**
     * Execute the job.
     */
    public function handle(BatchTransactionService $transaction): void
    {
        Log::info('AggregateStaffJob: Aggregating staff data', [
            'transaction_id' => $this->transactionId,
        ]);

        // Get tournament IDs from transaction
        $tournamentIds = $transaction->getTournamentIds($this->transactionId);

        if (empty($tournamentIds)) {
            Log::warning('AggregateStaffJob: No tournament IDs found in transaction', [
                'transaction_id' => $this->transactionId,
            ]);

            $transaction->markStaffAggregated($this->transactionId);

            return;
        }

        Log::info('AggregateStaffJob: Scanning cache for tournament staff', [
            'transaction_id' => $this->transactionId,
            'tournament_count' => count($tournamentIds),
        ]);

        $aggregatedStaffData = $transaction->getStaffPayloads($this->transactionId);

        foreach ($aggregatedStaffData as $tournamentId => $tournamentStaff) {
            Log::debug('AggregateStaffJob: Found staff for tournament', [
                'transaction_id' => $this->transactionId,
                'tournament_id' => $tournamentId,
                'staff_count' => count($tournamentStaff),
            ]);
        }

        $transaction->markStaffAggregated($this->transactionId);

        Log::info('AggregateStaffJob: Staff aggregation complete', [
            'transaction_id' => $this->transactionId,
            'tournaments_with_staff' => count($aggregatedStaffData),
            'total_tournaments' => count($tournamentIds),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AggregateStaffJob failed permanently', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
