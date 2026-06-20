<?php

namespace App\Jobs;

use App\Models\AdminAsyncOperation;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentParticipantSyncService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessBulkStaffAddJob implements ShouldQueue
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
        private string $role,
        private int $adminId
    ) {
        $this->onQueue(QueueNames::OSU_ADMIN_PRIORITY);
    }

    public function handle(TournamentParticipantSyncService $syncService): void
    {
        $operation = AdminAsyncOperation::query()->findOrFail($this->operationId);
        $tournament = Tournament::query()->findOrFail($this->tournamentId);
        $admin = User::query()->find($this->adminId);

        $operation->start('Adding staff from osu! profiles...');

        $successes = [];
        $failures = [];

        foreach ($this->usernames as $username) {
            try {
                $staff = $syncService->addStaffByUsername($tournament, $username, $this->role, $this->adminId);

                $successes[] = [
                    'staff_id' => $staff->id,
                    'user_id' => $staff->user_id,
                    'username' => $staff->user->username,
                    'role' => $this->role,
                ];

                $operation->advance("Added {$staff->user->username}");
            } catch (\Throwable $e) {
                $failures[] = [
                    'username' => $username,
                    'message' => $e->getMessage(),
                ];

                $operation->recordItemError([
                    'username' => $username,
                    'message' => $e->getMessage(),
                ], "Failed to add {$username}");

                Log::warning('Bulk staff add item failed', [
                    'operation_id' => $operation->id,
                    'tournament_id' => $tournament->id,
                    'username' => $username,
                    'role' => $this->role,
                    'error' => $e->getMessage(),
                ]);

                $operation->advance("Skipped {$username}");
            }
        }

        $operation->finish('Staff bulk add complete', [
            'refresh' => ['staff'],
        ]);

        if ($admin) {
            AdminAuditLog::log($admin, 'tournament.staff_bulk_added', $tournament, [
                'operation_id' => $operation->id,
                'role' => $this->role,
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
            ?->fail('Staff bulk add failed', $exception);
    }
}
