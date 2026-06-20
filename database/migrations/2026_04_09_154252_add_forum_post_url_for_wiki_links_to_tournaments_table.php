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
        Schema::table('tournaments', function (Blueprint $table) {
            // Re-add forum_post_url column to store wiki URLs
            // This allows official tournaments (OWC, MWC, etc.) to store wiki page URLs
            // for badge matching when forum_topic_id is not available
            $table->string('forum_post_url', 500)->nullable()->after('forum_topic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('forum_post_url');
        });
    }
};
