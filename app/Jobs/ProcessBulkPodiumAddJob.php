<?php

namespace App\Jobs;

use App\Models\AdminAsyncOperation;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\User;
use App\Services\ParticipationPodiumBackfillService;
use App\Services\TournamentParticipantSyncService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessBulkPodiumAddJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    /**
     * @param  array<int, string>  $usernames
     */
    public function __construct(
        private int $operationId,
        private int $tournamentId,
        private array $usernames,
        private int $placement,
        private int $adminId
    ) {
        $this->onQueue(QueueNames::OSU_ADMIN_PRIORITY);
    }

    public function handle(TournamentParticipantSyncService $syncService): void
    {
        $operation = AdminAsyncOperation::query()->findOrFail($this->operationId);
        $tournament = Tournament::query()->findOrFail($this->tournamentId);
        $admin = User::query()->find($this->adminId);

        $operation->start('Adding podium users from osu! profiles...');

        $successes = [];
        $failures = [];
        $podiumBackfill = app(ParticipationPodiumBackfillService::class);
        $podiumGroupId = $podiumBackfill->defaultGroupIdForNewWinner($tournament, $this->placement);

        foreach ($this->usernames as $username) {
            try {
                $winner = $syncService->addPodiumByUsername($tournament, $username, $this->placement, $podiumGroupId);

                $successes[] = [
                    'winner_id' => $winner->id,
                    'user_id' => $winner->user_id,
                    'username' => $winner->username,
                    'placement' => $this->placement,
                ];

                $operation->advance("Added {$winner->username}");
            } catch (\Throwable $e) {
                $failures[] = [
                    'username' => $username,
                    'message' => $e->getMessage(),
                ];

                $operation->recordItemError([
                    'username' => $username,
                    'message' => $e->getMessage(),
                ], "Failed to add {$username}");

                Log::warning('Bulk podium add item failed', [
                    'operation_id' => $operation->id,
                    'tournament_id' => $tournament->id,
                    'username' => $username,
                    'placement' => $this->placement,
                    'error' => $e->getMessage(),
                ]);

                $operation->advance("Skipped {$username}");
            }
        }

        $operation->finish('Podium bulk add complete', [
            'refresh' => ['podium'],
        ]);

        if ($successes !== []) {
            $podiumBackfill->backfillTournamentGroup($tournament->id, $podiumGroupId);
        }

        if ($admin) {
            AdminAuditLog::log($admin, 'tournament.podium_winners_bulk_added', $tournament, [
                'operation_id' => $operation->id,
                'placement' => $this->placement,
                'requested_usernames' => array_values($this->usernames),
                'successes' => $successes,
                'failures' => $failures,
                'total_count' => count($this->usernames),
                'success_count' => count($successes),
                'failed_count' => count($failures),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        AdminAsyncOperation::query()
            ->find($this->operationId)
            ?->fail('Podium bulk add failed', $exception);
    }
}
