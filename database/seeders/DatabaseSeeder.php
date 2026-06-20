<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Discord channels.
        $this->call([
            DiscordChannelSeeder::class,
        ]);
    }
}
