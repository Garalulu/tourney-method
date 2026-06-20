<?php

namespace Database\Factories;

use App\Models\TournamentWinner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentWinner>
 */
class TournamentWinnerFactory extends Factory
{
    protected $model = TournamentWinner::class;

    public function definition(): array
    {
        return [
            'placement' => $this->faker->numberBetween(1, 16),
            'username' => $this->faker->userName(),
            'osu_id' => $this->faker->numberBetween(1000, 99999999),
            'gamemode' => 'osu',
        ];
    }
}
