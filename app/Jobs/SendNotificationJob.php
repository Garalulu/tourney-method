<?php

namespace App\Jobs;

use App\Models\NotificationQueue;
use App\Services\DiscordService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public array $backoff = [60, 120, 240];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $notificationQueueId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DiscordService $discordService): void
    {
        $notification = NotificationQueue::with(['user', 'tournament'])->find($this->notificationQueueId);

        if (! $notification) {
            Log::warning('SendNotificationJob: Notification not found', [
                'notification_id' => $this->notificationQueueId,
            ]);

            return;
        }

        // Skip if already sent
        if ($notification->isSent()) {
            return;
        }

        // Record attempt
        $notification->incrementAttempts();

        $user = $notification->user;
        $tournament = $notification->tournament;

        // Get webhook URL based on channel type
        $webhookUrl = $this->getWebhookUrl($notification);

        if (! $webhookUrl) {
            $notification->markAsFailed('No webhook URL available');
            Log::warning('SendNotificationJob: No webhook URL', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel,
            ]);

            return;
        }

        // Build embed from payload
        $embed = $this->buildEmbed($notification);

        // Build content for role mentions (if any)
        $content = $notification->payload['content'] ?? null;
        $allowedMentions = $notification->payload['allowed_mentions'] ?? null;

        // Send the notification
        $result = $discordService->sendEmbed($webhookUrl, $embed, $content, $allowedMentions);

        if ($result['success']) {
            $notification->markAsSent();
            Log::info('SendNotificationJob: Notification sent', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'user_id' => $user?->id,
            ]);

            return;
        }

        // If max attempts reached, mark as failed
        if ($notification->attempts >= $this->tries) {
            $notification->markAsFailed($result['error'] ?? 'Unknown error after max retries');
            Log::error('SendNotificationJob: Max retries reached', [
                'notification_id' => $notification->id,
                'error' => $result['error'],
            ]);

            return;
        }

        // Otherwise, the job will be retried automatically
        Log::warning('SendNotificationJob: Send failed, will retry', [
            'notification_id' => $notification->id,
            'attempt' => $notification->attempts,
            'error' => $result['error'],
        ]);

        throw new \Exception($result['error'] ?? 'Failed to send notification');
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        $notification = NotificationQueue::find($this->notificationQueueId);

        if ($notification && ! $notification->isFailed()) {
            $notification->markAsFailed($exception?->getMessage() ?? 'Job failed');
        }

        Log::error('SendNotificationJob: Job failed permanently', [
            'notification_id' => $this->notificationQueueId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Get the webhook URL based on channel type.
     */
    private function getWebhookUrl(NotificationQueue $notification): ?string
    {
        if ($notification->channel === 'discord_personal') {
            return $notification->user?->discord_webhook_url;
        }

        // For central Discord notifications, URL would be in payload or config
        if ($notification->channel === 'discord_central') {
            return $notification->payload['webhook_url'] ?? null;
        }

        return null;
    }

    /**
     * Build the Discord embed from notification data.
     *
     * @return array<string, mixed>
     */
    private function buildEmbed(NotificationQueue $notification): array
    {
        // If payload already contains a full embed, use it
        if (! empty($notification->payload['embed'])) {
            return $notification->payload['embed'];
        }

        // Build embed from tournament data, with payload taking precedence
        if ($notification->tournament) {
            $tournament = $notification->tournament;

            // Merge tournament data with payload (payload takes precedence)
            $data = array_merge([
                'title' => $tournament->title,
                'banner_url' => $tournament->banner_url,
                'modes' => $tournament->modes,
                'is_bws' => $tournament->is_bws,
                'rank_range_min' => $tournament->rank_range_min,
                'rank_range_max' => $tournament->rank_range_max,
                'registration_end' => $tournament->registration_end?->timestamp,
                'registration_url' => $tournament->registration_url,
                'forum_post_url' => $tournament->forum_post_url,
                'twitch_url' => $tournament->twitch_url,
                'team_size_display' => $tournament->team_size_display,
                'vs_size' => $tournament->vs_size,
                'team_size_min' => $tournament->team_size_min,
                'team_size_max' => $tournament->team_size_max,
                'host_username' => $tournament->host_username,
                'host_osu_id' => $tournament->host_osu_id,
            ], $notification->payload);

            Log::info('SendNotificationJob: Building embed', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'has_stream_title' => isset($data['stream_title']),
                'stream_title' => $data['stream_title'] ?? null,
                'has_streamer_avatar' => isset($data['streamer_avatar']),
                'streamer_avatar' => $data['streamer_avatar'] ?? null,
                'has_host_osu_id' => isset($data['host_osu_id']),
                'host_osu_id' => $data['host_osu_id'] ?? null,
                'team_size_display' => $data['team_size_display'] ?? null,
            ]);

            return app(DiscordService::class)->buildTournamentEmbed($data, $notification->type);
        }

        // Fallback: simple embed from payload
        return [
            'title' => $notification->payload['title'] ?? 'Notification',
            'description' => $notification->payload['description'] ?? '',
            'color' => $notification->payload['color'] ?? 0x0099FF,
        ];
    }
}
