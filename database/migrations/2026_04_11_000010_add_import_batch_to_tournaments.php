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
            $table->integer('import_batch_id')->nullable()->after('import_source');
            $table->timestamp('otr_deleted_at')->nullable()->after('import_batch_id');

            // Add foreign key constraint
            $table->foreign('import_batch_id')
                ->references('id')
                ->on('otr_import_history')
                ->nullOnDelete();

            // Add index for performance
            $table->index('import_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropIndex(['import_batch_id']);
            $table->dropColumn(['import_batch_id', 'otr_deleted_at']);
        });
    }
};
