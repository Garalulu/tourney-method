<?php

namespace App\Services;

use App\Models\UserMatchParticipation;
use Illuminate\Support\Facades\Cache;

class MatchStatsService
{
    /**
     * Get match statistics for a user.
     *
     * Results are cached for 1 hour to improve performance.
     *
     * @return array{total_matches: int, total_games: int, games_won: int, win_rate: float, frequent_teammates: array<array{user_id: int, username: string, avatar_url: string|null, match_count: int}>, frequent_opponents: array<array{user_id: int, username: string, avatar_url: string|null, match_count: int}>}
     */
    public function getUserStats(int $userId): array
    {
        return Cache::remember(
            "user.{$userId}.match_stats",
            now()->addHour(),
            fn () => $this->calculateStats($userId)
        );
    }

    /**
     * Invalidate the cached stats for a user.
     * Should be called when a new match is approved.
     */
    public function invalidateUserStats(int $userId): void
    {
        Cache::forget("user.{$userId}.match_stats");
    }

    /**
     * Calculate statistics from scratch.
     *
     * @return array{total_matches: int, total_games: int, games_won: int, win_rate: float, frequent_teammates: array<array{user_id: int, username: string, avatar_url: string|null, match_count: int}>, frequent_opponents: array<array{user_id: int, username: string, avatar_url: string|null, match_count: int}>}
     */
    protected function calculateStats(int $userId): array
    {
        // Get all approved match participations
        $participations = UserMatchParticipation::query()
            ->where('user_id', $userId)
            ->whereHas('match', fn ($q) => $q->where('status', 'approved'))
            ->get();

        $totalMatches = $participations->count();
        $totalGames = $participations->sum('games_played');
        $gamesWon = $participations->sum('games_won');

        $winRate = $totalGames > 0
            ? round(($gamesWon / $totalGames) * 100, 2)
            : 0.0;

        return [
            'total_matches' => $totalMatches,
            'total_games' => $totalGames,
            'games_won' => $gamesWon,
            'win_rate' => $winRate,
            'frequent_teammates' => $this->getFrequentTeammates($userId),
            'frequent_opponents' => $this->getFrequentOpponents($userId),
        ];
    }

    /**
     * Get frequent teammates (5+ matches together).
     *
     * @return array<array{user_id: int, username: string, avatar_url: string|null, match_count: int}>
     */
    protected function getFrequentTeammates(int $userId): array
    {
        // Get all match IDs where the user participated
        $userMatchIds = UserMatchParticipation::query()
            ->where('user_id', $userId)
            ->whereHas('match', fn ($q) => $q->where('status', 'approved'))
            ->pluck('match_id');

        if ($userMatchIds->isEmpty()) {
            return [];
        }

        // Find other users who participated in the same matches
        $teammateMatchCounts = UserMatchParticipation::query()
            ->whereIn('match_id', $userMatchIds)
            ->where('user_id', '!=', $userId)
            ->selectRaw('user_id, COUNT(*) as match_count')
            ->havingRaw('COUNT(*) >= ?', [5])
            ->groupBy('user_id')
            ->orderBy('match_count', 'desc')
            ->limit(3)
            ->with('user:id,osu_id,username')
            ->get();

        return $teammateMatchCounts->map(fn ($p) => [
            'user_id' => $p->user_id,
            'username' => $p->user->username,
            'avatar_url' => $p->user->avatar_url,
            'match_count' => $p->match_count,
        ])->toArray();
    }

    /**
     * Get frequent opponents (5+ matches against).
     *
     * @return array<array{user_id: int, username: string, avatar_url: string|null, match_count: int}>
     */
    protected function getFrequentOpponents(int $userId): array
    {
        // Get all match IDs where the user participated
        $userMatchIds = UserMatchParticipation::query()
            ->where('user_id', $userId)
            ->whereHas('match', fn ($q) => $q->where('status', 'approved'))
            ->pluck('match_id');

        if ($userMatchIds->isEmpty()) {
            return [];
        }

        // Get the user's team in each match
        $userTeams = UserMatchParticipation::query()
            ->where('user_id', $userId)
            ->whereIn('match_id', $userMatchIds)
            ->pluck('team', 'match_id');

        // Find opponents - users who were on different teams in the same matches
        $opponentMatchCounts = UserMatchParticipation::query()
            ->whereIn('match_id', $userMatchIds)
            ->where('user_id', '!=', $userId)
            ->where(function ($query) use ($userTeams) {
                foreach ($userTeams as $matchId => $userTeam) {
                    // For head-to-head (no team), all others are opponents
                    // For team matches, opponents are on different teams
                    if ($userTeam === null) {
                        $query->orWhere(function ($q) use ($matchId) {
                            $q->where('match_id', $matchId)
                                ->whereNull('team');
                        });
                    } else {
                        $query->orWhere(function ($q) use ($matchId, $userTeam) {
                            $q->where('match_id', $matchId)
                                ->where('team', '!=', $userTeam)
                                ->orWhereNull('team');
                        });
                    }
                }
            })
            ->selectRaw('user_id, COUNT(*) as match_count')
            ->havingRaw('COUNT(*) >= ?', [5])
            ->groupBy('user_id')
            ->orderBy('match_count', 'desc')
            ->limit(3)
            ->with('user:id,osu_id,username')
            ->get();

        return $opponentMatchCounts->map(fn ($p) => [
            'user_id' => $p->user_id,
            'username' => $p->user->username,
            'avatar_url' => $p->user->avatar_url,
            'match_count' => $p->match_count,
        ])->toArray();
    }

    /**
     * Get total matches for a user (approved only).
     */
    public function getTotalMatches(int $userId): int
    {
        return UserMatchParticipation::query()
            ->where('user_id', $userId)
            ->whereHas('match', fn ($q) => $q->where('status', 'approved'))
            ->count();
    }

    /**
     * Get total games played for a user.
     */
    public function getTotalGames(int $userId): int
    {
        return (int) UserMatchParticipation::query()
            ->where('user_id', $userId)
            ->whereHas('match', fn ($q) => $q->where('status', 'approved'))
            ->sum('games_played');
    }

    /**
     * Calculate win rate for a user.
     *
     * @return float Win rate percentage (0-100)
     */
    public function getWinRate(int $userId): float
    {
        $participations = UserMatchParticipation::query()
            ->where('user_id', $userId)
            ->whereHas('match', fn ($q) => $q->where('status', 'approved'))
            ->get();

        $totalGames = $participations->sum('games_played');
        $gamesWon = $participations->sum('games_won');

        return $totalGames > 0
            ? round(($gamesWon / $totalGames) * 100, 2)
            : 0.0;
    }
}
