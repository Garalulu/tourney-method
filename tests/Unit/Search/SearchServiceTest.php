<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\DTOs\SearchQuery;
use App\Models\Tournament;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SearchService unit tests
 *
 * Tests search functionality with priority sorting:
 * - Tournament name: priority 100 (highest)
 * - Host name: priority 75
 * - Staff name: priority 50
 * - Podium name: priority 25 (lowest)
 */
final class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private SearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchService = new SearchService;
    }

    /**
     * Test searching tournaments by name with highest priority
     */
    public function test_searches_tournaments_by_name_with_priority_100(): void
    {
        // Arrange: Create tournaments
        Tournament::factory()->create([
            'title' => 'osu! World Cup 2024',
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);

        Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
        ]);

        // Act: Search by name
        $results = $this->searchService->searchTournaments('World Cup', 5);

        // Assert: Should find the tournament with priority 100
        $this->assertCount(1, $results);
        $this->assertEquals('osu! World Cup 2024', $results->first()->title);
        $this->assertEquals(100, $results->first()->priority);
    }

    /**
     * Test searching tournaments by host name with medium priority
     */
    public function test_searches_tournaments_by_host_name_with_priority_75(): void
    {
        // Arrange: Create tournament with specific host
        Tournament::factory()->create([
            'title' => 'Tournament A',
            'host_username' => 'host_player',
            'status' => 'approved',
        ]);

        // Act: Search by host name
        $results = $this->searchService->searchTournaments('host_player', 5);

        // Assert: Should find the tournament with priority 75
        $this->assertCount(1, $results);
        $this->assertEquals('host_player', $results->first()->host_username);
        $this->assertEquals(75, $results->first()->priority);
    }

    /**
     * Test searching tournaments by staff name with lower priority
     */
    public function test_searches_tournaments_by_staff_name_with_priority_50(): void
    {
        // Arrange: Create tournament with staff
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
        ]);

        $user = User::factory()->create(['username' => 'staff_member']);
        $tournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        // Act: Search by staff name
        $results = $this->searchService->searchTournaments('staff_member', 5);

        // Assert: Should find the tournament with priority 50
        $this->assertCount(1, $results);
        $this->assertEquals(50, $results->first()->priority);
    }

    /**
     * Test searching tournaments by staff previous username
     */
    public function test_searches_tournaments_by_staff_previous_username(): void
    {
        // Arrange: Create tournament with staff who changed username
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
        ]);

        $user = User::factory()->create([
            'username' => 'current_staff',
            'previous_usernames' => ['old_staff_name'],
        ]);

        $tournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        // Act: Search by previous staff username
        $results = $this->searchService->searchStaffTournaments('old_staff_name', 5);

        // Assert: Should find the tournament and expose staff role
        $this->assertCount(1, $results);
        $this->assertEquals($tournament->id, $results->first()->id);
        $this->assertEquals('Organizer', $results->first()->getAttribute('role'));
    }

    /**
     * Test staff tournament results sort by tournament end date only.
     */
    public function test_staff_tournament_results_sort_by_latest_tournament_end(): void
    {
        // Arrange: Create two tournaments with different staff roles and dates
        $user = User::factory()->create(['username' => 'staff_sort_user']);

        $olderOrganizerTournament = Tournament::factory()->create([
            'title' => 'Older Organizer Tournament',
            'status' => 'approved',
            'tournament_end' => '2023-01-01',
        ]);

        $newerRefereeTournament = Tournament::factory()->create([
            'title' => 'Newer Referee Tournament',
            'status' => 'approved',
            'tournament_end' => '2025-01-01',
        ]);

        $olderOrganizerTournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        $newerRefereeTournament->staff()->attach($user->id, [
            'role' => 'referee',
            'status' => 'approved',
        ]);

        // Act: Search by staff name
        $results = $this->searchService->searchStaffTournaments('staff_sort_user', 5);

        // Assert: Recent tournament comes first, regardless of staff role priority
        $this->assertCount(2, $results);
        $this->assertEquals($newerRefereeTournament->id, $results->first()->id);
        $this->assertEquals($olderOrganizerTournament->id, $results->last()->id);
    }

    /**
     * Test pending staff are not searchable by previous username
     */
    public function test_pending_staff_previous_username_is_not_searchable(): void
    {
        // Arrange: Create tournament with pending staff who changed username
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
        ]);

        $user = User::factory()->create([
            'username' => 'current_pending_staff',
            'previous_usernames' => ['old_pending_staff'],
        ]);

        $tournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'pending',
        ]);

        // Act: Search by previous staff username
        $results = $this->searchService->searchStaffTournaments('old_pending_staff', 5);

        // Assert: Should not find pending staff
        $this->assertCount(0, $results);
    }

    /**
     * Test searching tournaments by podium name with lowest priority
     */
    public function test_searches_tournaments_by_podium_name_with_priority_25(): void
    {
        // Arrange: Create tournament with podium winner
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);

        $tournament->winners()->create([
            'osu_id' => 123456,
            'username' => 'winner_player',
            'placement' => 1,
            'gamemode' => 'osu',
        ]);

        // Act: Search by podium name
        $results = $this->searchService->searchTournaments('winner_player', 5);

        // Assert: Should find the tournament with priority 25
        $this->assertCount(1, $results);
        $this->assertEquals(25, $results->first()->priority);
    }

    /**
     * Test searching tournaments by linked podium user's previous username
     */
    public function test_searches_tournaments_by_linked_podium_previous_username(): void
    {
        // Arrange: Create tournament with linked podium winner who changed username
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);

        $user = User::factory()->create([
            'username' => 'current_winner',
            'previous_usernames' => ['old_winner_name'],
            'osu_id' => 123456,
        ]);

        $tournament->winners()->create([
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => 'WinnerSnapshot',
            'placement' => 1,
            'gamemode' => 'osu',
        ]);

        // Act: Search by previous podium username
        $results = $this->searchService->searchPodiumTournaments('old_winner_name', 5);

        // Assert: Should find the tournament and expose placement
        $this->assertCount(1, $results);
        $this->assertEquals($tournament->id, $results->first()->id);
        $this->assertEquals('🏆 #1', $results->first()->getAttribute('role'));
    }

    /**
     * Test searching tournaments by raw podium snapshot username still works
     */
    public function test_searches_tournaments_by_raw_podium_snapshot_username(): void
    {
        // Arrange: Create tournament with podium winner
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);

        $tournament->winners()->create([
            'osu_id' => 123456,
            'username' => 'snapshot_winner',
            'placement' => 2,
            'gamemode' => 'osu',
        ]);

        // Act: Search by imported winner username
        $results = $this->searchService->searchPodiumTournaments('snapshot_winner', 5);

        // Assert: Should find the tournament
        $this->assertCount(1, $results);
        $this->assertEquals($tournament->id, $results->first()->id);
        $this->assertEquals('#2', $results->first()->getAttribute('role'));
    }

    /**
     * Test merging results from all sources
     */
    public function test_merges_results_from_all_sources(): void
    {
        // Arrange: Create tournaments that match by different criteria
        $tournamentByName = Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        $tournamentByHost = Tournament::factory()->create([
            'title' => 'Different Tournament',
            'host_username' => 'Test',
            'status' => 'approved',
        ]);

        // Act: Search that should match both
        $results = $this->searchService->searchTournaments('Test', 10);

        // Assert: Should find both tournaments
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $tournamentByName->id));
        $this->assertTrue($results->contains('id', $tournamentByHost->id));
    }

    /**
     * Test removing duplicates keeping highest priority
     */
    public function test_removes_duplicate_tournaments_keeping_highest_priority(): void
    {
        // Arrange: Create tournament that matches by name AND host
        $tournament = Tournament::factory()->create([
            'title' => 'Test Tournament',
            'host_username' => 'Test',
            'status' => 'approved',
        ]);

        // Act: Search
        $results = $this->searchService->searchTournaments('Test', 5);

        // Assert: Should only appear once with highest priority (name = 100)
        $this->assertCount(1, $results);
        $this->assertEquals($tournament->id, $results->first()->id);
        $this->assertEquals(100, $results->first()->priority); // Name match has higher priority
    }

    /**
     * Test sorting by priority descending
     */
    public function test_sorts_tournaments_by_priority_descending(): void
    {
        // Arrange: Create tournaments
        $tournamentByPodium = Tournament::factory()->create([
            'title' => 'Tournament C',
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
            'title' => 'Tournament B',
            'host_username' => 'Test',
            'status' => 'approved',
        ]);

        $tournamentByName = Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        // Act: Search
        $results = $this->searchService->searchTournaments('Test', 10);

        // Assert: Should be sorted by priority (name > host > podium)
        $this->assertCount(3, $results);
        $this->assertEquals(100, $results->get(0)->priority); // Name match
        $this->assertEquals(75, $results->get(1)->priority);  // Host match
        $this->assertEquals(25, $results->get(2)->priority);  // Podium match
    }

    /**
     * Test limiting tournament results
     */
    public function test_limits_tournament_results_to_specified_count(): void
    {
        // Arrange: Create multiple tournaments
        Tournament::factory()->count(10)->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        // Act: Search with limit
        $results = $this->searchService->searchTournaments('Test', 5);

        // Assert: Should only return 5 results
        $this->assertCount(5, $results);
    }

    /**
     * Test searching users by username
     */
    public function test_searches_users_by_username(): void
    {
        // Arrange: Create users
        User::factory()->create(['username' => 'test_player_one']);
        User::factory()->create(['username' => 'test_player_two']);
        User::factory()->create(['username' => 'other_player']);

        // Act: Search
        $results = $this->searchService->searchUsers('test', 10);

        // Assert: Should find matching users
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('username', 'test_player_one'));
        $this->assertTrue($results->contains('username', 'test_player_two'));
    }

    /**
     * Test limiting user results
     */
    public function test_limits_user_results_to_specified_count(): void
    {
        // Arrange: Create users
        User::factory()->count(10)->create(['username' => 'test_user']);

        // Act: Search with limit
        $results = $this->searchService->searchUsers('test', 5);

        // Assert: Should only return 5 results
        $this->assertCount(5, $results);
    }

    /**
     * Test empty query returns empty collection
     */
    public function test_returns_empty_collection_for_empty_query(): void
    {
        // Arrange: Create some data
        Tournament::factory()->count(5)->create(['status' => 'approved']);
        User::factory()->count(5)->create();

        // Act: Search with empty query
        $tournamentResults = $this->searchService->searchTournaments('', 5);
        $userResults = $this->searchService->searchUsers('', 5);

        // Assert: Should return empty collections
        $this->assertCount(0, $tournamentResults);
        $this->assertCount(0, $userResults);
    }

    /**
     * Test query under 3 characters returns empty collection
     */
    public function test_returns_empty_collection_for_query_under_3_characters(): void
    {
        // Arrange: Create some data
        Tournament::factory()->create(['title' => 'ABC', 'status' => 'approved']);
        User::factory()->create(['username' => 'XYZ']);

        // Act: Search with short query
        $tournamentResults = $this->searchService->searchTournaments('AB', 5);
        $userResults = $this->searchService->searchUsers('XY', 5);

        // Assert: Should return empty collections
        $this->assertCount(0, $tournamentResults);
        $this->assertCount(0, $userResults);
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

        $user = User::factory()->create(['username' => 'pending_staff']);
        $tournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'pending', // Not approved
        ]);

        // Act: Search by pending staff name
        $results = $this->searchService->searchTournaments('pending_staff', 5);

        // Assert: Should NOT find the tournament
        $this->assertCount(0, $results);
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

        // Act: Search by name
        $results = $this->searchService->searchTournaments('Pending', 5);

        // Assert: Should NOT find the tournament
        $this->assertCount(0, $results);
    }

    /**
     * Test soft-deleted tournaments are not searchable
     */
    public function test_soft_deleted_tournaments_are_not_searchable(): void
    {
        // Arrange: Create soft-deleted tournament
        Tournament::factory()->create([
            'title' => 'Deleted Tournament',
            'status' => 'approved',
            'deleted_at' => now(),
        ]);

        // Act: Search by name
        $results = $this->searchService->searchTournaments('Deleted', 5);

        // Assert: Should NOT find the tournament
        $this->assertCount(0, $results);
    }

    /**
     * Test soft-deleted users are not searchable
     */
    public function test_soft_deleted_users_are_not_searchable(): void
    {
        // Arrange: Create soft-deleted user
        User::factory()->create([
            'username' => 'deleted_user',
            'deleted_at' => now(),
        ]);

        // Act: Search by username
        $results = $this->searchService->searchUsers('deleted', 5);

        // Assert: Should NOT find the user
        $this->assertCount(0, $results);
    }

    /**
     * Test tournament search returns year extracted from tournament_end
     */
    public function test_tournament_search_returns_year_from_tournament_end(): void
    {
        // Arrange: Create tournament
        Tournament::factory()->create([
            'title' => 'Test Tournament 2024',
            'status' => 'approved',
            'tournament_end' => '2024-12-31 23:59:59',
        ]);

        // Act: Search
        $results = $this->searchService->searchTournaments('Test', 5);

        // Assert: Should return year 2024
        $this->assertCount(1, $results);
        $this->assertEquals('2024', $results->first()->year);
    }

    /**
     * Test searching users by previous username
     */
    public function test_searches_users_by_previous_username(): void
    {
        // Arrange: Create user with previous usernames
        User::factory()->create([
            'username' => 'current_name',
            'previous_usernames' => ['old_name_1', 'old_name_2', 'old_name_3'],
        ]);

        // Act: Search by previous username
        $results = $this->searchService->searchUsers('old_name_1', 10);

        // Assert: Should find the user
        $this->assertCount(1, $results);
        $this->assertEquals('current_name', $results->first()->username);
    }

    /**
     * Test searching users by previous username with special characters
     */
    public function test_searches_users_by_previous_username_with_special_characters(): void
    {
        // Arrange: Create user with previous usernames containing special characters
        User::factory()->create([
            'username' => 'current_player',
            'previous_usernames' => ['[alt][F4]', 'test-player', 'player_123'],
        ]);

        // Act: Search by previous username with special characters
        $results = $this->searchService->searchUsers('[alt][F4]', 10);

        // Assert: Should find the user
        $this->assertCount(1, $results);
        $this->assertEquals('current_player', $results->first()->username);
    }

    /**
     * Test searching users finds both current and previous username matches
     */
    public function test_searches_users_finds_both_current_and_previous_username_matches(): void
    {
        // Arrange: Create multiple users
        User::factory()->create([
            'username' => 'player_one',
            'previous_usernames' => ['old_player_one', 'veteran_one'],
        ]);

        User::factory()->create([
            'username' => 'player_two',
            'previous_usernames' => ['old_player_two'],
        ]);

        User::factory()->create([
            'username' => 'player_three',
            'previous_usernames' => ['player_one'], // Another user's previous name matches player_one's current name
        ]);

        // Act: Search for "player_one" (should match current username of first user AND previous username of third user)
        $results = $this->searchService->searchUsers('player_one', 10);

        // Assert: Should find both users
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('username', 'player_one')); // Current username match
        $this->assertTrue($results->contains('username', 'player_three')); // Previous username match
    }

    /**
     * Test searching users with empty previous_usernames array
     */
    public function test_searches_users_with_empty_previous_usernames_still_works(): void
    {
        // Arrange: Create user with empty previous usernames
        User::factory()->create([
            'username' => 'test_player',
            'previous_usernames' => [],
        ]);

        // Act: Search by current username
        $results = $this->searchService->searchUsers('test', 10);

        // Assert: Should find the user by current username
        $this->assertCount(1, $results);
        $this->assertEquals('test_player', $results->first()->username);
    }

    /**
     * Test searching users with null previous_usernames
     */
    public function test_searches_users_with_null_previous_usernames_still_works(): void
    {
        // Arrange: Create user with null previous usernames (default for existing users)
        User::factory()->create([
            'username' => 'test_player',
            'previous_usernames' => null,
        ]);

        // Act: Search by current username
        $results = $this->searchService->searchUsers('test', 10);

        // Assert: Should find the user by current username
        $this->assertCount(1, $results);
        $this->assertEquals('test_player', $results->first()->username);
    }

    /**
     * Test previous username search is case-insensitive
     */
    public function test_previous_username_search_is_case_insensitive(): void
    {
        // Arrange: Create user with previous username
        User::factory()->create([
            'username' => 'current_name',
            'previous_usernames' => ['Old_Name_One', 'Old_Name_Two'],
        ]);

        // Act: Search with different case
        $results = $this->searchService->searchUsers('old_name_one', 10);

        // Assert: Should find the user (case-insensitive search)
        $this->assertCount(1, $results);
        $this->assertEquals('current_name', $results->first()->username);
    }

    /**
     * Test searching users by partial previous username match
     */
    public function test_searches_users_by_partial_previous_username(): void
    {
        // Arrange: Create user with previous usernames
        User::factory()->create([
            'username' => 'current_name',
            'previous_usernames' => ['Pyroflayer', 'borborygmos', 'test_player_long'],
        ]);

        // Act: Search with partial match
        $results = $this->searchService->searchUsers('Pyro', 10);

        // Assert: Should find the user (partial match in previous username)
        $this->assertCount(1, $results);
        $this->assertEquals('current_name', $results->first()->username);
    }

    public function test_rank_command_matches_near_start_rank_and_excludes_open_or_eligibility_only_matches(): void
    {
        $nearStart = Tournament::factory()->create([
            'title' => 'Near Start Rank Tournament',
            'status' => 'approved',
            'rank_range_min' => 950,
            'rank_range_max' => 5000,
        ]);

        Tournament::factory()->create([
            'title' => 'Open Rank Tournament',
            'status' => 'approved',
            'rank_range_min' => null,
            'rank_range_max' => null,
        ]);

        Tournament::factory()->create([
            'title' => 'Eligibility Range Only Tournament',
            'status' => 'approved',
            'rank_range_min' => 1,
            'rank_range_max' => 1000,
        ]);

        $results = $this->searchService
            ->searchTournamentsForPage('rank:1000', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $nearStart->id));
        $this->assertCount(1, $results);
    }

    public function test_star_rating_command_matches_within_first_and_last_range(): void
    {
        $matching = Tournament::factory()->create([
            'title' => 'Matching Star Rating Tournament',
            'status' => 'approved',
            'star_rating_first' => 4.5,
            'star_rating_last' => 5.8,
        ]);

        Tournament::factory()->create([
            'title' => 'Too Low Star Rating Tournament',
            'status' => 'approved',
            'star_rating_first' => 3.0,
            'star_rating_last' => 4.0,
        ]);

        $results = $this->searchService
            ->searchTournamentsForPage('sr:5.5', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $matching->id));
        $this->assertCount(1, $results);
    }

    public function test_country_command_matches_restricted_countries_with_or_condition(): void
    {
        $chinaRegional = Tournament::factory()->create([
            'title' => 'China Regional Tournament',
            'status' => 'approved',
            'restricted_countries' => ['CN', 'TW', 'HK', 'MO'],
        ]);

        $taiwanRegional = Tournament::factory()->create([
            'title' => 'Taiwan Regional Tournament',
            'status' => 'approved',
            'restricted_countries' => ['TW'],
        ]);

        Tournament::factory()->create([
            'title' => 'Global Tournament',
            'status' => 'approved',
            'restricted_countries' => null,
        ]);

        Tournament::factory()->create([
            'title' => 'Korea Regional Tournament',
            'status' => 'approved',
            'restricted_countries' => ['KR'],
        ]);

        $results = $this->searchService
            ->searchTournamentsForPage('countries:CN,TW', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $chinaRegional->id));
        $this->assertTrue($results->contains('id', $taiwanRegional->id));
        $this->assertCount(2, $results);
    }

    public function test_format_command_matches_team_formation_style(): void
    {
        $draft = Tournament::factory()->create([
            'title' => 'Draft Tournament',
            'status' => 'approved',
            'team_formation_style' => 'draft',
            'format_tags' => ['draft'],
        ]);

        Tournament::factory()->create([
            'title' => 'Standard Tournament',
            'status' => 'approved',
            'team_formation_style' => 'standard',
            'format_tags' => [],
        ]);

        $results = $this->searchService
            ->searchTournamentsForPage('format:draft', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $draft->id));
        $this->assertCount(1, $results);
    }

    public function test_format_command_matches_battle_royale_tag(): void
    {
        $battleRoyale = Tournament::factory()->create([
            'title' => 'Battle Royale Tournament',
            'status' => 'approved',
            'format_tags' => ['battle_royale'],
            'format_structure' => [
                'stages' => [
                    ['type' => 'battle_royale', 'advance_per_lobby' => 4],
                ],
            ],
        ]);

        Tournament::factory()->create([
            'title' => 'Draft Tournament',
            'status' => 'approved',
            'team_formation_style' => 'draft',
            'format_tags' => ['draft'],
        ]);

        $results = $this->searchService
            ->searchTournamentsForPage('format:battle-royale', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $battleRoyale->id));
        $this->assertCount(1, $results);
    }

    public function test_staff_command_requires_approved_staff_with_requested_english_role(): void
    {
        $matching = Tournament::factory()->create([
            'title' => 'Matching Staff Tournament',
            'status' => 'approved',
        ]);

        $wrongRole = Tournament::factory()->create([
            'title' => 'Wrong Role Staff Tournament',
            'status' => 'approved',
        ]);

        $pending = Tournament::factory()->create([
            'title' => 'Pending Staff Tournament',
            'status' => 'approved',
        ]);

        $user = User::factory()->create(['username' => 'Garalulu']);

        $matching->staff()->attach($user->id, ['role' => 'organizer', 'status' => 'approved']);
        $wrongRole->staff()->attach($user->id, ['role' => 'mapper', 'status' => 'approved']);
        $pending->staff()->attach($user->id, ['role' => 'organizer', 'status' => 'pending']);

        $results = $this->searchService
            ->searchTournamentsForPage('staff:Garalulu@organizer', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $matching->id));
        $this->assertCount(1, $results);
    }

    public function test_staff_command_without_role_matches_all_approved_staff_roles_for_username(): void
    {
        $organizerTournament = Tournament::factory()->create([
            'title' => 'Organizer Staff Tournament',
            'status' => 'approved',
        ]);

        $mapperTournament = Tournament::factory()->create([
            'title' => 'Mapper Staff Tournament',
            'status' => 'approved',
        ]);

        $pendingTournament = Tournament::factory()->create([
            'title' => 'Pending Staff Tournament',
            'status' => 'approved',
        ]);

        $user = User::factory()->create(['username' => 'Garalulu']);
        $otherUser = User::factory()->create(['username' => 'SomeoneElse']);

        $organizerTournament->staff()->attach($user->id, ['role' => 'organizer', 'status' => 'approved']);
        $mapperTournament->staff()->attach($user->id, ['role' => 'mapper', 'status' => 'approved']);
        $pendingTournament->staff()->attach($user->id, ['role' => 'organizer', 'status' => 'pending']);

        Tournament::factory()->create([
            'title' => 'Other Staff Tournament',
            'status' => 'approved',
        ])->staff()->attach($otherUser->id, ['role' => 'organizer', 'status' => 'approved']);

        $results = $this->searchService
            ->searchTournamentsForPage('staff:Garalulu', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $organizerTournament->id));
        $this->assertTrue($results->contains('id', $mapperTournament->id));
        $this->assertCount(2, $results);
    }

    public function test_podium_command_requires_matching_username_and_placement(): void
    {
        $matching = Tournament::factory()->create([
            'title' => 'Matching Podium Tournament',
            'status' => 'approved',
        ]);

        $wrongPlacement = Tournament::factory()->create([
            'title' => 'Wrong Placement Tournament',
            'status' => 'approved',
        ]);

        $matching->winners()->create([
            'osu_id' => 123,
            'username' => 'Garalulu',
            'placement' => 1,
            'gamemode' => 'osu',
        ]);

        $wrongPlacement->winners()->create([
            'osu_id' => 123,
            'username' => 'Garalulu',
            'placement' => 2,
            'gamemode' => 'osu',
        ]);

        $results = $this->searchService
            ->searchTournamentsForPage('podium:Garalulu@1st', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $matching->id));
        $this->assertCount(1, $results);
    }

    public function test_podium_command_without_placement_matches_all_podium_placements_for_username(): void
    {
        $firstPlace = Tournament::factory()->create([
            'title' => 'First Place Podium Tournament',
            'status' => 'approved',
        ]);

        $secondPlace = Tournament::factory()->create([
            'title' => 'Second Place Podium Tournament',
            'status' => 'approved',
        ]);

        Tournament::factory()->create([
            'title' => 'Other Player Podium Tournament',
            'status' => 'approved',
        ])->winners()->create([
            'osu_id' => 456,
            'username' => 'SomeoneElse',
            'placement' => 1,
            'gamemode' => 'osu',
        ]);

        $firstPlace->winners()->create([
            'osu_id' => 123,
            'username' => 'Garalulu',
            'placement' => 1,
            'gamemode' => 'osu',
        ]);

        $secondPlace->winners()->create([
            'osu_id' => 123,
            'username' => 'Garalulu',
            'placement' => 2,
            'gamemode' => 'osu',
        ]);

        $results = $this->searchService
            ->searchTournamentsForPage('podium:Garalulu', new SearchQuery(tab: 'all'), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $firstPlace->id));
        $this->assertTrue($results->contains('id', $secondPlace->id));
        $this->assertCount(2, $results);
    }

    public function test_command_filters_combine_with_keyword_and_existing_filters(): void
    {
        $matching = Tournament::factory()->create([
            'title' => 'Alpha Filtered Tournament',
            'status' => 'approved',
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'is_badge' => true,
            'tournament_end' => now()->subMonth(),
            'star_rating_first' => 5.0,
            'star_rating_last' => 6.0,
        ]);

        Tournament::factory()->create([
            'title' => 'Alpha Wrong Mode Tournament',
            'status' => 'approved',
            'modes' => [['mode' => 'taiko', 'key_count' => null]],
            'is_badge' => true,
            'tournament_end' => now()->subMonth(),
            'star_rating_first' => 5.0,
            'star_rating_last' => 6.0,
        ]);

        $results = $this->searchService
            ->searchTournamentsForPage('Alpha sr:5.5', new SearchQuery(
                mode: 'osu',
                badgeOnly: true,
                tab: 'ended',
                selectedYear: (int) now()->subMonth()->year,
            ), 10)
            ->getCollection();

        $this->assertTrue($results->contains('id', $matching->id));
        $this->assertCount(1, $results);
    }
}
