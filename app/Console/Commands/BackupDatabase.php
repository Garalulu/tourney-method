<?php

namespace App\Console\Commands;

use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
                            {--description= : Optional description for this backup}
                            {--disk=s3 : Storage disk (default: s3 for R2)}';

    protected $description = 'Create on-demand database backup to R2';

    public function handle(): int
    {
        $description = $this->option('description') ?? 'Manual backup';
        $disk = $this->option('disk');

        $this->info('Creating manual database backup...');
        $this->info("   Description: {$description}");
        $this->info("   Storage: {$disk}");

        if (app()->environment('testing')) {
            $this->error('External database backup is disabled in the testing environment.');

            return self::FAILURE;
        }

        $startTime = microtime(true);
        $success = false;
        $details = [];
        $localPath = null;

        try {
            $localPath = $this->performBackup();
            $this->info("Local backup created: {$localPath}");

            // Get file size before upload (file gets deleted during upload)
            $sizeBytes = filesize($localPath);
            $duration = round(microtime(true) - $startTime, 2);

            $r2Path = $this->uploadBackupToR2($localPath);
            $this->info("Uploaded to R2: {$r2Path}");

            $this->info("Backup completed in {$duration}s");
            $this->info("Size: {$this->formatBytes($sizeBytes)}");
            $this->info("R2 Path: {$r2Path}");

            $details = [
                'size_bytes' => $sizeBytes,
                'duration_seconds' => $duration,
                'r2_path' => $r2Path,
                'description' => $description,
            ];

            $success = true;

        } catch (\Exception $e) {
            $this->error("Backup failed: {$e->getMessage()}");

            $details = [
                'error' => $e->getMessage(),
                'description' => $description,
            ];

            Log::error('Manual backup failed', [
                'error' => $e->getMessage(),
                'description' => $description,
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->sendBackupNotification($success, $details);
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    protected function performBackup(): string
    {
        $database = config('database.connections.'.config('database.default').'.database');
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $tempDir = sys_get_temp_dir();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "{$database}_{$timestamp}.sql.gz";
        $backupPath = "{$tempDir}/{$filename}";

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --clean --if-exists | gzip > %s',
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($database),
            escapeshellarg($backupPath)
        );

        $output = [];
        $returnCode = 0;

        exec($command.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('pg_dump failed: '.implode("\n", $output));
        }

        return $backupPath;
    }

    protected function uploadBackupToR2(string $localPath): string
    {
        $disk = config('database.backup.disk', 's3');
        $directory = config('database.backup.directory', 'backups');
        $database = config('database.connections.'.config('database.default').'.database');

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "{$database}_{$timestamp}.sql.gz";
        $r2Path = "{$directory}/{$filename}";

        Storage::disk($disk)->put(
            $r2Path,
            file_get_contents($localPath),
            ['visibility' => 'private']
        );

        Log::info('Manual backup uploaded to R2', [
            'r2_path' => $r2Path,
            'local_path' => $localPath,
            'size_bytes' => filesize($localPath),
        ]);

        if (config('database.backup.verify_upload', true)) {
            $this->verifyR2Upload($disk, $r2Path, filesize($localPath));
        }

        if (file_exists($localPath)) {
            unlink($localPath);
        }

        return $r2Path;
    }

    protected function verifyR2Upload(string $disk, string $r2Path, int $expectedSize): void
    {
        $exists = Storage::disk($disk)->exists($r2Path);
        $actualSize = Storage::disk($disk)->size($r2Path);

        if (! $exists) {
            throw new \Exception("R2 upload verification failed: File does not exist at {$r2Path}");
        }

        if ($actualSize !== $expectedSize) {
            throw new \Exception("R2 upload size mismatch: expected {$expectedSize} bytes, got {$actualSize} bytes");
        }
    }

    /**
     * Send Discord notification for backup operation
     *
     * @param  bool  $success  Whether backup succeeded
     * @param  array<string, mixed>  $details  Backup details (size, duration, path, error, etc.)
     */
    protected function sendBackupNotification(bool $success, array $details): void
    {
        $webhookUrl = config('database.backup.discord_webhook');

        if (! $webhookUrl) {
            return;
        }

        $discord = app(DiscordService::class);

        if ($success) {
            $embed = [
                'title' => 'Manual Database Backup',
                'description' => 'Manual database backup completed and uploaded to R2.',
                'color' => 0x00FF00,
                'fields' => [
                    [
                        'name' => 'Description',
                        'value' => $details['description'] ?? 'Manual backup',
                        'inline' => true,
                    ],
                    [
                        'name' => 'Size',
                        'value' => $this->formatBytes($details['size_bytes'] ?? 0),
                        'inline' => true,
                    ],
                    [
                        'name' => 'Duration',
                        'value' => ($details['duration_seconds'] ?? 0).'s',
                        'inline' => true,
                    ],
                    [
                        'name' => 'R2 Path',
                        'value' => '`'.($details['r2_path'] ?? 'N/A').'`',
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
                'title' => 'Manual Database Backup Failed',
                'description' => 'Failed to create manual database backup.',
                'color' => 0xFF0000,
                'fields' => [
                    [
                        'name' => 'Description',
                        'value' => $details['description'] ?? 'Manual backup',
                        'inline' => true,
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

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
