<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ValidateBannerUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'banners:validate 
                            {--limit=100 : Maximum number of banners to validate}
                            {--detailed : Show detailed results including successful validations}
                            {--fix : Update database to set null for invalid banners}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate all tournament banner URLs and show which ones are broken';

    private int $totalChecked = 0;

    private int $successful = 0;

    private int $failed = 0;

    private int $alreadyNull = 0;

    /** @var array<int, array{url: string, status: int|null, content_type: string|null, error: string, tournament_id: int, tournament_title: string}> */
    private array $failures = [];

    /** @var array<int, array{url: string, content_type: string, tournament_id: int, tournament_title: string}> */
    private array $successes = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $detailed = $this->option('detailed') === true;
        $fix = $this->option('fix') === true;

        $this->info("Validating banner URLs (limit: {$limit})...");

        // Get tournaments with banner URLs
        /** @var Collection<int, Tournament> $tournaments */
        $tournaments = Tournament::whereNotNull('banner_url')
            ->limit($limit)
            ->get();

        $this->line("Found {$tournaments->count()} tournaments with banner URLs");
        $this->newLine();

        $bar = $this->output->createProgressBar($tournaments->count());
        $bar->start();

        foreach ($tournaments as $tournament) {
            $this->totalChecked++;

            $result = $this->validateBanner($tournament->banner_url);

            if ($result['success']) {
                $this->successful++;
                if ($detailed) {
                    $this->successes[$tournament->id] = [
                        'url' => substr($tournament->banner_url, 0, 80),
                        'content_type' => $result['content_type'],
                        'tournament_id' => $tournament->id,
                        'tournament_title' => $tournament->title,
                    ];
                }
            } else {
                $this->failed++;
                $this->failures[$tournament->id] = [
                    'url' => $tournament->banner_url,
                    'status' => $result['status'] ?? null,
                    'content_type' => $result['content_type'] ?? null,
                    'error' => $result['error'],
                    'tournament_id' => $tournament->id,
                    'tournament_title' => $tournament->title,
                ];

                // Fix: Set banner_url to null if requested
                if ($fix) {
                    $tournament->update(['banner_url' => null]);
                }
            }

            // Small delay to be respectful (50ms)
            usleep(50000);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Print summary
        $this->info('=== Validation Summary ===');
        $this->line("Total checked: {$this->totalChecked}");
        $this->line("Already null: {$this->alreadyNull}");
        $this->line("✅ Successful: {$this->successful}");
        $this->line("❌ Failed: {$this->failed}");

        if ($fix && $this->failed > 0) {
            $this->newLine();
            $this->warn("Fixed: Set {$this->failed} invalid banner URLs to null");
        }

        // Print detailed failures
        if (! empty($this->failures)) {
            $this->newLine();
            $this->error('=== Failed Validations ===');
            $this->newLine();

            foreach ($this->failures as $id => $failure) {
                $this->line("ID: {$failure['tournament_id']} | {$failure['tournament_title']}");
                $this->line("  URL: {$failure['url']}");
                $this->line("  Error: {$failure['error']}");

                if ($failure['status'] !== null) {
                    $this->line("  Status: {$failure['status']}");
                }

                if ($failure['content_type'] !== null) {
                    $this->line("  Content-Type: {$failure['content_type']}");
                }

                $this->newLine();
            }
        }

        // Print detailed successes if requested
        if ($detailed && ! empty($this->successes)) {
            $this->newLine();
            $this->info('=== Successful Validations ===');
            $this->newLine();

            foreach ($this->successes as $id => $success) {
                $this->line("ID: {$success['tournament_id']} | {$success['tournament_title']}");
                $this->line("  URL: {$success['url']}...");
                $this->line("  Content-Type: {$success['content_type']}");
                $this->newLine();
            }
        }

        return self::SUCCESS;
    }

    /**
     * Validate a single banner URL
     *
     * @return array{success: true, content_type: string}|array{success: false, error: string, status?: int, content_type?: string|null}
     */
    private function validateBanner(string $url): array
    {
        try {
            $response = Http::timeout(5)->head($url);

            $statusCode = $response->status();

            if ($statusCode !== 200) {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'error' => "HTTP {$statusCode}",
                ];
            }

            $contentType = $response->header('Content-Type');

            if ($contentType === null || ! str_starts_with($contentType, 'image/')) {
                return [
                    'success' => false,
                    'content_type' => $contentType,
                    'error' => 'Not an image',
                ];
            }

            return [
                'success' => true,
                'content_type' => $contentType,
            ];

        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'error' => 'Connection timeout or refused',
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Request failed: '.$e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
