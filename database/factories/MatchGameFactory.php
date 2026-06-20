<?php

namespace Database\Factories;

use App\Models\MatchGame;
use App\Models\OsuMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchGame>
 */
class MatchGameFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MatchGame>
     */
    protected $model = MatchGame::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => OsuMatch::factory(),
            'game_id' => fake()->numberBetween(1, 999999),
            'beatmap_id' => fake()->numberBetween(1, 9999999),
            'beatmap_title' => fake()->words(3, true),
            'beatmap_version' => fake()->word(),
            'mods' => ['NM'],
            'mode' => 'osu',
            'scoring_type' => 'score',
            'team_type' => 'head_to_head',
            'start_time' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'end_time' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
