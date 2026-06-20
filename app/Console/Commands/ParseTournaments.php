<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\CreatesBackup;
use App\Console\Commands\Concerns\RequiresConfirmation;
use App\Jobs\AggregateStaffJob;
use App\Jobs\BatchFetchUsersJob;
use App\Jobs\ParseForumTopicJob;
use App\Models\AdminMaintenanceRun;
use App\Services\AdminMaintenanceRunRecorder;
use App\Services\BatchTransactionService;
use App\Services\OsuApiService;
use App\Support\QueueNames;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ParseTournaments extends Command
{
    use CreatesBackup, RequiresConfirmation;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournaments:parse
                            {topic_id? : Specific forum topic ID to parse}
                            {--dry-run : Parse topics without creating tournaments}
                            {--force : Skip the 24-hour cooldown check}
                            {--confirm : Confirm execution of data-modifying command}
                            {--no-backup : Skip database backup before execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse osu! forum for tournament posts and create/update tournament records.
                             Fetches 50 most recent topics (forum_id=55) or processes specific topic_id.
                             REQUIRES --confirm flag to execute.';

    private int $topicsProcessed = 0;

    private int $topicsQueued = 0;

    private int $topicsSkipped = 0;

    private int $topicsFailed = 0;

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, ParseForumTopicJob> */
    private array $jobs = [];

    /**
     * Execute the console command.
     */
    public function handle(
        OsuApiService $osuApi,
        BatchTransactionService $transaction,
        AdminMaintenanceRunRecorder $maintenanceRuns
    ): int {
        $isDryRun = $this->option('dry-run') === true;
        $specificTopicId = $this->argument('topic_id');
        $maintenanceRun = null;

        // Require confirmation for data-modifying operations
        if (! $isDryRun && ! $this->confirmExecution()) {
            return self::FAILURE;
        }

        if (! $isDryRun) {
            $maintenanceRun = $maintenanceRuns->start(AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE, [
                'topic_id' => $specificTopicId,
                'force' => (bool) $this->option('force'),
            ]);
        }

        $forumId = 55; // Tournaments forum

        $this->info('Starting tournament parser...');

        if ($specificTopicId) {
            $this->info("Processing specific topic ID: {$specificTopicId}");
            $topics = [['id' => (int) $specificTopicId, 'title' => "Topic #{$specificTopicId}"]];
        } else {
            $this->info('Fetching 50 most recent topics from osu! forum...');
            // Fetch recent forum topics (single API call)
            $response = $osuApi->getForumTopics($forumId, 50, 1);
            $topics = $response['topics'] ?? [];
        }

        // Create backup before modifying data (skip in dry-run mode or if --no-backup flag)
        $backupPath = null;
        if (! $isDryRun && $this->shouldCreateBackup()) {
            $backupPath = $this->createBackup();
        }

        try {
            if (empty($topics)) {
                $this->warn('No topics found.');
                $maintenanceRuns->complete($maintenanceRun, [
                    'processed' => 0,
                    'queued' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                ]);

                return self::SUCCESS;
            }

            if (! $specificTopicId) {
                $this->info('Found '.count($topics).' topics');
            }

            // Generate unique transaction ID for this parsing run
            $transactionId = $transaction->generateTransactionId($maintenanceRun?->id);

            $this->info("Transaction ID: {$transactionId}");

            // Process each topic
            $bar = $this->output->createProgressBar(count($topics));
            $bar->start();

            foreach ($topics as $topic) {
                $this->topicsProcessed++;

                try {
                    /** @var int|null */
                    $topicId = $topic['id'] ?? null;
                    $title = $topic['title'] ?? 'Unknown';

                    if ($topicId === null) {
                        $this->topicsFailed++;
                        $message = "Topic missing ID: {$title}";
                        $this->errors[] = $message;
                        $maintenanceRuns->recordError($maintenanceRun, $message, ['topic_title' => $title]);
                        $bar->advance();

                        continue;
                    }

                    if ($isDryRun) {
                        $this->line("  [DRY RUN] Would parse topic: {$title}");
                        $this->topicsQueued++;
                    } else {
                        // Collect jobs for batching with transaction ID
                        // Pass force flag to allow re-parsing of non-pending tournaments
                        $this->jobs[] = new ParseForumTopicJob(
                            $topicId,
                            $transactionId,
                            forceReparse: $this->option('force') === true
                        );
                        $this->topicsQueued++;

                        Log::info('Queued forum topic for parsing', [
                            'topic_id' => $topicId,
                            'title' => $title,
                            'transaction_id' => $transactionId,
                            'force' => $this->option('force'),
                        ]);
                    }

                } catch (\Exception $e) {
                    $this->topicsFailed++;
                    $errorMessage = "Topic {$topicId}: {$e->getMessage()}";
                    $this->errors[] = $errorMessage;
                    $maintenanceRuns->recordError($maintenanceRun, $errorMessage, [
                        'topic_id' => $topicId,
                    ]);
                    Log::error('Failed to queue forum topic', [
                        'topic_id' => $topicId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Dispatch jobs as a chained batch using Bus::chain()
            if (! $isDryRun && count($this->jobs) > 0) {
                $this->info('Dispatching 3-stage chain...');

                // Create a batch for Stage 1 (ParseForumTopicJob)
                Bus::batch($this->jobs)
                    ->then(function (Batch $batch) use ($transactionId) {
                        Log::info('Stage 1 complete: Tournament parsing batch completed', [
                            'transaction_id' => $transactionId,
                            'batch_id' => $batch->id,
                            'total_jobs' => $batch->totalJobs,
                            'failed_jobs' => $batch->failedJobs,
                        ]);

                        // Stage 2a: Aggregate staff before user fetch chunks are dispatched.
                        Bus::batch([
                            new AggregateStaffJob($transactionId),
                        ])
                            ->then(function (Batch $aggregateBatch) use ($transactionId) {
                                Log::info('Stage 2a complete: Staff aggregation completed', [
                                    'transaction_id' => $transactionId,
                                    'batch_id' => $aggregateBatch->id,
                                ]);

                                // Stage 2b coordinator dispatches user chunks, then Stage 3 merge.
                                Bus::batch([
                                    new BatchFetchUsersJob($transactionId),
                                ])
                                    ->then(function (Batch $fetchBatch) use ($transactionId) {
                                        Log::info('Stage 2b coordinator complete: User fetch chunks dispatched', [
                                            'transaction_id' => $transactionId,
                                            'batch_id' => $fetchBatch->id,
                                        ]);
                                    })
                                    ->catch(function (Batch $fetchBatch, \Throwable $e) use ($transactionId) {
                                        Log::error('Stage 2b failed: User fetch coordinator failed', [
                                            'transaction_id' => $transactionId,
                                            'batch_id' => $fetchBatch->id,
                                            'error' => $e->getMessage(),
                                        ]);

                                        app(BatchTransactionService::class)
                                            ->recordError($transactionId, 'users_fetching', $e->getMessage());
                                    })
                                    ->finally(function (Batch $fetchBatch) use ($transactionId) {
                                        Log::info('Stage 2b coordinator finished', [
                                            'transaction_id' => $transactionId,
                                            'batch_id' => $fetchBatch->id,
                                        ]);
                                    })
                                    ->name('Stage 2b: Fetch Users Coordinator')
                                    ->onQueue(QueueNames::OSU_ADMIN)
                                    ->dispatch();
                            })
                            ->catch(function (Batch $aggregateBatch, \Throwable $e) use ($transactionId) {
                                Log::error('Stage 2a failed: Staff aggregation failed', [
                                    'transaction_id' => $transactionId,
                                    'batch_id' => $aggregateBatch->id,
                                    'error' => $e->getMessage(),
                                ]);

                                app(BatchTransactionService::class)
                                    ->recordError($transactionId, 'staff_aggregation', $e->getMessage());
                            })
                            ->finally(function (Batch $aggregateBatch) use ($transactionId) {
                                Log::info('Stage 2a finished: Staff aggregation finished', [
                                    'transaction_id' => $transactionId,
                                    'batch_id' => $aggregateBatch->id,
                                ]);
                            })
                            ->name('Stage 2a: Aggregate Staff')
                            ->onQueue(QueueNames::OSU_ADMIN)
                            ->dispatch();
                    })
                    ->catch(function (Batch $batch, \Throwable $e) use ($transactionId) {
                        Log::error('Stage 1 failed: Tournament parsing batch failed', [
                            'transaction_id' => $transactionId,
                            'batch_id' => $batch->id,
                            'failed_jobs' => $batch->failedJobs,
                            'error' => $e->getMessage(),
                        ]);

                        // Clean up transaction cache on failure
                        app(BatchTransactionService::class)->cleanupTransaction($transactionId);
                    })
                    ->finally(function (Batch $batch) use ($transactionId) {
                        Log::info('Stage 1 finished: Tournament parsing batch finished', [
                            'transaction_id' => $transactionId,
                            'batch_id' => $batch->id,
                            'total_jobs' => $batch->totalJobs,
                            'failed_jobs' => $batch->failedJobs,
                        ]);
                    })
                    ->name('Stage 1: Parse Tournaments')
                    ->onQueue(QueueNames::OSU_ADMIN)
                    ->dispatch();

                $this->info("Transaction ID: {$transactionId}");
                $this->info('3-stage chain dispatched: Parse → Aggregate & Fetch → Merge');
                $this->info('Check Horizon dashboard for progress');
            }

            if (! $isDryRun && count($this->jobs) === 0) {
                $maintenanceRuns->complete($maintenanceRun);
            }

            // Print summary
            $this->info('=== Parsing Summary ===');
            $this->info("Topics processed: {$this->topicsProcessed}");
            $this->info("Topics queued: {$this->topicsQueued}");
            $this->info("Topics skipped: {$this->topicsSkipped}");
            $this->warn("Topics failed: {$this->topicsFailed}");

            if (! empty($this->errors)) {
                $this->newLine();
                $this->error('Errors encountered:');
                foreach ($this->errors as $error) {
                    $this->line("  - {$error}");
                }
            }

            Log::info('Tournament parsing completed', [
                'processed' => $this->topicsProcessed,
                'queued' => $this->topicsQueued,
                'skipped' => $this->topicsSkipped,
                'failed' => $this->topicsFailed,
                'dry_run' => $isDryRun,
            ]);

            $maintenanceRuns->updateSummary($maintenanceRun, [
                'processed' => $this->topicsProcessed,
                'queued' => $this->topicsQueued,
                'skipped' => $this->topicsSkipped,
                'failed' => $this->topicsFailed,
            ]);

        } catch (\Exception $e) {
            $this->error("Parsing failed: {$e->getMessage()}");
            $maintenanceRuns->fail($maintenanceRun, $e->getMessage());
            Log::error('Tournament parser command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Restore backup if it was created
            if (! $isDryRun && $backupPath !== null) {
                $this->restoreBackup($backupPath);
            }

            return self::FAILURE;
        }

        // Clean up old backups after successful execution
        if (! $isDryRun && $this->shouldCreateBackup()) {
            $this->cleanupOldBackups();
        }

        return self::SUCCESS;
    }
}
