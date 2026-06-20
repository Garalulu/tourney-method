<?php

use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear existing tournaments and import history
    Tournament::query()->delete();
    DB::table('otr_import_history')->truncate();

    // Create test directories
    $dumpDir = storage_path('app/otr/dumps/tmp');
    $extractedDir = storage_path('app/otr/extracted/tmp');
    $tmpDir = storage_path('app/otr/tmp');

    if (! is_dir($dumpDir)) {
        mkdir($dumpDir, 0755, true);
    }
    if (! is_dir($extractedDir)) {
        mkdir($extractedDir, 0755, true);
    }
    if (! is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }

    // Mock Storage for file operations
    Storage::fake('otr');
});

test('command lists available dumps', function () {
    // Mock GCS listing response (XML format)
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult>
    <Contents><Key>otr-public-replica_2026_04_07_11_50_02.gz</Key></Contents>
    <Contents><Key>otr-public-replica_2026_04_14_11_50_03.gz</Key></Contents>
</ListBucketResult>';

    Http::fake([
        'https://storage.googleapis.com/otr-public-replica/' => Http::response($xml, 200),
    ]);

    $this->artisan('otr:import-dump')
        ->expectsOutput('Available OTR dumps:')
        ->expectsOutput('1. 2026_04_14_11_50_03 (2026-04-14 11:50:03)')
        ->expectsOutput('2. 2026_04_07_11_50_02 (2026-04-07 11:50:02)')
        ->assertExitCode(0);
});

test('command imports a specific dump', function () {
    // Mock GCS listing and file content (XML format)
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult>
    <Contents><Key>otr-public-replica_2026_04_07_11_50_02.gz</Key></Contents>
</ListBucketResult>';

    Http::fake([
        'https://storage.googleapis.com/otr-public-replica/' => Http::response($xml, 200),
        'https://storage.googleapis.com/otr-public-replica/otr-public-replica_2026_04_07_11_50_02.gz' => Http::response('mock gz content', 200),
    ]);

    // Create a test SQL dump with proper gzip compression (actual column structure)
    $sqlContent = "-- PostgreSQL dump
COPY public.tournaments (id, name, abbreviation, forum_url, rank_range_lower_bound, ruleset, lobby_size, verification_status, rejection_reason, submitted_by_user_id, verified_by_user_id, start_time, end_time, created, updated, is_lazer) FROM stdin;
1\tTest Tournament 1\tTT1\thttps://osu.ppy.sh/community/forums/topics/123456\t1000\t0\t16\t1\t\\N\t\\N\t\\N\t2024-01-01 12:00:00+00\t2024-01-02 18:00:00+00\t2024-01-01 00:00:00+00\t2024-01-02 00:00:00+00\tf
2\tTest Tournament 2\tTT2\thttps://osu.ppy.sh/community/forums/topics/789012\t\\N\t1\t8\t1\t\\N\t\\N\t\\N\t2024-01-03 12:00:00+00\t2024-01-04 18:00:00+00\t2024-01-03 00:00:00+00\t2024-01-04 00:00:00+00\tf
\\.
";

    // Create properly gzipped content
    $gzippedContent = gzencode($sqlContent);
    Storage::put('otr/dumps/tmp/dump_2026_04_07_11_50_02.gz', $gzippedContent);

    $this->artisan('otr:import-dump 2026_04_07_11_50_02')
        ->expectsOutput('Importing dump 2026_04_07_11_50_02...')
        ->expectsOutput('Successfully imported 2 tournaments')
        ->assertExitCode(0);

    // Verify tournaments were created
    $tournaments = Tournament::query()->count();
    expect($tournaments)->toBe(2);
});

test('command shows error for non-existent dump', function () {
    // Mock GCS listing response (XML format) with version that doesn't exist
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult>
    <Contents><Key>otr-public-replica_2026_04_07_11_50_02.gz</Key></Contents>
</ListBucketResult>';

    Http::fake([
        'https://storage.googleapis.com/otr-public-replica/' => Http::response($xml, 200),
    ]);

    $this->artisan('otr:import-dump 2026_04_14_11_50_03')
        ->expectsOutput('Error: Dump version 2026_04_14_11_50_03 not found')
        ->assertExitCode(1);
});

