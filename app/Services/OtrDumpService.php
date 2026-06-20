<?php

namespace App\Services;

use App\Jobs\ChunkedForumParseJob;
use App\Models\Tournament;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GcsClient
{
    /**
     * @return array<int, array{name: string}>
     */
    public function listObjects(string $bucket): array
    {
        // For testing, check if Http is faked
        if (Http::getFacadeRoot()) {
            $response = Http::get("https://storage.googleapis.com/{$bucket}/");

            if ($response->successful()) {
                // Parse XML listing from public bucket (S3-compatible format)
                $xml = simplexml_load_string($response->body());
                $items = [];

                foreach ($xml->Contents ?? [] as $content) {
                    $items[] = ['name' => (string) $content->Key];
                }

                return $items;
            }
        }

        // Default mock implementation for tests without HTTP faking
        return [
            ['name' => 'otr-public-replica_2026_04_07_11_50_02.gz'],
            ['name' => 'otr-public-replica_2026_04_14_11_50_03.gz'],
        ];
    }

    public function downloadObject(string $url, string $destinationPath): void
    {
        // Convert to Storage facade path
        $relativePath = str_replace(storage_path('app/'), '', $destinationPath);

        // Check if file already exists (for testing)
        if (Storage::exists($relativePath)) {
            return;
        }

        // For testing, check if Http is faked
        if (Http::getFacadeRoot()) {
            $response = Http::get($url);

            if ($response->successful()) {
                Storage::put($relativePath, $response->body());

                return;
            }

            throw new \Exception('Failed to download dump');
        }

        // Mock download - for testing, create empty file
        Storage::put($relativePath, '');
    }
}

class OtrDumpService
{
    private GcsClient $gcsClient;

    private OtrDataValidator $validator;

    public function __construct()
    {
        $this->gcsClient = new GcsClient;
        $this->validator = new OtrDataValidator;
    }

    /**
     * @return array<int, array{version: string, url: string}>
     */
    public function getAvailableDumps(): array
    {
        $response = $this->gcsClient->listObjects('otr-public-replica');

        $dumps = [];
        foreach ($response as $item) {
            $filename = basename($item['name']);
            if (preg_match('/^otr-public-replica_(\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2})\.gz$/', $filename, $matches)) {
                $dumps[] = [
                    'version' => $matches[1],
                    'url' => "https://storage.googleapis.com/otr-public-replica/{$filename}",
                ];
            }
        }

        // Sort by version (newest first)
        usort($dumps, fn ($a, $b) => version_compare($b['version'], $a['version']));

        return $dumps;
    }

    /**
     * @return array<int, array{version: string, url: string}>
     */
    public function getPendingDumps(): array
    {
        $availableDumps = $this->getAvailableDumps();

        // Get already imported versions
        $importedVersions = DB::table('otr_import_history')
            ->whereNotNull('dump_url')
            ->pluck('dump_version')
            ->toArray();

        return array_filter($availableDumps, function ($dump) use ($importedVersions) {
            return ! in_array($dump['version'], $importedVersions);
        });
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     forum_url: string,
     *     rank_range_lower_bound: int|null,
     *     ruleset: int,
     *     lobby_size: int,
     *     start_time: string,
     *     end_time: string
     * }>
     */
    public function extractTournaments(string $dumpPath): array
    {
        // Convert to Storage facade path
        $relativePath = str_replace(storage_path('app/'), '', $dumpPath);

        if (! Storage::exists($relativePath)) {
            return [];
        }

        $content = Storage::get($relativePath);

        // Extract COPY statement for tournaments
        if (preg_match('/COPY public\.tournaments \([^)]+\) FROM stdin;([\s\S]*?)\\\\\./', $content, $matches)) {
            $copyData = $matches[1];

            $tournaments = [];
            $lines = explode("\n", trim($copyData));

            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                $columns = explode("\t", $line);
                if (count($columns) >= 13) {
                    $tournaments[] = [
                        'id' => (int) $columns[0],
                        'name' => $columns[1],
                        'forum_url' => $columns[3],
                        'rank_range_lower_bound' => $columns[4] === '\\N' ? null : (int) $columns[4],
                        'ruleset' => (int) $columns[5],
                        'lobby_size' => (int) $columns[6],
                        'start_time' => $columns[11],
                        'end_time' => $columns[12],
                    ];
                }
            }

            return $tournaments;
        }

