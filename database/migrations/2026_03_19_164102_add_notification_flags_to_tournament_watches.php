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
        Schema::table('tournament_watches', function (Blueprint $table) {
            $table->boolean('notify_registration_close')->default(true)->after('watch_type');
            $table->boolean('notify_stream_live')->default(true)->after('notify_registration_close');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_watches', function (Blueprint $table) {
            $table->dropColumn(['notify_registration_close', 'notify_stream_live']);
        });
    }
};
