<?php

namespace Database\Factories;

use App\Models\OsuMatch;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OsuMatch>
 */
class OsuMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('-1 month', 'now');
        $endTime = (clone $startTime)->modify('+'.fake()->numberBetween(30, 180).' minutes');

        return [
            'osu_match_id' => fake()->unique()->numberBetween(10000000, 99999999),
            'name' => fake()->sentence(4),
            'tournament_id' => null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
            'submitted_by' => User::factory(),
            'reviewed_by' => null,
            'raw_data' => [
                'match' => [
                    'id' => fake()->numberBetween(10000000, 99999999),
                    'name' => fake()->sentence(4),
                ],
                'events' => [],
                'games' => [],
                'users' => [],
            ],
        ];
    }

    /**
     * Indicate that the match is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the match is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'reviewed_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the match is linked to a tournament.
     */
    public function withTournament(?int $tournamentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tournament_id' => $tournamentId ?? Tournament::factory(),
        ]);
    }
}
