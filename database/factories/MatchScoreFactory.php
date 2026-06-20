<?php

namespace Database\Factories;

use App\Models\MatchGame;
use App\Models\MatchScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchScore>
 */
class MatchScoreFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MatchScore>
     */
    protected $model = MatchScore::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_game_id' => MatchGame::factory(),
            'user_id' => User::factory(),
            'osu_user_id' => fake()->numberBetween(1, 99999999),
            'username' => fake()->userName(),
            'team' => fake()->optional()->word(),
            'score' => fake()->numberBetween(0, 10000000),
            'accuracy' => fake()->randomFloat(4, 0, 1),
            'max_combo' => fake()->numberBetween(0, 5000),
            'count_300' => fake()->numberBetween(0, 2000),
            'count_100' => fake()->numberBetween(0, 500),
            'count_50' => fake()->numberBetween(0, 200),
            'count_miss' => fake()->numberBetween(0, 100),
            'count_geki' => fake()->numberBetween(0, 500),
            'count_katu' => fake()->numberBetween(0, 200),
            'perfect' => fake()->boolean(),
            'passed' => fake()->boolean(80), // 80% chance of passing
            'mods' => fake()->optional()->randomElements(['NM', 'HD', 'HR', 'DT', 'EZ', 'HT', 'NC', 'FL'], null),
        ];
    }
}
