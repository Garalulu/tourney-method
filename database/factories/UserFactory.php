<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'osu_id' => fake()->unique()->numberBetween(1000, 9999999),
            'username' => fake()->userName(),
            'country_code' => fake()->countryCode(),
            'main_mode' => null,  // Default to null (incomplete setup)
            'role' => 'player',
            'osu_data_synced_at' => now(),
            'last_login_at' => null,
        ];
    }

    /**
     * Indicate that the user has completed setup.
     */
    public function withSetup(string $mode = 'osu'): static
    {
        return $this->state(fn (array $attributes) => [
            'main_mode' => $mode,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Indicate that the user is a master.
     */
    public function master(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'master',
        ]);
    }

    /**
     * Indicate that the user is a regular player.
     */
    public function player(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'player',
        ]);
    }
}
