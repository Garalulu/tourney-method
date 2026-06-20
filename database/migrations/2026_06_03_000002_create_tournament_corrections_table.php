<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->jsonb('payload');
            $table->jsonb('current_snapshot');
            $table->jsonb('admin_decisions')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'status'], 'tournament_corrections_tournament_status_index');
            $table->index(['submitted_by', 'created_at'], 'tournament_corrections_submitter_created_index');
        });

        DB::statement("
            CREATE UNIQUE INDEX tournament_corrections_one_pending_per_tournament
            ON tournament_corrections (tournament_id)
            WHERE status = 'pending'
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_corrections');
    }
};
