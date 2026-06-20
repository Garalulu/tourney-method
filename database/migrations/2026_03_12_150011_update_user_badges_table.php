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
            // Drop old gamemode column
            $table->dropColumn('gamemode');

            // Add proper foreign key constraint
            $table->foreign('tournament_id')
                ->references('id')
                ->on('tournaments')
                ->nullOnDelete();

            // Add index for tournament badges query
            $table->index(['tournament_id', 'is_bws_eligible'], 'idx_user_badges_tournament_bws');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            // Restore gamemode column
            $table->string('gamemode')->nullable();

            // Drop foreign key and index
            $table->dropForeign(['tournament_id']);
            $table->dropIndex('idx_user_badges_tournament_bws');
        });
    }
};
