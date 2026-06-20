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
        Schema::table('tournament_winners', function (Blueprint $table) {
            // Add gamemode tracking
            $table->string('gamemode', 10)->after('placement')->default('osu');

            // Add badge metadata from osu! API
            $table->string('badge_description')->nullable()->after('gamemode');
            $table->string('badge_image_url')->nullable()->after('badge_description');
            $table->string('badge_image_2x_url')->nullable()->after('badge_image_url');
            $table->timestamp('badge_awarded_at')->nullable()->after('badge_image_2x_url');

            // Add URL linking
            $table->string('badge_url')->nullable()->after('badge_awarded_at');

            // Add index for gamemode filtering
            $table->index(['tournament_id', 'gamemode'], 'idx_tournament_winners_gamemode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_winners', function (Blueprint $table) {
            $table->dropIndex('idx_tournament_winners_gamemode');
            $table->dropColumn([
                'gamemode',
                'badge_description',
                'badge_image_url',
                'badge_image_2x_url',
                'badge_awarded_at',
                'badge_url',
            ]);
        });
    }
};