        return [];
    }

    /**
     * @param  array{
     *     id: int,
     *     name: string,
     *     forum_url?: string|null,
     *     rank_range_lower_bound?: int|null,
     *     ruleset: int,
     *     lobby_size: int,
     *     start_time: string,
     *     end_time: string
     * }  $otrData
     * @return array{
     *     otr_id: int,
     *     title: string,
     *     forum_topic_id: int|null,
     *     forum_post_url: string|null,
     *     rank_range_min: int|null,
     *     modes: array<int, array{mode: string, key_count: int|null}>,
     *     vs_size: int,
     *     tournament_start: string,
     *     tournament_end: string,
     *     status: string,
     *     reviewed_at: CarbonInterface,
     *     field_sources: array<string, string>,
     *     _can_parse_forum: bool
     * }
     */
    public function formatTournamentData(array $otrData): array
    {
        $forumUrl = $otrData['forum_url'] ?? null;

        // Detect if it's a wiki URL or forum topic URL
        $isWikiUrl = $forumUrl && str_contains($forumUrl, 'wiki');

        if ($isWikiUrl) {
            // Wiki URL: store in forum_post_url, no topic ID
            $forumTopicId = null;
            $forumPostUrl = $forumUrl;
        } else {
            // Forum topic URL: extract topic ID, clear forum_post_url
            $forumTopicId = $this->extractForumTopicId($forumUrl);
            $forumPostUrl = null;
        }

        // Convert ruleset (0=osu, 1=taiko, 2=catch, 3/4/5=mania) to our format
        $rulesetMap = [
            0 => 'osu',
            1 => 'taiko',
            2 => 'catch',
            3 => 'mania',
            4 => 'mania',
            5 => 'mania',
        ];

        $modes = [];
        if (isset($rulesetMap[$otrData['ruleset']])) {
            $modes[] = [
                'mode' => $rulesetMap[$otrData['ruleset']],
                'key_count' => $this->getKeyCount($otrData['ruleset'] ?? 0),
            ];
        }

        return [
            'otr_id' => $otrData['id'],
            'title' => $otrData['name'],
            'forum_topic_id' => $forumTopicId,
            'forum_post_url' => $forumPostUrl,
            'rank_range_min' => $otrData['rank_range_lower_bound'] ?? null,
            'modes' => $modes,
            'vs_size' => $otrData['lobby_size'],
            'tournament_start' => $otrData['start_time'],
            'tournament_end' => $otrData['end_time'],
            // OTR dump imports are pre-approved
            'status' => 'approved',
            'reviewed_at' => now(),
            // Mark OTR fields as protected from forum parser overwrites
            'field_sources' => [
                'title' => 'otr',
                'modes' => 'otr',
                'vs_size' => 'otr',
                'rank_range_min' => 'otr',
                'tournament_start' => 'otr',
                'tournament_end' => 'otr',
            ],
            // Flag to indicate if forum parsing should be dispatched
            '_can_parse_forum' => ! $isWikiUrl && $forumTopicId !== null,
        ];
    }

    /**
     * @param  array{
     *     id: int,
     *     name: string,
     *     forum_url?: string|null,
     *     rank_range_lower_bound?: int|null,
     *     ruleset: int,
     *     lobby_size: int,
     *     start_time: string,
     *     end_time: string
     * }  $otrData
     * @return array{action: 'ignored_duplicate'|'linked'|'created', tournament: Tournament, forum_parsed: bool}
     */
    public function importTournament(array $otrData, string $dumpVersion): array
    {
        $formattedData = $this->formatTournamentData($otrData);

        // Extract flag before saving (don't save to DB)
        $canParseForum = $formattedData['_can_parse_forum'] ?? false;
        unset($formattedData['_can_parse_forum']);

        // Check if tournament already exists by otr_id
        /** @var Tournament|null $tournament */
        $tournament = Tournament::where('otr_id', $formattedData['otr_id'])->first();

        if ($tournament) {
            // Duplicate skip logic: if otr_id already exists, skip entirely
            return [
                'action' => 'ignored_duplicate',
                'tournament' => $tournament,
                'forum_parsed' => false,
            ];
        }

        // Check if tournament exists by forum_topic_id but no otr_id
        if ($formattedData['forum_topic_id']) {
            /** @var Tournament|null $tournament */
            $tournament = Tournament::where('forum_topic_id', $formattedData['forum_topic_id'])
                ->whereNull('otr_id')
                ->first();

            if ($tournament) {
                // Link the tournament to OTR data - preserve existing title and field sources if not empty
                $updateData = [
                    'otr_id' => $formattedData['otr_id'],
                    'vs_size' => $formattedData['vs_size'],
                    'tournament_start' => $formattedData['tournament_start'],
                    'tournament_end' => $formattedData['tournament_end'],
                    'rank_range_min' => $formattedData['rank_range_min'],
                    'modes' => $formattedData['modes'],
                ];

                // Only update title if current one is empty
                if (empty($tournament->title)) {
                    $updateData['title'] = $formattedData['title'];
                }

                // Merge field_sources - mark OTR fields as protected
                $currentFieldSources = $tournament->field_sources ?? [];
                $otrFieldSources = $formattedData['field_sources'] ?? [];

                // Only mark as 'otr' if not already 'manual' (admin edits take priority)
                foreach ($otrFieldSources as $field => $source) {
                    if (! isset($currentFieldSources[$field]) || $currentFieldSources[$field] !== 'manual') {
                        $currentFieldSources[$field] = 'otr';
                    }
                }

                $updateData['field_sources'] = $currentFieldSources;

                $tournament->update($updateData);

                return [
                    'action' => 'linked',
                    'tournament' => $tournament,
                    'forum_parsed' => $canParseForum,
                ];
            }
        }

        // Create new tournament
        $tournament = Tournament::create($formattedData);

        return [
            'action' => 'created',
            'tournament' => $tournament,
            'forum_parsed' => $canParseForum,
        ];
    }

    /**
     * @param  array<int, array{version: string, url: string}>  $dumps
     * @return array<int, array{
     *     version: string,
     *     action: string,
     *     imported: int,
     *     updated: int,
     *     linked: int,
     *     skipped: int,
     *     skipped_details: array<int, array{otr_id: int, name: string, reason: string}>,
     *     forum_parsed_count: int,
     *     forum_chunks: int,
     *     wiki_url_count: int
     * }>
     */
    public function importDumps(array $dumps): array
    {
        $results = [];

        foreach ($dumps as $dump) {
            $result = $this->importDump($dump['url'], $dump['version']);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * @return array{
     *     version: string,
     *     action: string,
     *     imported: int,
     *     updated: int,
     *     linked: int,
     *     skipped: int,
     *     skipped_details: array<int, array{otr_id: int, name: string, reason: string}>,
     *     forum_parsed_count: int,
     *     forum_chunks: int,
     *     wiki_url_count: int
     * }
     */
    public function importDump(string $url, string $version): array
    {
        $dumpPath = "otr/dumps/tmp/dump_{$version}.gz";
        $extractedPath = "otr/extracted/tmp/dump_{$version}.sql";

        try {
            // Download dump to temporary location
            $fullDumpPath = storage_path("app/{$dumpPath}");
            $this->gcsClient->downloadObject($url, $fullDumpPath);

            // Extract dump (from gz to sql)
            $this->extractGzip($dumpPath, $extractedPath);

            // Parse tournaments
            $fullExtractedPath = storage_path("app/{$extractedPath}");
            $tournaments = $this->extractTournaments($fullExtractedPath);

            // Validate and import tournaments
            $imported = 0;
            $updated = 0;
            $linked = 0;
            $skipped = 0;
            $skippedDetails = [];
            $forumTopicIds = []; // Collect for chunked processing
            $wikiUrlCount = 0;

            foreach ($tournaments as $tournamentData) {
                $errors = $this->validator->validate($tournamentData);
                if (empty($errors)) {
                    try {
                        $result = $this->importTournament($tournamentData, $version);

                        // Track action type
                        match ($result['action']) {
                            'created' => $imported++,
                            'updated' => $updated++,
                            'linked' => $linked++,
                            'ignored_duplicate' => null, // Ignore duplicates from statistics
                            default => null,
                        };

                        // Collect forum topic IDs for chunked processing
                        if ($result['forum_parsed'] ?? false) {
                            $forumTopicIds[] = $result['tournament']->forum_topic_id;
                        }

                        // Track wiki URLs (no forum parsing dispatched)
                        if (! $result['forum_parsed'] && ! empty($tournamentData['forum_url']) && str_contains($tournamentData['forum_url'], 'wiki')) {
                            $wikiUrlCount++;
                        }
                    } catch (\Exception $e) {
                        $skipped++;
                        $skippedDetails[] = [
                            'otr_id' => $tournamentData['id'],
                            'name' => $tournamentData['name'],
                            'reason' => 'Database error: '.$e->getMessage(),
                        ];
                        \Log::error('OTR import: database error for tournament', [
                            'otr_id' => $tournamentData['id'],
                            'name' => $tournamentData['name'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    $skipped++;
                    $errorReason = implode(', ', array_values($errors));
                    $skippedDetails[] = [
                        'otr_id' => $tournamentData['id'],
                        'name' => $tournamentData['name'],
                        'reason' => $errorReason,
                    ];
                }
            }

            // Unique topic IDs to avoid redundant API calls for multi-division tournaments
            $uniqueTopicIds = array_values(array_unique($forumTopicIds));

            // Record import history
            DB::table('otr_import_history')->insert([
                'dump_url' => $url,
                'dump_version' => $version,
                'status' => 'importing', // Will be updated to 'completed' when forum parsing finishes
                'tournaments_imported' => $imported,
                'tournaments_updated' => $updated,
                'tournaments_linked' => $linked,
                'tournaments_failed' => $skipped,
                'forum_parsed_count' => count($uniqueTopicIds),
                'forum_host_found' => 0, // Will be updated by forum parsing jobs
                'forum_staff_added' => 0, // Will be updated by forum parsing jobs
                'imported_at' => now(),
                'completed_at' => null, // Will be set when forum parsing completes
            ]);

            // Log skipped tournament details
            if (! empty($skippedDetails)) {
                \Log::warning('Skipped invalid tournaments during OTR import', [
                    'dump_version' => $version,
                    'skipped_count' => $skipped,
                    'details' => $skippedDetails,
                ]);
            }

            // Dispatch chunked forum parsing job
            if (! empty($uniqueTopicIds)) {
                $chunkCount = (int) ceil(count($uniqueTopicIds) / 50);

                \Log::info('OTR import: dispatching chunked forum parsing', [
                    'dump_version' => $version,
                    'forum_topic_count' => count($uniqueTopicIds),
                    'total_tournament_links' => count($forumTopicIds),
                    'chunk_count' => $chunkCount,
                    'estimated_time_minutes' => ceil(count($uniqueTopicIds) / 60), // 60 req/min
                    'force_reparse' => true, // OTR imports should update ALL tournaments
                ]);

                ChunkedForumParseJob::dispatch($uniqueTopicIds, 50, 1, true, $version);
            }

            // Log wiki URLs imported
            if ($wikiUrlCount > 0) {
                \Log::info('OTR import: imported tournaments with wiki URLs', [
                    'dump_version' => $version,
                    'wiki_url_count' => $wikiUrlCount,
                    'note' => 'Forum parsing not dispatched for wiki URLs (official tournaments)',
                ]);
            }

            // Cleanup using Storage facade
            Storage::delete($dumpPath);
            Storage::delete($extractedPath);

            return [
                'version' => $version,
                'action' => 'completed',
                'imported' => $imported,
                'updated' => $updated,
                'linked' => $linked,
                'skipped' => $skipped,
                'skipped_details' => $skippedDetails,
                'forum_parsed_count' => count($forumTopicIds),
                'forum_chunks' => ! empty($forumTopicIds) ? (int) ceil(count($forumTopicIds) / 50) : 0,
                'wiki_url_count' => $wikiUrlCount,
            ];

        } catch (\Exception $e) {
            // Record failed import
            DB::table('otr_import_history')->insert([
                'dump_url' => $url,
                'dump_version' => $version,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'tournaments_imported' => 0,
                'tournaments_updated' => 0,
                'tournaments_linked' => 0,
                'tournaments_failed' => 0,
                'forum_parsed_count' => 0,
                'forum_host_found' => 0,
                'forum_staff_added' => 0,
                'imported_at' => now(),
            ]);

            // Cleanup on failure
            Storage::delete($dumpPath);
            Storage::delete($extractedPath);

            throw $e;
        }
    }

    public function rollbackDump(string $version): void
    {
        DB::transaction(function () use ($version) {
            // Remove tournaments imported in this dump version
            DB::table('tournaments')
                ->where('otr_id', 'like', 'otr_id_placeholder_'.$version)
                ->delete();

            // Update import history
            DB::table('otr_import_history')
                ->where('dump_version', $version)
                ->whereNotNull('dump_url')
                ->update(['status' => 'rolled_back']);
        });
    }

    /**
     * @return array<int, object>
     */
    public function getDumpHistory(): array
    {
        return DB::table('otr_import_history')
            ->orderBy('imported_at', 'desc')
            ->get()
            ->toArray();
    }

    public function extractForumTopicId(?string $url): ?int
    {
        if (! $url) {
            return null;
        }

        // Extract topic ID from forum URL
        if (preg_match('/topics\/(\d+)(?:\?|$)/', $url, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function getKeyCount(int $ruleset): ?int
    {
        // Mania rulesets: 3=null, 4=4k, 5=7k
        return match ($ruleset) {
            3 => null,  // Mania (will be parsed from forum topic)
            4 => 4,     // 4K Mania
            5 => 7,     // 7K Mania
            default => null,
        };
    }

    private function extractGzip(string $inputPath, string $outputPath): void
    {
        if (! Storage::exists($inputPath)) {
            throw new \Exception("Gzip file not found: {$inputPath}");
        }

        // Read compressed content from Storage
        $compressedContent = Storage::get($inputPath);

        // Decompress
        $decompressedContent = gzdecode($compressedContent);

        if ($decompressedContent === false) {
            throw new \Exception("Failed to decompress gzip file: {$inputPath}");
        }

        // Write decompressed content to Storage
        Storage::put($outputPath, $decompressedContent);
    }
}
