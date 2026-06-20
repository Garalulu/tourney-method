<?php

namespace Database\Factories;

use App\Models\DiscordServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordServer>
 */
class DiscordServerFactory extends Factory
{
    protected $model = DiscordServer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_name' => fake()->unique()->words(2, true).' server',
            'role_mappings' => [],
            'countries' => [],
            'send_unmatched_rank_alerts' => true,
        ];
    }
}
