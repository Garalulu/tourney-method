<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorQueueHealth extends Command
{
    protected $signature = 'queue:health';

    protected $description = 'Check queue and cron job health status';

    public function handle(): int
    {
        $this->info('=== Queue & Cron Health Check ===');
        $this->newLine();

        // Check pending jobs
        $pendingJobs = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->get();

        if ($pendingJobs->isEmpty()) {
            $this->info('✅ No pending jobs in queue');
        } else {
            $this->table(
                ['Queue', 'Pending Count'],
                $pendingJobs->map(fn ($job) => [(string) $job->queue, (string) $job->count])
            );
        }

        $this->newLine();

        // Check failed jobs (last 24 hours)
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>', now()->subDay())
            ->count();

        if ($failedJobs === 0) {
            $this->info('✅ No failed jobs in last 24 hours');
        } else {
            $this->error("❌ {$failedJobs} failed jobs in last 24 hours");

            $recentFailures = DB::table('failed_jobs')
                ->select('id', 'queue', 'exception', 'failed_at')
                ->orderBy('failed_at', 'desc')
                ->limit(5)
                ->get();

            $this->table(
                ['ID', 'Queue', 'Failed At', 'Exception Preview'],
                $recentFailures->map(fn ($job) => [
                    (string) $job->id,
                    (string) $job->queue,
                    $job->failed_at,
                    substr($job->exception, 0, 100).'...',
                ])
            );
        }

        $this->newLine();

        // Check import jobs
        $importJobs = DB::table('import_jobs')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>', now()->subDay())
            ->groupBy('status')
            ->get();

        if ($importJobs->isEmpty()) {
            $this->comment('⚠️  No import jobs in last 24 hours');
        } else {
            $this->info('📊 Import Jobs (last 24h):');
            $this->table(
                ['Status', 'Count'],
                $importJobs->map(fn ($job) => [(string) $job->status, (string) $job->count])
            );
        }

        $this->newLine();

        // Check for stuck jobs (reserved too long)
        $stuckJobs = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', now()->subHour()->getTimestamp())
            ->count();

        if ($stuckJobs > 0) {
            $this->error("⚠️  {$stuckJobs} jobs may be stuck (reserved for >1 hour)");
        } else {
            $this->info('✅ No stuck jobs detected');
        }

        $this->newLine();
        $this->info('✨ Health check complete!');

        return self::SUCCESS;
    }
}
