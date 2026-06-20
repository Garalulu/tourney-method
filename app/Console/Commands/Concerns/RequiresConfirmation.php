<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Console\Command;

/**
 * Trait for commands that require explicit confirmation before modifying data
 *
 * This trait adds a --confirm flag to commands that modify data.
 * Commands must have --confirm flag set to actually execute.
 * Prevents accidental data modification from typos or mistaken command execution.
 *
 * Usage:
 *   use RequiresConfirmation;
 *
 *   public function handle()
 *   {
 *       if (!$this->confirmExecution()) {
 *           return Command::FAILURE;
 *       }
 *
 *       // Your command logic here
 *   }
 */
trait RequiresConfirmation
{
    /**
     * Check if command execution is confirmed
     *
     * Verifies that --confirm flag is provided before allowing destructive operations.
     * Outputs helpful error message if not confirmed.
     *
     * @return bool True if confirmed, false otherwise
     */
    protected function confirmExecution(): bool
    {
        if (! $this->hasOption('confirm') || ! $this->option('confirm')) {
            $this->error('⚠️  This command will modify data and requires explicit confirmation.');
            $this->newLine();
            $this->line('To proceed, add the --confirm flag:');
            $this->info('  php artisan '.$this->getName().' --confirm');
            $this->newLine();
            $this->line('Use --dry-run flag first to preview changes:');
            $this->info('  php artisan '.$this->getName().' --dry-run');
            $this->newLine();

            return false;
        }

        return true;
    }

    /**
     * Get the console command options
     *
     * Adds --confirm flag to command options
     */
    protected function getOptionsWithConfirmation(): array
    {
        return array_merge(
            $this->getOptions(),
            [
                ['confirm', null, InputOption::VALUE_NONE, 'Confirm execution of data-modifying command'],
            ]
        );
    }
}
