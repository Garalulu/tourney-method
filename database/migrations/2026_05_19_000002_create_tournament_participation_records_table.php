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
        Schema::create('tournament_participation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('manual');
            $table->string('review_status', 20)->default('pending');
            $table->string('selection_outcome', 40)->nullable();
            $table->string('final_result', 30)->nullable();
            $table->string('stage_type', 30)->nullable();
            $table->string('stage_name', 100)->nullable();
            $table->string('round_label', 100)->nullable();
            $table->string('bracket_path', 30)->nullable();
            $table->unsignedInteger('placement')->nullable();
            $table->unsignedInteger('seed')->nullable();
            $table->string('team_name', 150)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tournament_id'], 'participation_records_user_tournament_unique');
            $table->index(['tournament_id', 'review_status'], 'participation_records_tournament_review_index');
            $table->index(['user_id', 'final_result'], 'participation_records_user_result_index');
            $table->index('selection_outcome', 'participation_records_selection_outcome_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_participation_records');
    }
};
