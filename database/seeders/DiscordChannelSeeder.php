<?php

namespace Database\Seeders;

use App\Models\DiscordChannel;
use App\Models\DiscordServer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class DiscordChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example role mappings - these should be configured from actual Discord server role IDs
        // The values here are Discord snowflake IDs (role IDs)
        // Get webhook URLs from config or environment variables
        // These are configured in config/services.php and .env
        $webhooks = Config::get('services.discord.central_webhooks', []);

        $server = DiscordServer::query()->firstOrCreate(
            ['server_name' => 'Default Discord Server'],
            ['role_mappings' => []]
        );

        // Create badge tournament channels for each mode
        $modes = ['osu', 'taiko', 'catch', 'mania'];

        foreach ($modes as $mode) {
            DiscordChannel::updateOrCreate(
                [
                    'channel_name' => "{$mode}-badge-tournaments",
                ],
                [
                    'discord_server_id' => $server->id,
                    'webhook_url' => $webhooks["{$mode}_badge"] ?? null,
                    'mode' => $mode,
                    'is_badge' => true,
                    'role_mappings' => [],
                    'is_active' => ! empty($webhooks["{$mode}_badge"]),
                ]
            );

            // Also create non-badge tournament channels
            DiscordChannel::updateOrCreate(
                [
                    'channel_name' => "{$mode}-nonbadge-tournaments",
                ],
                [
                    'discord_server_id' => $server->id,
                    'webhook_url' => $webhooks["{$mode}_nonbadge"] ?? null,
                    'mode' => $mode,
                    'is_badge' => false,
                    'role_mappings' => [],
                    'is_active' => ! empty($webhooks["{$mode}_nonbadge"]),
                ]
            );
        }

        $this->command->info('Discord channels seeded successfully.');
        $this->command->newLine();
        $this->command->comment('To configure webhook URLs, set them in config/services.php or .env:');
        $this->command->comment('DISCORD_STD_BADGE_WEBHOOK=https://discord.com/api/webhooks/... ');
        $this->command->comment('DISCORD_TAIKO_BADGE_WEBHOOK=https://discord.com/api/webhooks/...');
        $this->command->comment('etc.');
    }
}
