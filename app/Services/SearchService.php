<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\SearchCommandCriteria;
use App\DTOs\SearchQuery;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SearchService
 *
 * Provides unified search functionality for tournaments and users
 * with priority-based sorting for tournament results.
 *
 * Tournament search priority (highest to lowest):
 * - Tournament name: 100
 * - Host name: 75
 * - Staff name: 50
 * - Podium name: 25
 *
 * Now supports filtering by tab (active/ended/all), mode, badge, registration status, and eligibility.
 */
final readonly class SearchService
{
    /**
     * Minimum search query length
     */
    private const int MIN_QUERY_LENGTH = 3;

    /**
     * Search tournaments with priority sorting
     *
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum number of results to return
     * @return Collection<int, Tournament> Collection of tournaments with priority attribute
     */
    public function searchTournaments(string $query, int $limit = 5): Collection
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        // Create SearchQuery with tab='all' for global search (search modal)
        // This ensures search results include both active and ended tournaments
        $filters = new SearchQuery(
            mode: '',
            badgeOnly: false,
            regOpenOnly: false,
            eligibleOnly: false,
            tab: 'all'
        );

        // Search by all sources with different priorities
        $results = collect();

        $results = $results->concat($this->searchByTournamentName($query, $limit, $filters));
        $results = $results->concat($this->searchByHostName($query, $limit, $filters));
        $results = $results->concat($this->searchByStaffName($query, $limit, $filters));
        $results = $results->concat($this->searchByPodiumName($query, $limit, $filters));

        return $this->mergeAndSortTournaments($results, $limit);
    }

    /**
     * Search tournaments by title only.
     *
     * Used by the global search dialog's primary tournament section so it only
     * shows direct tournament-name matches.
     *
     * @return Collection<int, Tournament>
     */
    public function searchTournamentNames(string $query, int $limit = 5): Collection
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $filters = new SearchQuery(tab: 'all');

        return $this->searchByTournamentName($query, $limit, $filters);
    }

    /**
     * Search the global dialog's primary section by tournament title or host.
     *
     * @return Collection<int, Tournament>
     */
    public function searchPrimaryTournaments(string $query, int $limit = 5): Collection
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $filters = new SearchQuery(tab: 'all');
        $results = $this->searchByTournamentName($query, $limit, $filters)
            ->concat($this->searchByHostName($query, $limit, $filters));

        return $this->mergeAndSortTournaments($results, $limit);
    }

    /**
     * Search tournaments by approved staff current or previous username.
     *
     * @return Collection<int, Tournament>
     */
    public function searchStaffTournaments(string $query, int $limit = 5, ?SearchQuery $filters = null): Collection
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $filters ??= new SearchQuery(tab: 'all');

        return $this->searchByStaffName($query, $limit, $filters);
    }

    /**
     * Search tournaments by podium current, previous, or imported username.
     *
     * @return Collection<int, Tournament>
     */
    public function searchPodiumTournaments(string $query, int $limit = 5, ?SearchQuery $filters = null): Collection
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $filters ??= new SearchQuery(tab: 'all');

        return $this->searchByPodiumName($query, $limit, $filters);
    }

    /**
     * Search tournaments for page with pagination and filters
     *
     * Used by TournamentList Livewire component.
     *
     * @param  string  $searchQuery  Search query
     * @param  SearchQuery  $filters  Filter criteria
     * @param  int  $perPage  Number of results per page
     * @return LengthAwarePaginator<int, Tournament> Paginated tournament results
     */
    public function searchTournamentsForPage(string $searchQuery, SearchQuery $filters, int $perPage = 50): LengthAwarePaginator
    {
        $parsedQuery = app(SearchCommandParser::class)->parse($searchQuery);
        $filters = $this->withCommandCriteria($filters, $parsedQuery);
        $searchQuery = trim($parsedQuery->keyword);

        if (strlen($searchQuery) < self::MIN_QUERY_LENGTH && ! $parsedQuery->hasCommands()) {
            // Return empty paginator if query is too short
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                1
            );
        }

        if (strlen($searchQuery) < self::MIN_QUERY_LENGTH) {
            $query = Tournament::query()
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->with(['staff']);

            $query = $this->applyFiltersToQuery($query, $filters);

            return $query->orderByRaw('tournament_end DESC NULLS LAST')
                ->orderBy('title', 'asc')
                ->paginate($perPage);
        }

        $rankedTournamentIds = $this->searchRankedTournamentIdsWithFilters($searchQuery, 1000, $filters);
        $searchedTournamentIds = $rankedTournamentIds
            ->pluck('id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($searchedTournamentIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                1
            );
        }

        // Build base query with searched IDs
        $query = Tournament::query()
            ->whereIn('id', $searchedTournamentIds)
            ->with(['staff']);

        // Apply additional filters to the searched results
        $query = $this->applyFiltersToQuery($query, $filters);

        $priorityCase = $rankedTournamentIds
            ->map(fn (array $match) => 'WHEN '.$match['id'].' THEN '.$match['rank'])
            ->implode(' ');

        $prioritySql = $priorityCase !== ''
            ? "CASE id {$priorityCase} ELSE 4 END"
            : '4';

        // Return paginated results with source priority ordering:
        // podium, staff, then title/host fallback.
        return $query->orderByRaw($prioritySql.' ASC')
            ->orderByRaw('tournament_end DESC NULLS LAST')
            ->orderBy('title', 'asc')
            ->paginate($perPage);
    }

    private function withCommandCriteria(SearchQuery $filters, SearchCommandCriteria $commands): SearchQuery
    {
        return new SearchQuery(
            mode: $filters->mode,
            badgeOnly: $filters->badgeOnly,
            regOpenOnly: $filters->regOpenOnly,
            eligibleOnly: $filters->eligibleOnly,
            tab: $filters->tab,
            selectedYear: $filters->selectedYear,
            commands: $commands,
        );
    }

    /**
     * Search tournament IDs with source rank for page sorting.
     *
     * Lower rank sorts first: podium (1), staff (2), title/host (3).
     *
     * @return Collection<int, array{id: int, rank: int}>
     */
    private function searchRankedTournamentIdsWithFilters(string $query, int $limit, SearchQuery $filters): Collection
    {
        $matches = collect();

        $this->searchByPodiumName($query, $limit, $filters)
            ->each(fn (Tournament $tournament) => $matches->push(['id' => $tournament->id, 'rank' => 1]));

        $this->searchByStaffName($query, $limit, $filters)
            ->each(fn (Tournament $tournament) => $matches->push(['id' => $tournament->id, 'rank' => 2]));

        $this->searchByTournamentName($query, $limit, $filters)
            ->merge($this->searchByHostName($query, $limit, $filters))
            ->each(fn (Tournament $tournament) => $matches->push(['id' => $tournament->id, 'rank' => 3]));

        return $matches
            ->groupBy('id')
            ->map(fn (Collection $sourceMatches, int|string $id) => [
                'id' => (int) $id,
                'rank' => (int) $sourceMatches->min('rank'),
            ])
            ->sortBy([
                ['rank', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * Apply filters to a query builder instance.
     *
     * Used for applying filters to the final paginated query.
     *
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    private function applyFiltersToQuery($query, SearchQuery $filters)
    {
        // Apply tab filter
        if ($filters->isActiveTabOnly()) {
            $query->where(function ($q) {
                $q->whereNull('tournament_end')
                    ->orWhere('tournament_end', '>=', now());
            });
        } elseif ($filters->isEndedTabOnly()) {
            $query->whereNotNull('tournament_end')
                ->where('tournament_end', '<', now());
        }
        // If tab='all', don't apply any tab filter

        // Apply registration open filter
        if ($filters->regOpenOnly) {
            $query->where(function ($q) {
                $q->whereNull('registration_end')
                    ->orWhere('registration_end', '>=', now());
            })->where(function ($q) {
                $q->whereNull('registration_start')
                    ->orWhere('registration_start', '<=', now());
            });
        }

        // Apply mode filter
        if ($filters->mode) {
            $query->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements(modes::jsonb) AS mode_elem
                WHERE mode_elem->>'mode' = ?
            )", [$filters->mode]);
        }

        // Apply badge filter
        if ($filters->badgeOnly) {
            $query->where('is_badge', true);
        }

        // Apply year filter
        if ($filters->selectedYear) {
            $query->whereYear('tournament_end', $filters->selectedYear);
        }

        $this->applyCommandFiltersToQuery($query, $filters);

        // Note: eligibleOnly filter is not applied here
        // It requires per-tournament BWS calculations and is handled in TournamentList

        return $query;
    }

    /**
     * Apply parsed search commands as additional tournament constraints.
     *
     * @param  Builder<Tournament>  $query
     */
    private function applyCommandFiltersToQuery(Builder $query, SearchQuery $filters): void
    {
        $commands = $filters->commandCriteria();

        if ($commands->rank !== null) {
            $tolerance = max(100, (int) round($commands->rank * 0.10));

            $query->whereNotNull('rank_range_min')
                ->whereBetween('rank_range_min', [
                    $commands->rank - $tolerance,
                    $commands->rank + $tolerance,
                ]);
        }

        if ($commands->starRating !== null) {
            $query->whereNotNull('star_rating_first')
                ->whereNotNull('star_rating_last')
                ->where('star_rating_first', '<=', $commands->starRating)
                ->where('star_rating_last', '>=', $commands->starRating);
        }

        if ($commands->countries !== []) {
            $placeholders = implode(',', array_fill(0, count($commands->countries), '?'));

            $query->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements_text(restricted_countries::jsonb) AS country_code
                WHERE country_code IN ({$placeholders})
            )", $commands->countries);
        }

        if ($commands->formatTag !== null) {
            $query->where(function (Builder $formatQuery) use ($commands) {
                $formatQuery->whereJsonContains('format_tags', $commands->formatTag);

                if ($commands->formatTag !== 'battle_royale') {
                    $formatQuery->orWhere('team_formation_style', $commands->formatTag);
                }
            });
        }

        if ($commands->staffUsername !== null) {
            $query->whereExists(function (QueryBuilder $staffQuery) use ($commands) {
                $staffQuery->selectRaw('1')
                    ->from('tournament_staff')
                    ->join('users', 'tournament_staff.user_id', '=', 'users.id')
                    ->whereColumn('tournament_staff.tournament_id', 'tournaments.id')
                    ->where('tournament_staff.status', 'approved')
                    ->when($commands->staffRole !== null, function (QueryBuilder $roleQuery) use ($commands) {
                        $roleQuery->where('tournament_staff.role', $commands->staffRole);
                    })
                    ->whereNull('users.deleted_at')
                    ->where(function (QueryBuilder $usernameQuery) use ($commands) {
                        $this->applyUsernameSearch($usernameQuery, 'users.username', 'users.previous_usernames', $commands->staffUsername);
                    });
            });
        }

        if ($commands->podiumUsername !== null) {
            $query->whereExists(function (QueryBuilder $podiumQuery) use ($commands) {
                $podiumQuery->selectRaw('1')
                    ->from('tournament_winners')
                    ->leftJoin('users', 'tournament_winners.user_id', '=', 'users.id')
                    ->whereColumn('tournament_winners.tournament_id', 'tournaments.id')
                    ->when($commands->podiumPlacement !== null, function (QueryBuilder $placementQuery) use ($commands) {
                        $placementQuery->where('tournament_winners.placement', $commands->podiumPlacement);
                    })
                    ->where(function (QueryBuilder $usernameQuery) use ($commands) {
                        $usernameQuery->where('tournament_winners.username', 'ilike', "%{$commands->podiumUsername}%")
                            ->orWhere(function (QueryBuilder $linkedUserQuery) use ($commands) {
                                $linkedUserQuery
                                    ->whereNotNull('tournament_winners.user_id')
                                    ->whereNull('users.deleted_at')
                                    ->where(function (QueryBuilder $linkedUsernameQuery) use ($commands) {
                                        $this->applyUsernameSearch($linkedUsernameQuery, 'users.username', 'users.previous_usernames', $commands->podiumUsername);
                                    });
                            });
                    });
            });
        }
    }

    /**
     * Search users by username
     *
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum number of results to return
     * @return Collection<int, User>
     */
    public function searchUsers(string $query, int $limit = 5): Collection
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $users */
        $users = User::query()
            ->where(function ($q) use ($query) {
                $this->applyUsernameSearch($q, 'username', 'previous_usernames', $query);
            })
            ->when(auth()->check(), fn (Builder $builder) => $builder->whereKeyNot(auth()->id()))
            ->whereNull('deleted_at')
            ->orderBy('username', 'asc')
            ->limit($limit)
            ->get();

        return $users;
    }

    /**
     * Search tournaments by name (priority: 100)
     *
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum results from this source
     * @param  SearchQuery  $filters  Filter criteria
     * @return Collection<int, Tournament>
     */
    private function searchByTournamentName(string $query, int $limit, SearchQuery $filters): Collection
    {
        /** @var Builder<Tournament> $tournaments */
        $tournaments = Tournament::query()
            ->where('title', 'ilike', "%{$query}%")
            ->where('status', 'approved')
            ->whereNull('deleted_at');

        // Apply tab filter
        $tournaments = $this->applyTabFilterToSearchQuery($tournaments, $filters);

        // Apply other filters
        $tournaments = $this->applyFiltersToQuery($tournaments, $filters);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tournament> $results */
        $results = $tournaments
            ->orderByRaw('tournament_end DESC NULLS LAST')
            ->orderBy('title', 'asc')
            ->limit($limit)
            ->get();

        return $results->each(fn ($tournament) => $tournament->setAttribute('priority', 100));
    }

    /**
     * Search tournaments by host name (priority: 75)
     *
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum results from this source
     * @param  SearchQuery  $filters  Filter criteria
     * @return Collection<int, Tournament>
     */
    private function searchByHostName(string $query, int $limit, SearchQuery $filters): Collection
    {
        /** @var Builder<Tournament> $tournaments */
        $tournaments = Tournament::query()
            ->where('host_username', 'ilike', "%{$query}%")
            ->where('status', 'approved')
            ->whereNull('deleted_at');

        // Apply tab filter
        $tournaments = $this->applyTabFilterToSearchQuery($tournaments, $filters);

        // Apply other filters
        $tournaments = $this->applyFiltersToQuery($tournaments, $filters);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tournament> $results */
        $results = $tournaments->limit($limit)->get();

        return $results->each(fn ($tournament) => $tournament->setAttribute('priority', 75));
    }

    /**
     * Search tournaments by staff name (priority: 50)
     *
     * Only searches approved staff.
     *
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum results from this source
     * @param  SearchQuery  $filters  Filter criteria
     * @return Collection<int, Tournament>
     */
    private function searchByStaffName(string $query, int $limit, SearchQuery $filters): Collection
    {
        $staffQuery = $this->baseStaffSearchQuery($query);

        // Apply tab filter to the subquery
        if ($filters->isActiveTabOnly()) {
            $staffQuery->where(function ($q) {
                $q->whereNull('tournaments.tournament_end')
                    ->orWhere('tournaments.tournament_end', '>=', now());
            });
        } elseif ($filters->isEndedTabOnly()) {
            $staffQuery->whereNotNull('tournaments.tournament_end')
                ->where('tournaments.tournament_end', '<', now());
        }

        $staffMatches = $staffQuery
            ->orderByRaw('tournaments.tournament_end DESC NULLS LAST')
            ->limit($limit * 5)
            ->get()
            ->unique('tournament_id')
            ->take($limit)
            ->values();

        if ($staffMatches->isEmpty()) {
            return collect();
        }

        $roleByTournamentId = $staffMatches->pluck('role', 'tournament_id');

        /** @var Builder<Tournament> $tournaments */
        $tournaments = Tournament::query()
            ->whereIn('id', $staffMatches->pluck('tournament_id'))
            ->where('status', 'approved')
            ->whereNull('deleted_at');

        // Apply other filters (mode, badge, regOpen)
        $tournaments = $this->applyFiltersToQuery($tournaments, $filters);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tournament> $results */
        $results = $tournaments
            ->orderByRaw('tournament_end DESC NULLS LAST')
            ->get();

        return $results->each(function (Tournament $tournament) use ($roleByTournamentId) {
            $tournament->setAttribute('priority', 50);
            $tournament->setAttribute('role', ucfirst((string) $roleByTournamentId->get($tournament->id)));
        });
    }

    /**
     * Count unique approved staff tournament matches.
     */
    public function countStaffTournaments(string $query): int
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return 0;
        }

        return (int) $this->baseStaffSearchQuery($query)
            ->distinct()
            ->count('tournament_staff.tournament_id');
    }

    private function baseStaffSearchQuery(string $query): QueryBuilder
    {
        return DB::table('tournament_staff')
            ->join('users', 'tournament_staff.user_id', '=', 'users.id')
            ->join('tournaments', 'tournament_staff.tournament_id', '=', 'tournaments.id')
            ->where(function ($q) use ($query) {
                $this->applyUsernameSearch($q, 'users.username', 'users.previous_usernames', $query);
            })
            ->where('tournament_staff.status', 'approved')
            ->whereNull('users.deleted_at')
            ->where('tournaments.status', 'approved')
            ->whereNull('tournaments.deleted_at')
            ->select('tournament_staff.tournament_id', 'tournament_staff.role');
    }

    /**
     * Search tournaments by podium/winner name (priority: 25)
     *
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum results from this source
     * @param  SearchQuery  $filters  Filter criteria
     * @return Collection<int, Tournament>
     */
    private function searchByPodiumName(string $query, int $limit, SearchQuery $filters): Collection
    {
        $podiumQuery = $this->basePodiumSearchQuery($query);

        // Apply tab filter to the subquery
        if ($filters->isActiveTabOnly()) {
            $podiumQuery->where(function ($q) {
                $q->whereNull('tournaments.tournament_end')
                    ->orWhere('tournaments.tournament_end', '>=', now());
            });
        } elseif ($filters->isEndedTabOnly()) {
            $podiumQuery->whereNotNull('tournaments.tournament_end')
                ->where('tournaments.tournament_end', '<', now());
        }

        $podiumMatches = $podiumQuery
            ->orderByRaw('tournaments.tournament_end DESC NULLS LAST')
            ->orderBy('tournament_winners.placement', 'asc')
            ->limit($limit * 5)
            ->get()
            ->unique('tournament_id')
            ->take($limit)
            ->values();

        if ($podiumMatches->isEmpty()) {
            return collect();
        }

        $placementByTournamentId = $podiumMatches->pluck('placement', 'tournament_id');

        /** @var Builder<Tournament> $tournaments */
        $tournaments = Tournament::query()
            ->whereIn('id', $podiumMatches->pluck('tournament_id'))
            ->where('status', 'approved')
            ->whereNull('deleted_at');

        // Apply other filters (mode, badge, regOpen)
        $tournaments = $this->applyFiltersToQuery($tournaments, $filters);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tournament> $results */
        $results = $tournaments
            ->orderByRaw('tournament_end DESC NULLS LAST')
            ->get();

        return $results->each(function (Tournament $tournament) use ($placementByTournamentId) {
            $placement = $placementByTournamentId->get($tournament->id);

            $tournament->setAttribute('priority', 25);
            $tournament->setAttribute('role', ($placement === 1 ? '🏆 ' : '').'#'.$placement);
        });
    }

    /**
     * Count unique approved podium tournament matches.
     */
    public function countPodiumTournaments(string $query): int
    {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return 0;
        }

        return (int) $this->basePodiumSearchQuery($query)
            ->distinct()
            ->count('tournament_winners.tournament_id');
    }

    private function basePodiumSearchQuery(string $query): QueryBuilder
    {
        return DB::table('tournament_winners')
            ->leftJoin('users', 'tournament_winners.user_id', '=', 'users.id')
            ->join('tournaments', 'tournament_winners.tournament_id', '=', 'tournaments.id')
            ->where(function ($q) use ($query) {
                $q->where('tournament_winners.username', 'ilike', "%{$query}%")
                    ->orWhere(function ($linkedUserQuery) use ($query) {
                        $linkedUserQuery
                            ->whereNotNull('tournament_winners.user_id')
                            ->whereNull('users.deleted_at')
                            ->where(function ($usernameQuery) use ($query) {
                                $this->applyUsernameSearch($usernameQuery, 'users.username', 'users.previous_usernames', $query);
                            });
                    });
            })
            ->where('tournaments.status', 'approved')
            ->whereNull('tournaments.deleted_at')
            ->select('tournament_winners.tournament_id', 'tournament_winners.placement');
    }

    /**
     * Apply current and previous username matching to a query.
     *
     * @param  Builder<User>|QueryBuilder  $query
     */
    private function applyUsernameSearch(Builder|QueryBuilder $query, string $usernameColumn, string $previousUsernamesColumn, string $search): void
    {
        $query->where($usernameColumn, 'ilike', "%{$search}%")
            ->orWhere($previousUsernamesColumn, 'ilike', "%\"{$search}%");
    }

    /**
     * Apply tab filter to a search query.
     *
     * Used in individual search methods to filter at the source.
     *
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    private function applyTabFilterToSearchQuery($query, SearchQuery $filters)
    {
        if ($filters->isActiveTabOnly()) {
            $query->where(function ($q) {
                $q->whereNull('tournament_end')
                    ->orWhere('tournament_end', '>=', now());
            });
        } elseif ($filters->isEndedTabOnly()) {
            $query->whereNotNull('tournament_end')
                ->where('tournament_end', '<', now());
        }
        // If tab='all', don't apply any filter

        return $query;
    }

    /**
     * Merge and sort tournament results by priority
     *
     * Removes duplicates and sorts by priority (highest first).
     *
     * @param  Collection<int, Tournament>  $results  Collection of tournaments with priority attribute
     * @param  int  $limit  Maximum number of results to return
     * @return Collection<int, Tournament> Sorted and deduplicated tournaments
     */
    private function mergeAndSortTournaments(Collection $results, int $limit): Collection
    {
        return $results
            ->sortByDesc('priority') // Sort by priority (highest first)
            ->unique('id') // Remove duplicates (keep first/highest priority)
            ->take($limit) // Limit results
            ->values() // Re-key collection
            ->each(fn ($tournament) => $tournament->setAttribute('year',
                $tournament->tournament_end ? $tournament->tournament_end->year : null
            ));
    }
}
