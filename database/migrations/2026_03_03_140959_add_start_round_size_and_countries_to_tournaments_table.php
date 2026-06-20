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
            $table->unsignedInteger('start_round_size')->nullable()->after('vs_size');
            $table->json('restricted_countries')->nullable()->after('start_round_size');
            $table->index('start_round_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex('tournaments_start_round_size_index');
            $table->dropColumn(['start_round_size', 'restricted_countries']);
        });
    }
};
