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
        Schema::create('tournament_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('placement')->comment('1st, 2nd, 3rd, etc.');
            $table->string('username')->nullable();
            $table->unsignedBigInteger('osu_id')->nullable();
            $table->json('metadata')->nullable()->comment('Additional winner data from tcomm');
            $table->timestamps();

            $table->index(['tournament_id', 'placement']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_winners');
    }
};
