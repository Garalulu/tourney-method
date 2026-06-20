<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_maintenance_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 80);
            $table->string('label', 80);
            $table->string('source', 40)->default('cli');
            $table->string('status', 40)->default('running');
            $table->jsonb('options')->nullable();
            $table->jsonb('summary')->nullable();
            $table->jsonb('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['command', 'status']);
            $table->index('started_at');
        });

        Schema::create('admin_maintenance_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_maintenance_run_id')
                ->constrained('admin_maintenance_runs')
                ->cascadeOnDelete();
            $table->string('item_type', 40);
            $table->string('action', 40);
            $table->string('status', 40)->default('completed');
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tournament_title')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('osu_id')->nullable();
            $table->string('username')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['admin_maintenance_run_id', 'item_type', 'action'], 'maintenance_items_run_type_action_idx');
            $table->index(['tournament_id', 'action']);
            $table->index(['user_id', 'action']);
        });

        Schema::table('tournament_parse_batches', function (Blueprint $table) {
            $table->foreignId('admin_maintenance_run_id')
                ->nullable()
                ->constrained('admin_maintenance_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_parse_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_maintenance_run_id');
        });

        Schema::dropIfExists('admin_maintenance_run_items');
        Schema::dropIfExists('admin_maintenance_runs');
    }
};
