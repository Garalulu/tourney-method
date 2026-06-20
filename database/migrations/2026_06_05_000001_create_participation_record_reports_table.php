<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participation_record_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_participation_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('category', 40);
            $table->text('explanation')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'participation_reports_status_created_index');
            $table->index('reported_by', 'participation_reports_reported_by_index');
            $table->unique(
                ['tournament_participation_record_id', 'reported_by', 'category', 'status'],
                'participation_reports_record_reporter_category_status_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_record_reports');
    }
};
