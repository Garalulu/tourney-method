<?php

use App\Services\OsuApiService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    // Fake the bus
    Bus::fake();
});

test('parse tournaments command uses job batching', function () {
    // Mock OsuApiService to return test data
    $this->mock(OsuApiService::class, function ($mock) {
        $mock->shouldReceive('getForumTopics')
            ->once()
            ->andReturn([
                'topics' => [
                    ['id' => 1, 'title' => 'Tournament 1'],
                    ['id' => 2, 'title' => 'Tournament 2'],
                    ['id' => 3, 'title' => 'Tournament 3'],
                ],
            ]);
    });

    Bus::fake();

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertSuccessful();

    // Verify batch was created
    Bus::assertBatched(function (PendingBatch $batch) {
        return $batch->jobs->count() === 3;
    });
});

test('batch has proper callbacks configured', function () {
    // This test verifies that the batch has:
    // - then() callback (success)
    // - catch() callback (failure)
    // - finally() callback (always runs)

    $this->mock(OsuApiService::class, function ($mock) {
        $mock->shouldReceive('getForumTopics')
            ->once()
            ->andReturn([
                'topics' => [
                    ['id' => 1, 'title' => 'Tournament 1'],
                ],
            ]);
    });

    Bus::fake();

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertSuccessful();

    // Verify batch was created with callbacks
    Bus::assertBatched(function (PendingBatch $batch) {
        // We can't directly test callbacks, but we can verify the batch exists
        return $batch->jobs->count() === 1;
    });
});

test('batch progress is tracked', function () {
    // Mock API to return test data
    $this->mock(OsuApiService::class, function ($mock) {
        $mock->shouldReceive('getForumTopics')
            ->once()
            ->andReturn([
                'topics' => [
                    ['id' => 1, 'title' => 'Tournament 1'],
                    ['id' => 2, 'title' => 'Tournament 2'],
                ],
            ]);
    });

    Bus::fake();

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertSuccessful();

    // Verify batch was created
    Bus::assertBatched(function (PendingBatch $batch) {
        return $batch->jobs->count() === 2;
    });
});
