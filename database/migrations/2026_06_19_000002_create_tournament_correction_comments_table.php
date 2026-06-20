<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_correction_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_correction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['tournament_correction_id', 'created_at'], 'correction_comments_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_correction_comments');
    }
};
