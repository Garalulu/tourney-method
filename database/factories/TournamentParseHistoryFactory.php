<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentParseHistory>
 */
class TournamentParseHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TournamentParseHistory>
     */
    protected $model = TournamentParseHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'parsed_by' => User::factory(),
            'changes' => [
                'title' => [
                    'old' => $this->faker->sentence(3),
                    'new' => $this->faker->sentence(3),
                ],
            ],
            'parsed_data' => [
                'title' => $this->faker->sentence(3),
                'description' => $this->faker->paragraph(),
                'modes' => ['osu', 'taiko'],
            ],
            'compacted_at' => null,
        ];
    }
}
