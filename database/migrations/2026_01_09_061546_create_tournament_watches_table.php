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
        Schema::create('tournament_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->enum('watch_type', ['interested', 'watching', 'stream']);
            $table->timestamps();

            // Unique constraint: user can only watch a tournament once
            $table->unique(['user_id', 'tournament_id']);

            // Indexes for efficient queries
            $table->index(['user_id', 'created_at']);
            $table->index(['tournament_id', 'watch_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_watches');
    }
};
