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
        // Check if country_code column already exists
        if (! Schema::hasColumn('users', 'country_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->char('country_code', 2)->nullable()->after('username');
                $table->index('country_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop if we actually created it (check for our specific index)
        if (Schema::hasIndex('users', 'users_country_code_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_country_code_index');
                $table->dropColumn('country_code');
            });
        }
    }
};
