<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationQueue;
use App\Models\Tournament;
use App\Models\TournamentWatch;
use App\Models\User;
use App\Services\TwitchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckStreams extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'streams:check';

    /**
     * The console command description.
     */
    protected $description = 'Check for newly live Twitch streams and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(TwitchService $twitchService): int
    {
        if (! $twitchService->isConfigured()) {
            $this->warn('Twitch service is not configured. Skipping stream checks.');

            return Command::SUCCESS;
        }

        $this->info('Checking for live tournament streams...');

        // Find tournaments that:
        // 1. Are approved
        // 2. Have a Twitch URL configured
        // 3. Are in "live period" (start - 3 days to end)
        $tournaments = Tournament::where('status', 'approved')
            ->whereNotNull('twitch_url')
            ->where('twitch_url', '!=', '')
            ->where(function ($query) {
                // Tournament is in "live period": start - 3 days to end
                $query->where(function ($q) {
                    $q->whereNotNull('tournament_start')
                        ->where('tournament_start', '<=', now()->addDays(3));
                })
                    ->where(function ($q) {
                        $q->whereNull('tournament_end')
                            ->orWhere('tournament_end', '>=', now());
                    });
            })
            ->get();

        if ($tournaments->isEmpty()) {
            $this->info('No active tournaments with Twitch streams to check.');

            return Command::SUCCESS;
        }

        $this->info("Checking {$tournaments->count()} tournament stream(s)...");

        // Extract channel names and fetch statuses in batch
        $channels = $tournaments->pluck('twitch_url')->unique()->values()->toArray();
        $streamStatuses = $twitchService->getMultipleStreamStatuses($channels);

        // Fetch streamer avatars in batch
        $streamerAvatars = $twitchService->getMultipleUserAvatars($channels);

        $notificationCount = 0;
        $newlyLiveCount = 0;

        foreach ($tournaments as $tournament) {
            $channel = $this->normalizeChannelName($tournament->twitch_url);
            $streamStatus = $streamStatuses[strtolower($channel)] ?? null;
            $streamerAvatar = $streamerAvatars[strtolower($channel)] ?? null;

            $cacheKey = "stream_was_live_{$tournament->id}";
            $wasLive = Cache::get($cacheKey, false);
            $isLive = $streamStatus !== null && ($streamStatus['is_live'] ?? false);

            // Detect "newly live" transition
            if ($isLive && ! $wasLive) {
                $this->line("Stream went live: {$tournament->title}");
                $newlyLiveCount++;

                // Get host user's avatar
                $hostUser = null;
                $hostAvatarUrl = null;
                if ($tournament->host_osu_id) {
                    $hostUser = User::where('osu_id', $tournament->host_osu_id)->first();
                    $hostAvatarUrl = $hostUser?->avatar_url;
                }

                // Get users watching with stream notifications enabled
                $watches = TournamentWatch::with('user')
                    ->where('tournament_id', $tournament->id)
                    ->where('notify_stream_live', true)
                    ->whereHas('user', function ($query) {
                        $query->whereNotNull('discord_webhook_url')
                            ->where('discord_webhook_valid', true)
                            ->where('notify_stream', true);
                    })
                    ->get();

                foreach ($watches as $watch) {
                    $user = $watch->user;

                    // Check deduplication: don't send if recently notified for this stream going live
                    // (within 1 hour, to prevent spam if stream goes on/off)
                    $existingNotification = NotificationQueue::where('user_id', $user->id)
                        ->where('tournament_id', $tournament->id)
                        ->where('type', 'stream_live')
                        ->where('created_at', '>=', now()->subHour())
                        ->whereIn('status', ['pending', 'sent'])
                        ->exists();

                    if ($existingNotification) {
                        continue;
                    }

                    // Create notification
                    $notification = NotificationQueue::create([
                        'user_id' => $user->id,
                        'tournament_id' => $tournament->id,
                        'type' => 'stream_live',
                        'channel' => 'discord_personal',
                        'payload' => [
                            'title' => $tournament->title,
                            'banner_url' => $tournament->banner_url,
                            'modes' => $tournament->modes,
                            'twitch_url' => $tournament->twitch_url,
                            'stream_title' => $streamStatus['title'] ?? null,
                            'streamer_avatar' => $streamerAvatar,
                            'team_size_display' => $tournament->team_size_display,
                            'vs_size' => $tournament->vs_size,
                            'team_size_min' => $tournament->team_size_min,
                            'team_size_max' => $tournament->team_size_max,
                            'rank_range_min' => $tournament->rank_range_min,
                            'rank_range_max' => $tournament->rank_range_max,
                            'forum_post_url' => $tournament->forum_post_url,
                            'host_username' => $tournament->host_username,
                            'host_avatar' => $hostAvatarUrl,
                        ],
                        'status' => 'pending',
                        'scheduled_for' => now(),
                    ]);

                    SendNotificationJob::dispatch($notification->id);
                    $notificationCount++;
                }
            }

            // Update cache with current state
            Cache::put($cacheKey, $isLive, now()->addMinutes(10));
        }

        $this->info("Detected {$newlyLiveCount} newly live stream(s), queued {$notificationCount} notification(s).");

        Log::info('Stream check completed', [
            'tournaments_checked' => $tournaments->count(),
            'newly_live' => $newlyLiveCount,
            'notifications_queued' => $notificationCount,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Normalize channel name from URL.
     */
    private function normalizeChannelName(string $url): string
    {
        if (str_contains($url, 'twitch.tv/')) {
            $path = parse_url($url, PHP_URL_PATH);

            return trim($path ?? '', '/');
        }

        return $url;
    }
}
