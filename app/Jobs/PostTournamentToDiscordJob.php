<?php

namespace App\Jobs;

use App\Models\Tournament;
use App\Services\DiscordService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PostTournamentToDiscordJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $tournamentId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DiscordService $discord): void
    {
        $tournament = Tournament::with('host')->find($this->tournamentId);

        if ($tournament === null) {
            Log::warning('Tournament not found for Discord announcement', [
                'tournament_id' => $this->tournamentId,
            ]);

            return;
        }

        // Get all modes from the modes array for Discord routing
        // Multi-mode tournaments will post to all relevant channels
        $rawModes = $tournament->modes ?? [];

        if (empty($rawModes)) {
            Log::warning('Tournament has no modes, skipping Discord announcement', [
                'tournament_id' => $tournament->id,
                'title' => $tournament->title,
            ]);

            return;
        }

        // Normalize modes to extract mode names
        /** @var array<string, string> $normalizedModes */
        $normalizedModes = [];
        foreach ($rawModes as $mode) {
            // Handle enhanced format: ['mode' => 'mania', 'key_count' => 4]
            if (is_array($mode) && isset($mode['mode']) && is_string($mode['mode'])) {
                $normalizedModes[$mode['mode']] = $mode['mode'];
            } elseif (is_string($mode)) {
                // Handle legacy format: 'mania'
                $normalizedModes[$mode] = $mode;
            }
        }

        $modes = array_values($normalizedModes);

        if (empty($modes)) {
            Log::warning('Tournament has no valid modes after normalization, skipping Discord announcement', [
                'tournament_id' => $tournament->id,
                'title' => $tournament->title,
                'raw_modes' => $rawModes,
            ]);

            return;
        }

        // Prepare base tournament data for Discord
        $baseTournamentData = [
            'title' => $tournament->title,
            'description' => $tournament->description,
            'is_badge' => $tournament->is_badge,
            'is_bws' => $tournament->is_bws,
            'rank_range_min' => $tournament->rank_range_min,
            'rank_range_max' => $tournament->rank_range_max,
            'banner_url' => $tournament->banner_url,
            'tournament_url' => $tournament->showRoute(),
            'forum_post_url' => $tournament->forum_post_url,
            'spreadsheet_url' => $tournament->spreadsheet_url,
            'registration_url' => $tournament->registration_url,
            'discord_url' => $tournament->discord_url,
            'registration_end' => $tournament->registration_end?->timestamp,
            'host_username' => $tournament->host_username,
            'host_osu_id' => $tournament->host_osu_id,
            'host_user_id' => $tournament->host?->id,
            'vs_size' => $tournament->vs_size,
            'team_size_min' => $tournament->team_size_min,
            'team_size_max' => $tournament->team_size_max,
            'star_rating_first' => $tournament->star_rating_first,
            'star_rating_last' => $tournament->star_rating_last,
            'star_rating_qualifier' => $tournament->star_rating_qualifier,
            'format' => $tournament->format,
            'team_formation_style_label' => $tournament->team_formation_style_label,
            'progression_summary' => $tournament->progression_summary,
            'restricted_countries' => $tournament->restricted_countries ?? [],
        ];

        // Post to central Discord server for each mode
        $postedChannels = [];
        $failedModes = [];

        foreach ($modes as $mode) {
            $tournamentData = array_merge($baseTournamentData, ['mode' => $mode]);
            $result = $discord->postTournamentAnnouncement($tournamentData);

            if ($result['success']) {
                $postedChannels = array_merge($postedChannels, $result['posted_to']);
            } else {
                $failedModes[] = $mode;
                Log::warning('Failed to post tournament announcement to Discord for mode', [
                    'tournament_id' => $tournament->id,
                    'title' => $tournament->title,
                    'mode' => $mode,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        }

        if (! empty($postedChannels)) {
            Log::info('Tournament announcement posted to Discord channels', [
                'tournament_id' => $tournament->id,
                'title' => $tournament->title,
                'modes' => $modes,
                'posted_to' => $postedChannels,
            ]);
        }

        if (! empty($failedModes)) {
            Log::error('Failed to post tournament announcement to Discord for some modes', [
                'tournament_id' => $tournament->id,
                'title' => $tournament->title,
                'failed_modes' => $failedModes,
                'successful_modes' => array_diff($modes, $failedModes),
            ]);
        }

        // Don't fail the job if Discord posting fails - it's non-critical
        // The tournament is already approved, just the notification failed
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Discord announcement job failed', [
            'tournament_id' => $this->tournamentId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
