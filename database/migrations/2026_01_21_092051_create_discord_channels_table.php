<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discord_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name')->unique()->comment('Unique name for the channel');
            $table->string('webhook_url')->nullable()->comment('Discord webhook URL for posting');
            $table->enum('mode', ['osu', 'taiko', 'catch', 'mania'])->comment('Game mode for this channel');
            $table->boolean('is_badge')->default(true)->comment('Badge channel flag');
            $table->jsonb('role_mappings')->default('{}')->comment('Rank range to Discord role ID mapping');
            $table->boolean('is_active')->default(true)->comment('Channel enabled status');
            $table->timestamps();

            // Unique index for lookup by mode and badge status
            $table->unique(['mode', 'is_badge'], 'discord_channel_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_channels');
    }
};
