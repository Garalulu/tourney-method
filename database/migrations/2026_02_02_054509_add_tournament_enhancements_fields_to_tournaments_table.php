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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->integer('vs_size')->nullable()->after('team_size_max');
            $table->decimal('star_rating_first', 5, 2)->nullable()->after('star_rating_max');
            $table->decimal('star_rating_last', 5, 2)->nullable()->after('star_rating_first');
            $table->decimal('star_rating_qualifier', 5, 2)->nullable()->after('star_rating_last');
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['vs_size', 'star_rating_first', 'star_rating_last', 'star_rating_qualifier']);
            $table->dropSoftDeletes();
        });
    }
};
