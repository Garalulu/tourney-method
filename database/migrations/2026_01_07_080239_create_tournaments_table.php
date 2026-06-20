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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->integer('forum_topic_id')->nullable();
            $table->string('forum_post_url', 500)->nullable();
            $table->string('title', 256);
            $table->text('description')->nullable();
            $table->integer('host_osu_id')->nullable();
            $table->string('host_username')->nullable();
            $table->string('status', 20)->default('pending_review');
            $table->json('modes'); // Array of game modes
            $table->integer('team_size_min')->nullable();
            $table->integer('team_size_max')->nullable();
            $table->timestamp('registration_start')->nullable();
            $table->timestamp('registration_end')->nullable();
            $table->timestamp('tournament_start')->nullable();
            $table->timestamp('tournament_end')->nullable();
            $table->integer('rank_range_min')->nullable();
            $table->integer('rank_range_max')->nullable();
            $table->boolean('is_badge')->default(false);
            $table->boolean('is_bws')->default(false);
            $table->decimal('bws_base_exponent', 6, 4)->default(0.9937)->nullable();
            $table->decimal('bws_badge_power', 4, 2)->default(2.0)->nullable();
            $table->integer('bws_badge_age_limit')->nullable();
            $table->decimal('star_rating_min', 4, 2)->nullable();
            $table->decimal('star_rating_max', 4, 2)->nullable();
            $table->string('format', 100)->nullable();
            $table->string('banner_url', 500)->nullable();
            $table->string('discord_url', 500)->nullable();
            $table->string('twitch_url', 500)->nullable();
            $table->string('spreadsheet_url', 500)->nullable();
            $table->string('bracket_url', 500)->nullable();
            $table->string('registration_url', 500)->nullable();
            $table->string('tcomm_url', 500)->nullable();
            $table->string('tcomm_id', 100)->nullable();
            $table->integer('otr_id')->nullable();
            $table->string('import_source', 20)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->unique('forum_topic_id', 'idx_tournaments_forum_topic');
            $table->unique('tcomm_id', 'idx_tournaments_tcomm_id');
            $table->unique('otr_id', 'idx_tournaments_otr_id');
            $table->index('status', 'idx_tournaments_status');
            $table->index(['registration_end', 'tournament_start'], 'idx_tournaments_dates');
            $table->index(['rank_range_min', 'rank_range_max'], 'idx_tournaments_rank_range');
            $table->index('import_source', 'idx_tournaments_import_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
