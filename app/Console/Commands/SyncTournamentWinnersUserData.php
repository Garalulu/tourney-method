<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncTournamentWinnersUserData extends Command
{
    protected $signature = 'winners:sync-user-data {--dry-run : Preview changes without executing}';

    protected $description = 'Sync tournament winners user data from osu! API (DEPRECATED - use sync:user-profiles)';

    public function handle(): int
    {
        $this->warn('This command is deprecated. Use: php artisan sync:user-profiles --type=winners --queue');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('DRY RUN MODE: No changes will be made');
            $this->info('To execute the sync, run: php artisan sync:user-profiles --type=winners');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->info('Calling sync:user-profiles --type=winners --queue...');

        Artisan::call('sync:user-profiles', [
            '--type' => 'winners',
            '--queue' => true,
        ]);

        $this->info('Job dispatched successfully!');
        $this->newLine();
        $this->info('Monitor the job with: php artisan queue:listen');
        $this->info('Or check Horizon at: http://localhost/horizon');

        return self::SUCCESS;
    }
}
