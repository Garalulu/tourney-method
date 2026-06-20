<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('otr_import_history_table_is_created', function () {
    // Assert the table exists
    expect(Schema::hasTable('otr_import_history'))->toBeTrue();

    // Assert the table has the expected columns
    $columns = Schema::getColumnListing('otr_import_history');
    $expectedColumns = [
        'id', 'dump_version', 'dump_url', 'imported_at',
        'tournaments_imported', 'tournaments_updated', 'tournaments_linked',
        'tournaments_failed', 'forum_parsed_count', 'forum_host_found',
        'forum_staff_added', 'status', 'error_message', 'sha256_checksum',
        'gpg_signature_verified', 'created_at', 'updated_at',
    ];

    foreach ($expectedColumns as $column) {
        expect($columns)->toContain($column);
    }
});

test('tournaments_table_has_new_columns', function () {
    // Assert the tournaments table has the new columns
    expect(Schema::hasColumn('tournaments', 'import_batch_id'))->toBeTrue();
    expect(Schema::hasColumn('tournaments', 'otr_deleted_at'))->toBeTrue();

    // Assert the foreign key constraint exists
    $foreignKeys = DB::select("SELECT
        tc.constraint_name,
        tc.table_name,
        kcu.column_name,
        ccu.table_name AS foreign_table_name,
        ccu.column_name AS foreign_column_name
    FROM information_schema.table_constraints AS tc
    JOIN information_schema.key_column_usage AS kcu
        ON tc.constraint_name = kcu.constraint_name
        AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage AS ccu
        ON ccu.constraint_name = tc.constraint_name
        AND ccu.table_schema = tc.table_schema
    WHERE tc.constraint_type = 'FOREIGN KEY'
    AND tc.table_name = 'tournaments'");

    expect($foreignKeys)->not->toBeEmpty('Foreign key constraint should exist');

    // Find the specific foreign key to import_batch_id
    $importBatchForeignKey = collect($foreignKeys)->first(function ($fk) {
        return str_contains($fk->constraint_name, 'import_batch');
    });

    expect($importBatchForeignKey)->not->toBeNull('Foreign key to otr_import_history should exist');
    expect($importBatchForeignKey->foreign_table_name)->toBe('otr_import_history');
});

test('indexes_are_created', function () {
    // Check for unique index on dump_version
    $indexes = DB::select("SELECT indexname
        FROM pg_indexes
        WHERE tablename = 'otr_import_history'
        AND indexname LIKE '%dump_version%'");
    expect($indexes)->not->toBeEmpty('Unique index on dump_version should exist');

    // Check for index on imported_at
    $indexes = DB::select("SELECT indexname
        FROM pg_indexes
        WHERE tablename = 'otr_import_history'
        AND indexname LIKE '%imported_at%'");
    expect($indexes)->not->toBeEmpty('Index on imported_at should exist');

    // Check for index on tournaments.import_batch_id
    $indexes = DB::select("SELECT indexname
        FROM pg_indexes
        WHERE tablename = 'tournaments'
        AND indexname LIKE '%import_batch%'");
    expect($indexes)->not->toBeEmpty('Index on import_batch_id should exist');
});
