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
        Schema::create('sip_fetch_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('osu_id');
            $table->string('username');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('sip')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('osu_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sip_fetch_queue');
    }
};
