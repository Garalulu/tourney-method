<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job to import tournaments from tcomm.hivie.tn
 *
 * This job handles importing badge tournaments from the tcomm API
 * in the background to avoid blocking the main application.
 */
class ImportTournamentsFromTcommJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600; // 1 hour timeout

    /**
     * Create a new job instance.
     */
    public function __construct(
        private ImportJob $importJob,
        private bool $dryRun = false,
    ) {
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(ImportService $importService): void
    {
        Log::info('Starting tcomm import job', ['job_id' => $this->importJob->id]);

        try {
            $this->importJob->markAsStarted();

            $stats = $importService->runTcommImport($this->importJob, $this->dryRun);

            $this->importJob->updateProgress($stats);
            $this->importJob->markAsCompleted();

            Log::info('Tcomm import job completed', [
                'job_id' => $this->importJob->id,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Tcomm import job failed', [
                'job_id' => $this->importJob->id,
                'error' => $e->getMessage(),
            ]);

            $this->importJob->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Tcomm import job failed after retries', [
            'job_id' => $this->importJob->id,
            'error' => $exception->getMessage(),
        ]);

        $this->importJob->markAsFailed(
            'Job failed after retries: '.$exception->getMessage()
        );
    }
}
