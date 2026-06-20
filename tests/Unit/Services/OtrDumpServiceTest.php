<?php

use App\Models\Tournament;
use App\Services\OtrDumpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear existing tournaments in test transaction
    Tournament::query()->delete();

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

    // Create test tournament records
    Tournament::create([
        'otr_id' => 12345,
        'title' => 'Existing Tournament',
        'forum_topic_id' => 67890,
        'status' => 'approved',
        'modes' => [['mode' => 'std', 'key_count' => null]],
    ]);

    Tournament::create([
        'otr_id' => null,
        'title' => 'Existing Tournament No OTR',
        'forum_topic_id' => 67890,
        'status' => 'approved',
        'modes' => [['mode' => 'std', 'key_count' => null]],
    ]);
});

test('getAvailableDumps returns available versions', function () {
    // Mock GCS listing response (XML format)
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult>
    <Contents><Key>otr-public-replica_2026_04_07_11_50_02.gz</Key></Contents>
    <Contents><Key>otr-public-replica_2026_04_14_11_50_03.gz</Key></Contents>
</ListBucketResult>';

    Http::fake([
        'https://storage.googleapis.com/otr-public-replica/' => Http::response($xml, 200),
    ]);

    $service = new OtrDumpService;
    $dumps = $service->getAvailableDumps();

    expect($dumps)->toHaveCount(2);
    expect($dumps[0])->toHaveKey('version');
    expect($dumps[0])->toHaveKey('url');
});

test('getPendingDumps returns only new dumps', function () {
    // Mock GCS listing (XML format)
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
        'imported_at' => now(),
    ]);

    $service = new OtrDumpService;
    $pending = $service->getPendingDumps();

    expect($pending)->toHaveCount(1);
    expect($pending[0]['version'])->toBe('2026_04_14_11_50_03');
});

test('extractTournaments parses COPY statement correctly', function () {
    $service = new OtrDumpService;

    // Create a test SQL dump with COPY statement (actual column structure)
    $sqlContent = "-- PostgreSQL dump
COPY public.tournaments (id, name, abbreviation, forum_url, rank_range_lower_bound, ruleset, lobby_size, verification_status, rejection_reason, submitted_by_user_id, verified_by_user_id, start_time, end_time, created, updated, is_lazer) FROM stdin;
1\tTest Tournament 1\tTT1\thttps://osu.ppy.sh/community/forums/topics/123456\t1000\t0\t16\t1\t\\N\t\\N\t\\N\t2024-01-01 12:00:00+00\t2024-01-02 18:00:00+00\t2024-01-01 00:00:00+00\t2024-01-02 00:00:00+00\tf
2\tTest Tournament 2\tTT2\thttps://osu.ppy.sh/community/forums/topics/789012\t\\N\t1\t8\t1\t\\N\t\\N\t\\N\t2024-01-03 12:00:00+00\t2024-01-04 18:00:00+00\t2024-01-03 00:00:00+00\t2024-01-04 00:00:00+00\tf
\\.
";

    $dumpPath = storage_path('app/otr/extracted/tmp/test.sql');
    Storage::put('otr/extracted/tmp/test.sql', $sqlContent);

    $tournaments = $service->extractTournaments($dumpPath);

    expect($tournaments)->toHaveCount(2);
    expect($tournaments[0]['id'])->toBe(1);
    expect($tournaments[0]['name'])->toBe('Test Tournament 1');
    expect($tournaments[1]['id'])->toBe(2);

    // Cleanup
    Storage::delete('otr/extracted/tmp/test.sql');
});

test('formatTournamentData converts OTR data to our format', function () {
    $otrData = [
        'id' => 12345,
        'name' => 'Test Tournament',
        'forum_url' => 'https://osu.ppy.sh/community/forums/topics/123456',
        'rank_range_lower_bound' => 1000,
        'ruleset' => 0,
        'lobby_size' => 16,
        'start_time' => '2024-01-01 12:00:00+00',
        'end_time' => '2024-01-02 18:00:00+00',
    ];

    $service = new OtrDumpService;
    $formatted = $service->formatTournamentData($otrData);

    expect($formatted['otr_id'])->toBe(12345);
    expect($formatted['title'])->toBe('Test Tournament');
    expect($formatted['forum_topic_id'])->toBe(123456);
    expect($formatted['rank_range_min'])->toBe(1000);
    expect($formatted['modes'])->toBe([['mode' => 'osu', 'key_count' => null]]);
    expect($formatted['vs_size'])->toBe(16);
    expect($formatted['tournament_start'])->toBe('2024-01-01 12:00:00+00');
    expect($formatted['tournament_end'])->toBe('2024-01-02 18:00:00+00');
});

test('importTournament skips existing tournament with same otr id', function () {
    $otrData = [
        'id' => 12345,
        'name' => 'Updated Tournament',
        'forum_url' => 'https://osu.ppy.sh/community/forums/topics/123456',
        'rank_range_lower_bound' => 2000,
        'ruleset' => 0,
        'lobby_size' => 16,
        'start_time' => '2024-01-01 12:00:00+00',
        'end_time' => '2024-01-02 18:00:00+00',
    ];

    $service = new OtrDumpService;
    $result = $service->importTournament($otrData, 'test-version');

    expect($result['action'])->toBe('ignored_duplicate');
    expect($result['tournament']->title)->toBe('Existing Tournament');
    expect($result['tournament']->rank_range_min)->toBeNull();
});

test('importTournament links to existing tournament', function () {
    // Check that our test data is correct
    $existing = Tournament::where('forum_topic_id', 67890)->whereNull('otr_id')->first();
    expect($existing)->not->toBeNull();
    expect($existing->title)->toBe('Existing Tournament No OTR');

    $otrData = [
        'id' => 54321,
        'name' => 'New Tournament',
        'forum_url' => 'https://osu.ppy.sh/community/forums/topics/67890',
        'rank_range_lower_bound' => 1500,
        'ruleset' => 1,
        'lobby_size' => 8,
        'start_time' => '2024-01-01 12:00:00+00',
        'end_time' => '2024-01-02 18:00:00+00',
    ];

    $service = new OtrDumpService;
    $result = $service->importTournament($otrData, 'test-version');

    expect($result['action'])->toBe('linked');
    expect($result['tournament']->otr_id)->toBe(54321);
    expect($result['tournament']->title)->toBe('Existing Tournament No OTR');
});

test('importTournament creates new tournament', function () {
    $otrData = [
        'id' => 99999,
        'name' => 'Brand New Tournament',
        'forum_url' => 'https://osu.ppy.sh/community/forums/topics/111111',
        'rank_range_lower_bound' => 500,
        'ruleset' => 2,
        'lobby_size' => 12,
        'start_time' => '2024-01-01 12:00:00+00',
        'end_time' => '2024-01-02 18:00:00+00',
    ];

    $service = new OtrDumpService;
    $result = $service->importTournament($otrData, 'test-version');

    expect($result['action'])->toBe('created');
    expect($result['tournament']->otr_id)->toBe(99999);
    expect($result['tournament']->title)->toBe('Brand New Tournament');
    expect($result['tournament']->forum_topic_id)->toBe(111111);
});

test('extractForumTopicId extracts topic ID from URL', function () {
    $service = new OtrDumpService;

    expect($service->extractForumTopicId('https://osu.ppy.sh/community/forums/topics/123456'))->toBe(123456);
    expect($service->extractForumTopicId('https://osu.ppy.sh/community/forums/topics/123456?n=1'))->toBe(123456);
    expect($service->extractForumTopicId(null))->toBeNull();
    expect($service->extractForumTopicId(''))->toBeNull();
});
