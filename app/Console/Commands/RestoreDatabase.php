<?php

namespace App\Console\Commands;

use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RestoreDatabase extends Command
{
    protected $signature = 'db:restore
                            {backup_file : R2 path (e.g., backups/tourney_method_2026-03-09_12-34-56.sql.gz)}
                            {--disk=s3 : Storage disk}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Restore database from R2 backup';

    public function handle(): int
    {
        $r2Path = $this->argument('backup_file');
        $disk = $this->option('disk');
        $force = $this->option('force');

        $this->warn("Restore from: {$r2Path}");
        $this->warn("Storage disk: {$disk}");
        $this->warn('This will REPLACE the current database!');

        if (! $force) {
            if (! $this->confirm('Do you wish to continue?')) {
                $this->info('Restore cancelled.');

                return self::SUCCESS;
            }
        }

        if (app()->environment('testing')) {
            $this->error('External database restore is disabled in the testing environment.');

            return self::FAILURE;
        }

        $this->info("Restoring database from {$r2Path}...");

        $success = false;
        $details = ['r2_path' => $r2Path];
        $tempPath = null;

        try {
            $diskName = config('database.backup.disk', 's3');

            $this->info('Downloading backup from R2...');
            $tempPath = tempnam(sys_get_temp_dir(), 'db_restore_');
            $stream = Storage::disk($diskName)->readStream($r2Path);
            file_put_contents($tempPath, stream_get_contents($stream));

            $this->info("Downloaded to temporary file: {$tempPath}");

            $this->info('Restoring database...');
            $this->performRestore($tempPath);

            $this->info('Database restored successfully');

            $success = true;

        } catch (\Exception $e) {
            $this->error("Restore failed: {$e->getMessage()}");
            $details['error'] = $e->getMessage();

            Log::error('Database restore failed', [
                'backup' => $r2Path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                unlink($tempPath);
            }

            $this->sendRestoreNotification($success, $details);
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    protected function performRestore(string $backupPath): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $command = sprintf(
            'gunzip -c %s | PGPASSWORD=%s psql -h %s -p %s -U %s -d %s',
            escapeshellarg($backupPath),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($config['database'])
        );

        $output = [];
        $returnCode = 0;

        exec($command.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('psql restore failed: '.implode("\n", $output));
        }
    }

    /**
     * Send Discord notification for restore operation
     *
     * @param  bool  $success  Whether restore succeeded
     * @param  array<string, mixed>  $details  Restore details (r2_path, error, etc.)
     */
    protected function sendRestoreNotification(bool $success, array $details): void
    {
        $webhookUrl = config('database.backup.discord_webhook');

        if (! $webhookUrl) {
            return;
        }

        $discord = app(DiscordService::class);

        if ($success) {
            $embed = [
                'title' => 'Database Restored',
                'description' => 'Database was successfully restored from backup.',
                'color' => 0xFFA500,
                'fields' => [
                    [
                        'name' => 'Backup',
                        'value' => '`'.($details['r2_path'] ?? 'N/A').'`',
                        'inline' => false,
                    ],
                    [
                        'name' => 'Timestamp',
                        'value' => now()->format('Y-m-d H:i:s T'),
                        'inline' => false,
                    ],
                ],
                'footer' => [
                    'text' => 'Tourney Method Backups',
                ],
                'timestamp' => now()->toIso8601String(),
            ];
        } else {
            $embed = [
                'title' => 'Database Restore Failed',
                'description' => 'Failed to restore database from backup.',
                'color' => 0xFF0000,
                'fields' => [
                    [
                        'name' => 'Backup',
                        'value' => '`'.($details['r2_path'] ?? 'N/A').'`',
                        'inline' => false,
                    ],
                    [
                        'name' => 'Error',
                        'value' => '```'.($details['error'] ?? 'Unknown error').'```',
                        'inline' => false,
                    ],
                ],
                'footer' => [
                    'text' => 'Tourney Method Backups',
                ],
                'timestamp' => now()->toIso8601String(),
            ];
        }

        $discord->sendEmbed($webhookUrl, $embed);
    }
}
