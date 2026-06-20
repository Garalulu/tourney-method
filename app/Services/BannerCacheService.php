<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BannerCacheService
{
    private const TIMEOUT = 10; // seconds

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function cacheBanner(Tournament $tournament): bool
    {
        if (! $tournament->shouldCacheBanner()) {
            return false;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; TourneyMethod/1.0)',
                    'Accept' => 'image/*',
                ])
                ->get($tournament->banner_url);

            if (! $response->successful()) {
                return false;
            }

            $contentType = $response->header('Content-Type');
            if (! in_array($contentType, self::ALLOWED_MIME_TYPES)) {
                return false;
            }

            $imageData = $response->body();
            if (strlen($imageData) > self::MAX_FILE_SIZE) {
                return false;
            }

            // Validate it's actually an image
            if (! $this->isValidImage($imageData)) {
                return false;
            }

            $extension = $this->getExtension($contentType);
            $path = "banners/{$tournament->id}.{$extension}";

            Storage::disk('public')->put($path, $imageData);

            Tournament::withoutTimestamps(fn () => $tournament->updateQuietly([
                'banner_image_cached_at' => now(),
            ]));

            return true;
        } catch (\Exception $e) {
            logger()->warning('Failed to cache banner', [
                'tournament_id' => $tournament->id,
                'url' => $tournament->banner_url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function isValidImage(string $data): bool
    {
        // Check for valid image signatures (magic bytes)
        $signatures = [
            'jpeg' => "\xFF\xD8\xFF",
            'png' => "\x89\x50\x4E\x47",
            'gif' => 'GIF',
            'webp' => 'RIFF',
        ];

        foreach ($signatures as $sig) {
            if (str_starts_with($data, $sig)) {
                return true;
            }
        }

        return false;
    }

    protected function getExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    public function clearCache(Tournament $tournament): void
    {
        $extensions = ['jpg', 'png', 'gif', 'webp'];
        foreach ($extensions as $ext) {
            $path = "banners/{$tournament->id}.{$ext}";
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        Tournament::withoutTimestamps(fn () => $tournament->updateQuietly([
            'banner_image_cached_at' => null,
        ]));
    }
}
