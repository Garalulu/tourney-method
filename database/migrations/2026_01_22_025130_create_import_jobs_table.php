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
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['tcomm', 'otr', 'combined'])->notNull();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('tournaments_imported')->default(0);
            $table->integer('tournaments_updated')->default(0);
            $table->integer('tournaments_failed')->default(0);
            $table->integer('matches_imported')->default(0);
            $table->integer('matches_failed')->default(0);
            $table->string('last_cursor', 500)->nullable();
            $table->jsonb('error_log')->nullable();
            $table->timestamps();

            $table->index(['source', 'status'], 'idx_import_jobs_source_status');
            $table->index('created_at', 'idx_import_jobs_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
