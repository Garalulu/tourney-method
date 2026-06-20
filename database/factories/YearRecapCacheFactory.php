<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\YearRecapCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YearRecapCache>
 */
class YearRecapCacheFactory extends Factory
{
    protected $model = YearRecapCache::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => fake()->numberBetween(2020, (int) date('Y')),
            'image_path' => 'recaps/'.fake()->uuid().'.png',
            'stats_json' => [
                'total_matches' => fake()->numberBetween(10, 100),
                'total_games' => fake()->numberBetween(50, 500),
                'win_rate' => fake()->randomFloat(2, 0, 100),
                'frequent_teammates' => [],
                'frequent_opponents' => [],
            ],
            'generated_at' => now(),
        ];
    }
}
