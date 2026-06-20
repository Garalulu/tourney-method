<?php

use App\Models\ImportJob;
use App\Services\ImportService;

/**
 * @return array<string, int>
 */
function emptyImportStats(): array
{
    return [
        'tournaments_imported' => 0,
        'tournaments_updated' => 0,
        'tournaments_failed' => 0,
        'matches_imported' => 0,
        'matches_failed' => 0,
    ];
}

test('historical import requires explicit confirmation', function () {
    $this->artisan('import:historical', ['--no-backup' => true])
        ->expectsOutputToContain('requires explicit confirmation')
        ->assertFailed();
});

test('combined historical import delegates to both integrations', function () {
    $service = Mockery::mock(ImportService::class);
    $service->shouldReceive('runTcommImport')
        ->once()
        ->andReturn([
            'tournaments_imported' => 1,
            'tournaments_updated' => 0,
            'tournaments_failed' => 0,
        ]);
    $service->shouldReceive('runOtrImport')
        ->once()
        ->withArgs(fn (ImportJob $job, bool $dryRun, ?int $limit) => ! $dryRun && $limit === null)
        ->andReturn([
            'tournaments_imported' => 1,
            'tournaments_updated' => 0,
            'tournaments_failed' => 0,
            'matches_imported' => 2,
            'matches_failed' => 0,
        ]);
    app()->instance(ImportService::class, $service);

    $this->artisan('import:historical', ['--confirm' => true, '--no-backup' => true])
        ->expectsOutputToContain('Import completed')
        ->assertSuccessful();

    $job = ImportJob::query()->firstOrFail();
    expect($job->status)->toBe(ImportJob::STATUS_COMPLETED)
        ->and($job->tournaments_imported)->toBe(2)
        ->and($job->matches_imported)->toBe(2);
});

test('source option limits the historical integration used', function () {
    $service = Mockery::mock(ImportService::class);
    $service->shouldReceive('runTcommImport')
        ->once()
        ->andReturn(array_slice(emptyImportStats(), 0, 3, true));
    $service->shouldNotReceive('runOtrImport');
    app()->instance(ImportService::class, $service);

    $this->artisan('import:historical', [
        '--source' => 'tcomm',
        '--confirm' => true,
        '--no-backup' => true,
    ])->assertSuccessful();
});

test('failed historical import is recorded for resume', function () {
    $service = Mockery::mock(ImportService::class);
    $service->shouldReceive('runTcommImport')
        ->once()
        ->andThrow(new RuntimeException('integration unavailable'));
    app()->instance(ImportService::class, $service);

    $this->artisan('import:historical', [
        '--source' => 'tcomm',
        '--confirm' => true,
        '--no-backup' => true,
    ])
        ->expectsOutputToContain('Import failed')
        ->assertFailed();

    $job = ImportJob::query()->firstOrFail();
    expect($job->status)->toBe(ImportJob::STATUS_FAILED)
        ->and($job->error_log)->not->toBeEmpty();
});

test('historical import can resume the latest failed job', function () {
    $job = ImportJob::factory()->create([
        'source' => 'tcomm',
        'status' => ImportJob::STATUS_FAILED,
    ]);

    $service = Mockery::mock(ImportService::class);
    $service->shouldReceive('runTcommImport')
        ->once()
        ->andReturn(array_slice(emptyImportStats(), 0, 3, true));
    app()->instance(ImportService::class, $service);

    $this->artisan('import:historical', [
        '--resume' => true,
        '--confirm' => true,
        '--no-backup' => true,
    ])
        ->expectsOutputToContain("Resuming import job #{$job->id}")
        ->assertSuccessful();

    expect($job->fresh()->status)->toBe(ImportJob::STATUS_COMPLETED);
});
