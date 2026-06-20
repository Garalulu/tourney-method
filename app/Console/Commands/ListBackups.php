<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ListBackups extends Command
{
    protected $signature = 'db:list
                            {--disk=s3 : Storage disk}
                            {--days=7 : Only show backups from last N days}
                            {--all : Show all backups regardless of age}';

    protected $description = 'List available database backups in R2';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $days = (int) $this->option('days');
        $showAll = $this->option('all');
        $directory = config('database.backup.directory', 'backups');

        $this->info("Listing backups from {$disk}://{$directory}...");

        $files = Storage::disk($disk)->files($directory);

        if (empty($files)) {
            $this->warn('No backups found.');

            return self::SUCCESS;
        }

        if (! $showAll) {
            $cutoff = now()->subDays($days);
            $files = array_filter($files, function ($file) use ($disk, $cutoff) {
                return Storage::disk($disk)->lastModified($file) >= $cutoff->timestamp;
            });

            if (empty($files)) {
                $this->warn("No backups found from the last {$days} days.");
                $this->info('Use --all to see all backups regardless of age.');

                return self::SUCCESS;
            }
        }

        usort($files, function ($a, $b) use ($disk) {
            return Storage::disk($disk)->lastModified($b) <=> Storage::disk($disk)->lastModified($a);
        });

        $tableRows = [];
        $totalSize = 0;

        foreach ($files as $file) {
            $size = Storage::disk($disk)->size($file);
            $lastModified = Storage::disk($disk)->lastModified($file);

            $tableRows[] = [
                'path' => $file,
                'size' => $this->formatBytes($size),
                'date' => date('Y-m-d H:i:s', $lastModified),
                'age' => $this->formatAge($lastModified),
            ];

            $totalSize += $size;
        }

        $this->table(
            ['Path', 'Size', 'Date', 'Age'],
            $tableRows
        );

        $this->info('Total: '.count($files).' backup(s)');
        $this->info("Total size: {$this->formatBytes($totalSize)}");

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

    protected function formatAge(int $timestamp): string
    {
        $diff = now()->timestamp - $timestamp;
        $hours = floor($diff / 3600);
        $days = floor($hours / 24);

        if ($days > 0) {
            return "{$days} day(s) ago";
        } elseif ($hours > 0) {
            $remainingHours = $hours % 24;

            return "{$remainingHours} hour(s) ago";
        } else {
            $minutes = floor($diff / 60);

            return "{$minutes} minute(s) ago";
        }
    }
}
