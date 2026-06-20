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
        Schema::create('notification_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->cascadeOnDelete();
            $table->string('type', 50); // notification_type: registration_reminder, tournament_start, stream_live
            $table->string('channel', 20); // discord_personal, discord_central
            $table->jsonb('payload'); // Notification content
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Indexes for queue processing
            $table->index(['status', 'scheduled_for'], 'idx_notification_queue_status');
            $table->index('user_id', 'idx_notification_queue_user');
            // Index for deduplication
            $table->index(['user_id', 'tournament_id', 'type'], 'idx_notification_queue_dedup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_queue');
    }
};
