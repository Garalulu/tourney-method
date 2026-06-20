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
        // Add new column for absolute cutoff date
        Schema::table('tournaments', function (Blueprint $table) {
            $table->timestamp('bws_badge_age_cutoff')->nullable()->after('bws_badge_age_limit');
        });

        // Convert existing year-based limits to absolute dates
        $tournaments = DB::table('tournaments')
            ->whereNotNull('bws_badge_age_limit')
            ->get();

        foreach ($tournaments as $tournament) {
            // Convert years to absolute date (e.g., 2 years -> 2022-01-01)
            $cutoffDate = now()->subYears($tournament->bws_badge_age_limit)->startOfYear();

            DB::table('tournaments')
                ->where('id', $tournament->id)
                ->update(['bws_badge_age_cutoff' => $cutoffDate]);
        }

        // Drop old integer column
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('bws_badge_age_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert: Add back integer column
        Schema::table('tournaments', function (Blueprint $table) {
            $table->integer('bws_badge_age_limit')->nullable()->after('bws_badge_power');
        });

        // Convert absolute dates back to years (lossy conversion)
        $tournaments = DB::table('tournaments')
            ->whereNotNull('bws_badge_age_cutoff')
            ->get();

        foreach ($tournaments as $tournament) {
            $years = now()->diffInYears($tournament->bws_badge_age_cutoff);
            DB::table('tournaments')
                ->where('id', $tournament->id)
                ->update(['bws_badge_age_limit' => $years]);
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('bws_badge_age_cutoff');
        });
    }
};
