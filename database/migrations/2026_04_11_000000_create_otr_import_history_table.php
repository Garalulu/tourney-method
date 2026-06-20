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
        Schema::create('otr_import_history', function (Blueprint $table) {
            $table->id();
            $table->string('dump_version')->unique();
            $table->text('dump_url');
            $table->timestamp('imported_at')->default(now());
            $table->integer('tournaments_imported')->default(0);
            $table->integer('tournaments_updated')->default(0);
            $table->integer('tournaments_linked')->default(0);
            $table->integer('tournaments_failed')->default(0);
            $table->integer('forum_parsed_count')->default(0);
            $table->integer('forum_host_found')->default(0);
            $table->integer('forum_staff_added')->default(0);
            $table->string('status')->default('completed');
            $table->text('error_message')->nullable();
            $table->string('sha256_checksum', 64)->nullable();
            $table->boolean('gpg_signature_verified')->default(false);
            $table->timestamps();

            $table->index('imported_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otr_import_history');
    }
};
