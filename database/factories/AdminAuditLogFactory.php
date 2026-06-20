<?php

namespace Database\Factories;

use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => User::factory(),
            'action' => fake()->randomElement(['tournament.approved', 'tournament.rejected', 'tournament.updated', 'tournament.staff_added']),
            'entity_type' => fake()->randomElement([Tournament::class, TournamentStaff::class]),
            'entity_id' => fake()->randomNumber(),
            'details' => fake()->optional()->randomElements([
                'old_role' => fake()->word(),
                'new_role' => fake()->word(),
                'rejection_reason' => fake()->sentence(),
            ], 2),
            'ip_address' => fake()->optional()->ipv4(),
            'created_at' => now()->subDays(rand(0, 30)),
        ];
    }
}
