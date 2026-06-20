<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Search API endpoint tests
 *
 * Tests the /api/search endpoints for proper authentication,
 * response format, priority sorting, and data accuracy.
 */
final class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test authenticated user can access search endpoint
     */
    public function test_authenticated_user_can_access_search_endpoint(): void
    {
        // Arrange: Create and authenticate user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act: Make request
        $response = $this->getJson('/api/search?q=test');

        // Assert: Should return 200
        $response->assertStatus(200);
    }

    /**
     * Test unauthenticated user cannot access search endpoint
     */
    public function test_unauthenticated_user_cannot_access_search_endpoint(): void
    {
        // Act: Make request without authentication
        $response = $this->getJson('/api/search?q=test');

        // Assert: Should return 401
        $response->assertStatus(401);
    }

    /**
     * Test search returns tournaments with correct format
     */
    public function test_search_returns_tournaments_with_correct_format(): void
    {
        // Arrange: Create authenticated user and tournament
        $user = User::factory()->create();
        $this->actingAs($user);

        Tournament::factory()->create([
            'title' => 'Test Tournament',
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=Test');

        // Assert: Should return correct format
        $response->assertStatus(200)
            ->assertJsonStructure([
                'tournaments' => [
                    '*' => [
                        'id',
                        'title',
                        'modes',
                        'year',
                        'is_badge',
                    ],
                ],
                'staff_tournaments',
                'podium_tournaments',
                'users',
                'total_tournaments',
                'total_staff_tournaments',
                'total_podium_tournaments',
                'total_users',
            ]);

        $data = $response->json();
        $this->assertCount(1, $data['tournaments']);
        $this->assertEquals('Test Tournament', $data['tournaments'][0]['title']);
        $this->assertEquals(['osu'], $data['tournaments'][0]['modes']);
        $this->assertEquals('2024', $data['tournaments'][0]['year']);
    }

    /**
     * Test search returns users with correct format
     */
    public function test_search_returns_users_with_correct_format(): void
    {
        // Arrange: Create authenticated user and searchable user
        $user = User::factory()->create();
        $this->actingAs($user);

        $searchableUser = User::factory()->create([
            'username' => 'testplayer',
            'country_code' => 'US',
            'main_mode' => 'osu',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=test');

        // Assert: Should return correct format
        $response->assertStatus(200)
            ->assertJsonStructure([
                'tournaments',
                'users' => [
                    '*' => [
                        'id',
                        'username',
                        'avatar_url',
                        'country_code',
                        'main_mode',
                    ],
                ],
                'total_tournaments',
                'total_users',
            ]);

        $data = $response->json();
        $this->assertCount(1, $data['users']);
        $this->assertEquals('testplayer', $data['users'][0]['username']);
        $this->assertEquals('https://a.ppy.sh/'.$searchableUser->osu_id, $data['users'][0]['avatar_url']);
        $this->assertEquals('US', $data['users'][0]['country_code']);
        $this->assertEquals('osu', $data['users'][0]['main_mode']);
    }

    /**
     * Test search returns total counts for view all links
     */
    public function test_search_returns_total_counts_for_view_all_links(): void
    {
        // Arrange: Create authenticated user and data
        $user = User::factory()->create();
        $this->actingAs($user);

        Tournament::factory()->count(15)->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        User::factory()->count(8)->create(['username' => 'testuser']);

        // Act: Search
        $response = $this->getJson('/api/search?q=test');

        // Assert: Should return total counts
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(15, $data['total_tournaments']);
        $this->assertEquals(8, $data['total_users']);
    }

    /**
     * Test empty query returns empty results
     */
    public function test_empty_query_returns_empty_results(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act: Search with empty query
        $response = $this->getJson('/api/search?q=');

        // Assert: Should return empty results
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(0, $data['tournaments']);
        $this->assertCount(0, $data['users']);
        $this->assertEquals(0, $data['total_tournaments']);
        $this->assertEquals(0, $data['total_users']);
    }

    /**
     * Test search handles special characters
     */
    public function test_search_handles_special_characters(): void
    {
        // Arrange: Create authenticated user and tournament with special chars
        $user = User::factory()->create();
        $this->actingAs($user);

        Tournament::factory()->create([
            'title' => 'Tournament & "Special" <Chars>',
            'status' => 'approved',
        ]);

        // Act: Search with special characters
        $response = $this->getJson('/api/search?q='.urlencode('Tournament & "Special"'));

        // Assert: Should handle special characters properly
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['tournaments']);
    }

    /**
     * Test search results are sorted by priority
     */
    public function test_search_results_are_sorted_by_priority(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create tournaments that match by different priorities
        $tournamentByPodium = Tournament::factory()->create([
            'title' => 'Low Priority',
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
            'title' => 'Medium Priority',
            'host_username' => 'Test',
            'status' => 'approved',
        ]);

        $tournamentByName = Tournament::factory()->create([
            'title' => 'Test High Priority',
            'status' => 'approved',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=Test');

        // Assert: Primary tournaments keep name/host matches; podium is split out
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data['tournaments']);
        $this->assertEquals('Test High Priority', $data['tournaments'][0]['title']);
        $this->assertEquals('Medium Priority', $data['tournaments'][1]['title']);
        $this->assertCount(1, $data['podium_tournaments']);
        $this->assertEquals('Low Priority', $data['podium_tournaments'][0]['title']);
    }

    /**
     * Test tournament name match has higher priority than host match
     */
    public function test_tournament_name_match_has_higher_priority_than_host_match(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create tournaments
        $tournamentByHost = Tournament::factory()->create([
            'title' => 'Different Name',
            'host_username' => 'Test Query',
            'status' => 'approved',
        ]);

        $tournamentByName = Tournament::factory()->create([
            'title' => 'Test Query Tournament',
            'host_username' => 'Different Host',
            'status' => 'approved',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=Test Query');

        // Assert: Name match should come first
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data['tournaments']);
        $this->assertEquals('Test Query Tournament', $data['tournaments'][0]['title']);
        $this->assertEquals('Different Name', $data['tournaments'][1]['title']);
    }

    /**
     * Test host match has higher priority than staff match
     */
    public function test_host_match_has_higher_priority_than_staff_match(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create tournaments
        $tournamentByStaff = Tournament::factory()->create([
            'title' => 'Tournament A',
            'host_username' => 'Different',
            'status' => 'approved',
        ]);

        $staffUser = User::factory()->create(['username' => 'Test Staff']);
        $tournamentByStaff->staff()->attach($staffUser->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        $tournamentByHost = Tournament::factory()->create([
            'title' => 'Tournament B',
            'host_username' => 'Test Staff',
            'status' => 'approved',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=Test Staff');

        // Assert: Host remains in primary tournaments and staff is split out
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['tournaments']);
        $this->assertEquals('Tournament B', $data['tournaments'][0]['title']);
        $this->assertCount(1, $data['staff_tournaments']);
        $this->assertEquals('Tournament A', $data['staff_tournaments'][0]['title']);
    }

    /**
     * Test staff match has higher priority than podium match
     */
    public function test_staff_match_has_higher_priority_than_podium_match(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create tournaments
        $tournamentByPodium = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
            'tournament_end' => '2024-12-31',
        ]);

        $tournamentByPodium->winners()->create([
            'username' => 'Test Person',
            'placement' => 1,
            'gamemode' => 'osu',
            'osu_id' => 123,
        ]);

        $tournamentByStaff = Tournament::factory()->create([
            'title' => 'Tournament B',
            'status' => 'approved',
        ]);

        $staffUser = User::factory()->create(['username' => 'Test Person']);
        $tournamentByStaff->staff()->attach($staffUser->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=Test Person');

        // Assert: Staff and podium matches are split into dedicated sections
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(0, $data['tournaments']);
        $this->assertCount(1, $data['staff_tournaments']);
        $this->assertCount(1, $data['podium_tournaments']);
        $this->assertEquals('Tournament B', $data['staff_tournaments'][0]['title']);
        $this->assertEquals('Tournament A', $data['podium_tournaments'][0]['title']);
    }

    /**
     * Test search returns maximum 5 results per category
     */
    public function test_search_returns_maximum_5_results_per_category(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create more than 5 tournaments
        Tournament::factory()->count(10)->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        User::factory()->count(10)->create(['username' => 'testuser']);

        // Act: Search
        $response = $this->getJson('/api/search?q=test');

        // Assert: Should return maximum 5 results per category
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(5, $data['tournaments']);
        $this->assertCount(5, $data['users']);
        $this->assertEquals(10, $data['total_tournaments']);
        $this->assertEquals(10, $data['total_users']);
    }

    /**
     * Test search returns maximum 5 results for staff and podium sections
     */
    public function test_search_returns_maximum_5_results_for_staff_and_podium_sections(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        $staffUser = User::factory()->create(['username' => 'section_match_staff']);
        for ($i = 0; $i < 7; $i++) {
            $tournament = Tournament::factory()->create([
                'title' => "Staff Tournament {$i}",
                'status' => 'approved',
                'tournament_end' => now()->subDays($i),
            ]);

            $tournament->staff()->attach($staffUser->id, [
                'role' => 'organizer',
                'status' => 'approved',
            ]);
        }

        for ($i = 0; $i < 8; $i++) {
            $tournament = Tournament::factory()->create([
                'title' => "Podium Tournament {$i}",
                'status' => 'approved',
                'tournament_end' => now()->subDays($i),
            ]);

            $tournament->winners()->create([
                'username' => 'section_match_podium',
                'placement' => 1,
                'gamemode' => 'osu',
                'osu_id' => 200000 + $i,
            ]);
        }

        // Act: Search
        $response = $this->getJson('/api/search?q=section_match');

        // Assert: Should cap each section but return full totals
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(5, $data['staff_tournaments']);
        $this->assertCount(5, $data['podium_tournaments']);
        $this->assertEquals(7, $data['total_staff_tournaments']);
        $this->assertEquals(8, $data['total_podium_tournaments']);
    }

    /**
     * Test previous username query populates staff and podium sections
     */
    public function test_previous_username_query_populates_staff_and_podium_sections(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        $staffTournament = Tournament::factory()->create([
            'title' => 'Previous Staff Tournament',
            'status' => 'approved',
            'tournament_end' => '2025-01-01',
        ]);

        $staffUser = User::factory()->create([
            'username' => 'current_staff_user',
            'previous_usernames' => ['old_shared_name'],
        ]);

        $staffTournament->staff()->attach($staffUser->id, [
            'role' => 'mappooler',
            'status' => 'approved',
        ]);

        $podiumTournament = Tournament::factory()->create([
            'title' => 'Previous Podium Tournament',
            'status' => 'approved',
            'tournament_end' => '2025-02-01',
        ]);

        $podiumUser = User::factory()->create([
            'username' => 'current_podium_user',
            'previous_usernames' => ['old_shared_name'],
            'osu_id' => 345678,
        ]);

        $podiumTournament->winners()->create([
            'user_id' => $podiumUser->id,
            'osu_id' => $podiumUser->osu_id,
            'username' => 'PodiumSnapshot',
            'placement' => 1,
            'gamemode' => 'osu',
        ]);

        // Act: Search by previous username
        $response = $this->getJson('/api/search?q=old_shared_name');

        // Assert: Both new sections should be populated
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['staff_tournaments']);
        $this->assertCount(1, $data['podium_tournaments']);
        $this->assertEquals('Previous Staff Tournament', $data['staff_tournaments'][0]['title']);
        $this->assertEquals('Mappooler', $data['staff_tournaments'][0]['role']);
        $this->assertEquals('2025', $data['staff_tournaments'][0]['year']);
        $this->assertEquals('Previous Podium Tournament', $data['podium_tournaments'][0]['title']);
        $this->assertEquals('🏆 #1', $data['podium_tournaments'][0]['role']);
        $this->assertEquals('2025', $data['podium_tournaments'][0]['year']);
    }

    /**
     * Test search response time is acceptable
     */
    public function test_search_response_time_is_acceptable(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        Tournament::factory()->count(50)->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        // Act: Search and measure time
        $startTime = microtime(true);
        $response = $this->getJson('/api/search?q=test');
        $endTime = microtime(true);

        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assert: Should respond in less than 500ms
        $response->assertStatus(200);
        $this->assertLessThan(500, $duration, "Search took {$duration}ms, expected < 500ms");
    }

    /**
     * Test search by tournament name
     */
    public function test_search_tournaments_by_name(): void
    {
        // Arrange: Create authenticated user and tournament
        $user = User::factory()->create();
        $this->actingAs($user);

        Tournament::factory()->create([
            'title' => 'osu! World Cup 2024',
            'status' => 'approved',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=World');

        // Assert: Should find the tournament
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['tournaments']);
        $this->assertEquals('osu! World Cup 2024', $data['tournaments'][0]['title']);
    }

    /**
     * Test search by tournament host name
     */
    public function test_search_tournaments_by_host_name(): void
    {
        // Arrange: Create authenticated user and tournament
        $user = User::factory()->create();
        $this->actingAs($user);

        Tournament::factory()->create([
            'title' => 'Tournament A',
            'host_username' => 'HostPlayer',
            'status' => 'approved',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=Host');

        // Assert: Should find the tournament
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['tournaments']);
    }

    /**
     * Test search by staff name (approved only)
     */
    public function test_search_tournaments_by_staff_name_approved_only(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create tournament with approved staff
        $tournament = Tournament::factory()->create([
            'title' => 'Tournament A',
            'status' => 'approved',
        ]);

        $staffUser = User::factory()->create(['username' => 'StaffMember']);
        $tournament->staff()->attach($staffUser->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        // Act: Search
        $response = $this->getJson('/api/search?q=Staff');

        // Assert: Should find the tournament
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['staff_tournaments']);
    }

    /**
     * Test search by podium player username
     */
    public function test_search_tournaments_by_podium_player_username(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create tournament with winner
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

        // Act: Search
        $response = $this->getJson('/api/search?q=Winner');

        // Assert: Should find the tournament
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['podium_tournaments']);
    }

    /**
     * Test search tournaments endpoint
     */
    public function test_search_tournaments_endpoint(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        Tournament::factory()->create([
            'title' => 'Test Tournament',
            'status' => 'approved',
        ]);

        // Act: Use tournaments-specific endpoint
        $response = $this->getJson('/api/search/tournaments?q=Test');

        // Assert: Should return tournaments only
        $response->assertStatus(200)
            ->assertJsonStructure([
                'tournaments' => [
                    '*' => [
                        'id',
                        'title',
                        'modes',
                        'year',
                    ],
                ],
                'total',
            ]);

        $data = $response->json();
        $this->assertCount(1, $data['tournaments']);
    }

    /**
     * Test search users endpoint
     */
    public function test_search_users_endpoint(): void
    {
        // Arrange: Create authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        User::factory()->create(['username' => 'testplayer']);

        // Act: Use users-specific endpoint
        $response = $this->getJson('/api/search/users?q=test');

        // Assert: Should return users only
        $response->assertStatus(200)
            ->assertJsonStructure([
                'users' => [
                    '*' => [
                        'id',
                        'username',
                        'avatar_url',
                        'country_code',
                        'main_mode',
                    ],
                ],
                'total',
            ]);

        $data = $response->json();
        $this->assertCount(1, $data['users']);
    }
}
