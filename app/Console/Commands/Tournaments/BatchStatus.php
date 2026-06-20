<?php

namespace App\Console\Commands\Tournaments;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Console\Command;

class BatchStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournaments:batch-status
                            {--id= : Check status of specific batch ID}
                            {--limit=10 : Number of recent batches to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show status of tournament parsing batches';

    /**
     * Execute the console command.
     */
    public function handle(BatchRepository $batchRepository): int
    {
        $batchId = $this->option('id');
        $limit = (int) $this->option('limit');

        if ($batchId) {
            // Show specific batch
            $batch = $batchRepository->find($batchId);

            if (! $batch) {
                $this->error("Batch {$batchId} not found.");

                return self::FAILURE;
            }

            $this->displayBatchDetails($batch);
        } else {
            // Show recent batches
            /** @var array<int, Batch> */
            $batches = $batchRepository->get(50, $limit);
            $batchesArray = iterator_to_array($batches);

            if (empty($batchesArray)) {
                $this->info('No batches found.');

                return self::SUCCESS;
            }

            $this->info('=== Recent '.count($batchesArray).' Batches ===');
            $this->newLine();

            foreach ($batchesArray as $batch) {
                $this->displayBatchSummary($batch);
                $this->newLine();
            }
        }

        return self::SUCCESS;
    }

    /**
     * Display batch summary (one line)
     */
    protected function displayBatchSummary(Batch $batch): void
    {
        $status = $this->getBatchStatus($batch);
        $progress = $this->getBatchProgress($batch);

        $this->line("Batch ID: <fg=cyan>{$batch->id}</>");
        $this->line("Name: {$batch->name}");
        $this->line("Status: <fg={$this->getStatusColor($status)}>{$status}</>");
        $this->line("Progress: {$progress}");
        $this->line("Created: {$batch->createdAt->format('Y-m-d H:i:s')}");
    }

    /**
     * Display detailed batch information
     */
    protected function displayBatchDetails(Batch $batch): void
    {
        $status = $this->getBatchStatus($batch);
        $progress = $this->getBatchProgress($batch);

        $this->info('=== Batch Details ===');
        $this->newLine();
        $this->line("Batch ID:        <fg=cyan>{$batch->id}</>");
        $this->line("Name:            {$batch->name}");
        $this->line("Status:          <fg={$this->getStatusColor($status)}>{$status}</>");
        $this->line("Total Jobs:      {$batch->totalJobs}");
        $this->line("Pending Jobs:    {$batch->pendingJobs}");
        $this->line("Failed Jobs:     {$batch->failedJobs}");
        $this->newLine();
        $this->line("Created At:      {$batch->createdAt->format('Y-m-d H:i:s')}");
        $this->line('Cancelled At:    '.($batch->cancelledAt ? $batch->cancelledAt->format('Y-m-d H:i:s') : 'N/A'));
        $this->line('Finished At:     '.($batch->finishedAt ? $batch->finishedAt->format('Y-m-d H:i:s') : 'N/A'));
        $this->newLine();

        // Show failed job IDs if any
        if ($batch->failedJobs > 0) {
            $this->error('Failed Job IDs:');
            foreach ($batch->failedJobIds as $jobId) {
                $this->line("  - {$jobId}");
            }
        }
    }

    /**
     * Get batch status string
     */
    protected function getBatchStatus(Batch $batch): string
    {
        if ($batch->cancelled()) {
            return 'Cancelled';
        }

        if ($batch->finished()) {
            return $batch->failedJobs > 0 ? 'Completed (with failures)' : 'Completed';
        }

        return 'Processing';
    }

    /**
     * Get batch progress string
     */
    protected function getBatchProgress(Batch $batch): string
    {
        $completed = $batch->totalJobs - $batch->pendingJobs;
        $percentage = $batch->totalJobs > 0
            ? round(($completed / $batch->totalJobs) * 100, 1)
            : 0;

        return "{$completed}/{$batch->totalJobs} ({$percentage}%)";
    }

    /**
     * Get color for status display
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'Completed', 'Completed (with failures)' => 'green',
            'Processing' => 'yellow',
            'Cancelled' => 'red',
            default => 'white',
        };
    }
}
