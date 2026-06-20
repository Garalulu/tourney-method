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
        Schema::create('tournament_parse_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('parsed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes')->nullable();
            $table->json('parsed_data')->nullable();
            $table->timestamps();

            $table->index('tournament_id');
            $table->index('parsed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_parse_histories');
    }
};
