<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\ParticipationRecordMatch;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Services\ExactUsernameResolver;
use App\Services\ParticipationRecordQueryService;
use App\Services\ParticipationStageOptionsService;
use App\Services\ParticipationStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserProfileController extends Controller
{
    private const PARTICIPATION_RECORDS_PER_PAGE = 10;

    private const CONTRIBUTIONS_PER_PAGE = 10;

    public function __construct(
        protected ParticipationStatsService $participationStats,
        protected ParticipationStageOptionsService $participationStageOptions,
        protected ParticipationRecordQueryService $participationRecordQueries
    ) {}

    /**
     * Display the public user profile.
     *
     * GET /users/{id}
     */
    public function show(string|int $userId): View
    {
        $userId = (int) $userId;

        $user = User::with([
            'badges' => function ($query) {
                // Only load tournament badges (filter out non-tournament badges)
                $query->whereNotNull('tournament_id')
                    ->orderBy('awarded_at', 'desc');
            },
            'participationInputLock',
            'rankHistory' => function ($query) {
                $query->orderByDesc('recorded_at');
            },
        ])->findOrFail($userId);

        // Get gamemode for each badge from tournament_winners table
        // (using DB query because composite key relationships don't work with Eloquent)
        $badgeGamemodes = \DB::table('user_badges')
            ->join('tournament_winners', function ($join) {
                $join->on('user_badges.user_id', '=', 'tournament_winners.user_id')
                    ->on('user_badges.tournament_id', '=', 'tournament_winners.tournament_id');
            })
            ->where('user_badges.user_id', $userId)
            ->whereNotNull('user_badges.tournament_id')
            ->pluck('tournament_winners.gamemode', 'user_badges.id');

        // Group badges by gamemode
        // Build associative array first, then convert to collection for type safety
        $groupedBadges = [];
        foreach ($user->badges as $badge) {
            $mode = $badgeGamemodes->get($badge->id, 'other');
            $groupedBadges[$mode][] = $badge;
        }

        $badgesByMode = collect($groupedBadges);

        // Sort modes in preferred order: osu, taiko, catch, mania, 4k, 7k
        $modeOrder = ['osu', 'taiko', 'catch', 'mania', '4k', '7k'];
        $badgesByMode = $badgesByMode->sortBy(function ($badges, $mode) use ($modeOrder) {
            $index = array_search($mode, $modeOrder);

            return $index === false ? 999 : $index;
        });

        // Load staff roles (approved only from approved tournaments), sorted by tournament date (most recent first), grouped by tournament
        $staffRoles = TournamentStaff::with('tournament')
            ->where('tournament_staff.user_id', $userId)
            ->where('tournament_staff.status', 'approved')
            ->whereHas('tournament', fn ($q) => $q->where('status', 'approved'))
            ->join('tournaments', 'tournament_staff.tournament_id', '=', 'tournaments.id')
            ->orderByDesc('tournaments.tournament_end')
            ->select('tournament_staff.*')
            ->get()
            ->groupBy('tournament_id');

        // Load podium placements (approved tournaments only, most recent first)
        $podiumPlacements = TournamentWinner::where('user_id', $userId)
            ->where('placement', '<=', 3) // Only podium
            ->whereHas('tournament', fn ($q) => $q->where('status', 'approved')) // Approved only
            ->with(['tournament']) // Eager load tournament data
            ->join('tournaments', 'tournament_winners.tournament_id', '=', 'tournaments.id')
            ->orderByDesc('tournaments.tournament_end') // Most recent first
            ->select('tournament_winners.*')
            ->get()
            ->groupBy('tournament_id'); // One row per tournament

        $matchStats = $this->emptyMatchStats();

        $canManageParticipation = auth()->id() === $user->id || (auth()->user()?->isAdmin() ?? false);
        $viewerCanSeeHiddenParticipation = $canManageParticipation;

        $focusedParticipationTournamentId = request()->integer('participation_tournament');
        $participationRequest = $focusedParticipationTournamentId > 0 ? request() : null;
        $displayParticipationRecords = $this->participationRecordQueries->pageFor(
            $user,
            $viewerCanSeeHiddenParticipation,
            $participationRequest,
            self::PARTICIPATION_RECORDS_PER_PAGE
        );
        $participationRecordCount = $this->participationRecordQueries->countFor(
            $user,
            $viewerCanSeeHiddenParticipation,
            $participationRequest
        );

        $participationStats = $this->participationStats->summarizeForUser($user, $viewerCanSeeHiddenParticipation);
        $participationAvailableYears = $this->participationRecordQueries->availableYearsFor($user, $viewerCanSeeHiddenParticipation);
        $participationAvailableModes = $this->participationRecordQueries->availableModesFor($user, $viewerCanSeeHiddenParticipation);
        $participationIndices = $this->participationRecordQueries->globalIndicesFor($displayParticipationRecords, $user, $viewerCanSeeHiddenParticipation);
        $displayTeammatesByRecord = $this->participationRecordQueries->displayTeammatesForRecords($displayParticipationRecords);
        $displayTeammateBwsRanksByRecord = $this->participationRecordQueries
            ->displayTeammateBwsRanksForRecords($displayParticipationRecords, $displayTeammatesByRecord);
        $participationStageOptions = $displayParticipationRecords
            ->mapWithKeys(fn ($record) => [$record->tournament_id => $this->participationStageOptions->optionsFor($record->tournament)->all()]);
        /** @var Collection<int, Tournament> $participationTournamentOptions */
        $participationTournamentOptions = auth()->id() === $user->id
            ? Tournament::query()
                ->approved()
                ->orderByRaw('tournament_end DESC NULLS LAST')
                ->limit(100)
                ->get()
            : new Collection;
        $participationCreateStageOptions = $participationTournamentOptions
            ->mapWithKeys(fn (Tournament $tournament) => [$tournament->id => $this->participationStageOptions->optionsFor($tournament)->all()]);
        $participationTournamentDetails = $participationTournamentOptions
            ->merge($displayParticipationRecords->pluck('tournament'))
            ->unique('id')
            ->mapWithKeys(fn (Tournament $tournament) => [$tournament->id => [
                'id' => $tournament->id,
                'title' => $tournament->title,
                'year' => $tournament->tournament_end?->year,
                'modes' => collect($tournament->modes)->map(fn ($mode): ?string => data_get($mode, 'mode', $mode))->filter()->values()->all(),
                'is_badge' => (bool) $tournament->is_badge,
                'profile_url' => route('tournaments.show', $tournament),
                'forum_post_url' => $tournament->forum_post_url,
                'spreadsheet_url' => $tournament->spreadsheet_url,
                'bracket_url' => $tournament->bracket_url,
                'stage_options' => $this->participationStageOptions->optionsFor($tournament)->all(),
                'match_stage_options' => $this->participationStageOptions->matchStageLabelsFor($tournament),
                'has_qualifier' => $this->participationStageOptions->hasQualifier($tournament),
                'qualifier_cutoff' => $this->participationStageOptions->qualifierCutoff($tournament),
                'is_team_tournament' => $this->participationStageOptions->isTeamTournament($tournament),
            ]]);

        // Load ranks data per OpenAPI spec: { mode: { global_rank, country_rank, pp } }
        $ranks = $this->getUserRanks($user);

        // Get tournament count (per OpenAPI UserProfile schema)
        $tournamentCount = $this->getTournamentCount($userId);

        // Get match count (per OpenAPI UserProfile schema)
        $matchCount = $this->getMatchCount($userId);

        // Check for recap availability (current year and previous year)
        $currentYear = (int) date('Y');
        $previousYear = $currentYear - 1;
        $recapAvailable = [
            $currentYear => $this->hasParticipationDataForYear($user, $currentYear),
            $previousYear => $this->hasParticipationDataForYear($user, $previousYear),
        ];
        $contributions = $this->contributionsPageFor($user, 0, self::CONTRIBUTIONS_PER_PAGE);

        return view('users.show', [
            'user' => $user,
            'badgesByMode' => $badgesByMode,
            'staffRoles' => $staffRoles,
            'podiumPlacements' => $podiumPlacements,
            'matchStats' => $matchStats,
            'ranks' => $ranks,
            'tournamentCount' => $tournamentCount,
            'matchCount' => $matchCount,
            'recapAvailable' => $recapAvailable,
            'participationRecords' => $displayParticipationRecords,
            'participationStats' => $participationStats,
            'participationAvailableYears' => $participationAvailableYears,
            'participationAvailableModes' => $participationAvailableModes,
            'participationStageOptions' => $participationStageOptions,
            'participationIndices' => $participationIndices,
            'displayTeammatesByRecord' => $displayTeammatesByRecord,
            'displayTeammateBwsRanksByRecord' => $displayTeammateBwsRanksByRecord,
            'participationTournamentOptions' => $participationTournamentOptions,
            'participationCreateStageOptions' => $participationCreateStageOptions,
            'participationTournamentDetails' => $participationTournamentDetails,
            'activeTab' => request()->query('tab', auth()->id() === $user->id ? 'participation' : 'history'),
            'canManageParticipation' => $canManageParticipation,
            'participationRecordsUrl' => route('users.participation.records', $user),
            'participationRecordsNextOffset' => $participationRecordCount > self::PARTICIPATION_RECORDS_PER_PAGE
                ? self::PARTICIPATION_RECORDS_PER_PAGE
                : null,
            'participationRecordsHasMore' => $participationRecordCount > self::PARTICIPATION_RECORDS_PER_PAGE,
            'focusedParticipationTournamentId' => $focusedParticipationTournamentId > 0 ? $focusedParticipationTournamentId : null,
            'contributionGroups' => $contributions['groups'],
            'contributionCount' => $contributions['count'],
            'contributionGroupsHasMore' => $contributions['hasMore'],
            'contributionGroupsNextOffset' => $contributions['nextOffset'],
            'contributionGroupsUrl' => route('users.contributions', $user),
        ]);
    }

    public function redirectByUsername(string $username, ExactUsernameResolver $resolver): RedirectResponse
    {
        $user = $resolver->resolve($username);

        abort_unless($user !== null, 404);

        return redirect()->route('users.show', $user);
    }

    public function contributions(Request $request, User $user): JsonResponse
    {
        $offset = max(0, $request->integer('offset', 0));
        $contributions = $this->contributionsPageFor($user, $offset, self::CONTRIBUTIONS_PER_PAGE);

        return response()->json([
            'html' => view('users.partials.contribution-rows', [
                'contributionGroups' => $contributions['groups'],
            ])->render(),
            'next_offset' => $contributions['nextOffset'],
            'has_more' => $contributions['hasMore'],
        ]);
    }

    /**
     * @return array{
     *     groups: \Illuminate\Support\Collection<int, array{tournament: object}>,
     *     count: int,
     *     hasMore: bool,
     *     nextOffset: int|null
     * }
     */
    private function contributionsPageFor(User $user, int $offset, int $limit): array
    {
        $count = (int) DB::query()
            ->fromSub($this->contributionTournamentQuery($user), 'contribution_tournaments')
            ->join('tournaments', 'tournaments.id', '=', 'contribution_tournaments.tournament_id')
            ->where('tournaments.status', Tournament::STATUS_APPROVED)
            ->count();

        $rows = DB::query()
            ->fromSub($this->contributionTournamentQuery($user), 'contribution_tournaments')
            ->join('tournaments', 'tournaments.id', '=', 'contribution_tournaments.tournament_id')
            ->where('tournaments.status', Tournament::STATUS_APPROVED)
            ->select([
                'tournaments.id',
                'tournaments.title',
                'contribution_tournaments.latest_at',
            ])
            ->orderByDesc('contribution_tournaments.latest_at')
            ->orderBy('tournaments.title')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $groups = $rows
            ->take($limit)
            ->map(fn (object $row): array => [
                'tournament' => (object) [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                ],
            ])
            ->values();

        return [
            'groups' => $groups,
            'count' => $count,
            'hasMore' => $hasMore,
            'nextOffset' => $hasMore ? $offset + $limit : null,
        ];
    }

    private function contributionTournamentQuery(User $user): Builder
    {
        $corrections = TournamentCorrection::query()
            ->selectRaw('tournament_id, MAX(COALESCE(reviewed_at, created_at)) AS latest_at')
            ->where('submitted_by', $user->id)
            ->whereHas('tournament', fn ($query) => $query->where('status', Tournament::STATUS_APPROVED))
            ->whereIn('status', [
                TournamentCorrection::STATUS_APPROVED,
                TournamentCorrection::STATUS_PARTIALLY_APPROVED,
            ])
            ->groupBy('tournament_id');

        $adminEdits = AdminAuditLog::query()
            ->selectRaw('entity_id AS tournament_id, MAX(created_at) AS latest_at')
            ->where('admin_id', $user->id)
            ->where('entity_type', Tournament::class)
            ->whereIn('action', $this->adminContributionActions())
            ->groupBy('entity_id');

        return DB::query()
            ->fromSub($corrections->unionAll($adminEdits), 'contribution_events')
            ->selectRaw('tournament_id, MAX(latest_at) AS latest_at')
            ->whereNotNull('tournament_id')
            ->groupBy('tournament_id');
    }

    /**
     * @return list<string>
     */
    private function adminContributionActions(): array
    {
        return [
            'tournament.updated',
            'tournament.staff_added',
            'tournament.staff_role_updated',
            'tournament.staff_roles_replaced',
            'tournament.staff_removed',
            'tournament.staff_role_bulk_removed',
            'tournament.podium_winner_updated',
            'tournament.podium_winner_removed',
            'tournament.badge_url_added',
            'tournament.badge_url_removed',
            'tournament.host_changed',
        ];
    }

    /**
     * Get user ranks per mode (osu, taiko, catch, mania).
     * Returns latest recorded rank per mode.
     * Includes mania variants (4k, 7k) when user's main mode is mania.
     *
     * @return array<string, array{global_rank: int|null, country_rank: int|null, pp: float|null}>
     */
    protected function getUserRanks(User $user): array
    {
        $modes = ['osu', 'taiko', 'catch', 'mania'];
        $ranks = [];

        foreach ($modes as $mode) {
            $latestRank = $user->rankHistory
                ->first(fn ($rank) => $rank->mode === $mode);

            $ranks[$mode] = [
                'global_rank' => $latestRank?->global_rank,
                'country_rank' => $latestRank?->country_rank,
                'pp' => $latestRank?->pp,
            ];
        }

        // Add mania variants (4k, 7k) if user plays mania
        if ($user->main_mode === 'mania' || isset($ranks['mania'])) {
            $maniaVariants = ['4k', '7k'];
            foreach ($maniaVariants as $variant) {
                $latestRank = $user->rankHistory
                    ->first(fn ($rank) => $rank->mode === $variant);

                $ranks[$variant] = [
                    'global_rank' => $latestRank?->global_rank,
                    'country_rank' => null, // Variants don't have country rank
                    'pp' => $latestRank?->pp,
                ];
            }
        }

        return $ranks;
    }

    /**
     * Get total tournament count for user.
     */
    protected function getTournamentCount(int $userId): int
    {
        return TournamentParticipationRecord::query()
            ->where('user_id', $userId)
            ->where('review_status', TournamentParticipationRecord::REVIEW_APPROVED)
            ->distinct('tournament_id')
            ->count('tournament_id');
    }

    /**
     * Get total match count for user.
     */
    protected function getMatchCount(int $userId): int
    {
        return ParticipationRecordMatch::query()
            ->whereHas('participationRecord', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('review_status', TournamentParticipationRecord::REVIEW_APPROVED))
            ->count();
    }

    /**
     * Get user match statistics as JSON.
     *
     * GET /users/{userId}/stats
     */
    public function stats(string|int $userId): JsonResponse
    {
        $userId = (int) $userId;

        $user = User::findOrFail($userId);

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar_url' => $user->avatar_url,
                ],
                'stats' => $this->emptyMatchStats(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMatchStats(): array
    {
        return [
            'total_matches' => 0,
            'total_games' => 0,
            'games_won' => 0,
            'win_rate' => 0.0,
            'frequent_teammates' => [],
            'frequent_opponents' => [],
        ];
    }

    private function hasParticipationDataForYear(User $user, int $year): bool
    {
        return TournamentParticipationRecord::query()
            ->where('user_id', $user->id)
            ->where('review_status', TournamentParticipationRecord::REVIEW_APPROVED)
            ->whereHas('tournament', fn ($query) => $query
                ->whereBetween('tournament_end', [
                    now()->setDate($year, 1, 1)->startOfDay(),
                    now()->setDate($year, 12, 31)->endOfDay(),
                ]))
            ->exists();
    }
}
