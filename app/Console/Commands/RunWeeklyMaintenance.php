<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunWeeklyMaintenance extends Command
{
    protected $signature = 'maintenance:weekly-essential';

    protected $description = 'Sync essential podium user data, sync tournament badges, clean orphaned users, then prune database storage';

    public function handle(): int
    {
        Log::info('Weekly essential podium user sync started');

        $syncExitCode = Artisan::call('sync:user-profiles', [
            '--type' => 'winners',
            '--essential-only' => true,
            '--skip-sip' => true,
        ]);

        if ($syncExitCode !== self::SUCCESS) {
            Log::error('Weekly essential podium user sync failed', [
                'exit_code' => $syncExitCode,
            ]);

            $this->error("sync:user-profiles failed with exit code {$syncExitCode}");

            return self::FAILURE;
        }

        Log::info('Weekly essential podium user sync completed successfully');
        Log::info('Weekly tournament badge sync started');

        $tournamentSyncExitCode = Artisan::call('sync:tournament', []);

        if ($tournamentSyncExitCode !== self::SUCCESS) {
            Log::error('Weekly tournament badge sync failed', [
                'exit_code' => $tournamentSyncExitCode,
            ]);

            $this->error("sync:tournament failed with exit code {$tournamentSyncExitCode}");

            return self::FAILURE;
        }

        Log::info('Weekly tournament badge sync completed successfully');
        Log::info('Weekly orphaned user cleanup started');

        $cleanupExitCode = Artisan::call('cleanup:orphaned-users', [
            '--force' => true,
        ]);

        if ($cleanupExitCode !== self::SUCCESS) {
            Log::error('Weekly orphaned user cleanup failed', [
                'exit_code' => $cleanupExitCode,
            ]);

            $this->error("cleanup:orphaned-users failed with exit code {$cleanupExitCode}");

            return self::FAILURE;
        }

        Log::info('Weekly orphaned user cleanup completed successfully');
        Log::info('Weekly database storage prune started');

        $databasePruneExitCode = Artisan::call('maintenance:database-prune', [
            '--force' => true,
        ]);

        if ($databasePruneExitCode !== self::SUCCESS) {
            Log::error('Weekly database storage prune failed', [
                'exit_code' => $databasePruneExitCode,
            ]);

            $this->error("maintenance:database-prune failed with exit code {$databasePruneExitCode}");

            return self::FAILURE;
        }

        Log::info('Weekly database storage prune completed successfully');
        $this->info('Weekly maintenance completed successfully.');

        return self::SUCCESS;
    }
}
