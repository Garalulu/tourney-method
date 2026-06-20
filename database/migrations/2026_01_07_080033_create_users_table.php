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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('osu_id')->unique();
            $table->string('username');
            $table->char('country_code', 2)->nullable();
            $table->string('main_mode', 10)->nullable();
            $table->string('role', 20)->default('player');
            $table->string('discord_webhook_url', 500)->nullable();
            $table->boolean('discord_webhook_valid')->default(true);
            $table->boolean('notify_registration')->default(true);
            $table->boolean('notify_stream')->default(true);
            $table->timestamp('osu_data_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('osu_id', 'idx_users_osu_id');
            $table->index('role', 'idx_users_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
