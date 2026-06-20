<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the dashboard data into an array.
     *
     * Maps to Dashboard schema from OpenAPI spec:
     * - user: User object
     * - rank_milestones: { pp_at_100, pp_at_1000, pp_at_10000 }
     * - badge_tournament_count_year: integer
     * - currently_running: TournamentSummary[]
     * - registration_open: TournamentSummary[]
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];

        return [
            'user' => [
                'id' => $user->id,
                'osu_id' => $user->osu_id,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
                'country_code' => $user->country_code,
                'main_mode' => $user->main_mode,
                'role' => $user->role,
                'setup_complete' => $user->hasCompletedSetup(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'rank_milestones' => $this->resource['rank_milestones'],
            'badge_tournament_count_year' => $this->resource['badge_tournament_count_year'],
            'currently_running' => TournamentResource::collection($this->resource['currently_running']),
            'registration_open' => TournamentResource::collection($this->resource['registration_open']),
        ];
    }
}
