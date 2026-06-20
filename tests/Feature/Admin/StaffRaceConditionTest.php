<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminAsyncOperation;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Race condition test for concurrent staff addition
 *
 * Tests the fix for: SQLSTATE[23505]: Unique violation on users_osu_id_unique
 * Occurs when two concurrent requests try to add the same user simultaneously
 */
final class StaffRaceConditionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Verify we're using test database (CRITICAL SAFETY CHECK)
        $this->assertSame('testing', config('database.connections.pgsql.database'));

        // Clear cache to avoid stale data
        Cache::flush();
    }

    #[Test]
    public function concurrent_staff_addition_handles_race_condition(): void
    {
        // Create admin user
        $admin = User::factory()->create(['role' => 'admin']);

        // Create tournament
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
        ]);

        // Create the user first (simulating that it was just created)
        $existingUser = User::create([
            'osu_id' => 551369,
            'username' => '- Estella -',
            'country_code' => 'TH',
            'main_mode' => 'osu',
            'role' => 'player',
            'osu_data_synced_at' => now(),
        ]);

        // Mock osu! API to return the same user
        Http::fake([
            'https://osu.ppy.sh/api/v2/users/@-Estella -*' => Http::response([
                'id' => 551369,
                'username' => '- Estella -',
                'avatar_url' => 'https://a.ppy.sh/551369?1774322558.png',
                'country_code' => 'TH',
            ], 200),
        ]);

        // Add staff - should use existing user, not try to create duplicate
        $response = $this->actingAs($admin)->postJson(
            route('admin.tournaments.staff.add', $tournament),
            [
                'usernames' => '- Estella -',
                'role' => 'organizer',
            ]
        );

        // Should succeed without unique constraint error
        $response->assertStatus(202);
        $this->assertTrue($response->json('success'));

        // Verify only one user still exists (not duplicated)
        $this->assertEquals(1, User::where('osu_id', 551369)->count());

        // Verify staff record was created
        $this->assertDatabaseHas('tournament_staff', [
            'tournament_id' => $tournament->id,
            'user_id' => $existingUser->id,
            'role' => 'organizer',
        ]);
    }

    #[Test]
    public function update_or_create_handles_unique_constraint_gracefully(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tournament = Tournament::factory()->create(['status' => 'approved']);

        // Create the user first
        $existingUser = User::create([
            'osu_id' => 999999,
            'username' => 'Test User',
            'country_code' => 'US',
            'main_mode' => 'osu',
            'role' => 'player',
            'osu_data_synced_at' => now(),
        ]);

        // Mock osu! API to return the same user
        Http::fake([
            'https://osu.ppy.sh/api/v2/users/@Test User*' => Http::response([
                'id' => 999999,
                'username' => 'Test User',
                'avatar_url' => 'https://a.ppy.sh/999999',
                'country_code' => 'US',
            ], 200),
        ]);

        // First request adds the user as mappooler
        $response1 = $this->actingAs($admin)->postJson(
            route('admin.tournaments.staff.add', $tournament),
            [
                'usernames' => 'Test User',
                'role' => 'mappooler',
            ]
        );

        $response1->assertStatus(202);

        // Second request with same role should fail gracefully
        $response2 = $this->actingAs($admin)->postJson(
            route('admin.tournaments.staff.add', $tournament),
            [
                'usernames' => 'Test User',
                'role' => 'mappooler',
            ]
        );

        // The async operation records the duplicate without a database error.
        $response2->assertStatus(202);
        $this->assertTrue($response2->json('success'));
        $operation = AdminAsyncOperation::query()->findOrFail($response2->json('operation_id'));
        $this->assertStringContainsString('already has role', $operation->errors[0]['message']);

        // Still only one user in database
        $this->assertEquals(1, User::where('osu_id', 999999)->count());
    }
}
