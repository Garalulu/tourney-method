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
        // Drop unique index to allow multiple tournaments with same forum topic
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropUnique('idx_tournaments_forum_topic');
        });

        // Recreate as non-unique index for query performance
        Schema::table('tournaments', function (Blueprint $table) {
            $table->index('forum_topic_id', 'idx_tournaments_forum_topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse: drop non-unique index and recreate unique constraint
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex('idx_tournaments_forum_topic');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->unique('forum_topic_id', 'idx_tournaments_forum_topic');
        });
    }
};
