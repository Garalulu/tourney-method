<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;

class BwsCalculator
{
    /**
     * @var array<string, int>
     */
    private array $eligibleBadgeCountCache = [];

    /**
     * @var array<string, float|null>
     */
    private array $calculatedRankCache = [];

    /**
     * Calculate BWS rank from raw values
     *
     * Formula: BWS = rank^(base^(badgeCount^badgePower) / divisor)
     *
     * @param  int  $rank  User's global rank
     * @param  int  $eligibleBadges  Count of eligible badges
     * @param  float  $baseExponent  Base exponent (default 0.9937)
     * @param  float  $badgePower  Badge power (default 2.0)
     * @param  float  $divisor  Divisor (default 1.0)
     * @return float The calculated BWS rank
     */
    public function calculate(int $rank, int $eligibleBadges, float $baseExponent = 0.9937, float $badgePower = 2.0, float $divisor = 1.0): float
    {
        // If no badges, return raw rank
        if ($eligibleBadges === 0) {
            return (float) $rank;
        }

        // Calculate BWS: rank^(base^(badgeCount^badgePower) / divisor)
        $exponent = pow($baseExponent, pow($eligibleBadges, $badgePower)) / $divisor;
        $bws = pow($rank, $exponent);

        return $bws;
    }

    /**
     * Calculate BWS (Badge-Weighted Seeding) rank for a user in a tournament
     *
     * @param  User  $user  The user to calculate BWS for
     * @param  Tournament  $tournament  Tournament with BWS settings
     * @param  string  $mode  Game mode (osu, taiko, catch, mania)
     * @return float|null The calculated BWS rank, or null if rank unavailable
     */
    public function calculateForUser(User $user, Tournament $tournament, string $mode): ?float
    {
        $cacheKey = $this->calculatedRankCacheKey($user->id, $tournament->id, $mode);

        if (array_key_exists($cacheKey, $this->calculatedRankCache)) {
            return $this->calculatedRankCache[$cacheKey];
        }

        // Determine the actual mode to use for rank lookup
        $rankMode = $this->getRankMode($tournament, $mode);

        // Get user's current rank for the determined mode
        $rankHistory = $user->relationLoaded('rankHistory')
            ? $user->rankHistory
                ->where('mode', $rankMode)
                ->sortByDesc('recorded_at')
                ->first()
            : $user->rankHistory()
                ->where('mode', $rankMode)
                ->latest('recorded_at')
                ->first();

        if (! $rankHistory || ! $rankHistory->global_rank) {
            return $this->calculatedRankCache[$cacheKey] = null;
        }

        $rank = $rankHistory->global_rank;

        // Count eligible badges
        $badgeCount = $this->getEligibleBadgeCount($user, $tournament, $mode);

        // Get BWS parameters from tournament (or use defaults)
        $baseExponent = $tournament->bws_base_exponent ?? 0.9937;
        $badgePower = $tournament->bws_badge_power ?? 2.0;
        $divisor = $tournament->bws_divisor ?? 1.0;

        return $this->calculatedRankCache[$cacheKey] = $this->calculate(
            $rank,
            $badgeCount,
            $baseExponent,
            $badgePower,
            $divisor,
        );
    }

    /**
     * Calculate BWS ranks for multiple users while batching eligible badge counts.
     *
     * @param  iterable<int, User>  $users
     * @return array<int, float|null>
     */
    public function calculateForUsers(iterable $users, Tournament $tournament, string $mode): array
    {
        $users = collect($users)
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return [];
        }

        $ranks = [];
        $uncachedUsers = $users
            ->filter(function (User $user) use ($tournament, $mode, &$ranks): bool {
                $cacheKey = $this->calculatedRankCacheKey($user->id, $tournament->id, $mode);

                if (! array_key_exists($cacheKey, $this->calculatedRankCache)) {
                    return true;
                }

                $ranks[$user->id] = $this->calculatedRankCache[$cacheKey];

                return false;
            })
            ->values();

        if ($uncachedUsers->isEmpty()) {
            return $ranks;
        }

        $rankMode = $this->getRankMode($tournament, $mode);
        $badgeCounts = $this->getEligibleBadgeCounts($uncachedUsers, $tournament, $mode);
        $baseExponent = $tournament->bws_base_exponent ?? 0.9937;
        $badgePower = $tournament->bws_badge_power ?? 2.0;
        $divisor = $tournament->bws_divisor ?? 1.0;

