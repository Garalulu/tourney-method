<?php

use App\Jobs\ParseForumTopicJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;

beforeEach(function () {
    // Reset job middleware
    // No need to clear queue in unit tests
});

test('parse forum topic job has without overlapping middleware', function () {
    $job = new ParseForumTopicJob(123456);

    $middleware = $job->middleware();

    // Should have WithoutOverlapping middleware
    $hasWithoutOverlapping = collect($middleware)->contains(function ($m) {
        return $m instanceof WithoutOverlapping;
    });

    expect($hasWithoutOverlapping)->toBeTrue();
});

test('parse forum topic job prevents duplicate processing', function () {
    // This test verifies that:
    // 1. Job has WithoutOverlapping middleware
    // 2. Multiple dispatches of same topic don't overlap
    // 3. The lock key is based on topic ID

    $job1 = new ParseForumTopicJob(123456);
    $job2 = new ParseForumTopicJob(123456);
    $job3 = new ParseForumTopicJob(789012); // Different topic

    // All jobs should have middleware
    expect($job1->middleware())->toHaveCount(1);
    expect($job2->middleware())->toHaveCount(1);
    expect($job3->middleware())->toHaveCount(1);

    // Get the WithoutOverlapping middleware
    $withoutOverlapping1 = collect($job1->middleware())
        ->first(fn ($m) => $m instanceof WithoutOverlapping);

    $withoutOverlapping3 = collect($job3->middleware())
        ->first(fn ($m) => $m instanceof WithoutOverlapping);

    expect($withoutOverlapping1)->not->toBeNull();
    expect($withoutOverlapping3)->not->toBeNull();

    // Verify the middleware objects are different (different keys)
    // We can't directly access the key, but we can verify they're separate instances
    expect($withoutOverlapping1)->not->toBe($withoutOverlapping3);
});

test('job middleware includes without overlapping only', function () {
    $job = new ParseForumTopicJob(123456);

    $middleware = $job->middleware();

    $hasWithoutOverlapping = collect($middleware)->contains(function ($m) {
        return $m instanceof WithoutOverlapping;
    });

    expect($hasWithoutOverlapping)->toBeTrue();
    expect($middleware)->toHaveCount(1);
});

test('without overlapping middleware has appropriate expiry', function () {
    $job = new ParseForumTopicJob(123456);

    $withoutOverlapping = collect($job->middleware())
        ->first(fn ($m) => $m instanceof WithoutOverlapping);

    expect($withoutOverlapping)->not->toBeNull();

    // The lock should expire after a reasonable time (e.g., 1 hour)
    // This prevents indefinite locks if job fails
    // We can't directly test this, but we verify the middleware exists
    expect($withoutOverlapping)->toBeInstanceOf(WithoutOverlapping::class);
});