test('command handles import errors gracefully', function () {
    // Mock GCS to return error (XML format)
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult>
    <Contents><Key>otr-public-replica_2026_04_08_11_50_02.gz</Key></Contents>
</ListBucketResult>';

    Http::fake([
        'https://storage.googleapis.com/otr-public-replica/' => Http::response($xml, 200),
        'https://storage.googleapis.com/otr-public-replica/otr-public-replica_2026_04_08_11_50_02.gz' => Http::response('', 404),
    ]);

    $this->artisan('otr:import-dump 2026_04_08_11_50_02')
        ->expectsOutput('Error: Failed to download dump')
        ->assertExitCode(1);
});

test('command handles invalid COPY statement', function () {
    // Mock GCS listing and file content (XML format)
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult>
    <Contents><Key>otr-public-replica_2026_04_09_11_50_02.gz</Key></Contents>
</ListBucketResult>';

    Http::fake([
        'https://storage.googleapis.com/otr-public-replica/' => Http::response($xml, 200),
        'https://storage.googleapis.com/otr-public-replica/otr-public-replica_2026_04_09_11_50_02.gz' => Http::response('', 200),
    ]);

    // Create SQL dump with invalid COPY statement - gzipped
    $sqlContent = "-- PostgreSQL dump with invalid COPY
COPY public.tournaments (id, name, abbreviation, forum_url, rank_range_lower_bound, ruleset, lobby_size, verification_status, rejection_reason, submitted_by_user_id, verified_by_user_id, start_time, end_time, created, updated, is_lazer) FROM stdin;
-- Invalid data format
invalid\tdata\tformat\\.
";

    $gzippedContent = gzencode($sqlContent);
    Storage::put('otr/dumps/tmp/dump_2026_04_09_11_50_02.gz', $gzippedContent);

    $this->artisan('otr:import-dump 2026_04_09_11_50_02')
        ->expectsOutput('Importing dump 2026_04_09_11_50_02...')
        ->expectsOutput('Successfully imported 0 tournaments')
        ->assertExitCode(0);
});

test('command imports all pending dumps with --all flag', function () {
    // Mock GCS listing response (XML format) with some imported dumps
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult>
    <Contents><Key>otr-public-replica_2026_04_07_11_50_02.gz</Key></Contents>
    <Contents><Key>otr-public-replica_2026_04_14_11_50_03.gz</Key></Contents>
</ListBucketResult>';

    Http::fake([
        'https://storage.googleapis.com/otr-public-replica/' => Http::response($xml, 200),
    ]);

    // Create history record for one dump
    DB::table('otr_import_history')->insert([
        'dump_url' => 'https://storage.googleapis.com/otr-public-replica/otr-public-replica_2026_04_07_11_50_02.gz',
        'dump_version' => '2026_04_07_11_50_02',
        'status' => 'completed',
        'tournaments_imported' => 0,
        'tournaments_updated' => 0,
        'tournaments_linked' => 0,
        'tournaments_failed' => 0,
        'forum_parsed_count' => 0,
        'forum_host_found' => 0,
        'forum_staff_added' => 0,
        'imported_at' => now(),
    ]);

    // Create a test SQL dump for the pending dump - gzipped (actual column structure)
    $sqlContent = "-- PostgreSQL dump
COPY public.tournaments (id, name, abbreviation, forum_url, rank_range_lower_bound, ruleset, lobby_size, verification_status, rejection_reason, submitted_by_user_id, verified_by_user_id, start_time, end_time, created, updated, is_lazer) FROM stdin;
1\tTest Tournament\tTT\thttps://osu.ppy.sh/community/forums/topics/123456\t1000\t0\t16\t1\t\\N\t\\N\t\\N\t2024-01-01 12:00:00+00\t2024-01-02 18:00:00+00\t2024-01-01 00:00:00+00\t2024-01-02 00:00:00+00\tf
\\.
";

    $gzippedContent = gzencode($sqlContent);
    Storage::put('otr/dumps/tmp/dump_2026_04_14_11_50_03.gz', $gzippedContent);

    $this->artisan('otr:import-dump --all')
        ->expectsOutput('Importing 1 pending dump(s)...')
        ->expectsOutput('Dump 2026_04_14_11_50_03: completed - 1 tournaments imported, 0 skipped')
        ->assertExitCode(0);
});