        foreach ($uncachedUsers as $user) {
            $cacheKey = $this->calculatedRankCacheKey($user->id, $tournament->id, $mode);
            $rankHistory = $user->relationLoaded('rankHistory')
                ? $user->rankHistory
                    ->where('mode', $rankMode)
                    ->sortByDesc('recorded_at')
                    ->first()
                : $user->rankHistory()
                    ->where('mode', $rankMode)
                    ->latest('recorded_at')
                    ->first();

            if (! $rankHistory || ! $rankHistory->global_rank) {
                $ranks[$user->id] = $this->calculatedRankCache[$cacheKey] = null;

                continue;
            }

            $ranks[$user->id] = $this->calculatedRankCache[$cacheKey] = $this->calculate(
                $rankHistory->global_rank,
                $badgeCounts[$user->id] ?? 0,
                $baseExponent,
                $badgePower,
                $divisor,
            );
        }

        return $ranks;
    }

    private function calculatedRankCacheKey(int $userId, int $tournamentId, string $mode): string
    {
        return implode(':', [$userId, $tournamentId, $mode]);
    }

    /**
     * Get count of BWS-eligible badges for a user
     * Filters by gamemode to ensure only relevant badges are counted
     */
    private function getEligibleBadgeCount(User $user, Tournament $tournament, string $mode): int
    {
        $badgeGamemode = $this->mapModeToBadgeGamemode($mode);

        $cacheKey = implode(':', [
            $user->id,
            $badgeGamemode,
            optional($tournament->bws_badge_age_cutoff)->toDateTimeString() ?? 'none',
        ]);

        if (array_key_exists($cacheKey, $this->eligibleBadgeCountCache)) {
            return $this->eligibleBadgeCountCache[$cacheKey];
        }

        $query = \DB::table('user_badges')
            ->join('tournament_winners', function ($join) {
                $join->on('user_badges.user_id', '=', 'tournament_winners.user_id')
                    ->on('user_badges.tournament_id', '=', 'tournament_winners.tournament_id');
            })
            ->where('user_badges.user_id', $user->id)
            ->where('user_badges.is_bws_eligible', true)
            ->where('tournament_winners.gamemode', $badgeGamemode);

        // Use absolute cutoff date instead of relative years
        if ($tournament->bws_badge_age_cutoff) {
            $query->where('user_badges.awarded_at', '>=', $tournament->bws_badge_age_cutoff);
        }

        return $this->eligibleBadgeCountCache[$cacheKey] = $query->count();
    }

    /**
     * Batch eligible badge counts for users sharing one tournament/mode context.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, int>
     */
    private function getEligibleBadgeCounts(Collection $users, Tournament $tournament, string $mode): array
    {
        $badgeGamemode = $this->mapModeToBadgeGamemode($mode);
        $cutoff = optional($tournament->bws_badge_age_cutoff)->toDateTimeString() ?? 'none';
        $userIds = $users->pluck('id')->all();
        $counts = array_fill_keys($userIds, 0);
        $uncachedUserIds = [];

        foreach ($userIds as $userId) {
            $cacheKey = implode(':', [$userId, $badgeGamemode, $cutoff]);

            if (array_key_exists($cacheKey, $this->eligibleBadgeCountCache)) {
                $counts[$userId] = $this->eligibleBadgeCountCache[$cacheKey];

                continue;
            }

            $uncachedUserIds[] = $userId;
        }

        if ($uncachedUserIds === []) {
            return $counts;
        }

        $query = \DB::table('user_badges')
            ->join('tournament_winners', function ($join) {
                $join->on('user_badges.user_id', '=', 'tournament_winners.user_id')
                    ->on('user_badges.tournament_id', '=', 'tournament_winners.tournament_id');
            })
            ->select('user_badges.user_id', \DB::raw('count(*) as aggregate'))
            ->whereIn('user_badges.user_id', $uncachedUserIds)
            ->where('user_badges.is_bws_eligible', true)
            ->where('tournament_winners.gamemode', $badgeGamemode)
            ->groupBy('user_badges.user_id');

        if ($tournament->bws_badge_age_cutoff) {
            $query->where('user_badges.awarded_at', '>=', $tournament->bws_badge_age_cutoff);
        }

        foreach ($query->pluck('aggregate', 'user_id') as $userId => $count) {
            $counts[(int) $userId] = (int) $count;
        }

        foreach ($uncachedUserIds as $userId) {
            $cacheKey = implode(':', [$userId, $badgeGamemode, $cutoff]);
            $this->eligibleBadgeCountCache[$cacheKey] = $counts[$userId];
        }

        return $counts;
    }

    /**
     * Map tournament mode to badge gamemode format
     * Handles 'fruits' -> 'catch' mapping for API consistency
     */
    private function mapModeToBadgeGamemode(string $mode): string
    {
        // 'catch' in badges = 'catch' or 'fruits' in tournaments
        return match ($mode) {
            'fruits' => 'catch',
            default => $mode,
        };
    }

    /**
     * Check if a user is eligible for a tournament based on rank range and BWS
     */
    public function isEligible(User $user, Tournament $tournament, string $mode): bool
    {
        // If tournament doesn't have rank restrictions, everyone is eligible
        if (! $tournament->rank_range_min && ! $tournament->rank_range_max) {
            return true;
        }

        // Determine which rank to use
        if ($tournament->is_bws) {
            $effectiveRank = $this->calculateForUser($user, $tournament, $mode);
        } else {
            // Determine the actual mode to use for rank lookup
            $rankMode = $this->getRankMode($tournament, $mode);
            $rankHistory = $user->relationLoaded('rankHistory')
                ? $user->rankHistory
                    ->where('mode', $rankMode)
                    ->sortByDesc('recorded_at')
                    ->first()
                : $user->rankHistory()
                    ->where('mode', $rankMode)
                    ->latest('recorded_at')
                    ->first();
            $effectiveRank = $rankHistory?->global_rank;
        }

        if (! $effectiveRank) {
            return false;
        }

        // Check if rank is within range
        $minRank = $tournament->rank_range_min;
        $maxRank = $tournament->rank_range_max;

        if ($minRank && $effectiveRank < $minRank) {
            return false;
        }

        if ($maxRank && $effectiveRank > $maxRank) {
            return false;
        }

        return true;
    }

    /**
     * Get detailed BWS information for display
     *
     * @return array<string, mixed>
     */
    public function getDetails(User $user, Tournament $tournament, string $mode): array
    {
        $rankMode = $this->getRankMode($tournament, $mode);
        $rankHistory = $user->relationLoaded('rankHistory')
            ? $user->rankHistory
                ->where('mode', $rankMode)
                ->sortByDesc('recorded_at')
                ->first()
            : $user->rankHistory()
                ->where('mode', $rankMode)
                ->latest('recorded_at')
                ->first();
        $actualRank = $rankHistory?->global_rank;
        $bwsRank = $tournament->is_bws ? $this->calculateForUser($user, $tournament, $mode) : null;
        $eligibleBadges = $this->getEligibleBadgeCount($user, $tournament, $mode);
        $isEligible = $this->isEligible($user, $tournament, $mode);

        return [
            'actual_rank' => $actualRank,
            'bws_rank' => $bwsRank,
            'eligible_badge_count' => $eligibleBadges,
            'is_eligible' => $isEligible,
            'effective_rank' => $bwsRank ?? $actualRank,
        ];
    }

    /**
     * Determine the actual rank mode to use based on tournament configuration
     * For mania tournaments, checks if tournament is 4K or 7K specific
     *
     * @param  Tournament  $tournament  The tournament to check
     * @param  string  $mode  The base mode (osu, taiko, catch, mania)
     * @return string The actual mode to use for rank lookup ('osu', 'taiko', 'catch', 'mania', '4k', '7k')
     */
    private function getRankMode(Tournament $tournament, string $mode): string
    {
        // Only mania has variant-specific tournaments
        if ($mode !== 'mania') {
            return $mode;
        }

        // Parse tournament modes to check for key_count
        $tournamentModes = $tournament->modes ?? [];

        if (empty($tournamentModes)) {
            return $mode;
        }

        // Find the mania mode entry
        foreach ($tournamentModes as $modeConfig) {
            if (! isset($modeConfig['mode']) || $modeConfig['mode'] !== 'mania') {
                continue;
            }

            // Check if tournament is 4K or 7K specific
            $keyCount = $modeConfig['key_count'] ?? null;

            return match ($keyCount) {
                4 => '4k',
                7 => '7k',
                default => 'mania',
            };
        }

        return $mode;
    }
}
