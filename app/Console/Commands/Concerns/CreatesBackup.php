<?php

namespace App\Console\Commands\Concerns;

use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Trait for commands that create database backups before modifying data
 *
 * This trait automatically creates database backups before running
 * data-modifying commands. Provides automatic rollback on failure.
 *
 * Configuration via .env:
 * - BACKUP_BEFORE_MODIFICATION=true (default: true)
 * - BACKUP_DISK=local (default: local)
 * - BACKUP_RETENTION_DAYS=7 (default: 7 days)
 *
 * Usage:
 *   use CreatesBackup;
 *
 *   public function handle()
 *   {
 *       $backupPath = $this->createBackup();
 *
 *       try {
 *           // Your command logic here
 *           return Command::SUCCESS;
 *       } catch (\Exception $e) {
 *           $this->restoreBackup($backupPath);
 *           throw $e;
 *       }
 *   }
 */
trait CreatesBackup
{
    /**
     * Create a database backup before command execution
     *
     * Creates a timestamped SQL dump of the database.
     * Returns the backup file path for potential restoration.
     *
     * @return string|null Backup file path, or null if backup was skipped
     */
    protected function createBackup(): ?string
    {
        if (app()->environment('testing')) {
            $this->warn('Backup skipped in the testing environment.');

            return null;
        }

        // Check if backup is enabled
        if (! $this->shouldCreateBackup()) {
            $this->warn('Backup skipped (BACKUP_ENABLED=false)');

            return null;
        }

        $this->info('Creating database backup...');

        $startTime = microtime(true);
        $success = false;
        $details = [];
        $localPath = null;

        try {
            // Create local backup
            $localPath = $this->performBackup();
            $this->info("✅ Local backup created: {$localPath}");

            // Get metrics before upload (file gets deleted during upload)
            $sizeBytes = filesize($localPath);
            $duration = round(microtime(true) - $startTime, 2);

            // Upload to R2
            $r2Path = $this->uploadBackupToR2($localPath);
            $this->info("☁️  Uploaded to R2: {$r2Path}");

            $details = [
                'size_bytes' => $sizeBytes,
                'duration_seconds' => $duration,
                'r2_path' => $r2Path,
            ];

            $success = true;

            return $r2Path; // Return R2 path instead of local path

        } catch (\Exception $e) {
            $details = [
                'error' => $e->getMessage(),
            ];

            $this->error("❌ Backup failed: {$e->getMessage()}");
            $this->warn('Proceeding with command execution without backup...');

            return null;
        } finally {
            // Send Discord notification
            $this->sendBackupNotification($success, $details);
        }
    }

    /**
     * Restore database from backup
     *
     * Restores the database from the specified backup file.
     * Supports both local files and R2 paths.
     * Used when command execution fails.
     *
     * @param  string|null  $backupPath  Path to backup file (local or R2)
     * @return bool True if restore succeeded, false otherwise
     */
    protected function restoreBackup(?string $backupPath): bool
    {
        if (app()->environment('testing')) {
            $this->warn('Database restore is disabled in the testing environment.');

            return false;
        }

        if ($backupPath === null) {
            $this->warn('No backup to restore');

            return false;
        }

        // Check if this is an R2 path (contains backup directory)
        $directory = config('database.backup.directory', 'backups');
        $isR2Path = str_starts_with($backupPath, $directory.'/');

        $localPath = $backupPath;

        // If it's an R2 path, download to temporary file
        if ($isR2Path) {
            $diskName = config('database.backup.disk', 's3');

            $this->warn("Downloading backup from R2: {$backupPath}");

            try {
                // Create temporary file
                $localPath = tempnam(sys_get_temp_dir(), 'db_restore_');
                $stream = Storage::disk($diskName)->readStream($backupPath);
                file_put_contents($localPath, stream_get_contents($stream));

                $this->info("✅ Downloaded to temporary file: {$localPath}");
            } catch (\Exception $e) {
                $this->error("❌ Failed to download from R2: {$e->getMessage()}");
                $this->error('⚠️  Cannot restore without backup file!');

                return false;
            }
        } elseif (! file_exists($backupPath)) {
            $this->warn('No backup to restore (file does not exist)');

            return false;
        }

        $this->warn('Restoring database from backup...');

        try {
            $this->performRestore($localPath);
            $this->info('✅ Database restored successfully');

            // Clean up temp file if we downloaded from R2
            if ($isR2Path && file_exists($localPath)) {
                unlink($localPath);
            }

            return true;
        } catch (\Exception $e) {
            $this->error("❌ Restore failed: {$e->getMessage()}");
            $this->error('⚠️  Database may be in inconsistent state - manual intervention required!');

            // Clean up temp file on error too
            if ($isR2Path && file_exists($localPath)) {
                unlink($localPath);
            }

            return false;
        }
    }

    /**
     * Clean up old backups based on retention policy
     *
     * Removes backups older than BACKUP_RETENTION_DAYS from R2 storage.
     * Called after successful command execution.
     */
    protected function cleanupOldBackups(): void
    {
        $retentionDays = (int) config('database.backup.retention_days', 7);
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
     * Check if backup should be created
     *
     * Reads BACKUP_ENABLED environment variable.
     */
    protected function shouldCreateBackup(): bool
    {
        // Check environment variable
        $enabled = env('BACKUP_ENABLED', 'true');

        // Check if --no-backup flag was provided
        if ($this->hasOption('no-backup') && $this->option('no-backup')) {
            return false;
        }

        return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Perform the actual backup operation
     *
     * Uses pg_dump for PostgreSQL databases.
     * Creates timestamped backup file.
     *
     * @return string Backup file path
     *
     * @throws \Exception
     */
    protected function performBackup(): string
    {
        $disk = config('database.backup.disk', 'local');
        $directory = config('database.backup.directory', 'backups');
        $database = config('database.connections.'.config('database.default').'.database');

        // Ensure backup directory exists
        $diskPath = storage_path('app'.($disk === 'local' ? '' : '/'));
        $fullPath = $diskPath.'/'.$directory;

        if (! is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        // Generate backup filename
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "{$database}_{$timestamp}.sql.gz";
        $backupPath = "{$fullPath}/{$filename}";

        // Build pg_dump command
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

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
     *
     * @param  string  $localPath  Path to local backup file
     * @return string R2 path (e.g., "backups/tourney_method_2026-03-09_12-34-56.sql.gz")
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
     *
     * @param  string  $disk  Storage disk name
     * @param  string  $r2Path  R2 file path
     * @param  int  $expectedSize  Expected file size in bytes
     *
     * @throws \Exception if verification fails
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
     * Send Discord notification for backup operation
     *
     * @param  bool  $success  Whether backup succeeded
     * @param  array<string, mixed>  $details  Backup details (size, duration, path, etc.)
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

    /**
     * Perform the actual restore operation
     *
     * Uses gunzip and psql for PostgreSQL databases.
     *
     * @param  string  $backupPath  Path to backup file
     *
     * @throws \Exception
     */
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

        // Execute restore
        $output = [];
        $returnCode = 0;

        exec($command.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('psql restore failed: '.implode("\n", $output));
        }
    }
}
