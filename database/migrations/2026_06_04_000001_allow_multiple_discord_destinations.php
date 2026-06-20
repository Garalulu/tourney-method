<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_channels', function (Blueprint $table) {
            $table->dropUnique('discord_channel_lookup');
            $table->index(['mode', 'is_badge', 'is_active'], 'discord_channel_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('discord_channels', function (Blueprint $table) {
            $table->dropIndex('discord_channel_lookup');
            $table->unique(['mode', 'is_badge'], 'discord_channel_lookup');
        });
    }
};
