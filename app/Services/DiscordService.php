<?php

namespace App\Services;

use App\Models\DiscordChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    /**
     * Rate limit: 5 requests per 2 seconds per webhook
     */
    private const RATE_LIMIT_REQUESTS = 5;

    private const RATE_LIMIT_WINDOW = 2; // seconds

    /**
     * Retry configuration
     */
    private const MAX_RETRIES = 3;

    private const RETRY_DELAYS = [1, 2, 4]; // seconds

    /**
     * Validate a Discord webhook URL by sending a test request.
     */
    public function validateWebhook(string $url): bool
    {
        if (! $this->isValidWebhookFormat($url)) {
            return false;
        }

        try {
            // GET request to webhook URL returns webhook info if valid
            $response = Http::timeout(10)->get($url);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Discord webhook validation failed', [
                'url' => $this->maskWebhookUrl($url),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate webhook URL format matches Discord pattern.
     */
    public function isValidWebhookFormat(string $url): bool
    {
        return (bool) preg_match(
            '#^https://discord\.com/api/webhooks/\d+/[\w-]+$#',
            $url
        );
    }

    /**
     * Send an embed message to a Discord webhook.
     *
     * @param  array<string, mixed>  $embed  Discord embed structure
     * @param  string|null  $content  Optional text content (for role mentions)
     * @param  array<string, mixed>|null  $allowedMentions  Optional allowed_mentions configuration
     * @return array{success: bool, error?: string}
     */
    public function sendEmbed(
        string $webhookUrl,
        array $embed,
        ?string $content = null,
        ?array $allowedMentions = null
    ): array {
        if (! $this->isValidWebhookFormat($webhookUrl)) {
            return ['success' => false, 'error' => 'Invalid webhook URL format'];
        }

        $this->waitForRateLimit($webhookUrl);

        $payload = ['embeds' => [$embed]];

        if ($content !== null) {
            $payload['content'] = $content;
        }

        if ($allowedMentions !== null) {
            $payload['allowed_mentions'] = $allowedMentions;
        }

        return $this->sendWithRetry($webhookUrl, $payload);
    }

    /**
     * Send a simple text message to a Discord webhook.
     *
     * @return array{success: bool, error?: string}
     */
    public function sendMessage(string $webhookUrl, string $content): array
    {
        if (! $this->isValidWebhookFormat($webhookUrl)) {
            return ['success' => false, 'error' => 'Invalid webhook URL format'];
        }

        $this->waitForRateLimit($webhookUrl);

        return $this->sendWithRetry($webhookUrl, ['content' => $content]);
    }

    /**
     * Send a test message to verify webhook is working.
     *
     * @return array{success: bool, error?: string}
     */
    public function sendTestMessage(string $webhookUrl, string $username): array
    {
        $embed = [
            'title' => '✅ Webhook Test Successful',
            'description' => "This is a test message from Tourney Method.\n\nYour webhook is configured correctly and ready to receive notifications.",
            'color' => 0x00FF00, // Green
            'fields' => [
                [
                    'name' => 'User',
                    'value' => $username,
                    'inline' => true,
                ],
                [
                    'name' => 'Timestamp',
                    'value' => now()->format('Y-m-d H:i:s T'),
                    'inline' => true,
                ],
            ],
            'footer' => [
                'text' => 'Tourney Method',
            ],
        ];

        return $this->sendEmbed($webhookUrl, $embed);
    }

    /**
     * Post tournament announcement to central Discord server channels.
     *
     * Routes the announcement to the appropriate channel based on mode and badge status.
     * Includes role pings for badge tournaments based on rank range.
     *
     * @param  array<string, mixed>  $tournament  Tournament data
     * @return array{success: bool, posted_to: array<int, string>, errors?: array<int, string>, error?: string}
     */
    public function postTournamentAnnouncement(array $tournament): array
    {
        $mode = $tournament['mode'] ?? null;
        $isBadge = $tournament['is_badge'] ?? false;

        if ($mode === null) {
            return [
                'success' => false,
                'posted_to' => [],
                'error' => 'Tournament mode is required for routing',
            ];
        }

        $restrictedCountries = collect($tournament['restricted_countries'] ?? [])
            ->map(fn (mixed $country): string => strtoupper((string) $country))
            ->filter()
            ->values()
            ->all();

        $destinations = DiscordChannel::forTournamentDestinations($mode, $isBadge, $restrictedCountries);

        if ($destinations->isEmpty()) {
            return [
                'success' => false,
                'posted_to' => [],
                'error' => "No Discord destinations configured for {$mode} ".($isBadge ? 'badge' : 'general').' tournaments',
            ];
        }

        $embed = $this->buildTournamentEmbed($tournament, 'new_approval');
        $postedTo = [];
        $errors = [];

        foreach ($destinations as $destination) {
            if (empty($destination->webhook_url)) {
                $error = 'Discord webhook URL not configured for destination: '.$destination->channel_name;
                $errors[] = $error;

                Log::error('Failed to post tournament to central Discord destination', [
                    'tournament' => $tournament['title'] ?? 'Unknown',
                    'destination' => $destination->channel_name,
                    'error' => $error,
                ]);

                continue;
            }

            // Role mappings belong to the Discord server. Fall back to legacy
            // destination mappings only for pre-migration rows without a server.
            $server = $destination->server;
            $roleSource = $server === null || empty($server->role_mappings)
                ? $destination
                : $server;
            $minRank = $tournament['rank_range_min'] ?? null;
            $maxRank = $tournament['rank_range_max'] ?? null;

            $rankStartsBeyondServerRoles = $server !== null
                && $server->rankRangeStartsBeyondConfiguredRoles($minRank);

            if ($rankStartsBeyondServerRoles && $server !== null && ! $server->send_unmatched_rank_alerts) {
                $error = 'Skipped unmatched rank alert for destination: '.$destination->channel_name;
                $errors[] = $error;

                Log::info('Skipped Discord destination for unmatched rank alert', [
                    'tournament' => $tournament['title'] ?? 'Unknown',
                    'destination' => $destination->channel_name,
                    'rank_range_min' => $minRank,
                ]);

                continue;
            }

            $rolePings = $rankStartsBeyondServerRoles
                ? []
                : $roleSource->getRolePings($minRank, $maxRank);

            // Extract role IDs for allowed_mentions.
            $allowedRoles = [];
            foreach ($rolePings as $ping) {
                if (preg_match('/<@&(\d+)>/', $ping, $matches)) {
                    $allowedRoles[] = $matches[1];
                }
            }

            // Prepare content with role mentions.
            $content = ! empty($rolePings) ? implode(' ', $rolePings) : null;

            // Prepare allowed_mentions (only specific roles, not parse).
            $allowedMentions = ! empty($allowedRoles) ? [
                'roles' => $allowedRoles,
            ] : null;

            $result = $this->sendEmbed(
                $destination->webhook_url,
                $embed,
                $content,
                $allowedMentions
            );

            if ($result['success']) {
                Log::info('Tournament posted to central Discord destination', [
                    'tournament' => $tournament['title'] ?? 'Unknown',
                    'destination' => $destination->channel_name,
                    'role_pings' => count($rolePings),
                ]);

                $postedTo[] = $destination->channel_name;

                continue;
            }

            $error = $result['error'] ?? 'Unknown error';
            $errors[] = "{$destination->channel_name}: {$error}";

            Log::error('Failed to post tournament to central Discord destination', [
                'tournament' => $tournament['title'] ?? 'Unknown',
                'destination' => $destination->channel_name,
                'error' => $error,
            ]);
        }

        if ($postedTo !== []) {
            return [
                'success' => true,
                'posted_to' => $postedTo,
                'errors' => $errors,
            ];
        }

        return [
            'success' => false,
            'posted_to' => [],
            'errors' => $errors,
            'error' => $errors[0] ?? 'No Discord destinations were posted successfully',
        ];
    }

    /**
     * Format team size display (e.g., "1v1 / TS1" or "2v2 / TS2-4").
     *
     * @param  int|null  $vsSize  VS size (1, 2, 3, 4...)
     * @param  int|null  $teamSizeMin  Minimum team size
     * @param  int|null  $teamSizeMax  Maximum team size
     * @return string Formatted team size string
     */
    private function formatTeamSize(?int $vsSize, ?int $teamSizeMin, ?int $teamSizeMax): string
    {
        $vsPart = $vsSize ? "{$vsSize}v{$vsSize}" : '?v?';

        if ($teamSizeMin && $teamSizeMax) {
            $tsPart = $teamSizeMin === $teamSizeMax
                ? "TS{$teamSizeMin}"
                : "TS{$teamSizeMin}-{$teamSizeMax}";
        } else {
            $tsPart = 'TS?';
        }

        return "{$vsPart} / {$tsPart}";
    }

    /**
     * Format star rating range (e.g., "*6.80~*7.40 (QL *7.00)").
     *
     * @param  float|null  $srFirst  Star rating for first place
     * @param  float|null  $srLast  Star rating for last place
     * @param  float|null  $srQualifier  Star rating for qualifier
     * @return string Formatted SR range or empty string if not available
     */
    private function formatStarRatingRange(?float $srFirst, ?float $srLast, ?float $srQualifier): string
    {
        if (! $srFirst || ! $srLast) {
            return '';
        }

        $srRange = "*{$srFirst}~*{$srLast}";
        $qualifier = $srQualifier ? " (QL *{$srQualifier})" : '';

        // Wrap in code blocks for Discord formatting
        return "`{$srRange}{$qualifier}`";
    }

    /**
     * Build a tournament notification embed.
     *
     * @param  array<string, mixed>  $tournament
     * @return array<string, mixed>
     */
    public function buildTournamentEmbed(array $tournament, string $notificationType): array
    {
        $color = match ($notificationType) {
            'new_approval' => 0x00FF00, // Green
            'registration_reminder' => 0xFFA500, // Orange
            'tournament_start' => 0x00FF00, // Green
            'stream_live' => 0x9146FF, // Twitch purple
            default => 0x0099FF, // Blue
        };

        $embed = [
            'title' => $tournament['title'] ?? 'Unknown Tournament',
            'color' => $color,
            'fields' => [],
            'footer' => [
                'text' => 'Tourney Method',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        // Add notification-specific content and headers
        if ($notificationType === 'registration_reminder') {
            $embed['description'] = "⏰ **24h Reminder** - Registration closes soon!\nDon't forget to sign up before it's too late.";
        } elseif ($notificationType === 'stream_live') {
            $streamTitle = $tournament['stream_title'] ?? 'Tournament Stream';
            $embed['description'] = "🔴 **LIVE NOW**\n**{$streamTitle}**";
        }

        // Add banner if available
        if (! empty($tournament['banner_url'])) {
            $embed['image'] = ['url' => $tournament['banner_url']];
        }

        // New tournament alerts should link to the Tourney Method page.
        if ($notificationType === 'new_approval' && ! empty($tournament['tournament_url'])) {
            $embed['url'] = $tournament['tournament_url'];
        } elseif ($notificationType !== 'new_approval' && ! empty($tournament['forum_post_url'])) {
            $embed['url'] = $tournament['forum_post_url'];
        }

        // Add host field
        if (! empty($tournament['host_username'])) {
            $hostValue = $tournament['host_username'];
            if (! empty($tournament['host_user_id'])) {
                $hostValue = "[{$hostValue}](".route('users.show', $tournament['host_user_id']).')';
            }

            $embed['fields'][] = [
                'name' => 'Host',
                'value' => $hostValue,
                'inline' => true,
            ];
        }

        // Add rank range (always show, including "Open")
        $rankRange = $this->formatRankRange(
            $tournament['rank_range_min'] ?? null,
            $tournament['rank_range_max'] ?? null,
            $tournament['is_bws'] ?? false
        );
        $embed['fields'][] = [
            'name' => 'Rank',
            'value' => $rankRange,
            'inline' => true,
        ];

        // Add team size / vs size (use team_size_display if available, otherwise calculate)
        if (! empty($tournament['team_size_display'])) {
            $teamSize = $tournament['team_size_display'];
        } else {
            $teamSize = $this->formatTeamSize(
                $tournament['vs_size'] ?? null,
                $tournament['team_size_min'] ?? null,
                $tournament['team_size_max'] ?? null
            );
        }
        $embed['fields'][] = [
            'name' => 'Format',
            'value' => $teamSize ?: '?',
            'inline' => true,
        ];

        // Add description with SR range, team formation, and progression (for new_approval)
        if ($notificationType === 'new_approval') {
            $srRange = $this->formatStarRatingRange(
                $tournament['star_rating_first'] ?? null,
                $tournament['star_rating_last'] ?? null,
                $tournament['star_rating_qualifier'] ?? null
            );

            $firstLine = collect([
                $srRange,
                $tournament['team_formation_style_label'] ?? null,
            ])->filter()->implode(' ');

            $description = collect([
                $firstLine,
                $tournament['progression_summary'] ?? null,
            ])->filter()->implode("\n");

            if ($description) {
                $embed['fields'][] = [
                    'name' => 'Description',
                    'value' => $description,
                    'inline' => false,
                ];
            }

            $links = collect([
                'Forum Post' => $tournament['forum_post_url'] ?? null,
                'Main Sheet' => $tournament['spreadsheet_url'] ?? null,
                'Player Reg' => $tournament['registration_url'] ?? null,
                'Discord' => $tournament['discord_url'] ?? null,
            ])
                ->filter()
                ->map(fn (string $url, string $label): string => "[{$label}]({$url})")
                ->values()
                ->implode("\n");

            if ($links !== '') {
                $embed['fields'][] = [
                    'name' => 'Links',
                    'value' => $links,
                    'inline' => false,
                ];
            }
        }

        // Add registration end date (for new_approval and registration_reminder)
        if (
            ($notificationType === 'new_approval' || $notificationType === 'registration_reminder')
            && ! empty($tournament['registration_end'])
        ) {
            $embed['fields'][] = [
                'name' => 'Registration Ends',
                'value' => '<t:'.$tournament['registration_end'].':R>',
                'inline' => false,
            ];
        }

        // Add registration link only for registration reminder
        if ($notificationType === 'registration_reminder' && ! empty($tournament['registration_end'])) {
            $registrationLink = $tournament['registration_url'] ?? null;
            if ($registrationLink) {
                $embed['fields'][] = [
                    'name' => '📝 Register Now',
                    'value' => "[Sign up here]({$registrationLink})",
                    'inline' => false,
                ];
            }
        }

        // Add Twitch link for stream live notification
        if ($notificationType === 'stream_live' && ! empty($tournament['twitch_url'])) {
            $embed['fields'][] = [
                'name' => '🎥 Watch on Twitch',
                'value' => "[Open Stream]({$tournament['twitch_url']})",
                'inline' => false,
            ];
        }

        // Add streamer avatar as thumbnail for stream live notification
        if ($notificationType === 'stream_live' && ! empty($tournament['streamer_avatar'])) {
            $embed['thumbnail'] = ['url' => $tournament['streamer_avatar']];
        }

        // Add host avatar as thumbnail for other notifications (registration reminder, new_approval, etc.)
        if ($notificationType !== 'stream_live' && ! empty($tournament['host_osu_id'])) {
            $embed['thumbnail'] = ['url' => "https://a.ppy.sh/{$tournament['host_osu_id']}"];
        }

        Log::info('DiscordService: Built embed', [
            'type' => $notificationType,
            'description' => $embed['description'] ?? 'none',
            'field_count' => count($embed['fields'] ?? []),
            'format_value' => $teamSize ?? 'none',
            'has_thumbnail' => isset($embed['thumbnail']),
            'thumbnail_url' => $embed['thumbnail']['url'] ?? null,
            'has_image' => isset($embed['image']),
        ]);

        return $embed;
    }

    /**
     * Format rank range for display.
     *
     * @param  int|null  $min  Minimum rank
     * @param  int|null  $max  Maximum rank
     * @param  bool  $isBws  Whether tournament uses BWS ranking
     * @return string Formatted rank range with commas and optional BWS suffix
     */
    private function formatRankRange(?int $min, ?int $max, bool $isBws = false): string
    {
        if ($min === null && $max === null) {
            return $isBws ? 'Open Rank BWS' : 'Open Rank';
        }

        if ($min === null) {
            $formattedMax = $max !== null ? number_format($max) : '';
            $result = "#1 - #{$formattedMax}";

            if ($isBws) {
                $result .= ' BWS';
            }

            return $result;
        }

        if ($max === null) {
            $formattedMin = number_format($min);
            $result = "#{$formattedMin}+";

            if ($isBws) {
                $result .= ' BWS';
            }

            return $result;
        }

        $formattedMin = number_format($min);
        $formattedMax = number_format($max);
        $result = "#{$formattedMin} - #{$formattedMax}";

        if ($isBws) {
            $result .= ' BWS';
        }

        return $result;
    }

    /**
     * Send payload with retry logic.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, error?: string}
     */
    private function sendWithRetry(string $webhookUrl, array $payload): array
    {
        $lastError = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                $response = Http::timeout(10)
                    ->asJson()
                    ->post($webhookUrl, $payload);

                // Record request for rate limiting
                $this->recordRequest($webhookUrl);

                if ($response->successful()) {
                    Log::info('Discord webhook message sent', [
                        'url' => $this->maskWebhookUrl($webhookUrl),
                        'attempt' => $attempt + 1,
                    ]);

                    return ['success' => true];
                }

                // Handle rate limiting (429)
                if ($response->status() === 429) {
                    $retryAfterHeader = $response->header('Retry-After');
                    $retryAfter = $retryAfterHeader !== '' ? $retryAfterHeader : (string) self::RETRY_DELAYS[$attempt];
                    Log::warning('Discord rate limited, retrying', [
                        'retry_after' => $retryAfter,
                        'attempt' => $attempt + 1,
                    ]);
                    sleep((int) $retryAfter);

                    continue;
                }

                // Don't retry 4xx errors (except 429)
                if ($response->status() >= 400 && $response->status() < 500) {
                    $lastError = "HTTP {$response->status()}: {$response->body()}";
                    break;
                }

                // Retry 5xx errors
                $lastError = "HTTP {$response->status()}: {$response->body()}";

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::warning('Discord webhook request failed', [
                    'url' => $this->maskWebhookUrl($webhookUrl),
                    'error' => $lastError,
                    'attempt' => $attempt + 1,
                ]);
            }

            // Wait before retry (exponential backoff)
            if ($attempt < self::MAX_RETRIES - 1) {
                sleep(self::RETRY_DELAYS[$attempt]);
            }
        }

        Log::error('Discord webhook failed after retries', [
            'url' => $this->maskWebhookUrl($webhookUrl),
            'error' => $lastError,
        ]);

        return ['success' => false, 'error' => $lastError ?? 'Unknown error'];
    }

    /**
     * Wait for rate limit window if needed.
     */
    private function waitForRateLimit(string $webhookUrl): void
    {
        $cacheKey = $this->getRateLimitCacheKey($webhookUrl);
        $requests = Cache::get($cacheKey, []);

        // Clean old requests outside window
        $windowStart = now()->subSeconds(self::RATE_LIMIT_WINDOW)->timestamp;
        $requests = array_filter($requests, fn ($time) => $time > $windowStart);

        // Wait if at limit
        if (count($requests) >= self::RATE_LIMIT_REQUESTS) {
            $oldestRequest = min($requests);
            $waitTime = $oldestRequest + self::RATE_LIMIT_WINDOW - now()->timestamp;
            if ($waitTime > 0) {
                sleep($waitTime);
            }
        }
    }

    /**
     * Record a request for rate limiting.
     */
    private function recordRequest(string $webhookUrl): void
    {
        $cacheKey = $this->getRateLimitCacheKey($webhookUrl);
        $requests = Cache::get($cacheKey, []);

        // Clean old requests
        $windowStart = now()->subSeconds(self::RATE_LIMIT_WINDOW)->timestamp;
        $requests = array_filter($requests, fn ($time) => $time > $windowStart);

        // Add current request
        $requests[] = now()->timestamp;

        Cache::put($cacheKey, $requests, self::RATE_LIMIT_WINDOW + 1);
    }

    /**
     * Get cache key for rate limiting.
     */
    private function getRateLimitCacheKey(string $webhookUrl): string
    {
        // Hash the webhook URL to avoid exposing it in cache keys
        return 'discord_rate_limit_'.md5($webhookUrl);
    }

    /**
     * Mask webhook URL for logging.
     */
    private function maskWebhookUrl(string $url): string
    {
        // Only show domain and first few characters of the path
        return preg_replace(
            '#(webhooks/\d{5})\d+/(.{8}).*#',
            '$1***/$2***',
            $url
        ) ?? '[masked]';
    }
}
