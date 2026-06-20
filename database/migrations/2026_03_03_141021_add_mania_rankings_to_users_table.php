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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('rank_mania_4k')->nullable()->after('rank_mania');
            $table->unsignedInteger('rank_mania_7k')->nullable()->after('rank_mania_4k');
            $table->index('rank_mania_4k');
            $table->index('rank_mania_7k');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['users_rank_mania_4k_index', 'users_rank_mania_7k_index']);
            $table->dropColumn(['rank_mania_4k', 'rank_mania_7k']);
        });
    }
};
