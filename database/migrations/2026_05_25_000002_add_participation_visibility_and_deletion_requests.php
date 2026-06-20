<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_participation_records', function (Blueprint $table) {
            $table->timestamp('profile_hidden_at')->nullable()->after('user_input_deleted_reason');
            $table->foreignId('profile_hidden_by')->nullable()->after('profile_hidden_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('participation_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_participation_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'participation_delete_requests_status_created_index');
            $table->unique(
                ['tournament_participation_record_id', 'requested_by', 'status'],
                'participation_delete_requests_record_user_status_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_deletion_requests');

        Schema::table('tournament_participation_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_hidden_by');
            $table->dropColumn('profile_hidden_at');
        });
    }
};
