<?php

namespace Database\Factories;

use App\Models\DiscordChannel;
use App\Models\DiscordServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordChannel>
 */
class DiscordChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mode = fake()->randomElement(['osu', 'taiko', 'catch', 'mania']);

        return [
            'discord_server_id' => DiscordServer::factory(),
            'channel_name' => fake()->unique()->word().'-tournament-channel',
            'webhook_url' => fake()->optional()->url(),
            'mode' => $mode,
            'is_badge' => fake()->boolean(),
            'role_mappings' => [],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the channel is for badge tournaments.
     */
    public function badge(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_badge' => true,
            'channel_name' => $attributes['mode'].'-badge-tournaments',
        ]);
    }

    /**
     * Indicate that the channel is for general (non-badge) tournaments.
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_badge' => false,
            'channel_name' => $attributes['mode'].'-general-tournaments',
        ]);
    }

    /**
     * Indicate that the channel is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the mode for the channel.
     */
    public function mode(string $mode): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => $mode,
        ]);
    }
}
