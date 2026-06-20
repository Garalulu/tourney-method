<?php

namespace App\Services;

use App\Models\AdminMaintenanceRun;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncUserProfilesBatchHandler
{
    /**
     * Handle successful batch completion.
     */
    public function onSuccess(Batch $batch, string $type, ?int $maintenanceRunId = null): void
    {
        $successRate = $this->calculateSuccessRate($batch);
        $this->markBatchFinished($maintenanceRunId);

        Log::info('User profile sync batch completed', [
            'batch_id' => $batch->id,
            'type' => $type,
            'total_jobs' => $batch->totalJobs,
            'processed_jobs' => $batch->processedJobs(),
            'failed_jobs' => $batch->failedJobs,
            'success_rate' => round($successRate, 2).'%',
        ]);
    }

    /**
     * Handle batch failure.
     */
    public function onFailure(Batch $batch, Throwable $e, string $type, ?int $maintenanceRunId = null): void
    {
        app(AdminMaintenanceRunRecorder::class)->fail($maintenanceRunId, $e->getMessage(), [
            'batch_id' => $batch->id,
            'type' => $type,
        ]);

        Log::error('User profile sync batch failed', [
            'batch_id' => $batch->id,
            'type' => $type,
            'error' => $e->getMessage(),
            'failed_jobs' => $batch->failedJobs,
            'total_jobs' => $batch->totalJobs,
            'exception' => $e,
        ]);
    }

    /**
     * Calculate success rate for the batch.
     */
    private function calculateSuccessRate(Batch $batch): float
    {
        if ($batch->totalJobs === 0) {
            return 100.0;
        }

        return (($batch->totalJobs - $batch->failedJobs) / $batch->totalJobs) * 100;
    }

    private function markBatchFinished(?int $maintenanceRunId): void
    {
        if ($maintenanceRunId === null) {
            return;
        }

        $run = AdminMaintenanceRun::query()->find($maintenanceRunId);

        if (! $run) {
            return;
        }

        app(AdminMaintenanceRunRecorder::class)->incrementSummary($run, ['completed_batches' => 1]);

        $run->refresh();
        $summary = $run->summary ?? [];

        if ((int) ($summary['completed_batches'] ?? 0) >= (int) ($summary['total_batches'] ?? 1)) {
            app(AdminMaintenanceRunRecorder::class)->complete($run);
        }
    }
}
