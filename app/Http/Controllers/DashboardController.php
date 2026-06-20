<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Jobs\RefreshRankingPpJob;
use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Services\BwsCalculator;
use App\Services\OsuApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private OsuApiService $osuApiService
    ) {}

    /**
     * Display the user dashboard.
     * Returns view for web requests, JSON for API requests.
     */
    public function index(Request $request): View|DashboardResource
    {
        // Eager load user relationships to prevent N+1 queries
        $user = Auth::user()->load([
            'rankHistory' => function ($query) {
                $query->orderByDesc('recorded_at');
            },
            'badges' => function ($query) {
                $query->orderByDesc('awarded_at');
            },
        ]);

        // Get current rank for user's main_mode (now from preloaded collection)
        $rankHistory = $user->rankHistory
            ->first(fn ($rank) => $rank->mode === $user->main_mode);

        // For mania players, get variant ranks (4k, 7k) from users table AND rank history for PP
        $mania4kRank = null;
        $mania7kRank = null;
        $mania4kPP = null;
        $mania7kPP = null;

        if ($user->main_mode === 'mania') {
            $mania4kRank = $user->rank_mania_4k;
            $mania7kRank = $user->rank_mania_7k;

            // Get PP from rank history
            $mania4kHistory = $user->rankHistory->first(fn ($rank) => $rank->mode === '4k');
            $mania7kHistory = $user->rankHistory->first(fn ($rank) => $rank->mode === '7k');
            $mania4kPP = $mania4kHistory?->pp;
            $mania7kPP = $mania7kHistory?->pp;
        }

        // Get badge count for current year (now from preloaded collection)
        $badgeCountThisYear = $user->badges
            ->filter(fn ($badge) => $badge->awarded_at->year === now()->year)
            ->count();

        $bwsBadgeCount = DB::table('user_badges')
            ->join('tournament_winners', function ($join) {
                $join->on('user_badges.user_id', '=', 'tournament_winners.user_id')
                    ->on('user_badges.tournament_id', '=', 'tournament_winners.tournament_id');
            })
            ->where('user_badges.user_id', $user->id)
            ->where('user_badges.is_bws_eligible', true)
            ->where('tournament_winners.gamemode', $this->badgeGamemode($user->main_mode))
            ->count();

        // Get currently running tournaments (mode matches user's main_mode)
        $currentlyRunning = Tournament::query()
            ->where('status', 'approved')
            ->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements(modes::jsonb) AS mode_elem
                WHERE mode_elem->>'mode' = ?
            )", [$user->main_mode])
            ->where(function ($query) {
                $query->where('tournament_start', '<=', now())
                    ->where(function ($q) {
                        $q->where('tournament_end', '>', now())
                            ->orWhereNull('tournament_end');
                    });
            })
            ->orderBy('tournament_end', 'asc')
            ->orderByRaw('tournament_end IS NULL ASC')
            ->limit(10)
            ->get();

        // Get tournaments with registration open (mode matches user's main_mode)
        $registrationOpenQuery = Tournament::query()
            ->where('status', 'approved')
            ->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements(modes::jsonb) AS mode_elem
                WHERE mode_elem->>'mode' = ?
            )", [$user->main_mode])
            ->where('registration_start', '<=', now())
            ->where('registration_end', '>', now())
            ->orderBy('registration_end', 'asc');

        // Apply eligibility filter in PHP (after query)
        /** @var Collection<int, Tournament> $allRegistrationOpen */
        $allRegistrationOpen = $registrationOpenQuery->get();
        $registrationOpen = $allRegistrationOpen->filter(fn ($tournament) => $tournament->isEligibleForUser($user))
            ->take(10);

        // Get last week's results (previous calendar week: Monday-Sunday)
        $lastWeekStart = now()->subWeek()->startOfWeek();  // Monday of last week
        $lastWeekEnd = now()->subWeek()->endOfWeek();      // Sunday of last week

        $lastWeekResults = Tournament::query()
            ->where('status', 'approved')
            ->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements(modes::jsonb) AS mode_elem
                WHERE mode_elem->>'mode' = ?
            )", [$user->main_mode])
            ->whereNotNull('tournament_end')
            ->where('tournament_end', '>=', $lastWeekStart)
            ->where('tournament_end', '<=', $lastWeekEnd)
            ->orderByRaw('star_rating_last DESC NULLS LAST')
            ->orderByRaw('
                CASE
                    WHEN rank_range_min IS NULL THEN 0    -- Open rank (highest priority)
                    ELSE rank_range_min
                END ASC
            ')
            ->orderByRaw('
                CASE
                    WHEN restricted_countries IS NULL THEN 1      -- Global tournaments
                    WHEN jsonb_array_length(restricted_countries::jsonb) = 0 THEN 1
                    ELSE 2                                         -- Regional restrictions
                END ASC
            ')
            ->orderBy('tournament_end', 'desc')
            ->with(['winners' => function ($query) {
                $query->where('placement', '<=', 3)
                    ->orderBy('placement')
                    ->orderBy('username')
                    ->with('user.rankHistory');
            }])
            ->get()
            ->each(fn (Tournament $tournament) => $this->sortWinnersByOsuBwsRank($tournament));

        // Get rank milestones (PP at rank 100, 1000, 10000)
        $rankMilestones = $this->getRankMilestones($user->main_mode);

        // Return JSON for API requests
        if ($request->expectsJson()) {
            return new DashboardResource([
                'user' => $user,
                'rank_milestones' => $rankMilestones,
                'badge_tournament_count_year' => $badgeCountThisYear,
                'bws_badge_count' => $bwsBadgeCount,
                'currently_running' => $currentlyRunning,
                'registration_open' => $registrationOpen,
                'last_week_results' => $lastWeekResults,
                'last_week_start' => $lastWeekStart,
                'last_week_end' => $lastWeekEnd,
            ]);
        }

        // Return view for web requests
        return view('dashboard.index', [
            'user' => $user,
            'rankHistory' => $rankHistory,
            'mania4kRank' => $mania4kRank,
            'mania7kRank' => $mania7kRank,
            'mania4kPP' => $mania4kPP,
            'mania7kPP' => $mania7kPP,
            'badgeCountThisYear' => $badgeCountThisYear,
            'bwsBadgeCount' => $bwsBadgeCount,
            'rankMilestones' => $rankMilestones,
            'currentlyRunning' => $currentlyRunning,
            'registrationOpen' => $registrationOpen,
            'lastWeekResults' => $lastWeekResults,
            'lastWeekStart' => $lastWeekStart,
            'lastWeekEnd' => $lastWeekEnd,
        ]);
    }

    /**
     * Get PP at rank milestones from osu! API (FR-036)
     * Cached for 1 day since rankings change slowly
     *
     * @return array<string, float|null>
     */
    private function getRankMilestones(string $mode): array
    {
        $rankMilestones = $this->osuApiService->getCachedRankingPP($mode, [100, 1000, 10000]);

        if (collect($rankMilestones)->every(fn ($pp): bool => $pp === null)) {
            $lockKey = "dashboard_rank_milestones_refresh_dispatched_{$mode}";

            if (Cache::add($lockKey, true, now()->addMinutes(10))) {
                RefreshRankingPpJob::dispatch($mode);
            }
        }

        return $rankMilestones;
    }

    /**
     * Sort podium winners by placement, linked-user status, osu! BWS rank, then username.
     */
    private function sortWinnersByOsuBwsRank(Tournament $tournament): void
    {
        /** @var Collection<int, TournamentWinner> $winners */
        $winners = $tournament->winners;
        $bwsCalculator = app(BwsCalculator::class);

        $bwsRanks = [];
        $winners
            ->filter(fn (TournamentWinner $winner): bool => $winner->user !== null)
            ->groupBy('gamemode')
            ->each(function ($modeWinners, string $mode) use (&$bwsRanks, $bwsCalculator, $tournament): void {
                $modeRanks = $bwsCalculator->calculateForUsers(
                    $modeWinners->pluck('user'),
                    $tournament,
                    $mode,
                );

                foreach ($modeWinners as $winner) {
                    $bwsRanks[$winner->id] = $modeRanks[$winner->user_id] ?? null;
                }
            });

        $sortKeys = $winners
            ->mapWithKeys(function (TournamentWinner $winner) use ($bwsRanks): array {
                return [
                    $winner->id => [
                        'placement' => $winner->placement,
                        'linked' => $winner->user_id ? 0 : 1,
                        'bws_rank' => $bwsRanks[$winner->id] ?? PHP_INT_MAX,
                        'username' => mb_strtolower($winner->display_username),
                    ],
                ];
            });

        $sortedWinners = $winners
            ->sort(function (TournamentWinner $first, TournamentWinner $second) use ($sortKeys): int {
                $firstKey = $sortKeys[$first->id];
                $secondKey = $sortKeys[$second->id];

                $placementCompare = $firstKey['placement'] <=> $secondKey['placement'];

                if ($placementCompare !== 0) {
                    return $placementCompare;
                }

                $linkedCompare = $firstKey['linked'] <=> $secondKey['linked'];

                if ($linkedCompare !== 0) {
                    return $linkedCompare;
                }

                $rankCompare = $firstKey['bws_rank'] <=> $secondKey['bws_rank'];

                if ($rankCompare !== 0) {
                    return $rankCompare;
                }

                return $firstKey['username'] <=> $secondKey['username'];
            })
            ->values();

        $tournament->setRelation('winners', $sortedWinners);
    }

    private function badgeGamemode(?string $mode): ?string
    {
        return $mode === 'fruits' ? 'catch' : $mode;
    }
}
