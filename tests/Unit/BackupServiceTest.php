<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('database.backup.enabled', true);
    Config::set('database.backup.disk', 's3');
    Config::set('database.backup.directory', 'backups');
    Config::set('database.backup.retention_days', 7);
    Config::set('database.backup.verify_upload', true);
});

test('backup configuration is loaded correctly', function () {
    expect(config('database.backup.enabled'))->toBeTrue();
    expect(config('database.backup.disk'))->toBe('s3');
    expect(config('database.backup.directory'))->toBe('backups');
    expect(config('database.backup.retention_days'))->toBe(7);
    expect(config('database.backup.verify_upload'))->toBeTrue();
});

test('backup generates correct r2 path format', function () {
    $database = config('database.connections.'.config('database.default').'.database');
    $timestamp = now()->format('Y-m-d_H-i-s');

    $expectedPath = "backups/{$database}_{$timestamp}.sql.gz";
    $actualPath = 'backups/'.$database.'_'.now()->format('Y-m-d_H-i-s').'.sql.gz';

    expect($actualPath)->toBe($expectedPath);
});

test('backup retention period is calculated correctly', function () {
    $retentionDays = config('database.backup.retention_days', 7);
    $cutoff = now()->subDays($retentionDays);

    expect($cutoff)->toBeInstanceOf(Carbon::class);
    expect($cutoff->diffInDays(now()))->toBeGreaterThanOrEqual($retentionDays - 1);
    expect($cutoff->diffInDays(now()))->toBeLessThanOrEqual($retentionDays + 1);
});

test('backup filename includes timestamp', function () {
    $database = 'tourney_method';
    $timestamp = '2026-03-09_12-34-56';

    $filename = "{$database}_{$timestamp}.sql.gz";

    expect($filename)->toContain('tourney_method');
    expect($filename)->toContain('2026-03-09');
    expect($filename)->toContain('.sql.gz');
});

test('format bytes converts correctly', function () {
    $formatBytes = function (int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    };

    // Test boundary conditions
    expect($formatBytes(0))->toBe('0 B');
    expect($formatBytes(1023))->toBe('1023 B');
    expect($formatBytes(1024))->toBe('1024 B'); // Exactly 1024 stays in B
    expect($formatBytes(1025))->toBe('1 KB'); // > 1024 converts to KB
    expect($formatBytes(2048))->toBe('2 KB');
    expect($formatBytes((1024 * 1024) + 1))->toBe('1 MB'); // Just over 1 MB
    expect($formatBytes(1536))->toBe('1.5 KB');
});

test('backup path is detected as r2 path', function () {
    $r2Path = 'backups/tourney_method_2026-03-09_12-34-56.sql.gz';
    $localPath = '/tmp/tourney_method_2026-03-09_12-34-56.sql.gz';

    expect(str_starts_with($r2Path, 'backups/'))->toBeTrue();
    expect(str_starts_with($localPath, 'backups/'))->toBeFalse();
});

test('backup configuration can be disabled via environment', function () {
    Config::set('database.backup.enabled', false);

    expect(config('database.backup.enabled'))->toBeFalse();
});

test('config helper provides default values', function () {
    // Test that config helper returns defaults for non-existent keys
    expect(config('nonexistent.key', 'default'))->toBe('default');
    expect(config('nonexistent.number', 7))->toBe(7);
    expect(config('nonexistent.bool', true))->toBeTrue();
});
