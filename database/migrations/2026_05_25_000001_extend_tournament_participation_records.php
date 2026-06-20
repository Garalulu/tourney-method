<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_participation_records', function (Blueprint $table) {
            $table->text('memo')->nullable()->after('team_name');
            $table->jsonb('matches')->nullable()->after('memo');
            $table->jsonb('pending_teammate_osu_ids')->nullable()->after('matches');
            $table->unsignedInteger('placement_min')->nullable()->after('placement');
            $table->unsignedInteger('placement_max')->nullable()->after('placement_min');
            $table->unsignedInteger('placement_override')->nullable()->after('placement_max');
            $table->timestamp('user_input_deleted_at')->nullable()->after('metadata');
            $table->foreignId('user_input_deleted_by')->nullable()->after('user_input_deleted_at')->constrained('users')->nullOnDelete();
            $table->text('user_input_deleted_reason')->nullable()->after('user_input_deleted_by');
        });

        Schema::create('participation_record_teammates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_participation_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tournament_participation_record_id', 'user_id'], 'participation_teammates_record_user_unique');
            $table->index('user_id', 'participation_teammates_user_index');
        });

        Schema::create('participation_input_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_participation_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->jsonb('changed_fields')->nullable();
            $table->boolean('flagged')->default(false);
            $table->string('flag_reason', 120)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'participation_input_logs_user_created_index');
            $table->index(['flagged', 'created_at'], 'participation_input_logs_flagged_created_index');
        });

        Schema::create('participation_input_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_input_locks');
        Schema::dropIfExists('participation_input_logs');
        Schema::dropIfExists('participation_record_teammates');

        Schema::table('tournament_participation_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_input_deleted_by');
            $table->dropColumn([
                'memo',
                'matches',
                'pending_teammate_osu_ids',
                'placement_min',
                'placement_max',
                'placement_override',
                'user_input_deleted_at',
                'user_input_deleted_reason',
            ]);
        });
    }
};
