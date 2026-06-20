<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitchService
{
    /**
     * Cache TTL for stream status (3 minutes).
     */
    private const STREAM_STATUS_TTL = 180;

    /**
     * Cache TTL for user avatars (24 hours - avatars rarely change).
     */
    private const AVATAR_TTL = 86400;

    /**
     * Cache TTL for access token (slightly less than actual expiry).
     */
    private const TOKEN_TTL_BUFFER = 300; // 5 minutes buffer

    /**
     * Twitch API base URL.
     */
    private const API_BASE_URL = 'https://api.twitch.tv/helix';

    /**
     * Twitch OAuth token URL.
     */
    private const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';

    /**
     * Get stream status for a Twitch channel.
     *
     * @param  string  $channel  Twitch username/login
     * @return array<string, mixed>|null Stream data if live, null if offline or error
     */
    public function getStreamStatus(string $channel): ?array
    {
        $channel = $this->normalizeChannelName($channel);

        $cacheKey = "twitch_stream_status_{$channel}";

        return Cache::remember($cacheKey, self::STREAM_STATUS_TTL, function () use ($channel) {
            return $this->fetchStreamStatus($channel);
        });
    }

    /**
     * Check multiple streams at once (more efficient).
     *
     * @param  array<int, string>  $channels  Array of Twitch usernames
     * @return array<string, array<string, mixed>|null> Associative array of channel => stream data (null if offline)
     */
    public function getMultipleStreamStatuses(array $channels): array
    {
        if (empty($channels)) {
            return [];
        }

        $channels = array_map([$this, 'normalizeChannelName'], $channels);
        $channels = array_unique(array_filter($channels));

        if (empty($channels)) {
            return [];
        }

        // Check cache first
        $results = [];
        $uncached = [];

        foreach ($channels as $channel) {
            $cacheKey = "twitch_stream_status_{$channel}";
            if (Cache::has($cacheKey)) {
                $results[$channel] = Cache::get($cacheKey);
            } else {
                $uncached[] = $channel;
            }
        }

        // Fetch uncached channels
        if (! empty($uncached)) {
            $fetched = $this->fetchMultipleStreamStatuses($uncached);
            foreach ($fetched as $channel => $data) {
                $results[$channel] = $data;
                Cache::put("twitch_stream_status_{$channel}", $data, self::STREAM_STATUS_TTL);
            }
        }

        return $results;
    }

    /**
     * Get app access token for Twitch API.
     */
    private function getAccessToken(): ?string
    {
        $cacheKey = 'twitch_access_token';

        return Cache::remember($cacheKey, now()->addHours(1), function () {
            return $this->fetchAccessToken();
        });
    }

    /**
     * Fetch new access token from Twitch.
     */
    private function fetchAccessToken(): ?string
    {
        $clientId = config('services.twitch.client_id');
        $clientSecret = config('services.twitch.client_secret');

        if (! $clientId || ! $clientSecret) {
            Log::warning('TwitchService: Missing client credentials');

            return null;
        }

        try {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Cache with buffer before actual expiry
                $expiresIn = ($data['expires_in'] ?? 3600) - self::TOKEN_TTL_BUFFER;
                Cache::put('twitch_access_token', $data['access_token'], max(60, $expiresIn));

                return $data['access_token'];
            }

            Log::error('TwitchService: Failed to get access token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('TwitchService: Exception getting access token', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch stream status from Twitch API.
     *
     * @return array<string, mixed>|null
     */
    private function fetchStreamStatus(string $channel): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Client-Id' => config('services.twitch.client_id'),
            ])->get(self::API_BASE_URL.'/streams', [
                'user_login' => $channel,
            ]);

            if ($response->successful()) {
                $data = $response->json('data');

                if (empty($data)) {
                    // Channel is offline
                    return null;
                }

                $stream = $data[0];

                return [
                    'is_live' => true,
                    'title' => $stream['title'] ?? '',
                    'viewer_count' => $stream['viewer_count'] ?? 0,
                    'started_at' => $stream['started_at'] ?? null,
                    'game_name' => $stream['game_name'] ?? '',
                    'thumbnail_url' => $stream['thumbnail_url'] ?? null,
                ];
            }

            if ($response->status() === 404) {
                return null;
            }

            Log::warning('TwitchService: API error', [
                'channel' => $channel,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('TwitchService: Exception fetching stream status', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch multiple stream statuses in a single API call.
     *
     * @param  array<int, string>  $channels  Array of channel names
     * @return array<string, array<string, mixed>|null> Associative array of channel => stream data
     */
    private function fetchMultipleStreamStatuses(array $channels): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return array_fill_keys($channels, null);
        }

        // Twitch allows up to 100 channels per request
        $results = [];

        foreach (array_chunk($channels, 100) as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Client-Id' => config('services.twitch.client_id'),
                ])->get(self::API_BASE_URL.'/streams', [
                    'user_login' => $chunk,
                ]);

                if ($response->successful()) {
                    $streams = $response->json('data', []);

                    // Map live streams by user_login
                    $liveStreams = [];
                    foreach ($streams as $stream) {
                        $login = strtolower($stream['user_login'] ?? '');
                        $liveStreams[$login] = [
                            'is_live' => true,
                            'title' => $stream['title'] ?? '',
                            'viewer_count' => $stream['viewer_count'] ?? 0,
                            'started_at' => $stream['started_at'] ?? null,
                            'game_name' => $stream['game_name'] ?? '',
                            'thumbnail_url' => $stream['thumbnail_url'] ?? null,
                        ];
                    }

                    // Fill results - null for offline channels
                    foreach ($chunk as $channel) {
                        $normalizedChannel = strtolower($channel);
                        $results[$normalizedChannel] = $liveStreams[$normalizedChannel] ?? null;
                    }
                }
            } catch (\Exception $e) {
                Log::error('TwitchService: Exception fetching multiple streams', [
                    'error' => $e->getMessage(),
                ]);
                // Fill with null for this chunk
                foreach ($chunk as $channel) {
                    $results[strtolower($channel)] = null;
                }
            }
        }

        return $results;
    }

    /**
     * Normalize channel name (extract from URL if needed).
     */
    private function normalizeChannelName(string $channel): string
    {
        // If it's a URL, extract the channel name
        if (str_contains($channel, 'twitch.tv/')) {
            $path = parse_url($channel, PHP_URL_PATH);
            $channel = trim($path ?? '', '/');
        }

        return strtolower(trim($channel));
    }

    /**
     * Get user avatar URL for a Twitch channel.
     *
     * @param  string  $channel  Twitch username/login
     * @return string|null Avatar URL if found, null if error
     */
    public function getUserAvatar(string $channel): ?string
    {
        $channel = $this->normalizeChannelName($channel);

        $cacheKey = "twitch_user_avatar_{$channel}";

        return Cache::remember($cacheKey, self::AVATAR_TTL, function () use ($channel) {
            return $this->fetchUserAvatar($channel);
        });
    }

    /**
     * Get multiple user avatars at once (more efficient).
     *
     * @param  array<int, string>  $channels  Array of Twitch usernames
     * @return array<string, string|null> Associative array of channel => avatar URL (null if not found)
     */
    public function getMultipleUserAvatars(array $channels): array
    {
        if (empty($channels)) {
            return [];
        }

        $channels = array_map([$this, 'normalizeChannelName'], $channels);
        $channels = array_unique(array_filter($channels));

        if (empty($channels)) {
            return [];
        }

        // Check cache first
        $results = [];
        $uncached = [];

        foreach ($channels as $channel) {
            $cacheKey = "twitch_user_avatar_{$channel}";
            if (Cache::has($cacheKey)) {
                $results[$channel] = Cache::get($cacheKey);
            } else {
                $uncached[] = $channel;
            }
        }

        // Fetch uncached channels
        if (! empty($uncached)) {
            $fetched = $this->fetchMultipleUserAvatars($uncached);
            foreach ($fetched as $channel => $avatarUrl) {
                $results[$channel] = $avatarUrl;
                Cache::put("twitch_user_avatar_{$channel}", $avatarUrl, self::AVATAR_TTL);
            }
        }

        return $results;
    }

    /**
     * Fetch user avatar from Twitch API.
     *
     * @return string|null Avatar URL or null if error
     */
    private function fetchUserAvatar(string $channel): ?string
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Client-Id' => config('services.twitch.client_id'),
            ])->get(self::API_BASE_URL.'/users', [
                'login' => $channel,
            ]);

            if ($response->successful()) {
                $data = $response->json('data');

                if (empty($data)) {
                    // User not found
                    return null;
                }

                return $data[0]['profile_image_url'] ?? null;
            }

            Log::warning('TwitchService: Failed to get user avatar', [
                'channel' => $channel,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('TwitchService: Exception fetching user avatar', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch multiple user avatars in a single API call.
     *
     * @param  array<int, string>  $channels  Array of channel names
     * @return array<string, string|null> Associative array of channel => avatar URL
     */
    private function fetchMultipleUserAvatars(array $channels): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return array_fill_keys($channels, null);
        }

        // Twitch allows up to 100 users per request
        $results = [];

        foreach (array_chunk($channels, 100) as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Client-Id' => config('services.twitch.client_id'),
                ])->get(self::API_BASE_URL.'/users', [
                    'login' => $chunk,
                ]);

                if ($response->successful()) {
                    $users = $response->json('data', []);

                    // Map users by their login name
                    $userAvatars = [];
                    foreach ($users as $user) {
                        $login = strtolower($user['login'] ?? '');
                        $userAvatars[$login] = $user['profile_image_url'] ?? null;
                    }

                    // Fill results - null for users not found
                    foreach ($chunk as $channel) {
                        $normalizedChannel = strtolower($channel);
                        $results[$normalizedChannel] = $userAvatars[$normalizedChannel] ?? null;
                    }
                }
            } catch (\Exception $e) {
                Log::error('TwitchService: Exception fetching multiple user avatars', [
                    'error' => $e->getMessage(),
                ]);
                // Fill with null for this chunk
                foreach ($chunk as $channel) {
                    $results[strtolower($channel)] = null;
                }
            }
        }

        return $results;
    }

    /**
     * Check if Twitch service is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.twitch.client_id'))
            && ! empty(config('services.twitch.client_secret'));
    }
}
