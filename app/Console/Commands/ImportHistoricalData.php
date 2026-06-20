<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\CreatesBackup;
use App\Console\Commands\Concerns\RequiresConfirmation;
use App\Models\ImportJob;
use App\Services\ImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Import historical tournament and match data from tcomm.hivie.tn and o!TR
 *
 * Usage:
 *   php artisan import:historical --confirm
 *   php artisan import:historical --source=tcomm --confirm
 *   php artisan import:historical --source=otr --confirm
 *   php artisan import:historical --resume --confirm
 *   php artisan import:historical --dry-run
 */
class ImportHistoricalData extends Command
{
    use CreatesBackup, RequiresConfirmation;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:historical
        {--source=combined : Import source: tcomm, otr, or combined}
        {--resume : Resume from last checkpoint}
        {--dry-run : Preview without saving to database}
        {--link-users : Link users to matches after import}
        {--limit= : Limit number of tournaments to import (for testing)}
        {--confirm : Confirm execution of data-modifying command}
        {--no-backup : Skip database backup before execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical tournaments and matches from tcomm.hivie.tn and o!TR APIs
                             REQUIRES --confirm flag to execute (unless --dry-run).';

    public function __construct(
        private ImportService $importService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = $this->option('source');
        $resume = $this->option('resume');
        $dryRun = $this->option('dry-run');
        $linkUsers = $this->option('link-users');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $shouldBackup = ! $dryRun && $this->shouldCreateBackup();

        // Require confirmation for data-modifying operations
        if (! $dryRun && ! $this->confirmExecution()) {
            return self::FAILURE;
        }

        $this->info("Starting historical import from: {$source}");
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        // Create backup before modifying data (skip in dry-run mode)
        $backupPath = null;
        if ($shouldBackup) {
            $backupPath = $this->createBackup();
        }

        try {
            if ($resume) {
                return $this->resumeImport($dryRun, $backupPath);
            }

            return $this->runNewImport($source, $dryRun, $linkUsers, $backupPath, $limit);
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            Log::error('Import historical data failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Restore backup if it was created
            if (! $dryRun && $backupPath !== null) {
                $this->restoreBackup($backupPath);
            }

            return self::FAILURE;
        } finally {
            // Clean up old backups after execution (success or failure)
            if ($shouldBackup) {
                $this->cleanupOldBackups();
            }
        }
    }

    /**
     * Run a new import job
     */
    private function runNewImport(string $source, bool $dryRun, bool $linkUsers, ?string $backupPath, ?int $limit): int
    {
        $job = ImportJob::create([
            'source' => $source,
            'status' => ImportJob::STATUS_PENDING,
        ]);

        return $this->executeJob($job, $dryRun, $linkUsers, $limit);
    }

    /**
     * Resume a failed or cancelled import
     */
    private function resumeImport(bool $dryRun, ?string $backupPath): int
    {
        /** @var ImportJob|null $job */
        $job = ImportJob::where('status', ImportJob::STATUS_FAILED)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $job) {
            $this->error('No failed import job found to resume');

            return self::FAILURE;
        }

        $this->info("Resuming import job #{$job->id} from: {$job->source}");

        return $this->executeJob($job, $dryRun, false, null);
    }

    /**
     * Execute the import job
     */
    private function executeJob(ImportJob $job, bool $dryRun, bool $linkUsers, ?int $limit): int
    {
        $job->markAsStarted();

        $this->newLine();
        $this->info('Job #'.$job->id.' started at '.$job->started_at->toDateTimeString());

        $source = $job->source;

        // Initialize stats
        $stats = [
            'tournaments_imported' => 0,
            'tournaments_updated' => 0,
            'tournaments_failed' => 0,
            'matches_imported' => 0,
            'matches_failed' => 0,
        ];

        try {
            // Import from tcomm
            if (in_array($source, ['tcomm', 'combined'])) {
                $this->newLine();
                $this->info('Importing from tcomm.hivie.tn...');

                $bar = $this->output->createProgressBar();
                $bar->start();

                $tcommStats = $this->importService->runTcommImport($job, $dryRun);

                $stats['tournaments_imported'] += $tcommStats['tournaments_imported'];
                $stats['tournaments_updated'] += $tcommStats['tournaments_updated'];
                $stats['tournaments_failed'] += $tcommStats['tournaments_failed'];

                $bar->finish();
                $this->newLine();
            }

            // Import from o!TR
            if (in_array($source, ['otr', 'combined'])) {
                $this->newLine();
                $this->info('Importing from o!TR...');

                $bar = $this->output->createProgressBar();
                $bar->start();

                $otrStats = $this->importService->runOtrImport($job, $dryRun, $limit);

                $stats['tournaments_imported'] += $otrStats['tournaments_imported'];
                $stats['tournaments_updated'] += $otrStats['tournaments_updated'];
                $stats['tournaments_failed'] += $otrStats['tournaments_failed'];
                $stats['matches_imported'] += $otrStats['matches_imported'];
                $stats['matches_failed'] += $otrStats['matches_failed'];

                $bar->finish();
                $this->newLine();
            }

            // Update job with final stats
            $job->updateProgress($stats);

            // Link users to matches if requested
            if ($linkUsers && ! $dryRun) {
                $this->newLine();
                $this->info('Linking users to matches...');

                $result = $this->importService->linkAllUsersToMatches(false);

                $this->info("Processed {$result['processed']} matches, linked {$result['linked']} participants");
            }

            // Mark job as completed
            if (! $dryRun) {
                $job->markAsCompleted();
            }

            $this->newLine();
            $this->info('Import completed!');
            $this->displayStats($stats, $job->error_log);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $job->markAsFailed($e->getMessage());

            $this->newLine();
            $this->error("Import failed: {$e->getMessage()}");
            $this->displayStats($stats, $job->error_log);

            return self::FAILURE;
        }
    }

    /**
     * Display import statistics
     *
     * @param  array<string, int>  $stats
     * @param  array<int, array<string, mixed>>|null  $errors
     */
    private function displayStats(array $stats, ?array $errors): void
    {
        $this->newLine();
        $this->info('=== Import Statistics ===');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Tournaments Imported', $stats['tournaments_imported']],
                ['Tournaments Updated', $stats['tournaments_updated']],
                ['Tournaments Failed', $stats['tournaments_failed']],
                ['Matches Imported', $stats['matches_imported']],
                ['Matches Failed', $stats['matches_failed']],
            ]
        );

        if ($errors && count($errors) > 0) {
            $this->newLine();
            $this->warn('Errors encountered: '.count($errors));

            // Show first 10 errors
            $displayErrors = array_slice($errors, 0, 10);
            foreach ($displayErrors as $error) {
                $type = $error['type'] ?? 'unknown';
                $id = $error['external_id'] ?? 'unknown';
                $message = $error['error'] ?? 'Unknown error';
                $this->line("  [{$type}] #{$id}: {$message}");
            }

            if (count($errors) > 10) {
                $this->line('  ... and '.(count($errors) - 10).' more errors');
            }
        }
    }
}
