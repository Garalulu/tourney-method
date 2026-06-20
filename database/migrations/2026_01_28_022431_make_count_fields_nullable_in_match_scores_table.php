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
        Schema::table('match_scores', function (Blueprint $table) {
            // Make count fields nullable to allow historical imports from OTR
            $table->integer('count_300')->nullable()->change();
            $table->integer('count_100')->nullable()->change();
            $table->integer('count_50')->nullable()->change();
            $table->integer('count_miss')->nullable()->change();
            $table->integer('count_geki')->nullable()->change();
            $table->integer('count_katu')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_scores', function (Blueprint $table) {
            // Revert to NOT NULL (will fail if there are null values)
            $table->integer('count_300')->nullable(false)->change();
            $table->integer('count_100')->nullable(false)->change();
            $table->integer('count_50')->nullable(false)->change();
            $table->integer('count_miss')->nullable(false)->change();
            $table->integer('count_geki')->nullable(false)->change();
            $table->integer('count_katu')->nullable(false)->change();
        });
    }
};
