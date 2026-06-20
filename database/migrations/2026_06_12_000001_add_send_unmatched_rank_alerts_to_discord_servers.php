<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_servers', function (Blueprint $table) {
            $table->boolean('send_unmatched_rank_alerts')
                ->default(true)
                ->after('countries');
        });
    }

    public function down(): void
    {
        Schema::table('discord_servers', function (Blueprint $table) {
            $table->dropColumn('send_unmatched_rank_alerts');
        });
    }
};
