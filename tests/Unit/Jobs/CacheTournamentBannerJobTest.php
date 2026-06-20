<?php

use App\Jobs\CacheTournamentBannerJob;
use App\Models\Tournament;
use App\Services\BannerCacheService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('job is queued with proper configuration', function () {
    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/banner.jpg',
    ]);

    $job = new CacheTournamentBannerJob($tournament);

    expect($job->tries)->toBe(3);
    expect($job->timeout)->toBe(30);
});

test('job calls banner cache service', function () {
    Http::fake([
        'example.com/*' => Http::response(
            "\xFF\xD8\xFF\xE0", // Valid JPEG
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/banner.jpg',
    ]);

    $bannerCache = app(BannerCacheService::class);
    $job = new CacheTournamentBannerJob($tournament);
    $job->handle($bannerCache);

    Storage::disk('public')->assertExists("banners/{$tournament->id}.jpg");
    expect($tournament->fresh()->banner_image_cached_at)->not->toBeNull();
});

test('job handles service failure gracefully', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/missing.jpg',
    ]);

    $bannerCache = app(BannerCacheService::class);
    $job = new CacheTournamentBannerJob($tournament);

    // Should not throw exception
    $job->handle($bannerCache);

    expect($tournament->fresh()->banner_image_cached_at)->toBeNull();
});

test('job can be dispatched to queue', function () {
    Queue::fake();

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/banner.jpg',
    ]);

    CacheTournamentBannerJob::dispatch($tournament);

    Queue::assertPushed(CacheTournamentBannerJob::class, function ($job) use ($tournament) {
        return $job->tournament->id === $tournament->id;
    });
});

test('job retries on failure', function () {
    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/banner.jpg',
    ]);

    $job = new CacheTournamentBannerJob($tournament);

    expect($job->tries)->toBe(3);
});

test('job has unique tournament id', function () {
    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/banner.jpg',
    ]);

    $job = new CacheTournamentBannerJob($tournament);

    expect($job->tournament->id)->toBe($tournament->id);
});

test('job does not cache local images', function () {
    $tournament = Tournament::factory()->create([
        'banner_url' => '/images/local.png',
    ]);

    $bannerCache = app(BannerCacheService::class);
    $job = new CacheTournamentBannerJob($tournament);
    $job->handle($bannerCache);

    expect($tournament->fresh()->banner_image_cached_at)->toBeNull();
});
