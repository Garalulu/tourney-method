<?php

use App\Services\OtrDumpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
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
});

/**
 * @return array{content: string, checksum: string}
 */
function setupHttpMocksForVersion(string $version, string $content = 'test content'): array
{
    $actualChecksum = hash('sha256', $content);
    $checksumResponse = "{$actualChecksum}  otr-public-replica_{$version}.gz\n";

    Http::fake([
        "https://storage.googleapis.com/otr-public-replica/otr-public-replica_{$version}.gz" => Http::response($content, 200),
        "https://storage.googleapis.com/otr-public-replica/otr-public-replica_{$version}.gz.sha256" => Http::response($checksumResponse),
        "https://storage.googleapis.com/otr-public-replica/otr-public-replica_{$version}.gz.sig" => Http::response('signature content'),
        'https://storage.googleapis.com/otr-public-replica/otr-public-key.asc' => Http::response('public key content'),
    ]);

    return [
        'content' => $content,
        'checksum' => $actualChecksum,
    ];
}

test('downloadDump downloads file with correct size', function () {
    $version = '2026_04_11_12_00_00';
    $url = "https://storage.googleapis.com/otr-public-replica/otr-public-replica_{$version}.gz";
    $localPath = storage_path("app/otr/dumps/tmp/otr-public-replica_{$version}.gz");

    // Mock the HTTP response
    setupHttpMocksForVersion($version, 'fake dump content');

    // Create an instance of the service
    $service = new OtrDumpVerifier;

    // Test download
    $result = $service->downloadDump($url, $localPath);

    expect($result)->toBeTrue();
    expect(file_exists($localPath))->toBeTrue();
    expect(file_get_contents($localPath))->toBe('fake dump content');
});

test('downloadDump retries on failure', function () {
    $version = '2026_04_11_12_00_00';
    $url = "https://storage.googleapis.com/otr-public-replica/otr-public-replica_{$version}.gz";
    $localPath = storage_path("app/otr/dumps/tmp/otr-public-replica_{$version}.gz");

    // Mock the HTTP response to fail first 2 times, then succeed
    Http::fake([
        $url => Http::sequence()
            ->push('', 500)
            ->push('', 500)
            ->push('success', 200),
    ]);

    $service = new OtrDumpVerifier;
    $result = $service->downloadDump($url, $localPath);

    expect($result)->toBeTrue();
    expect(file_get_contents($localPath))->toBe('success');
});

test('verifySha256 passes with correct checksum', function () {
    $version = '2026_04_11_12_00_00';
    $dumpPath = storage_path('app/otr/dumps/tmp/test.dump');

    // Create test dump file
    file_put_contents($dumpPath, 'test content');

    // Setup mocks
    setupHttpMocksForVersion($version, 'test content');

    $service = new OtrDumpVerifier;

    // This should not throw an exception
    $service->verifySha256($dumpPath, $version);

    expect(true)->toBeTrue(); // If we reach here, test passes
});

test('verifySha256 fails with incorrect checksum', function () {
    $version = '2026_04_11_12_00_00';
    $dumpPath = storage_path('app/otr/dumps/tmp/test.dump');

    // Create test dump file
    file_put_contents($dumpPath, 'test content');

    // Setup mocks with wrong checksum
    $wrongChecksum = str_repeat('a', 64); // Wrong checksum
    Http::fake([
        "https://storage.googleapis.com/otr-public-replica/otr-public-replica_{$version}.gz.sha256" => Http::response("{$wrongChecksum}  otr-public-replica_{$version}.gz\n"),
    ]);

    $service = new OtrDumpVerifier;

    expect(function () use ($service, $dumpPath, $version) {
        $service->verifySha256($dumpPath, $version);
    })->toThrow(Exception::class, 'SHA256 mismatch');
});

test('extractDumpFile extracts gzip file', function () {
    $version = '2026_04_11_12_00_00';
    $dumpPath = storage_path("app/otr/dumps/tmp/otr-public-replica_{$version}.gz");
    $expectedPath = storage_path("app/otr/dumps/tmp/otr-public-replica_{$version}.sql");

    // Create a test gzip file
    $sqlContent = "COPY public.tournaments (id, name) FROM stdin;\n1\tTest Tournament\n\\.\n";
    $gzippedContent = gzencode($sqlContent);
    file_put_contents($dumpPath, $gzippedContent);

    $service = new OtrDumpVerifier;
    $result = $service->extractDumpFile($dumpPath);

    expect($result)->toBe($expectedPath);
    expect(file_exists($expectedPath))->toBeTrue();
    expect(file_get_contents($expectedPath))->toBe($sqlContent);
});

test('downloadAndVerify performs full verification pipeline', function () {
    $version = '2026_04_11_12_00_00';

    // Setup mocks
    setupHttpMocksForVersion($version);

    $service = new OtrDumpVerifier;

    expect(fn () => $service->downloadAndVerify($version))
        ->toThrow(Exception::class, 'GPG verification failed');
});
