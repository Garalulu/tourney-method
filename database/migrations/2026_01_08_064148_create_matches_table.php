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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('osu_match_id')->unique();
            $table->string('name');
            $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('tournament_id');
            $table->index('status');
            $table->index('submitted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
