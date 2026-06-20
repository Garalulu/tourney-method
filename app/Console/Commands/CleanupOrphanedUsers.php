<?php

namespace App\Console\Commands;

use App\Services\OrphanedUserCleanupService;
use App\Services\UserDeletionCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupOrphanedUsers extends Command
{
    protected $signature = 'cleanup:orphaned-users
                            {--dry-run : Preview what would be deleted without deleting}
                            {--force : Skip confirmation prompt}
                            {--batch-size=100 : Number of users to process at once}
                            {--min-age=0 : Minimum days since user creation (0 = all)}
                            {--whitelist= : Custom whitelist file path (JSON array)}';

    protected $description = 'Clean up orphaned users who have no tournament participation';

    private int $deleted = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(OrphanedUserCleanupService $orphanedUsers, UserDeletionCleanupService $userDeletionCleanup): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        $minAge = (int) $this->option('min-age');
        $customWhitelist = $this->option('whitelist');

        $whitelist = $this->getWhitelist($customWhitelist);
        $this->info('Scanning for orphaned users...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $query = $orphanedUsers->query($whitelist, $minAge);

        if ($minAge > 0) {
            $this->info("Only users created {$minAge}+ days ago will be considered.");
        }

        // Count total orphaned users (clone query to avoid consuming it)
        $totalOrphaned = (clone $query)->count();
        $this->info("Found {$totalOrphaned} orphaned user(s) matching criteria.");
        $this->newLine();

        if ($totalOrphaned === 0) {
            $this->info('No orphaned users found.');

            return self::SUCCESS;
        }

        // Preview users to be deleted
        $this->info('Users to be deleted:');
        $this->newLine();

        (clone $query)->with(['tournamentWinners', 'tournaments', 'tournamentsHosted'])
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $mainModeSource = $user->main_mode_source ?? 'null';
                    $this->line("  - ID: {$user->id}, Username: {$user->username}");
                    $this->line("    Role: {$user->role}, Main Mode Source: {$mainModeSource}");
                    $this->line("    Created: {$user->created_at->format('Y-m-d H:i:s')}");
                }
            });

        $this->newLine();

        if ($dryRun) {
            $this->info('Dry run complete - no users were deleted.');

            return self::SUCCESS;
        }

        // Ask for confirmation
        if (! $force) {
            if (! $this->confirm("Delete these {$totalOrphaned} orphaned users?")) {
                $this->info('Cleanup cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Deleting orphaned users...');
        $this->newLine();

        // Process users in batches with progress bar
        $this->output->progressStart($totalOrphaned);

        (clone $query)->chunkById($batchSize, function ($users) use ($orphanedUsers, $userDeletionCleanup) {
            foreach ($users as $user) {
                try {
                    // Double-check protection rules before deletion
                    if ($orphanedUsers->isProtected($user, $this->getWhitelist($this->option('whitelist')))) {
                        $this->skipped++;
                        $this->output->progressAdvance();

                        continue;
                    }

                    $cleanup = DB::transaction(function () use ($user, $userDeletionCleanup): array {
                        $cleanup = $userDeletionCleanup->cleanupBeforeDelete($user);
                        $user->delete();

                        return $cleanup;
                    });
                    $this->deleted++;

                    // Log deletion
                    Log::info('Soft deleted orphaned user', [
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'role' => $user->role,
                        'main_mode_source' => $user->main_mode_source,
                        'created_at' => $user->created_at,
                        'cleanup' => $cleanup,
                    ]);
                } catch (\Exception $e) {
                    $this->failed++;
                    $this->error("Failed to delete user {$user->username}: {$e->getMessage()}");

                    Log::error('Failed to delete orphaned user', [
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->output->progressAdvance();
            }
        });

        $this->output->progressFinish();
        $this->newLine();

        $this->info("Cleanup complete: {$this->deleted} deleted, {$this->skipped} skipped, {$this->failed} failed");

        // Log summary
        Log::info('Orphaned users cleanup complete', [
            'deleted' => $this->deleted,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'total_found' => $totalOrphaned,
        ]);

        return self::SUCCESS;
    }

    /**
     * Get whitelist from config or custom file
     *
     * @return array<string>
     */
    private function getWhitelist(?string $customWhitelist): array
    {
        if ($customWhitelist) {
            if (! file_exists($customWhitelist)) {
                $this->error("Custom whitelist file not found: {$customWhitelist}");

                return [];
            }

            $content = file_get_contents($customWhitelist);
            $whitelist = json_decode($content, true);

            if (! is_array($whitelist)) {
                $this->error('Invalid whitelist file format. Expected JSON array.');

                return [];
            }

            return $whitelist;
        }

        return Config::get('user-cleanup.whitelist', []);
    }
}
