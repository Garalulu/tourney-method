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
        Schema::create('user_match_participation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->string('team')->nullable();
            $table->integer('games_played')->default(0);
            $table->integer('games_won')->default(0);
            $table->bigInteger('total_score')->default(0);
            $table->decimal('avg_accuracy', 6, 4)->default(0);
            $table->timestamps();

            // Unique index to prevent duplicate participation records
            $table->unique(['user_id', 'match_id']);

            // Indexes
            $table->index('user_id');
            $table->index('match_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_match_participation');
    }
};
