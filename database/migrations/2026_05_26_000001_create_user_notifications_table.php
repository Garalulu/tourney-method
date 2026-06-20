<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_participation_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40);
            $table->string('type', 60);
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->string('action_url', 500);
            $table->jsonb('data')->nullable();
            $table->string('dedupe_key', 160)->nullable()->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'dismissed_at', 'read_at', 'created_at'], 'user_notifications_user_state_index');
            $table->index(['category', 'type'], 'user_notifications_category_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
