<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentStaff>
 */
class TournamentStaffFactory extends Factory
{
    protected $model = TournamentStaff::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['organizer', 'referee', 'mapper', 'streamer', 'commentator', 'other']),
            'notes' => fake()->optional()->sentence(),
            'status' => 'pending',
            'submitted_at' => now(),
            'reviewed_at' => null,
        ];
    }

    /**
     * Indicate that the staff role is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the staff role is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'reviewed_at' => now(),
        ]);
    }
}
