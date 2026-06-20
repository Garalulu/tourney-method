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
        Schema::create('tournament_parse_batches', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('stage', 40)->default('created');
            $table->string('status', 40)->default('pending');
            $table->json('tournament_ids')->nullable();
            $table->json('user_mapping')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'stage']);
        });

        Schema::create('tournament_parse_staff_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_parse_batch_id')
                ->constrained('tournament_parse_batches')
                ->cascadeOnDelete();
            $table->foreignId('tournament_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->json('staff_payload');
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tournament_parse_batch_id', 'tournament_id'],
                'parse_staff_payload_batch_tournament_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_parse_staff_payloads');
        Schema::dropIfExists('tournament_parse_batches');
    }
};
