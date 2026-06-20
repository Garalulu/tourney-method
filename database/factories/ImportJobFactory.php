<?php

namespace Database\Factories;

use App\Models\ImportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportJob>
 */
class ImportJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => fake()->randomElement(['tcomm', 'otr', 'combined']),
            'status' => 'pending',
            'tournaments_imported' => 0,
            'tournaments_updated' => 0,
            'tournaments_failed' => 0,
            'matches_imported' => 0,
            'matches_failed' => 0,
            'error_log' => null,
        ];
    }
}
