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
        Schema::table('matches', function (Blueprint $table) {
            // Make submitted_by nullable to allow historical imports from OTR
            $table->foreignId('submitted_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Revert to NOT NULL (will fail if there are null values)
            $table->foreignId('submitted_by')->nullable(false)->change();
        });
    }
};
