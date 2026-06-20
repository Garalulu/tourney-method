<?php

namespace App\Console\Commands;

use App\Services\ForumParser;
use App\Services\OsuApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Manual testing command for forum parser
 * Tests parsing algorithm against 10 real tournament topics from osu! forum
 */
class TestForumParser extends Command
{
    protected $signature = 'tournaments:test-parser
                            {--count=10 : Number of tournaments to test}
                            {--detailed : Show detailed parsing results}';

    protected $description = 'Test forum parser against real tournament topics';

    private int $topicsTested = 0;

    private int $topicsPassed = 0;

    private int $topicsFailed = 0;

    /** @var array<int, array<string, mixed>> */
    private array $results = [];

    public function handle(OsuApiService $osuApi, ForumParser $parser): int
    {
        $count = (int) $this->option('count');
        $isDetailed = $this->option('detailed') === true;

        $this->info('=== Forum Parser Test ===');
        $this->info('Loading most recent tournament topics...');
        $this->newLine();

        try {
            // Fetch most recent forum topics (sorted by latest reply)
            $forumId = 55; // Tournaments forum
            $response = $osuApi->getForumTopics($forumId, 50);
            /** @var array<int, array<string, mixed>> $topics */
            $topics = $response['topics'] ?? [];

            if (empty($topics)) {
                $this->warn('No recent topics found.');

                return self::SUCCESS;
            }

            $this->info('Found '.count($topics).' topics');

            // Filter to actual tournament posts
            /** @var Collection<int, array<string, mixed>> $tournamentTopics */
            $tournamentTopics = collect($topics)->filter(function (array $topic): bool {
                $title = strtolower($topic['title'] ?? '');
                // Skip non-tournament posts
                if (str_contains($title, 'discussion')) {
                    return false;
                }
                if (str_contains($title, 'thread')) {
                    return false;
                }
                if (str_contains($title, 'question')) {
                    return false;
                }

                // Must have tournament-like keywords
                return str_contains($title, 'tournament')
                    || str_contains($title, 'cup')
                    || str_contains($title, 'bracket');
            })->take($count)->values();

            if ($tournamentTopics->count() < $count) {
                $this->warn("Only found {$tournamentTopics->count()} tournament topics (requested {$count})");
            }

            $this->info("Testing {$tournamentTopics->count()} tournament topics...");
            $this->newLine();

            // Test each topic
            foreach ($tournamentTopics as $topic) {
                $this->testTopic($parser, $topic, $isDetailed);
            }

            // Print summary
            $this->printSummary();

            return $this->topicsFailed > 0 ? self::FAILURE : self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Test failed: {$e->getMessage()}");
            Log::error('Forum parser test command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $topic
     */
    private function testTopic(ForumParser $parser, array $topic, bool $isDetailed): void
    {
        $this->topicsTested++;

        $topicId = $topic['id'];
        $title = $topic['title'];
        $createdAt = $topic['created_at'] ?? 'Unknown';

        $this->line("{$this->topicsTested}. Testing topic {$topicId}: {$title}");

        try {
            $parsed = $parser->parseForumTopic($topicId);

            if ($parsed === null) {
                $this->topicsFailed++;
                $this->error('  ✗ FAILED: Could not parse topic');
                $this->results[$topicId] = [
                    'title' => $title,
                    'status' => 'failed',
                    'error' => 'Parser returned null',
                ];

                return;
            }

            // Validate parsed data
            $validation = $this->validateParsedData($topicId, $parsed);

            if ($validation['passed']) {
                $this->topicsPassed++;
                $this->info('  ✓ PASSED: Successfully parsed');
                $this->results[$topicId] = [
                    'title' => $title,
                    'status' => 'passed',
                    'data' => $parsed,
                ];

                if ($isDetailed) {
                    $this->printDetails($parsed);
                }
            } else {
                $this->topicsFailed++;
                $this->error("  ✗ FAILED: {$validation['error']}");
                $this->results[$topicId] = [
                    'title' => $title,
                    'status' => 'failed',
                    'error' => $validation['error'],
                    'data' => $parsed,
                ];
            }

        } catch (\Exception $e) {
            $this->topicsFailed++;
            $this->error("  ✗ FAILED: Exception - {$e->getMessage()}");
            $this->results[$topicId] = [
                'title' => $title,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];

            Log::error('Failed to parse forum topic in test', [
                'topic_id' => $topicId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->newLine();
    }

    /**
     * Validate parsed tournament data
     *
     * @param  array<string, mixed>  $data
     * @return array{passed: bool, error: string|null}
     */
    private function validateParsedData(int $topicId, array $data): array
    {
        // Must have forum_topic_id
        if (! isset($data['forum_topic_id']) || $data['forum_topic_id'] !== $topicId) {
            return ['passed' => false, 'error' => 'Invalid forum_topic_id'];
        }

        // Must have title
        if (empty($data['title'])) {
            return ['passed' => false, 'error' => 'Missing title'];
        }

        // Must have at least one mode
        if (empty($data['modes']) || ! is_array($data['modes'])) {
            return ['passed' => false, 'error' => 'Missing or invalid modes'];
        }

        // Modes must be valid
        $validModes = ['osu', 'taiko', 'catch', 'mania'];
        foreach ($data['modes'] as $mode) {
            if (! in_array($mode, $validModes, true)) {
                return ['passed' => false, 'error' => "Invalid mode: {$mode}"];
            }
        }

        // Must have description
        if (empty($data['description'])) {
            return ['passed' => false, 'error' => 'Missing description'];
        }

        // Rank ranges must be integers or null
        if ($data['rank_range_min'] !== null && ! is_int($data['rank_range_min'])) {
            return ['passed' => false, 'error' => 'rank_range_min must be integer or null'];
        }

        if ($data['rank_range_max'] !== null && ! is_int($data['rank_range_max'])) {
            return ['passed' => false, 'error' => 'rank_range_max must be integer or null'];
        }

        // If rank_range_min exists, it should be positive
        if ($data['rank_range_min'] !== null && $data['rank_range_min'] <= 0) {
            return ['passed' => false, 'error' => 'rank_range_min must be positive'];
        }

        // If both exist, max should be >= min
        if ($data['rank_range_min'] !== null && $data['rank_range_max'] !== null) {
            if ($data['rank_range_max'] < $data['rank_range_min']) {
                return ['passed' => false, 'error' => 'rank_range_max must be >= rank_range_min'];
            }
        }

        // is_badge must be boolean
        if (! is_bool($data['is_badge'])) {
            return ['passed' => false, 'error' => 'is_badge must be boolean'];
        }

        // URLs must be string or null
        $urlFields = ['discord_url', 'twitch_url', 'spreadsheet_url', 'bracket_url', 'registration_url', 'tcomm_url'];
        foreach ($urlFields as $field) {
            if ($data[$field] !== null && ! is_string($data[$field])) {
                return ['passed' => false, 'error' => "{$field} must be string or null"];
            }
        }

        // Dates must be valid ISO 8601 or null
        $dateFields = ['registration_start', 'registration_end', 'tournament_start', 'tournament_end'];
        foreach ($dateFields as $field) {
            if ($data[$field] !== null) {
                try {
                    Carbon::parse($data[$field]);
                } catch (\Exception $e) {
                    return ['passed' => false, 'error' => "{$field} is not a valid date"];
                }
            }
        }

        return ['passed' => true, 'error' => null];
    }

    /**
     * Print detailed parsing results
     *
     * @param  array<string, mixed>  $data
     */
    private function printDetails(array $data): void
    {
        $this->line('  Details:');
        $this->line('    Modes: '.implode(', ', $data['modes']));
        $this->line('    Badge: '.($data['is_badge'] ? 'Yes' : 'No'));

        if ($data['rank_range_min'] !== null || $data['rank_range_max'] !== null) {
            $rankRange = "#{$data['rank_range_min']} - #{$data['rank_range_max']}";
            $this->line("    Rank Range: {$rankRange}");
        }

        if ($data['discord_url']) {
            $this->line("    Discord: {$data['discord_url']}");
        }

        if ($data['tcomm_url']) {
            $this->line("    TCOMM: {$data['tcomm_url']}");
        }

        if ($data['registration_start'] || $data['registration_end']) {
            $this->line("    Registration: {$data['registration_start']} - {$data['registration_end']}");
        }

        if ($data['tournament_start'] || $data['tournament_end']) {
            $this->line("    Tournament: {$data['tournament_start']} - {$data['tournament_end']}");
        }
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== Test Summary ===');
        $this->line("Topics Tested: {$this->topicsTested}");
        $this->line("Passed: {$this->topicsPassed}");
        $this->warn("Failed: {$this->topicsFailed}");

        if ($this->topicsTested > 0) {
            $successRate = ($this->topicsPassed / $this->topicsTested) * 100;
            $this->line('Success Rate: '.number_format($successRate, 1).'%');

            if ($successRate >= 80) {
                $this->info('✓ Parser is working well (≥80% success rate)');
            } else {
                $this->error('✗ Parser needs improvement (<80% success rate)');
            }
        }

        // Log detailed results
        Log::info('Forum parser test completed', [
            'tested' => $this->topicsTested,
            'passed' => $this->topicsPassed,
            'failed' => $this->topicsFailed,
            'success_rate' => $this->topicsTested > 0 ? ($this->topicsPassed / $this->topicsTested) * 100 : 0,
        ]);

        // Show failed topics
        if ($this->topicsFailed > 0) {
            $this->newLine();
            $this->error('Failed Topics:');
            foreach ($this->results as $topicId => $result) {
                if ($result['status'] === 'failed') {
                    $this->line("  - Topic {$topicId}: {$result['title']}");
                    $this->line("    Error: {$result['error']}");
                }
            }
        }
    }
}
