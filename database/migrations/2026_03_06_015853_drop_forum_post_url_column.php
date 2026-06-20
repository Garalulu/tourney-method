<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the forum_post_url column (redundant, will use accessor)
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('forum_post_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add column for rollback
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('forum_post_url', 500)->nullable();
        });

        // Backfill from topic IDs (for rollback safety)
        DB::statement("
            UPDATE tournaments
            SET forum_post_url = 'https://osu.ppy.sh/community/forums/topics/' || forum_topic_id
            WHERE forum_topic_id IS NOT NULL
        ");
    }
};
