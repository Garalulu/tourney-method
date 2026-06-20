<?php

namespace App\Console\Commands;

use App\Services\OtrDumpService;
use Illuminate\Console\Command;

class ImportOtrDumpCommand extends Command
{
    protected $signature = 'otr:import-dump {version?} {--all}';

    protected $description = 'Import tournaments from OTR dump files';

    protected OtrDumpService $dumpService;

    public function __construct(OtrDumpService $dumpService)
    {
        parent::__construct();
        $this->dumpService = $dumpService;
    }

    public function handle(): int
    {
        $version = $this->argument('version');
        $allFlag = $this->option('all');

        if ($version && $allFlag) {
            $this->error('Cannot specify version and --all flag together');

            return 1;
        }

        if (! $version && ! $allFlag) {
            return $this->listAvailableDumps();
        }

        try {
            if ($allFlag) {
                return $this->importAllPendingDumps();
            }

            return $this->importSpecificDump((string) $version);
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function listAvailableDumps(): int
    {
        $dumps = $this->dumpService->getAvailableDumps();

        if (empty($dumps)) {
            $this->info('No OTR dumps found');

            return self::SUCCESS;
        }

        $this->info('Available OTR dumps:');

        foreach ($dumps as $index => $dump) {
            $formattedVersion = $this->formatVersion($dump['version']);
            $this->line(sprintf(
                '%d. %s (%s)',
                $index + 1,
                $dump['version'],
                $formattedVersion
            ));
        }

        return self::SUCCESS;
    }

    private function importAllPendingDumps(): int
    {
        $pendingDumps = $this->dumpService->getPendingDumps();

        if (empty($pendingDumps)) {
            $this->info('No pending dumps to import');

            return 0;
        }

        $this->info('Importing '.count($pendingDumps).' pending dump(s)...');

        $results = $this->dumpService->importDumps($pendingDumps);

        foreach ($results as $result) {
            $this->line("Dump {$result['version']}: {$result['action']} - {$result['imported']} tournaments imported, {$result['skipped']} skipped");
        }

        return 0;
    }

    private function importSpecificDump(string $version): int
    {
        $dumps = $this->dumpService->getAvailableDumps();
        $dump = null;

        foreach ($dumps as $d) {
            if ($d['version'] === $version) {
                $dump = $d;
                break;
            }
        }

        if (! $dump) {
            $this->error("Error: Dump version {$version} not found");

            return 1;
        }

        $status = $this->getDumpStatus($version);
        if ($status === 'COMPLETED') {
            $this->error("Error: Dump {$version} has already been imported");

            return 1;
        }

        $this->info("Importing dump {$version}...");

        try {
            // Create a temporary dump entry for single import
            $result = $this->dumpService->importDump($dump['url'], $version);

            $this->info("Successfully imported {$result['imported']} tournaments");

            if ($result['updated'] > 0) {
                $this->info("Updated {$result['updated']} existing tournaments");
            }

            if ($result['linked'] > 0) {
                $this->info("Linked {$result['linked']} tournaments to OTR data");
            }

            if ($result['skipped'] > 0) {
                $this->warn("Skipped {$result['skipped']} invalid tournaments:");
                foreach (array_slice($result['skipped_details'] ?? [], 0, 10) as $detail) {
                    $this->line("  - OTR ID {$detail['otr_id']}: {$detail['name']} ({$detail['reason']})");
                }
                if (count($result['skipped_details'] ?? []) > 10) {
                    $remaining = count($result['skipped_details']) - 10;
                    $this->line("  ... and {$remaining} more (check logs for details)");
                }
            }

            if (isset($result['forum_parsed_count']) && $result['forum_parsed_count'] > 0) {
                $chunkCount = $result['forum_chunks'] ?? 0;
                $this->info("Dispatched {$result['forum_parsed_count']} forum parsing jobs in {$chunkCount} chunks");
                $this->comment('Each chunk processes 50 jobs sequentially');
                $this->comment('These will run in the background at ~60 requests/minute');
                $this->comment('Estimated time: ~'.ceil($result['forum_parsed_count'] / 60).' minutes');
            }

            if (isset($result['wiki_url_count']) && $result['wiki_url_count'] > 0) {
                $this->info("Imported {$result['wiki_url_count']} tournaments with wiki URLs");
                $this->comment('Wiki URLs cannot be parsed via osu! API (official tournaments)');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function getDumpStatus(string $version): string
    {
        $history = \DB::table('otr_import_history')
            ->where('dump_version', $version)
            ->first();

        if (! $history) {
            return 'PENDING';
        }

        return strtoupper($history->status);
    }

    private function formatVersion(string $version): string
    {
        $parts = explode('_', $version);
        if (count($parts) === 6) {
            $date = $parts[0].'-'.$parts[1].'-'.$parts[2];
            $time = $parts[3].':'.$parts[4].':'.$parts[5];

            return "{$date} {$time}";
        }

        return $version;
    }
}
