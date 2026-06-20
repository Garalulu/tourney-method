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
        Schema::table('tournament_parse_histories', function (Blueprint $table) {
            $table->timestamp('compacted_at')->nullable()->after('parsed_at');
            $table->index('compacted_at');
        });

        DB::statement('DROP INDEX IF EXISTS idx_users_osu_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_parse_histories', function (Blueprint $table) {
            $table->dropIndex(['compacted_at']);
            $table->dropColumn('compacted_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('osu_id', 'idx_users_osu_id');
        });
    }
};
