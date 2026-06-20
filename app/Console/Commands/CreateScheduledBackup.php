<?php

namespace App\Console\Commands;

use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateScheduledBackup extends Command
{
    protected $signature = 'backups:create
                            {--description= : Optional description for this backup}';

    protected $description = 'Create scheduled database backup (runs before tournament parsing)';

    public function handle(): int
    {
        if (app()->environment('testing')) {
            $this->error('External database backup is disabled in the testing environment.');

            return self::FAILURE;
        }

        $this->info('📦 Creating scheduled database backup...');

        $startTime = microtime(true);
        $success = false;
        $details = [];
        $localPath = null;

        try {
            // Perform backup
            $localPath = $this->performBackup();
            $this->info("✅ Local backup created: {$localPath}");

            // Get metrics before upload (file gets deleted during upload)
            $sizeBytes = filesize($localPath);
            $duration = round(microtime(true) - $startTime, 2);

            // Upload to R2
            $r2Path = $this->uploadBackupToR2($localPath);
            $this->info("☁️  Uploaded to R2: {$r2Path}");

            // Clean up old backups
            $this->cleanupOldBackups();

            $this->info("✅ Backup completed in {$duration}s");
            $this->info("   Size: {$this->formatBytes($sizeBytes)}");
            $this->info("   R2 Path: {$r2Path}");

            $details = [
                'size_bytes' => $sizeBytes,
                'duration_seconds' => $duration,
                'r2_path' => $r2Path,
            ];

            $success = true;

        } catch (\Exception $e) {
            $this->error("❌ Backup failed: {$e->getMessage()}");

            $details = [
                'error' => $e->getMessage(),
            ];

            Log::error('Scheduled backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // Send Discord notification
            $this->sendBackupNotification($success, $details);
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Perform the actual backup operation
     */
    protected function performBackup(): string
    {
        $database = config('database.connections.'.config('database.default').'.database');
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        // Create temporary directory
        $tempDir = sys_get_temp_dir();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "{$database}_{$timestamp}.sql.gz";
        $backupPath = "{$tempDir}/{$filename}";

        // Build pg_dump command
        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --clean --if-exists | gzip > %s',
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($database),
            escapeshellarg($backupPath)
        );

        // Execute backup
        $output = [];
        $returnCode = 0;

        exec($command.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('pg_dump failed: '.implode("\n", $output));
        }

        return $backupPath;
    }

    /**
     * Upload backup file to R2 storage
     */
    protected function uploadBackupToR2(string $localPath): string
    {
        $disk = config('database.backup.disk', 's3');
        $directory = config('database.backup.directory', 'backups');
        $database = config('database.connections.'.config('database.default').'.database');

        // Generate R2 path
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "{$database}_{$timestamp}.sql.gz";
        $r2Path = "{$directory}/{$filename}";

        // Upload to R2
        Storage::disk($disk)->put(
            $r2Path,
            file_get_contents($localPath),
            ['visibility' => 'private']
        );

        Log::info('Backup uploaded to R2', [
            'r2_path' => $r2Path,
            'local_path' => $localPath,
            'size_bytes' => filesize($localPath),
        ]);

        // Verify upload if enabled
        if (config('database.backup.verify_upload', true)) {
            $this->verifyR2Upload($disk, $r2Path, filesize($localPath));
        }

        // Delete local file after successful upload
        if (file_exists($localPath)) {
            unlink($localPath);
        }

        return $r2Path;
    }

    /**
     * Verify R2 upload was successful
     */
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

        Log::info('R2 upload verified', [
            'r2_path' => $r2Path,
            'size_bytes' => $actualSize,
        ]);
    }

    /**
     * Clean up old backups from R2
     */
    protected function cleanupOldBackups(): void
    {
        $retentionDays = config('database.backup.retention_days', 7);
        $diskName = config('database.backup.disk', 's3');
        $directory = config('database.backup.directory', 'backups');

        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        // List all files in backups directory
        $files = Storage::disk($diskName)->files($directory);

        foreach ($files as $file) {
            // Get last modified timestamp
            $lastModified = Storage::disk($diskName)->lastModified($file);

            // Delete if older than retention period
            if ($lastModified < $cutoff->timestamp) {
                Storage::disk($diskName)->delete($file);
                $deleted++;
                Log::info('Deleted old backup from R2', [
                    'file' => $file,
                    'age_days' => round((now()->timestamp - $lastModified) / 86400, 1),
                ]);
            }
        }

        if ($deleted > 0) {
            $this->info("🗑️  Cleaned up {$deleted} old backup(s) from R2 (older than {$retentionDays} days)");
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
            return; // Notifications disabled
        }

        $discord = app(DiscordService::class);

        if ($success) {
            $embed = [
                'title' => '✅ Database Backup Successful',
                'description' => 'Database backup completed and uploaded to R2.',
                'color' => 0x00FF00, // Green
                'fields' => [
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
                'title' => '❌ Database Backup Failed',
                'description' => 'Failed to create or upload database backup to R2.',
                'color' => 0xFF0000, // Red
                'fields' => [
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

    /**
     * Format bytes for human-readable display
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
