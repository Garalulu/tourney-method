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
        // Fix mismatch: database has 'global_rank' but code expects 'rank'
        // Rename global_rank to rank to match migration and model expectations
        if (Schema::hasColumn('user_rank_history', 'global_rank') && ! Schema::hasColumn('user_rank_history', 'rank')) {
            Schema::table('user_rank_history', function (Blueprint $table) {
                $table->renameColumn('global_rank', 'rank');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse: rename rank back to global_rank
        if (Schema::hasColumn('user_rank_history', 'rank') && ! Schema::hasColumn('user_rank_history', 'global_rank')) {
            Schema::table('user_rank_history', function (Blueprint $table) {
                $table->renameColumn('rank', 'global_rank');
            });
        }
    }
};
