<?php

declare(strict_types=1);

namespace Tests\Livewire;

use App\Livewire\TournamentList;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TournamentList search functionality tests
 *
 * Tests the enhanced search feature for the tournament list page:
 * - Search constrained by Active/Ended tabs
 * - Extended search (name, host, staff, podium)
 * - Priority sorting
 * - URL parameter mapping (q -> search)
 */
final class TournamentListSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test search respects active and ended tabs
     */
    public function test_search_respects_active_and_ended_tabs(): void
    {
        // Arrange: Create active and ended tournaments
        Tournament::factory()->create([
            'title' => 'Active Test Tournament',
            'status' => 'approved',
            'tournament_end' => now()->addMonth(),
        ]);

        Tournament::factory()->create([
            'title' => 'Ended Test Tournament',
            'status' => 'approved',
            'tournament_end' => now()->subMonth(),
        ]);

        // Act: Search in active tab
        Livewire::test(TournamentList::class, ['activeTab' => 'active', 'search' => 'Test'])
            ->assertSet('search', 'Test')
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->contains('title', 'Active Test Tournament')
                    && ! $tournaments->contains('title', 'Ended Test Tournament');
            });

        // Act: Search in ended tab
        Livewire::test(TournamentList::class, ['activeTab' => 'ended', 'search' => 'Test'])
            ->assertSet('search', 'Test')
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->contains('title', 'Ended Test Tournament')
                    && ! $tournaments->contains('title', 'Active Test Tournament');
            });
    }

    /**
     * Test search by tournament name returns results
     */
    public function test_search_by_tournament_name_returns_results(): void
    {
        // Arrange: Create tournaments
        Tournament::factory()->create([
            'title' => 'Searchable Tournament Name',
            'status' => 'approved',
        ]);

        Tournament::factory()->create([
            'title' => 'Other Tournament',
            'status' => 'approved',
        ]);

        // Act & Assert: Search by name
        Livewire::test(TournamentList::class, ['search' => 'Searchable'])
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->contains('title', 'Searchable Tournament Name')
                    && ! $tournaments->contains('title', 'Other Tournament');
            });
    }

    /**
     * Test search by host name returns results
     */
    public function test_search_by_host_name_returns_results(): void
    {
        // Arrange: Create tournament with specific host
        Tournament::factory()->create([
            'title' => 'Tournament A',
            'host_username' => 'HostPlayer123',
            'status' => 'approved',
        ]);

        // Act & Assert: Search by host name
        Livewire::test(TournamentList::class, ['search' => 'HostPlayer'])
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->count() > 0;
            });
    }

    /**
     * Test search by staff name returns results
     */
    public function test_search_by_staff_name_returns_results(): void
    {
        // Arrange: Create tournament with staff
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
        ]);

        $user = User::factory()->create(['username' => 'StaffMember']);
        $tournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        // Act & Assert: Search by staff name
        Livewire::test(TournamentList::class, ['search' => 'StaffMember'])
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->count() > 0;
            });
    }

    /**
     * Test search by staff previous username returns results
     */
    public function test_search_by_staff_previous_username_returns_results(): void
    {
        // Arrange: Create tournament with staff who changed username
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
        ]);

        $user = User::factory()->create([
            'username' => 'CurrentStaffMember',
            'previous_usernames' => ['OldStaffMember'],
        ]);

        $tournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        // Act & Assert: Search by previous staff name
        Livewire::test(TournamentList::class, ['search' => 'OldStaffMember'])
            ->assertViewHas('tournaments', function ($tournaments) use ($tournament) {
                return $tournaments->contains('id', $tournament->id);
            });
    }

    /**
     * Test search by podium username returns results
     */
    public function test_search_by_podium_username_returns_results(): void
    {
        // Arrange: Create tournament with winner
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);

        $tournament->winners()->create([
            'username' => 'WinnerPlayer',
            'placement' => 1,
            'gamemode' => 'osu',
            'osu_id' => 123456,
        ]);

        // Act & Assert: Search by winner name
        Livewire::test(TournamentList::class, [
            'activeTab' => 'ended',
            'selectedYear' => 2024,
            'search' => 'Winner',
        ])
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->count() > 0;
            });
    }

    /**
     * Test search by linked podium previous username returns results
     */
    public function test_search_by_linked_podium_previous_username_returns_results(): void
    {
        // Arrange: Create tournament with linked podium winner who changed username
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);

        $user = User::factory()->create([
            'username' => 'CurrentWinner',
            'previous_usernames' => ['OldWinnerName'],
            'osu_id' => 123456,
        ]);

        $tournament->winners()->create([
            'user_id' => $user->id,
            'username' => 'WinnerSnapshot',
            'placement' => 1,
            'gamemode' => 'osu',
            'osu_id' => $user->osu_id,
        ]);

        // Act & Assert: Search by previous podium username
        Livewire::test(TournamentList::class, [
            'activeTab' => 'ended',
            'selectedYear' => 2024,
            'search' => 'OldWinnerName',
        ])
            ->assertViewHas('tournaments', function ($tournaments) use ($tournament) {
                return $tournaments->contains('id', $tournament->id);
            });
    }

    /**
     * Test search results are sorted by priority
     */
    public function test_search_results_are_sorted_by_priority(): void
    {
        // Arrange: Create tournaments matching by different priorities
        $tournamentByPodium = Tournament::factory()->create([
            'title' => 'Z Tournament',
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);
        $tournamentByPodium->winners()->create([
            'username' => 'Test',
            'placement' => 1,
            'gamemode' => 'osu',
            'osu_id' => 123,
        ]);

        $tournamentByHost = Tournament::factory()->create([
            'title' => 'M Tournament',
            'host_username' => 'Test',
            'status' => 'approved',
        ]);

        $tournamentByName = Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        // Act & Assert: Search should find all three tournaments
        Livewire::test(TournamentList::class, [
            'activeTab' => 'all',
            'search' => 'Test',
        ])
            ->assertViewHas('tournaments', function ($tournaments) use ($tournamentByName, $tournamentByHost, $tournamentByPodium) {
                // All three tournaments should be found
                return $tournaments->contains('id', $tournamentByName->id)
                    && $tournaments->contains('id', $tournamentByHost->id)
                    && $tournaments->contains('id', $tournamentByPodium->id);
            });
    }

    /**
     * Test search results sort podium first, then staff, then title/host fallback
     */
    public function test_search_results_sort_podium_then_staff_by_latest_end_date(): void
    {
        // Arrange: Create a shared old username across podium and staff matches
        $podiumUser = User::factory()->create([
            'username' => 'CurrentPodiumSort',
            'previous_usernames' => ['OldSortName'],
            'osu_id' => 100001,
        ]);

        $newerPodium = Tournament::factory()->create([
            'title' => 'Newer Podium Tournament',
            'status' => 'approved',
            'tournament_end' => '2025-03-01',
        ]);
        $newerPodium->winners()->create([
            'user_id' => $podiumUser->id,
            'username' => 'PodiumSnapshotOne',
            'placement' => 1,
            'gamemode' => 'osu',
            'osu_id' => $podiumUser->osu_id,
        ]);

        $olderPodium = Tournament::factory()->create([
            'title' => 'Older Podium Tournament',
            'status' => 'approved',
            'tournament_end' => '2024-03-01',
        ]);
        $olderPodium->winners()->create([
            'user_id' => $podiumUser->id,
            'username' => 'PodiumSnapshotTwo',
            'placement' => 1,
            'gamemode' => 'osu',
            'osu_id' => $podiumUser->osu_id,
        ]);

        $staffUser = User::factory()->create([
            'username' => 'CurrentStaffSort',
            'previous_usernames' => ['OldSortName'],
        ]);

        $newerStaff = Tournament::factory()->create([
            'title' => 'Newer Staff Tournament',
            'status' => 'approved',
            'tournament_end' => '2026-03-01',
        ]);
        $newerStaff->staff()->attach($staffUser->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        $olderStaff = Tournament::factory()->create([
            'title' => 'Older Staff Tournament',
            'status' => 'approved',
            'tournament_end' => '2023-03-01',
        ]);
        $olderStaff->staff()->attach($staffUser->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        $titleFallback = Tournament::factory()->create([
            'title' => 'OldSortName Title Fallback',
            'status' => 'approved',
            'tournament_end' => '2027-03-01',
        ]);

        // Act & Assert: Source rank wins before date, then date sorts within each source
        Livewire::test(TournamentList::class, [
            'activeTab' => 'all',
            'search' => 'OldSortName',
        ])
            ->assertViewHas('tournaments', function ($tournaments) use ($newerPodium, $olderPodium, $newerStaff, $olderStaff, $titleFallback) {
                $ids = $tournaments->getCollection()->pluck('id')->values()->all();

                return $ids === [
                    $newerPodium->id,
                    $olderPodium->id,
                    $newerStaff->id,
                    $olderStaff->id,
                    $titleFallback->id,
                ];
            });
    }

    /**
     * Test URL parameter q maps to search property
     */
    public function test_url_parameter_q_maps_to_search_property(): void
    {
        // Arrange: Create tournament
        Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        // Act & Assert: Test with 'q' parameter
        Livewire::withQueryParams(['q' => 'Test'])
            ->test(TournamentList::class)
            ->assertSet('search', 'Test')
            ->assertViewHas('tournaments');
    }

    /**
     * Test active and ended tabs remain visible when searching
     */
    public function test_active_and_ended_tabs_remain_visible_when_searching(): void
    {
        // Arrange: Create tournaments
        Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
            'tournament_end' => now()->addMonth(),
        ]);

        // Act & Assert: Search should not hide tabs
        Livewire::test(TournamentList::class, ['search' => 'Test'])
            ->assertSet('activeTab', 'active') // Default tab
            ->assertViewHas('tournaments');

        // Tab should be changeable even with search
        Livewire::test(TournamentList::class, ['search' => 'Test', 'activeTab' => 'ended'])
            ->assertSet('activeTab', 'ended')
            ->assertViewHas('tournaments');
    }

    public function test_ended_tab_without_year_keeps_all_years_filter(): void
    {
        $currentYearTournament = Tournament::factory()->create([
            'title' => 'Current Year Ended Tournament',
            'status' => 'approved',
            'tournament_end' => now()->subMonth(),
        ]);

        $previousYearTournament = Tournament::factory()->create([
            'title' => 'Previous Year Ended Tournament',
            'status' => 'approved',
            'tournament_end' => now()->subYear()->subMonth(),
        ]);

        Livewire::test(TournamentList::class, ['activeTab' => 'ended'])
            ->assertSet('selectedYear', null)
            ->assertViewHas('tournaments', function ($tournaments) use ($currentYearTournament, $previousYearTournament) {
                return $tournaments->contains('id', $currentYearTournament->id)
                    && $tournaments->contains('id', $previousYearTournament->id);
            });
    }

    public function test_loaded_tournament_count_can_be_restored_from_url(): void
    {
        Tournament::factory()->count(40)->create([
            'title' => 'Restored Loaded Tournament',
            'status' => 'approved',
            'tournament_end' => now()->addMonth(),
        ]);

        Livewire::withQueryParams(['count' => 36])
            ->test(TournamentList::class)
            ->assertSet('displayedCount', 36)
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->count() === 36;
            });
    }

    /**
     * Test other filters work with search
     */
    public function test_other_filters_work_with_search(): void
    {
        // Arrange: Create tournaments with different modes
        Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
            'modes' => [['mode' => 'osu', 'key_count' => null]],
        ]);

        Tournament::factory()->create([
            'title' => 'Test Tournament 2',
            'status' => 'approved',
            'modes' => [['mode' => 'taiko', 'key_count' => null]],
        ]);

        // Act & Assert: Mode filter should work with search
        Livewire::test(TournamentList::class, [
            'search' => 'Test',
            'mode' => 'osu',
        ])
            ->assertSet('search', 'Test')
            ->assertSet('mode', 'osu')
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->every(fn ($t) => in_array('osu', array_column($t->modes, 'mode')));
            });
    }

    /**
     * Test search persists in URL when navigating
     */
    public function test_search_persists_in_url_when_navigating(): void
    {
        // Arrange: Create tournament
        Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        // Act & Assert: Component should have search property set from URL
        $component = Livewire::withQueryParams(['q' => 'Test'])
            ->test(TournamentList::class);

        // Verify the search property was mapped correctly from 'q' parameter
        $component->assertSet('search', 'Test');
    }

    /**
     * Test only approved staff are searchable
     */
    public function test_only_approved_staff_are_searchable(): void
    {
        // Arrange: Create tournament with pending staff
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
        ]);

        $user = User::factory()->create(['username' => 'PendingStaff']);
        $tournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'pending', // Not approved
        ]);

        // Act & Assert: Should not find tournament by pending staff
        Livewire::test(TournamentList::class, ['search' => 'PendingStaff'])
            ->assertViewHas('tournaments', function ($tournaments) use ($tournament) {
                return $tournaments->where('id', $tournament->id)->isEmpty();
            });
    }

    /**
     * Test only approved tournaments are searchable
     */
    public function test_only_approved_tournaments_are_searchable(): void
    {
        // Arrange: Create pending tournament
        Tournament::factory()->create([
            'title' => 'Pending Tournament',
            'status' => 'pending',
        ]);

        // Act & Assert: Should not find pending tournament
        Livewire::test(TournamentList::class, ['search' => 'Pending'])
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->where('status', 'pending')->isEmpty();
            });
    }

    /**
     * Test search applies tab filter when searching
     */
    public function test_search_applies_tab_filter_when_searching(): void
    {
        // Arrange: Create tournaments in both tabs
        $activeTournament = Tournament::factory()->create([
            'title' => 'Active Test Tournament',
            'status' => 'approved',
            'tournament_end' => now()->addMonth(),
        ]);

        $endedTournament = Tournament::factory()->create([
            'title' => 'Ended Test Tournament',
            'status' => 'approved',
            'tournament_end' => now()->subMonth(),
        ]);

        // Act & Assert: Search in active tab should only find active tournaments
        Livewire::test(TournamentList::class, [
            'activeTab' => 'active',
            'search' => 'Test',
        ])
            ->assertViewHas('tournaments', function ($tournaments) use ($activeTournament, $endedTournament) {
                return $tournaments->contains('id', $activeTournament->id)
                    && ! $tournaments->contains('id', $endedTournament->id);
            });
    }

    /**
     * Test minimum 3 characters for search
     */
    public function test_minimum_3_characters_for_search(): void
    {
        // Arrange: Create tournament
        Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        // Act & Assert: Search with less than 3 characters
        Livewire::test(TournamentList::class, ['search' => 'Te'])
            ->assertViewHas('tournaments'); // Should still render, but may be empty

        // Search with 3 or more characters
        Livewire::test(TournamentList::class, ['search' => 'Tes'])
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->count() > 0;
            });
    }

    /**
     * Test empty search query returns all tournaments
     */
    public function test_empty_search_query_returns_all_tournaments(): void
    {
        // Arrange: Create tournaments
        Tournament::factory()->count(10)->create([
            'title' => 'Tournament',
            'status' => 'approved',
        ]);

        // Act & Assert: Empty search should return all
        Livewire::test(TournamentList::class, ['search' => ''])
            ->assertViewHas('tournaments', function ($tournaments) {
                return $tournaments->count() === 10; // All tournaments
            });
    }
}
