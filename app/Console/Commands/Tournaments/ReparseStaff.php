<?php

namespace App\Console\Commands\Tournaments;

use App\Console\Commands\Concerns\CreatesBackup;
use App\Console\Commands\Concerns\RequiresConfirmation;
use App\Models\Tournament;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReparseStaff extends Command
{
    use CreatesBackup, RequiresConfirmation;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournaments:reparse-staff
                            {--id= : Specific tournament ID to reparse}
                            {--force : Force re-parse even if recently parsed}
                            {--dry-run : Show what would be done without making changes}
                            {--confirm : Confirm execution of data-modifying command}
                            {--no-backup : Skip database backup before execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-parse all tournaments to extract staff using fixed BBcode parser
                             REQUIRES --confirm flag to execute (unless --dry-run).';

    /**
     * Execute the console command.
     */
    public function handle(ForumParser $parser, OsuApiService $osuApi): int
    {
        $isDryRun = $this->option('dry-run');

        // Require confirmation for data-modifying operations
        if (! $isDryRun && ! $this->confirmExecution()) {
            return self::FAILURE;
        }

        $this->info('🔧 Re-parsing tournament staff...');

        // Create backup before modifying data (skip in dry-run mode)
        $backupPath = null;
        if (! $isDryRun) {
            $backupPath = $this->createBackup();
        }

        // Get tournaments to process
        $query = Tournament::whereNotNull('forum_topic_id');

        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
            $this->info("Processing tournament ID: {$this->option('id')}");
        } else {
            $this->info('Processing all tournaments with forum_topic_id...');
        }

        $tournaments = $query->get();
        $count = $tournaments->count();
        $processed = 0;
        $skipped = 0;
        $errors = 0;

        if ($count === 0) {
            $this->warn('No tournaments found to process.');

            // Clean up old backups
            if (! $isDryRun) {
                $this->cleanupOldBackups();
            }

            return Command::SUCCESS;
        }

        $this->info("Found {$count} tournaments to process.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        try {
            foreach ($tournaments as $tournament) {
                try {
                    // Fetch forum topic data
                    $topicData = $osuApi->getForumTopic($tournament->forum_topic_id);

                    if (! $topicData) {
                        $this->newLine();
                        $this->warn("  ⚠️  Tournament #{$tournament->id}: Failed to fetch forum topic");
                        $errors++;
                        $bar->advance();

                        continue;
                    }

                    // Extract BBcode content
                    $posts = $topicData['posts'] ?? [];
                    $post = $posts[0] ?? [];
                    $body = $post['body'] ?? null;

                    if ($body && is_array($body)) {
                        $bbcode = $body['raw'] ?? $body['html'] ?? null;
                    } else {
                        $bbcode = $body;
                    }

                    if (! $bbcode) {
                        $this->newLine();
                        $this->warn("  ⚠️  Tournament #{$tournament->id}: No content found");
                        $errors++;
                        $bar->advance();

                        continue;
                    }

                    // Parse staff from BBcode
                    $staff = $parser->parseStaffFromBBcode($bbcode);

                    if ($this->option('dry-run')) {
                        $this->newLine();
                        $this->info("  📋 Tournament #{$tournament->id} ({$tournament->title}):");
                        $this->info('     Would extract '.count($staff).' staff members');
                        $processed++;
                    } else {
                        // Build parsed staff array (filter out users that don't exist)
                        $parsedStaff = [];
                        foreach ($staff as $staffMember) {
                            $parsedStaff[] = [
                                'osu_id' => $staffMember['osu_id'],
                                'username' => '', // Will be filled by mergeStaffWithHistory from DB
                                'role' => $staffMember['role'],
                            ];
                        }

                        // Warn if parser returned no staff
                        if (empty($parsedStaff) && $tournament->staff()->count() > 0) {
                            $this->warn('  ⚠️  Parser returned no staff results - merge skipped to prevent data loss');
                            $this->warn('     Current staff will be preserved');
                            $skipped++;

                            continue;
                        }

                        // Merge staff with versioning
                        $result = $tournament->mergeStaffWithHistory(
                            $parsedStaff,
                            'reparse_command',
                            [
                                'parser_version' => '2.0', // Multi-format parser
                                'forum_topic_id' => $tournament->forum_topic_id,
                            ]
                        );

                        $this->newLine();
                        $this->info("  ✅ Tournament #{$tournament->id} ({$tournament->title}):");
                        $this->info('     Staff changes:');
                        $this->info("       - Added:   {$result['added']}");
                        $this->info("       - Updated: {$result['updated']}");
                        $this->info("       - Removed: {$result['removed']}");

                        // Warn if all staff were skipped
                        if ($result['added'] === 0 && $result['updated'] === 0 && count($staff) > 0) {
                            $this->warn('     ⚠️  All staff members were skipped (users may not exist in database)');
                            $skipped++;
                        } else {
                            $processed++;
                        }
                    }

                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("  ❌ Tournament #{$tournament->id}: {$e->getMessage()}");
                    Log::error('Failed to reparse tournament staff', [
                        'tournament_id' => $tournament->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }

                $bar->advance();
            }
        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error("❌ Fatal error during re-parsing: {$e->getMessage()}");
            Log::error('Reparse staff command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Restore backup if it was created
            if (! $isDryRun && $backupPath !== null) {
                $this->restoreBackup($backupPath);
            }

            return Command::FAILURE;
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        // Summary
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Summary:');
        $this->info("   Processed: {$processed}");
        $this->info("   Skipped:   {$skipped}");
        $this->info("   Errors:    {$errors}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($this->option('dry-run')) {
            $this->warn('⚠️  DRY RUN MODE - No changes were made');
            $this->info('Run without --dry-run to apply changes.');
        }

        // Clean up old backups after successful execution
        if (! $isDryRun) {
            $this->cleanupOldBackups();
        }

        return Command::SUCCESS;
    }
}
