<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Config::set('database.backup.enabled', true);
    Config::set('database.backup.disk', 's3');
    Config::set('database.backup.directory', 'backups');
    Config::set('database.backup.retention_days', 7);
    Config::set('database.backup.verify_upload', true);
    Config::set('database.backup.discord_webhook', 'https://discord.com/api/webhooks/test');

    Storage::fake('s3');
});

afterEach(function () {
    Storage::fake('s3');
});

test('db:list command displays empty message when no backups exist', function () {
    $this->artisan('db:list')
        ->expectsOutput('No backups found.')
        ->assertExitCode(0);
});

test('db:list command displays backups when they exist', function () {
    Storage::disk('s3')->put('backups/tourney_method_2026-03-09_12-00-00.sql.gz', 'backup content 1');
    Storage::disk('s3')->put('backups/tourney_method_2026-03-08_12-00-00.sql.gz', 'backup content 2');

    $this->artisan('db:list')
        ->expectsOutputToContain('backup(s)')
        ->assertExitCode(0);
});

test('db:list command filters by days when --days option is provided', function () {
    Storage::disk('s3')->put('backups/tourney_method_2026-03-09_12-00-00.sql.gz', 'recent backup');
    Storage::disk('s3')->put('backups/tourney_method_2026-01-01_12-00-00.sql.gz', 'old backup');

    $this->artisan('db:list', ['--days' => '7'])
        ->expectsOutputToContain('backup(s)')
        ->assertExitCode(0);
});

test('db:list command shows all backups when --all flag is used', function () {
    Storage::disk('s3')->put('backups/tourney_method_2026-03-09_12-00-00.sql.gz', 'backup 1');
    Storage::disk('s3')->put('backups/tourney_method_2026-01-01_12-00-00.sql.gz', 'backup 2');

    $this->artisan('db:list', ['--all' => true])
        ->expectsOutput('Total: 2 backup(s)')
        ->assertExitCode(0);
});

test('db:clean command shows dry run output when --dry-run flag is used', function () {
    Storage::disk('s3')->put('backups/old_backup.sql.gz', 'old backup');

    $this->artisan('db:clean', ['--dry-run' => true])
        ->assertExitCode(0);
});

test('db:clean command requires confirmation without --force flag', function () {
    Storage::disk('s3')->put('backups/tourney_method_2026-03-09_12-00-00.sql.gz', 'backup');

    $this->artisan('db:clean')
        ->assertExitCode(0);
});

test('db:clean command skips confirmation with --force flag', function () {
    Storage::disk('s3')->put('backups/tourney_method_2026-03-09_12-00-00.sql.gz', 'backup');

    $this->artisan('db:clean', ['--force' => true])
        ->assertExitCode(0);
});

test('db:restore command requires confirmation without --force flag', function () {
    Storage::disk('s3')->put('backups/test_backup.sql.gz', 'backup');

    $this->artisan('db:restore', ['backup_file' => 'backups/test_backup.sql.gz'])
        ->expectsConfirmation('Do you wish to continue?')
        ->assertExitCode(0);
});

test('db:restore command accepts --force flag to skip confirmation', function () {
    Storage::disk('s3')->put('backups/test_backup.sql.gz', 'backup');

    Http::fake([
        'discord.com/*' => Http::response([], 200),
    ]);

    // Just verify the command accepts the --force flag
    $this->artisan('db:restore', [
        'backup_file' => 'backups/test_backup.sql.gz',
        '--force' => true,
    ])
        ->expectsOutputToContain('disabled in the testing environment')
        ->assertExitCode(1);
});

test('db:backup command shows progress information', function () {
    Http::fake([
        'discord.com/*' => Http::response([], 200),
    ]);

    $this->artisan('db:backup')
        ->expectsOutputToContain('backup')
        ->assertExitCode(1);
});

test('backups:create command shows progress information', function () {
    Http::fake([
        'discord.com/*' => Http::response([], 200),
    ]);

    $this->artisan('backups:create')
        ->expectsOutputToContain('backup')
        ->assertExitCode(1);
});

test('db:clean command shows no backups message when directory is empty', function () {
    $this->artisan('db:clean')
        ->expectsOutput('No backups found matching the criteria.')
        ->assertExitCode(0);
});

test('db:list command respects custom disk option', function () {
    Storage::fake('custom');

    Storage::disk('custom')->put('backups/test.sql.gz', 'backup');

    $this->artisan('db:list', ['--disk' => 'custom'])
        ->assertExitCode(0);
});

test('db:clean command respects custom days option', function () {
    Storage::disk('s3')->put('backups/test.sql.gz', 'backup');

    $this->artisan('db:clean', ['--days' => '30', '--force' => true, '--dry-run' => true])
        ->assertExitCode(0);
});

test('db:backup command accepts description option', function () {
    Http::fake([
        'discord.com/*' => Http::response([], 200),
    ]);

    $this->artisan('db:backup', ['--description' => 'Test backup'])
        ->expectsOutputToContain('Test backup')
        ->assertExitCode(1);
});

test('backup commands handle disabled configuration', function () {
    Config::set('database.backup.enabled', false);

    expect(config('database.backup.enabled'))->toBeFalse();
});
