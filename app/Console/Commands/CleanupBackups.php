<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupBackups extends Command
{
    protected $signature = 'db:clean
                            {--days=7 : Delete backups older than N days}
                            {--force : Skip confirmation}
                            {--disk=s3 : Storage disk}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Clean up old backups from R2';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $disk = $this->option('disk');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $directory = config('database.backup.directory', 'backups');

        $this->info("Scanning {$disk}://{$directory} for backups older than {$days} days...");

        $cutoff = now()->subDays($days);
        $files = Storage::disk($disk)->files($directory);

        $toDelete = [];
        $totalSize = 0;

        foreach ($files as $file) {
            $lastModified = Storage::disk($disk)->lastModified($file);

            if ($lastModified < $cutoff->timestamp) {
                $size = Storage::disk($disk)->size($file);
                $toDelete[] = [
                    'file' => $file,
                    'size' => $size,
                    'age_days' => round((now()->timestamp - $lastModified) / 86400, 1),
                ];
                $totalSize += $size;
            }
        }

        if (empty($toDelete)) {
            $this->info('No backups found matching the criteria.');

            return self::SUCCESS;
        }

        usort($toDelete, function ($a, $b) {
            return $b['age_days'] <=> $a['age_days'];
        });

        $this->info('Found '.count($toDelete).' backup(s) to delete:');
        $this->newLine();

        foreach ($toDelete as $file) {
            $this->line("  - {$file['file']}");
            $this->line("    Size: {$this->formatBytes($file['size'])}, Age: {$file['age_days']} days");
        }

        $this->newLine();
        $this->info("Total space to be freed: {$this->formatBytes($totalSize)}");

        if ($dryRun) {
            $this->info('Dry run complete - no files were deleted.');

            return self::SUCCESS;
        }

        if (! $force) {
            if (! $this->confirm('Delete these backups?')) {
                $this->info('Cleanup cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Deleting backups...');

        $deleted = 0;
        $failed = 0;

        foreach ($toDelete as $file) {
            try {
                Storage::disk($disk)->delete($file['file']);
                $deleted++;

                Log::info('Deleted old backup from R2', [
                    'file' => $file['file'],
                    'age_days' => $file['age_days'],
                ]);

                $this->line("  Deleted: {$file['file']}");
            } catch (\Exception $e) {
                $failed++;
                $this->error("  Failed to delete {$file['file']}: {$e->getMessage()}");

                Log::error('Failed to delete backup', [
                    'file' => $file['file'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Cleanup complete: {$deleted} deleted, {$failed} failed");
        $this->info("Space freed: {$this->formatBytes($totalSize)}");

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
