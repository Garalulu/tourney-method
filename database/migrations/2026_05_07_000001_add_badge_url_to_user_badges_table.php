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
        Schema::table('user_badges', function (Blueprint $table) {
            $table->string('badge_url', 500)->nullable()->after('name');
            $table->string('image_2x_url', 500)->nullable()->after('image_url');

            $table->index(['user_id', 'badge_url'], 'idx_user_badges_user_badge_url');
            $table->index(['user_id', 'tournament_id'], 'idx_user_badges_user_tournament');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            $table->dropIndex('idx_user_badges_user_badge_url');
            $table->dropIndex('idx_user_badges_user_tournament');
            $table->dropColumn(['badge_url', 'image_2x_url']);
        });
    }
};
