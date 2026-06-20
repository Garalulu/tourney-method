<?php

namespace App\Http\Resources;

use App\Models\Tournament;
use App\Services\BwsCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property array<int, array{mode: string, key_count: ?int}>|null $modes
 * @property bool $is_badge
 * @property int|null $rank_range_min
 * @property int|null $rank_range_max
 * @property Carbon|null $registration_end
 * @property Carbon|null $registration_start
 * @property Carbon|null $tournament_start
 * @property Carbon|null $tournament_end
 * @property string|null $banner_url
 * @property string|null $description
 * @property string|null $host_username
 * @property int|null $team_size_min
 * @property int|null $team_size_max
 * @property bool $is_bws
 * @property float|null $bws_base_exponent
 * @property float|null $bws_badge_power
 * @property float|null $star_rating_min
 * @property float|null $star_rating_max
 * @property string|null $format
 * @property string|null $team_formation_style
 * @property array<int, string>|null $format_tags
 * @property array<string, mixed>|null $format_structure
 * @property string|null $discord_url
 * @property string|null $twitch_url
 * @property string|null $spreadsheet_url
 * @property string|null $bracket_url
 * @property string|null $registration_url
 * @property string|null $forum_post_url
 * @property bool|null $isDetail
 *
 * @mixin Tournament
 */
class TournamentResource extends JsonResource
{
    /**
     * Transform the tournament resource into an array.
     *
     * Maps to TournamentSummary or TournamentDetail schema from OpenAPI spec
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Base fields (TournamentSummary schema)
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'modes' => $this->modes,
            'is_badge' => $this->is_badge,
            'rank_range_min' => $this->rank_range_min,
            'rank_range_max' => $this->rank_range_max,
            'registration_end' => $this->registration_end?->toIso8601String(),
            'tournament_start' => $this->tournament_start?->toIso8601String(),
            'banner_url' => $this->banner_url,
            'registration_closes_in' => $this->getRegistrationClosesIn(),
        ];

        // Detail fields (TournamentDetail extends TournamentSummary)
        if ($this->isDetail ?? false) {
            $data = array_merge($data, [
                'description' => $this->description,
                'host_username' => $this->host_username,
                'team_size_min' => $this->team_size_min,
                'team_size_max' => $this->team_size_max,
                'registration_start' => $this->registration_start?->toIso8601String(),
                'tournament_end' => $this->tournament_end?->toIso8601String(),
                'is_bws' => $this->is_bws,
                'bws_base_exponent' => $this->bws_base_exponent,
                'bws_badge_power' => $this->bws_badge_power,
                'star_rating_min' => $this->star_rating_min,
                'star_rating_max' => $this->star_rating_max,
                'format' => $this->format,
                'team_formation_style' => $this->team_formation_style,
                'format_tags' => $this->format_tags,
                'format_structure' => $this->format_structure,
                'progression_summary' => $this->progression_summary,
                'discord_url' => $this->discord_url,
                'twitch_url' => $this->twitch_url,
                'spreadsheet_url' => $this->spreadsheet_url,
                'bracket_url' => $this->bracket_url,
                'registration_url' => $this->registration_url,
                'forum_post_url' => $this->forum_post_url,
            ]);
        }

        // User-specific fields (always included in detail view, nullable for guests)
        if ($this->isDetail ?? false) {
            $data['user_watch_status'] = auth()->check() ? $this->getUserWatchStatus() : null;
            $data['user_is_eligible'] = auth()->check() ? $this->getUserEligibility() : null;
        }

        return $data;
    }

    /**
     * Calculate human-readable countdown to registration close
     */
    protected function getRegistrationClosesIn(): ?string
    {
        if (! $this->registration_end) {
            return null;
        }

        $now = now();
        if ($this->registration_end <= $now) {
            return null; // Already closed
        }

        $diff = $now->diff($this->registration_end);

        // Only show if within 7 days
        if ($diff->days > 7) {
            return null;
        }

        if ($diff->days > 0) {
            return "{$diff->days}d {$diff->h}h";
        }

        if ($diff->h > 0) {
            return "{$diff->h}h {$diff->i}m";
        }

        return "{$diff->i}m";
    }

    /**
     * Get user's watch status for this tournament
     */
    protected function getUserWatchStatus(): ?string
    {
        if (! auth()->check()) {
            return null;
        }

        $watch = $this->watches()->where('user_id', auth()->id())->first();

        return $watch?->watch_type;
    }

    /**
     * Check if authenticated user is eligible for this tournament
     */
    protected function getUserEligibility(): ?bool
    {
        if (! auth()->check()) {
            return null;
        }

        $user = auth()->user();
        if (! $user->main_mode) {
            return false;
        }

        return app(BwsCalculator::class)->isEligible($user, $this->resource, $user->main_mode);
    }
}
