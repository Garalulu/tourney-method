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
        Schema::table('tournament_parse_histories', function (Blueprint $table) {
            $table->timestamp('parsed_at')->nullable()->after('changes');
            $table->string('parse_source')->default('forum')->after('parsed_at');
            $table->text('parse_notes')->nullable()->after('parse_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_parse_histories', function (Blueprint $table) {
            $table->dropColumn(['parsed_at', 'parse_source', 'parse_notes']);
        });
    }
};
