<?php

namespace Database\Factories;

use App\Models\OsuMatch;
use App\Models\User;
use App\Models\UserMatchParticipation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserMatchParticipation>
 */
class UserMatchParticipationFactory extends Factory
{
    protected $model = UserMatchParticipation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'match_id' => OsuMatch::factory(),
            'team' => fake()->randomElement(['red', 'blue']),
            'games_played' => fake()->numberBetween(1, 20),
            'games_won' => fake()->numberBetween(0, 20),
            'total_score' => fake()->numberBetween(100000, 10000000),
            'avg_accuracy' => fake()->randomFloat(4, 80, 100),
        ];
    }

    /**
     * Indicate the user is on the red team.
     */
    public function redTeam(): static
    {
        return $this->state(fn (array $attributes) => [
            'team' => 'red',
        ]);
    }

    /**
     * Indicate the user is on the blue team.
     */
    public function blueTeam(): static
    {
        return $this->state(fn (array $attributes) => [
            'team' => 'blue',
        ]);
    }
}
