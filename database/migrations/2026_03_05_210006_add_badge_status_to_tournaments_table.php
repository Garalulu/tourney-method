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
            // Track badge approval status from tcomm
            $table->enum('badge_status', ['approved', 'rejected', 'pending'])
                ->nullable()
                ->after('is_badge')
                ->comment('Badge approval status from tcomm (null = not a badge tournament)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('badge_status');
        });
    }
};
