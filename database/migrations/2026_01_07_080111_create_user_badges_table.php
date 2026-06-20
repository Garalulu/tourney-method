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
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('image_url', 500);
            $table->timestamp('awarded_at');
            $table->boolean('is_bws_eligible')->default(true);
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('user_id', 'idx_user_badges_user_id');
            $table->index(['user_id', 'is_bws_eligible'], 'idx_user_badges_bws_eligible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
