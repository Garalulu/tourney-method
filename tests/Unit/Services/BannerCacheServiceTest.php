<?php

use App\Models\Tournament;
use App\Services\BannerCacheService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('caches valid jpeg banner image', function () {
    Http::fake([
        'example.com/*' => Http::response(
            createTestJpegImage(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/banner.jpg',
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeTrue();
    Storage::disk('public')->assertExists("banners/{$tournament->id}.jpg");
    expect($tournament->fresh()->banner_image_cached_at)->not->toBeNull();
});

test('caching_banner_does_not_touch_updated_at', function () {
    Http::fake([
        'example.com/*' => Http::response(
            createTestJpegImage(),
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $updatedAt = now()->subDays(5);
    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/banner.jpg',
        'updated_at' => $updatedAt,
    ]);
    $originalUpdatedAt = $tournament->fresh()->updated_at;

    $service = new BannerCacheService;
    $service->cacheBanner($tournament);

    expect($tournament->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

test('caches valid png banner image', function () {
    Http::fake([
        'example.com/*' => Http::response(
            createTestPngImage(),
            200,
            ['Content-Type' => 'image/png']
        ),
    ]);

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/banner.png',
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeTrue();
    Storage::disk('public')->assertExists("banners/{$tournament->id}.png");
});

test('rejects invalid image types', function () {
    Http::fake([
        'example.com/*' => Http::response(
            'not an image',
            200,
            ['Content-Type' => 'text/html']
        ),
    ]);

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/page.html',
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeFalse();
    Storage::disk('public')->assertMissing("banners/{$tournament->id}.jpg");
});

test('does not cache local images', function () {
    $tournament = Tournament::factory()->create([
        'banner_url' => '/images/local-banner.png',
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeFalse();

    // Verify no banner files were created
    $files = Storage::disk('public')->allFiles('banners');
    expect($files)->toHaveCount(0);
});

test('does not cache asset images', function () {
    $tournament = Tournament::factory()->create([
        'banner_url' => asset('images/banner.png'),
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeFalse();
});

test('rejects images exceeding max file size', function () {
    Http::fake([
        'example.com/*' => Http::response(
            str_repeat('x', 6 * 1024 * 1024), // 6MB
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/huge.jpg',
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeFalse();
});

test('rejects invalid image signatures', function () {
    Http::fake([
        'example.com/*' => Http::response(
            'not valid image data',
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/fake.jpg',
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeFalse();
});

test('handles http errors gracefully', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/missing.jpg',
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeFalse();
});

test('handles timeout errors', function () {
    Http::fake(function ($request) {
        throw new ConnectionException('Connection timeout');
    });

    $tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/slow.jpg',
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeFalse();
});

test('clears cached banner', function () {
    Storage::disk('public')->put('banners/123.jpg', 'test image');

    $tournament = Tournament::factory()->create([
        'id' => 123,
        'banner_image_cached_at' => now(),
    ]);

    $service = new BannerCacheService;
    $service->clearCache($tournament);

    Storage::disk('public')->assertMissing('banners/123.jpg');
    expect($tournament->fresh()->banner_image_cached_at)->toBeNull();
});

test('clearing_cached_banner_does_not_touch_updated_at', function () {
    Storage::disk('public')->put('banners/124.jpg', 'test image');

    $updatedAt = now()->subDays(6);
    $tournament = Tournament::factory()->create([
        'id' => 124,
        'banner_image_cached_at' => now(),
        'updated_at' => $updatedAt,
    ]);
    $originalUpdatedAt = $tournament->fresh()->updated_at;

    $service = new BannerCacheService;
    $service->clearCache($tournament);

    expect($tournament->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

test('clears all possible banner extensions', function () {
    Storage::disk('public')->put('banners/456.jpg', 'jpeg');
    Storage::disk('public')->put('banners/456.png', 'png');
    Storage::disk('public')->put('banners/456.gif', 'gif');

    $tournament = Tournament::factory()->create([
        'id' => 456,
        'banner_image_cached_at' => now(),
    ]);

    $service = new BannerCacheService;
    $service->clearCache($tournament);

    Storage::disk('public')->assertMissing('banners/456.jpg');
    Storage::disk('public')->assertMissing('banners/456.png');
    Storage::disk('public')->assertMissing('banners/456.gif');
});

test('does not cache when banner_url is null', function () {
    $tournament = Tournament::factory()->create([
        'banner_url' => null,
    ]);

    $service = new BannerCacheService;
    $result = $service->cacheBanner($tournament);

    expect($result)->toBeFalse();
});

test('returns cached url if banner exists and is fresh', function () {
    Storage::disk('public')->put('banners/789.jpg', 'test image');

    $tournament = Tournament::factory()->create([
        'id' => 789,
        'banner_url' => 'https://example.com/original.jpg',
        'banner_image_cached_at' => now()->subHours(2), // Fresh cache
    ]);

    expect($tournament->cached_banner_url)->toContain('/storage/banners/789.jpg');
});

test('returns original url if cache is stale', function () {
    Storage::disk('public')->put('banners/101.jpg', 'test image');

    $tournament = Tournament::factory()->create([
        'id' => 101,
        'banner_url' => 'https://example.com/original.jpg',
        'banner_image_cached_at' => now()->subDays(10), // Stale cache
    ]);

    expect($tournament->cached_banner_url)->toBe('https://example.com/original.jpg');
});

test('returns default url when banner_url is null', function () {
    $tournament = Tournament::factory()->create([
        'banner_url' => null,
    ]);

    expect($tournament->cached_banner_url)->toContain('/images/default-tournament-banner');
});

// Helper functions to create test images
function createTestJpegImage(): string
{
    // JPEG magic bytes: FF D8 FF
    return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
}

function createTestPngImage(): string
{
    // PNG magic bytes: 89 50 4E 47
    return "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A\x00\x00\x00\x0DIHDR\x00";
}
