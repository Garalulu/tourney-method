<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_corrections', function (Blueprint $table) {
            $table->unsignedBigInteger('tournament_id')->nullable()->change();
            $table->string('kind', 30)->default('correction')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_corrections', function (Blueprint $table) {
            $table->dropColumn('kind');
            $table->unsignedBigInteger('tournament_id')->nullable(false)->change();
        });
    }
};
