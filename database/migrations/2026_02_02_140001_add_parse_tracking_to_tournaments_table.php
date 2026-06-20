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
            $table->unsignedInteger('parse_count')->default(0)->after('import_source');
            $table->timestamp('last_parsed_at')->nullable()->after('parsed_at');
            $table->foreignId('last_parsed_by')->nullable()->constrained('users')->nullOnDelete()->after('last_parsed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['parse_count', 'last_parsed_at', 'last_parsed_by']);
        });
    }
};
