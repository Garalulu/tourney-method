<?php

namespace App\Jobs;

use App\Models\AdminAsyncOperation;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BatchTransactionService;
use App\Services\UserProfileSyncService;
use App\Support\QueueNames;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchMergeStaffJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int<0, max>>
     */
    public array $backoff = [60, 120, 240];

    public function __construct(
        private string $parseBatchId,
        private ?int $operationId = null,
        string $queueName = QueueNames::OSU_ADMIN
    ) {
        $this->onQueue($queueName);
    }

    /**
     * Execute the job.
     */
    public function handle(?BatchTransactionService $transaction = null): void
    {
        $transaction ??= app(BatchTransactionService::class);

        Log::info('BatchMergeStaffJob: Merging staff to tournaments', [
            'transaction_id' => $this->parseBatchId,
        ]);

        $staffData = $transaction->getStaffPayloads($this->parseBatchId);
        $userMapping = $transaction->getUserMapping($this->parseBatchId);

        if (empty($staffData) && ! $transaction->hasTransaction($this->parseBatchId)) {
            Log::error('BatchMergeStaffJob: Required parse batch not found', [
                'transaction_id' => $this->parseBatchId,
            ]);
            throw new \Exception("Parse batch not found for transaction: {$this->parseBatchId}");
        }

        if (empty($staffData) || empty($userMapping)) {
            Log::info('BatchMergeStaffJob: No data to process', [
                'transaction_id' => $this->parseBatchId,
            ]);

            if ($this->operationId !== null) {
                AdminAsyncOperation::query()
                    ->find($this->operationId)
                    ?->finish('Tournament re-parse complete', [
                        'refresh' => ['staff'],
                    ]);
            }

            return;
        }

        $totalStaff = 0;
        $skippedStaff = 0;
        $totalAdded = 0;
        $totalUpdated = 0;
        $totalRemoved = 0;
        $skippedByTournament = [];
        $skippedByReason = [];

        // Wrap in transaction for ACID guarantees
        DB::transaction(function () use ($staffData, $userMapping, &$totalStaff, &$skippedStaff, &$totalAdded, &$totalUpdated, &$totalRemoved, &$skippedByTournament, &$skippedByReason) {
            foreach ($staffData as $tournamentId => $staffList) {
                $tournament = Tournament::find($tournamentId);

                if (! $tournament) {
                    Log::warning('BatchMergeStaffJob: Tournament not found', [
                        'transaction_id' => $this->parseBatchId,
                        'tournament_id' => $tournamentId,
                    ]);

                    continue;
                }

                // Build parsed staff array (only include users in mapping)
                $parsedStaff = [];
                $tournamentSkipped = 0;

                foreach ($staffList as $staff) {
                    $osuId = $staff['osu_id'];
                    $role = $staff['role'];

                    // Skip if user not in mapping
                    if (! isset($userMapping[$osuId])) {
                        $skippedStaff++;
                        $tournamentSkipped++;

                        // Track skipped staff by tournament
                        if (! isset($skippedByTournament[$tournamentId])) {
                            $skippedByTournament[$tournamentId] = [
                                'tournament_id' => $tournamentId,
                                'tournament_title' => $tournament->title,
                                'skipped_count' => 0,
                                'skipped_ids' => [],
                            ];
                        }
                        $skippedByTournament[$tournamentId]['skipped_count']++;
                        $skippedByTournament[$tournamentId]['skipped_ids'][] = $osuId;

                        // Track skipped by reason
                        $reason = 'user_not_in_mapping';
                        if (! isset($skippedByReason[$reason])) {
                            $skippedByReason[$reason] = [
                                'reason' => $reason,
                                'count' => 0,
                                'sample_ids' => [],
                            ];
                        }
                        $skippedByReason[$reason]['count']++;
                        if (count($skippedByReason[$reason]['sample_ids']) < 20) {
                            $skippedByReason[$reason]['sample_ids'][] = $osuId;
                        }

                        continue;
                    }

                    $userId = $userMapping[$osuId];
                    $totalStaff++;

                    // Build parsed staff entry
                    $parsedStaff[] = [
                        'osu_id' => $osuId,
                        'username' => '', // Will be filled by mergeStaffWithHistory from DB
                        'role' => $role,
                    ];
                }

                // Merge staff with versioning
                if (! empty($parsedStaff)) {
                    $result = $tournament->mergeStaffWithHistory(
                        $parsedStaff,
                        'forum_topic_parse',
                        [
                            'parse_batch_id' => $this->parseBatchId,
                            'parser_stage' => 'batch_merge_staff',
                        ]
                    );

                    $totalAdded += $result['added'];
                    $totalUpdated += $result['updated'];
                    $totalRemoved += $result['removed'];

                    // Update host from staff (organizer role)
                    // Get the first organizer from the parsed staff
                    foreach ($parsedStaff as $staff) {
                        if ($staff['role'] === 'organizer') {
                            $user = User::where('osu_id', $staff['osu_id'])->first();
                            if ($user) {
                                $tournament->update([
                                    'host_osu_id' => $user->osu_id,
                                    'host_username' => $user->username,
                                ]);
                                break; // Use first organizer found
                            }
                        }
                    }
                }

                Log::debug('BatchMergeStaffJob: Tournament processed', [
                    'transaction_id' => $this->parseBatchId,
                    'tournament_id' => $tournamentId,
                    'tournament_title' => $tournament->title,
                    'processed' => count($parsedStaff),
                    'skipped' => $tournamentSkipped,
                    'added' => $result['added'] ?? 0,
                    'updated' => $result['updated'] ?? 0,
                    'removed' => $result['removed'] ?? 0,
                ]);
            }
        });

        // Log detailed skip information
        if ($skippedStaff > 0) {
            Log::warning('BatchMergeStaffJob: Some staff were skipped due to missing user mapping', [
                'transaction_id' => $this->parseBatchId,
                'total_skipped' => $skippedStaff,
                'total_processed' => $totalStaff,
                'skip_percentage' => $totalStaff > 0 ? round(($skippedStaff / ($totalStaff + $skippedStaff)) * 100, 2) : 0,
                'skipped_by_tournament' => array_values($skippedByTournament),
                'skipped_by_reason' => array_values($skippedByReason),
            ]);
        }

        $this->syncStaffProfileMetadata($userMapping);

        Log::info('BatchMergeStaffJob: Staff merged successfully', [
            'transaction_id' => $this->parseBatchId,
            'total_staff' => $totalStaff,
            'added' => $totalAdded,
            'updated' => $totalUpdated,
            'removed' => $totalRemoved,
            'skipped_staff' => $skippedStaff,
        ]);

        if ($this->operationId !== null) {
            $operation = AdminAsyncOperation::query()->find($this->operationId);
            if ($operation) {
                $operation->advance('Staff merged');
                $operation->finish('Tournament re-parse complete', [
                    'refresh' => ['staff'],
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BatchMergeStaffJob failed permanently', [
            'transaction_id' => $this->parseBatchId,
            'error' => $exception->getMessage(),
        ]);

        app(BatchTransactionService::class)
            ->recordError($this->parseBatchId, 'staff_merging', $exception->getMessage());

        if ($this->operationId !== null) {
            AdminAsyncOperation::query()
                ->find($this->operationId)
                ?->fail('Staff merge failed', $exception);
        }
    }

    /**
     * @param  array<int, int>  $userMapping
     */
    private function syncStaffProfileMetadata(array $userMapping): void
    {
        if (empty($userMapping)) {
            return;
        }

        $profileSyncService = app(UserProfileSyncService::class);

        User::query()
            ->whereIn('id', array_values($userMapping))
            ->get()
            ->each(fn (User $user) => $profileSyncService->syncStaffProfileMetadata($user));
    }
}
