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
            // Make username nullable to allow historical imports from OTR
            $table->string('username')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_scores', function (Blueprint $table) {
            // Revert to NOT NULL (will fail if there are null values)
            $table->string('username')->nullable(false)->change();
        });
    }
};
