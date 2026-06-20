<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * SearchController
 *
 * Handles search API endpoints for the global search modal.
 * All endpoints require authentication.
 */
final readonly class SearchController
{
    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * Combined search endpoint (tournaments + users)
     *
     * @route GET /api/search?q={query}
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');

        $tournaments = $this->searchService->searchPrimaryTournaments($query, 5);
        $staffTournaments = $this->searchService->searchStaffTournaments($query, 5);
        $podiumTournaments = $this->searchService->searchPodiumTournaments($query, 5);
        $users = $this->searchService->searchUsers($query, 5);

        $totalTournaments = $this->searchService->searchTournaments($query, 1000)->count();
        $totalStaffTournaments = $this->searchService->countStaffTournaments($query);
        $totalPodiumTournaments = $this->searchService->countPodiumTournaments($query);
        $totalUsers = $this->searchService->searchUsers($query, 1000)->count();

        $usersData = $users->map(fn ($user) => [
            'id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'country_code' => $user->country_code,
            'main_mode' => $user->main_mode,
        ]);

        return response()->json([
            'tournaments' => $this->formatTournamentResults($tournaments)->toArray(),
            'staff_tournaments' => $this->formatTournamentResults($staffTournaments)->toArray(),
            'podium_tournaments' => $this->formatTournamentResults($podiumTournaments)->toArray(),
            'users' => $usersData->toArray(),
            'total_tournaments' => $totalTournaments,
            'total_staff_tournaments' => $totalStaffTournaments,
            'total_podium_tournaments' => $totalPodiumTournaments,
            'total_users' => $totalUsers,
        ]);
    }

    /**
     * Tournaments-only search endpoint
     *
     * @route GET /api/search/tournaments?q={query}
     */
    public function tournaments(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');

        $tournaments = $this->searchService->searchTournaments($query, 5);
        $total = $this->searchService->searchTournaments($query, 1000)->count();

        return response()->json([
            'tournaments' => $this->formatTournamentResults($tournaments)->toArray(),
            'total' => $total,
        ]);
    }

    /**
     * Users-only search endpoint
     *
     * @route GET /api/search/users?q={query}
     */
    public function users(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');

        $users = $this->searchService->searchUsers($query, 5);
        $total = $this->searchService->searchUsers($query, 1000)->count();

        $usersData = $users->map(fn ($user) => [
            'id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'country_code' => $user->country_code,
            'main_mode' => $user->main_mode,
        ]);

        return response()->json([
            'users' => $usersData->toArray(),
            'total' => $total,
        ]);
    }

    /**
     * @param  Collection<int, Tournament>  $tournaments
     * @return Collection<int, array<string, mixed>>
     */
    private function formatTournamentResults(Collection $tournaments): Collection
    {
        return $tournaments->map(function (Tournament $tournament): array {
            $data = [
                'id' => $tournament->id,
                'title' => $tournament->title,
                'modes' => collect($tournament->modes)->pluck('mode')->toArray(),
                'year' => $tournament->year ?? $tournament->tournament_end?->year,
                'is_badge' => $tournament->is_badge,
                'priority' => $tournament->priority ?? null,
            ];

            if ($tournament->role ?? null) {
                $data['role'] = $tournament->role;
            }

            return $data;
        });
    }
}
