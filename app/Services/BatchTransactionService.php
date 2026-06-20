<?php

namespace App\Services;

use App\Models\TournamentParseBatch;
use App\Models\TournamentParseStaffPayload;
use Illuminate\Support\Facades\DB;

class BatchTransactionService
{
    /**
     * Generate a unique transaction ID for a batch chain.
     */
    public function generateTransactionId(?int $adminMaintenanceRunId = null): string
    {
        $transactionId = 'txn_'.uniqid().'_'.str_replace('.', '', (string) microtime(true));

        $this->getOrCreateBatch($transactionId, adminMaintenanceRunId: $adminMaintenanceRunId);

        return $transactionId;
    }

    public function getAdminMaintenanceRunId(string $transactionId): ?int
    {
        return TournamentParseBatch::query()
            ->where('transaction_id', $transactionId)
            ->value('admin_maintenance_run_id');
    }

    /**
     * Store tournament IDs for a transaction.
     *
     * @param  array<int>  $tournamentIds
     */
    public function storeTournamentIds(string $transactionId, array $tournamentIds): void
    {
        $batch = $this->getOrCreateBatch($transactionId);

        $batch->update([
            'tournament_ids' => array_values(array_unique($tournamentIds)),
            'stage' => 'parse',
            'status' => 'running',
            'started_at' => $batch->started_at ?? now(),
        ]);
    }

    /**
     * Retrieve tournament IDs for a transaction.
     *
     * @return array<int>
     */
    public function getTournamentIds(string $transactionId): array
    {
        $batch = TournamentParseBatch::query()
            ->where('transaction_id', $transactionId)
            ->first();

        return $batch->tournament_ids ?? [];
    }

    /**
     * Add a tournament ID to a transaction.
     */
    public function addTournamentId(string $transactionId, int $tournamentId): void
    {
        DB::transaction(function () use ($transactionId, $tournamentId) {
            $batch = $this->getOrCreateBatch($transactionId, lockForUpdate: true);
            $tournamentIds = $batch->tournament_ids ?? [];

            if (! in_array($tournamentId, $tournamentIds, true)) {
                $tournamentIds[] = $tournamentId;
            }

            $batch->update([
                'tournament_ids' => array_values($tournamentIds),
                'stage' => 'parse',
                'status' => 'running',
                'started_at' => $batch->started_at ?? now(),
            ]);
        });
    }

    /**
     * Check if a transaction exists.
     */
    public function hasTransaction(string $transactionId): bool
    {
        return TournamentParseBatch::query()
            ->where('transaction_id', $transactionId)
            ->exists();
    }

    /**
     * Store parsed staff for a tournament in a parse batch.
     *
     * @param  array<int, array<string, mixed>>  $staffPayload
     */
    public function storeTournamentStaff(string $transactionId, int $tournamentId, array $staffPayload): void
    {
        $batch = $this->getOrCreateBatch($transactionId);

        TournamentParseStaffPayload::query()->updateOrCreate(
            [
                'tournament_parse_batch_id' => $batch->id,
                'tournament_id' => $tournamentId,
            ],
            [
                'staff_payload' => array_values($staffPayload),
                'parsed_at' => now(),
            ]
        );

        $this->addTournamentId($transactionId, $tournamentId);
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getStaffPayloads(string $transactionId): array
    {
        $batch = TournamentParseBatch::query()
            ->where('transaction_id', $transactionId)
            ->with('staffPayloads')
            ->first();

        if (! $batch) {
            return [];
        }

        return $batch->staffPayloads()
            ->get()
            ->mapWithKeys(fn (TournamentParseStaffPayload $payload) => [
                $payload->tournament_id => $payload->staff_payload,
            ])
            ->all();
    }

    /**
     * Mark staff aggregation complete.
     */
    public function markStaffAggregated(string $transactionId): void
    {
        $this->getOrCreateBatch($transactionId)->update([
            'stage' => 'staff_aggregated',
            'status' => 'running',
        ]);
    }

    /**
     * @return array<int>
     */
    public function getUniqueStaffOsuIds(string $transactionId): array
    {
        return collect($this->getStaffPayloads($transactionId))
            ->flatten(1)
            ->pluck('osu_id')
            ->filter(fn (mixed $osuId) => is_numeric($osuId))
            ->map(fn (mixed $osuId) => (int) $osuId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Merge user mappings into the parse batch.
     *
     * @param  array<int, int>  $userMapping
     */
    public function mergeUserMapping(string $transactionId, array $userMapping): void
    {
        DB::transaction(function () use ($transactionId, $userMapping) {
            $batch = $this->getOrCreateBatch($transactionId, lockForUpdate: true);
            $existingMapping = $batch->user_mapping ?? [];

            foreach ($userMapping as $osuId => $userId) {
                $existingMapping[(string) $osuId] = $userId;
            }

            $batch->update([
                'user_mapping' => $existingMapping,
                'stage' => 'users_fetched',
                'status' => 'running',
            ]);
        });
    }

    /**
     * @return array<int, int>
     */
    public function getUserMapping(string $transactionId): array
    {
        $mapping = TournamentParseBatch::query()
            ->where('transaction_id', $transactionId)
            ->value('user_mapping') ?? [];

        $normalized = [];
        foreach ($mapping as $osuId => $userId) {
            $normalized[(int) $osuId] = (int) $userId;
        }

        return $normalized;
    }

    /**
     * Record a parse batch error.
     */
    public function recordError(string $transactionId, string $stage, string $message): void
    {
        DB::transaction(function () use ($transactionId, $stage, $message) {
            $batch = $this->getOrCreateBatch($transactionId, lockForUpdate: true);
            $errors = $batch->errors ?? [];
            $errors[] = [
                'stage' => $stage,
                'message' => $message,
                'recorded_at' => now()->toISOString(),
            ];

            $batch->update([
                'errors' => $errors,
                'status' => 'failed',
                'stage' => $stage,
            ]);

            if ($batch->admin_maintenance_run_id !== null) {
                app(AdminMaintenanceRunRecorder::class)
                    ->fail($batch->admin_maintenance_run_id, $message, ['stage' => $stage]);
            }
        });
    }

    /**
     * Mark the transaction complete without deleting persisted handoff state.
     */
    public function cleanupTransaction(string $transactionId): void
    {
        $batch = TournamentParseBatch::query()
            ->where('transaction_id', $transactionId)
            ->first();

        if ($batch) {
            $batch->update([
                'stage' => 'completed',
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            if ($batch->admin_maintenance_run_id !== null) {
                app(AdminMaintenanceRunRecorder::class)->complete($batch->admin_maintenance_run_id, [
                    'tournaments_processed' => count($batch->tournament_ids ?? []),
                ]);
            }
        }
    }

    private function getOrCreateBatch(
        string $transactionId,
        bool $lockForUpdate = false,
        ?int $adminMaintenanceRunId = null
    ): TournamentParseBatch {
        $query = TournamentParseBatch::query()
            ->where('transaction_id', $transactionId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $batch = $query->first();

        if ($batch) {
            if ($adminMaintenanceRunId !== null && $batch->admin_maintenance_run_id === null) {
                $batch->update(['admin_maintenance_run_id' => $adminMaintenanceRunId]);
            }

            return $batch;
        }

        return TournamentParseBatch::query()->create([
            'transaction_id' => $transactionId,
            'admin_maintenance_run_id' => $adminMaintenanceRunId,
            'stage' => 'created',
            'status' => 'pending',
            'tournament_ids' => [],
            'user_mapping' => [],
            'errors' => [],
        ]);
    }
}
