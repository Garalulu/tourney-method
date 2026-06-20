<?php

namespace App\Http\Controllers;

use App\Helpers\StaffRoleHelper;
use App\Http\Resources\TournamentResource;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\User;
use App\Services\BwsCalculator;
use App\Services\TournamentResultDisplayService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TournamentController extends Controller
{
    public function __construct(
        private BwsCalculator $bwsCalculator,
        private TournamentResultDisplayService $resultDisplayService,
    ) {}

    /**
     * Display a listing of tournaments
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse|View|RedirectResponse
    {
        // Redirect to user's default mode if authenticated and no mode specified AND no search query
        // Don't redirect when searching - search should show all gamemodes
        if ($request->wantsJson() === false && ! $request->has('mode') && ! $request->has('q') && auth()->check()) {
            $user = auth()->user();
            if ($user->main_mode) {
                return redirect()->route('tournaments.index', ['mode' => $user->main_mode]);
            }
        }

        // Require authentication for eligible_only filter
        if ($request->boolean('eligible_only') && ! auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Create cache key from filters
        $cacheKey = $this->getTournamentListCacheKey($request);

        // Cache eligibility-specific queries separately (per user)
        $useCache = ! $request->boolean('eligible_only');

        $loadTournaments = function () use ($request) {
            return $this->getTournamentsQuery($request)
                ->paginate(min((int) $request->input('per_page', 20), 50));
        };

        if ($useCache) {
            $cache = Cache::store();
            $tournaments = $cache->supportsTags()
                ? $cache->tags(['tournaments'])->remember($cacheKey, now()->addMinutes(5), $loadTournaments)
                : $cache->remember($cacheKey, now()->addMinutes(5), $loadTournaments);
        } else {
            $tournaments = $loadTournaments();
        }

        // Filter by eligibility in PHP for accurate mania variant checking
        if ($request->boolean('eligible_only') && auth()->check()) {
            $user = auth()->user();
            $mode = $request->input('mode', $user->main_mode);

            $tournaments->setCollection(
                $tournaments->getCollection()->filter(function ($tournament) use ($user, $mode) {
                    return $this->bwsCalculator->isEligible($user, $tournament, $mode);
                })->values()
            );
        }

        // Return JSON for API requests (Accept header or per_page parameter)
        if ($request->wantsJson() || $request->has('per_page')) {
            return TournamentResource::collection($tournaments);
        }

        // Return view for web requests
        return view('tournaments.index');
    }

    /**
     * Build cache key from request parameters
     */
    private function getTournamentListCacheKey(Request $request): string
    {
        $params = $request->only([
            'status',
            'mode',
            'is_badge',
            'search',
            'year',
            'reg_open',
            'ongoing',
            'per_page',
            'page',
        ]);
        $params['effective_mode'] = $this->effectiveMode($request);
        $params['version'] = Cache::get('tournaments.list.version', 1);
        $hash = md5(json_encode($params));

        return 'tournaments.list.'.$hash;
    }

    /**
     * Get tournaments query with all filters applied
     *
     * @return Builder<Tournament>
     */
    private function getTournamentsQuery(Request $request): Builder
    {
        $query = Tournament::query()
            ->where('status', 'approved');

        // Determine status (always define, even if mode is selected)
        $status = $request->input('status', 'active');

        if ($status === 'active') {
            $query->where(function ($q) {
                $q->where('tournament_end', '>', now())
                    ->orWhereNull('tournament_end');
            });
        } elseif ($status === 'ended') {
            $query->where('tournament_end', '<=', now());
        }

        if ($request->boolean('ongoing')) {
            $query->where('registration_end', '<', now())
                ->where('tournament_start', '<=', now())
                ->where('tournament_end', '>', now());
        }

        // Implement new sorting priority:
        // 1. Registration open tournaments first
        // 2. Ongoing badge tournaments
        // 3. High rank tournaments (lower rank_min first)
        // 4. Then by date

        $query->orderByRaw('
            CASE
                WHEN registration_end IS NULL THEN 3
                WHEN registration_end >= now() THEN 1
                ELSE 2
            END
        ');

        $query->orderByRaw('
            CASE
                WHEN is_badge = true AND registration_end < now() THEN 1
                WHEN is_badge = true THEN 2
                ELSE 3
            END
        ');

        $query->orderBy('rank_range_min', 'asc');
        $query->orderBy('tournament_start', 'asc');
        $query->orderBy('created_at', 'desc');

        // Filter by registration status
        if ($request->boolean('reg_open')) {
            // Show only registration open tournaments
            $query->where(function ($q) {
                $q->whereNull('registration_end')
                    ->orWhere('registration_end', '>=', now());
            });
        }
        // When reg_open is false or not provided, don't apply any registration filter

        // Filter by year (for ended tournaments)
        if ($request->filled('year')) {
            $year = (int) $request->year;
            // For ended tournaments, filter by year of tournament_end
            // For active/upcoming tournaments, filter by year of tournament_start
            if ($status === 'ended') {
                $query->whereYear('tournament_end', $year);
            } else {
                $query->whereYear('tournament_start', $year);
            }
        }

        // Filter by mode (new JSON structure: [{"mode":"osu","key_count":null}])
        $mode = $this->effectiveMode($request);
        if ($mode !== null) {
            $query->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements(modes::jsonb) AS mode_elem
                WHERE mode_elem->>'mode' = ?
            )", [$mode]);
        }

        // Filter by is_badge
        if ($request->has('is_badge')) {
            $query->where('is_badge', $request->boolean('is_badge'));
        }

        // Search by title or description (simple search for API filtering)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        /** @var Builder<Tournament> */
        return $query;
    }

    private function effectiveMode(Request $request): ?string
    {
        if ($request->filled('mode')) {
            return (string) $request->input('mode');
        }

        if ($request->wantsJson() && ! $request->boolean('eligible_only') && auth()->check()) {
            return auth()->user()->main_mode;
        }

        return null;
    }

    /**
     * Display the specified tournament
     */
    public function show(Request $request, Tournament $tournament): TournamentResource|View
    {
        // Only show approved tournaments to non-admins
        if ($tournament->status !== 'approved' && ! auth()->user()?->isAdminOrMaster()) {
            abort(404);
        }

        // Set flag to indicate this is detail view
        $tournament->isDetail = true;

        // Eager load staff with role sorting for web requests
        if (! $request->wantsJson()) {
            $tournament = Tournament::with(['staff' => function ($query) {
                $query->orderByRaw(StaffRoleHelper::roleOrderSql());
            }])->findOrFail($tournament->id);
        }

        // Return JSON for API requests
        if ($request->wantsJson()) {
            return new TournamentResource($tournament);
        }

        // Calculate eligibility for authenticated users
        $userEligible = null;
        $userRankValue = null;
        $userBwsRank = null;
        $userRawRank = null;
        $ineligibleReason = null;

        if (auth()->check()) {
            $user = auth()->user();
            $tournamentMode = $this->getTournamentMode($tournament);

            if ($tournament->isRegionRestricted() && ! $tournament->isUserEligibleByCountry($user->country_code)) {
                $userEligible = false;
                $ineligibleReason = 'country';
            } else {
                $eligibilityDetails = $this->bwsCalculator->getDetails($user, $tournament, $tournamentMode);

                $userEligible = $eligibilityDetails['is_eligible'];
                $userRawRank = $eligibilityDetails['actual_rank'];
                $userBwsRank = $eligibilityDetails['bws_rank'];
                $effectiveRank = $eligibilityDetails['effective_rank'];
                $userRankValue = $effectiveRank === null ? null : (int) ceil($effectiveRank);

                if (! $userEligible && (! is_null($tournament->rank_range_min) || ! is_null($tournament->rank_range_max))) {
                    $ineligibleReason = 'rank';
                }
            }
        }

        // Return view for web requests
        $pendingCorrection = $tournament->corrections()
            ->pending()
            ->with('submitter')
            ->first();
        $approvedCorrectionCount = $tournament->corrections()
            ->whereIn('status', [
                TournamentCorrection::STATUS_APPROVED,
                TournamentCorrection::STATUS_PARTIALLY_APPROVED,
            ])
            ->count();
        $correctionContributorIds = TournamentCorrection::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', [
                TournamentCorrection::STATUS_APPROVED,
                TournamentCorrection::STATUS_PARTIALLY_APPROVED,
            ])
            ->pluck('submitted_by')
            ->unique()
            ->values()
            ->all();
        $adminContributorIds = AdminAuditLog::query()
            ->where('entity_type', Tournament::class)
            ->where('entity_id', $tournament->id)
            ->whereIn('action', $this->adminContributionActions())
            ->pluck('admin_id')
            ->unique()
            ->values()
            ->all();
        $correctionContributors = User::query()
            ->whereIn('id', array_values(array_unique([...$correctionContributorIds, ...$adminContributorIds])))
            ->orderBy('username')
            ->get();
        $podiumSections = $this->resultDisplayService->podiumSections($tournament);
        $currentUserResultTeam = $this->resultDisplayService->currentUserTeam($tournament, auth()->user());

        return view('tournaments.show', [
            'tournament' => $tournament,
            'userEligible' => $userEligible,
            'ineligibleReason' => $ineligibleReason,
            'userRankValue' => $userRankValue,
            'userBwsRank' => $userBwsRank,
            'userRawRank' => $userRawRank,
            'pendingCorrection' => $pendingCorrection,
            'approvedCorrectionCount' => $approvedCorrectionCount,
            'correctionContributors' => $correctionContributors,
            'podiumSections' => $podiumSections,
            'currentUserResultTeam' => $currentUserResultTeam,
        ]);
    }

    public function expandedResults(Tournament $tournament): \Illuminate\Contracts\View\View
    {
        if ($tournament->status !== 'approved' && ! auth()->user()?->isAdminOrMaster()) {
            abort(404);
        }

        return view('tournaments.partials.expanded-results', [
            'sections' => $this->resultDisplayService->expandedSections($tournament),
        ]);
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
     * Get tournament's primary mode from modes JSON
     */
    private function getTournamentMode(Tournament $tournament): string
    {
        $modes = $tournament->modes ?? [];

        if (empty($modes)) {
            return 'osu'; // Default fallback
        }

        // Return the first mode
        return $modes[0]['mode'] ?? 'osu';
    }
}
