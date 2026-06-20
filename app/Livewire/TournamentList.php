<?php

namespace App\Livewire;

use App\DTOs\SearchQuery;
use App\Models\Tournament;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TournamentList extends Component
{
    // Filter properties
    public string $mode = '';

    public bool $badgeOnly = false;

    public bool $eligibleOnly = false;

    public bool $regOpenOnly = false;

    public string $search = '';

    // Tab properties
    public string $activeTab = 'active';

    public ?int $selectedYear = null;

    // Load more properties
    public int $displayedCount = 0;

    public int $batchSize = 18;

    public int $totalCount = 0;

    public bool $hasMorePages = true;

    /**
     * @var array<string, array<string, mixed>|int>
     */
    protected array $queryString = [
        'mode' => ['except' => ''],
        'badgeOnly' => ['except' => false, 'as' => 'badge'],
        'eligibleOnly' => ['except' => false, 'as' => 'eligible'],
        'regOpenOnly' => ['except' => false, 'as' => 'reg_open'],
        'search' => ['except' => '', 'as' => 'q'],  // Map 'q' to 'search' for cleaner URLs
        'activeTab' => ['except' => 'active', 'as' => 'tab'],
        'selectedYear' => ['except' => null, 'as' => 'year'],
        'displayedCount' => ['except' => 18, 'as' => 'count'],
    ];

    public function mount(): void
    {
        if ($this->displayedCount < $this->batchSize) {
            $this->displayedCount = $this->batchSize; // Initial load
        }

        // If no tab specified, default to 'active' (not 'all')
        // This ensures first-time visitors see active tab selected
        if ($this->activeTab === '') {
            $this->activeTab = 'active';
        }

        // Set year default based on tab
        // - tab=all or tab=active: no year filter (All Years)
        // - tab=ended with no year: no year filter (All Years)
        if ($this->activeTab === 'all' || $this->activeTab === 'active') {
            $this->selectedYear = null; // All Years
        }
        // If year is already set in URL, keep it as-is
    }

    public function loadMore(): void
    {
        $this->displayedCount += $this->batchSize;
    }

    public function setBatchSize(int $size): void
    {
        $this->batchSize = $size;
        // Reset count if changing size (optional)
        if ($this->displayedCount <= $this->batchSize) {
            $this->displayedCount = $this->batchSize;
        }
    }

    public function resetDisplay(): void
    {
        $this->displayedCount = $this->batchSize;
    }

    public function updatedMode(): void
    {
        $this->resetDisplay();
    }

    public function updatedBadgeOnly(): void
    {
        $this->resetDisplay();
    }

    public function updatedEligibleOnly(): void
    {
        // If currently on tab=all, switch to active tab
        // Eligible filter only makes sense for active tournaments
        if ($this->activeTab === 'all') {
            $this->activeTab = 'active';
        }

        $this->resetDisplay();
    }

    public function updatedRegOpenOnly(): void
    {
        // If currently on tab=all, switch to active tab
        // Registration open filter only makes sense for active tournaments
        if ($this->activeTab === 'all') {
            $this->activeTab = 'active';
        }

        $this->resetDisplay();
    }

    public function updatedSearch(): void
    {
        $this->resetDisplay();
    }

    public function updatedActiveTab(): void
    {
        $this->resetDisplay();
        // Reset tab-specific filters when switching tabs
        $this->eligibleOnly = false;
        $this->regOpenOnly = false;

        // Reset year when switching to active or all tabs
        // Year filter only makes sense for ended tournaments
        if ($this->activeTab === 'active' || $this->activeTab === 'all') {
            $this->selectedYear = null;
        }
    }

    public function updatedSelectedYear(): void
    {
        // If currently on tab=all, switch to ended tab
        // Year filter only makes sense for ended tournaments
        if ($this->activeTab === 'all') {
            $this->activeTab = 'ended';
        }

        $this->resetDisplay();
    }

    public function render(): View
    {
        $isEndedTab = $this->activeTab === 'ended';
        $isSearching = ! empty($this->search);

        // If searching, use SearchService with filters
        if ($isSearching) {
            return $this->renderWithSearch();
        }

        // Non-search rendering (original logic)
        return $this->renderWithoutSearch();
    }

    /**
     * Render with search using SearchService.
     */
    protected function renderWithSearch(): View
    {
        // Create SearchQuery from current filter state
        $filters = SearchQuery::fromArray([
            'mode' => $this->mode,
            'badgeOnly' => $this->badgeOnly,
            'regOpenOnly' => $this->regOpenOnly,
            'eligibleOnly' => $this->eligibleOnly,
            'tab' => $this->activeTab,
            'selectedYear' => $this->selectedYear,
        ]);

        // Use SearchService to get paginated results with filters
        // Get a larger page size to accommodate displayedCount
        $searchService = app(SearchService::class);
        $perPage = max($this->displayedCount, 50);
        $paginatedResults = $searchService->searchTournamentsForPage($this->search, $filters, $perPage);

        // Apply eligibility filter (requires PHP-level BWS calculations)
        if ($this->eligibleOnly && Auth::check()) {
            $paginatedResults = $this->applyEligibilityFilter($paginatedResults);
        }

        // Get total count first
        $this->totalCount = $paginatedResults->total();
        $this->hasMorePages = $this->displayedCount < $this->totalCount;

        // Slice the collection to respect displayedCount
        $tournamentsCollection = $paginatedResults->getCollection()
            ->take($this->displayedCount);

        // Create LengthAwarePaginator manually with sliced results
        $tournaments = new LengthAwarePaginator(
            $tournamentsCollection,
            $this->totalCount,
            $this->batchSize,
            null,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        // Get available years for ended tournaments
        $availableYears = Tournament::query()
            ->where('status', 'approved')
            ->whereNotNull('tournament_end')
            ->where('tournament_end', '<', now())
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM tournament_end) as year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return view('livewire.tournament-list', [
            'tournaments' => $tournaments,
            'availableYears' => $availableYears,
        ]);
    }

    /**
     * Render without search (original filter logic).
     */
    protected function renderWithoutSearch(): View
    {
        $query = Tournament::query()
            ->where('status', 'approved')
            ->with(['staff']);

        $isEndedTab = $this->activeTab === 'ended';

        // Apply tab filter
        if ($this->activeTab === 'active') {
            // ACTIVE: Tournament hasn't ended yet (ongoing or upcoming)
            $query->where(function ($q) {
                $q->whereNull('tournament_end')
                    ->orWhere('tournament_end', '>=', now());
            });
        } elseif ($this->activeTab === 'ended') {
            // ENDED: Tournament has ended
            $query->whereNotNull('tournament_end')
                ->where('tournament_end', '<', now());

            // Apply year filter for ended tournaments
            if ($this->selectedYear) {
                $query->whereYear('tournament_end', $this->selectedYear);
            }
        }
        // If tab='all', don't apply any tab filter

        // Apply registration open filter
        if ($this->regOpenOnly) {
            $query->where(function ($q) {
                $q->whereNull('registration_end')
                    ->orWhere('registration_end', '>=', now());
            })->where(function ($q) {
                $q->whereNull('registration_start')
                    ->orWhere('registration_start', '<=', now());
            });
        }

        // Apply mode filter
        if ($this->mode) {
            $query->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements(modes::jsonb) AS mode_elem
                WHERE mode_elem->>'mode' = ?
            )", [$this->mode]);
        }

        // Apply badge filter
        if ($this->badgeOnly) {
            $query->where('is_badge', true);
        }

        // Apply eligibility filter
        if ($this->eligibleOnly && Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            // Only check tournaments that could be eligible
            $query->where(function ($q) {
                $q->where('registration_end', '>=', now())
                    ->orWhere('registration_start', '>=', now())
                    ->orWhereNull('tournament_end')
                    ->orWhere('tournament_end', '>=', now());
            });

            // Get all tournaments and filter in PHP
            $allTournaments = $query->get();
            $eligibleTournaments = $allTournaments->filter(fn ($tournament) => $tournament->isEligibleForUser($user));
            $eligibleIds = $eligibleTournaments->pluck('id');

            // Rebuild query with eligible IDs
            $query = Tournament::query()
                ->whereIn('id', $eligibleIds)
                ->where('status', 'approved')
                ->with(['staff']);
        }

        // Apply sorting
        $query = $this->applySorting($query, $isEndedTab);

        // Get total count
        $this->totalCount = (clone $query)->count();
        $this->hasMorePages = $this->displayedCount < $this->totalCount;

        // Fetch limited results
        $tournamentsCollection = $query
            ->take($this->displayedCount)
            ->get();

        // Create LengthAwarePaginator manually
        $tournaments = new LengthAwarePaginator(
            $tournamentsCollection,
            $this->totalCount,
            $this->batchSize,
            null,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        // Get available years
        $availableYears = Tournament::query()
            ->where('status', 'approved')
            ->whereNotNull('tournament_end')
            ->where('tournament_end', '<', now())
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM tournament_end) as year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return view('livewire.tournament-list', [
            'tournaments' => $tournaments,
            'availableYears' => $availableYears,
        ]);
    }

    /**
     * Apply eligibility filter to paginated results.
     *
     * @param  LengthAwarePaginator<int, Tournament>  $paginator
     * @return LengthAwarePaginator<int, Tournament>
     */
    protected function applyEligibilityFilter(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        /** @var User $user */
        $user = Auth::user();

        // Filter tournaments by eligibility
        $eligibleTournaments = $paginator->getCollection()
            ->filter(fn ($tournament) => $tournament->isEligibleForUser($user));

        // Return new paginator with filtered collection
        return new LengthAwarePaginator(
            $eligibleTournaments,
            $eligibleTournaments->count(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Apply sorting based on tab.
     *
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    protected function applySorting($query, bool $isEndedTab): Builder
    {
        if ($isEndedTab) {
            // Ended tab sorting
            /** @var Builder<Tournament> $sortedQuery */
            $sortedQuery = $query->orderByRaw('star_rating_last DESC NULLS LAST')
                ->orderByRaw('(star_rating_first + star_rating_last) / 2 DESC NULLS LAST')
                ->orderByRaw('
                    CASE
                        WHEN rank_range_min IS NULL THEN 0
                        ELSE rank_range_min
                    END ASC
                ')
                ->orderByRaw('
                    CASE
                        WHEN restricted_countries IS NULL THEN 1
                        WHEN jsonb_array_length(restricted_countries::jsonb) = 0 THEN 1
                        ELSE 2
                    END ASC
                ')
                ->orderByDesc('tournament_end');

            return $sortedQuery;
        }

        // Active tab sorting
        /** @var Builder<Tournament> $sortedQuery */
        $sortedQuery = $query->orderByRaw('
            CASE
                WHEN registration_end IS NULL THEN 3
                WHEN registration_end >= now() THEN 1
                ELSE 2
            END ASC
        ')
            ->orderByRaw('
            CASE
                WHEN registration_end >= now() THEN EXTRACT(EPOCH FROM registration_end)
                WHEN registration_end < now() THEN EXTRACT(EPOCH FROM COALESCE(tournament_end, now()))
                ELSE 0
            END ASC
        ')
            ->orderByRaw('
            CASE
                WHEN is_badge = true THEN 1
                ELSE 2
            END ASC
        ')
            ->orderByRaw('
            CASE
                WHEN rank_range_min IS NULL THEN 0
                ELSE rank_range_min
            END ASC
        ')
            ->orderByRaw('
            CASE
                WHEN restricted_countries IS NULL THEN 1
                WHEN jsonb_array_length(restricted_countries::jsonb) = 0 THEN 1
                ELSE 2
            END ASC
        ');

        return $sortedQuery;
    }
}
