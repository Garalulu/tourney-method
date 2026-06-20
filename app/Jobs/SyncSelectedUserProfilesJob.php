<?php

namespace App\Jobs;

use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use App\Models\User;
use App\Services\AdminMaintenanceRunRecorder;
use App\Services\UserProfileSyncService;
use App\Support\QueueNames;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SyncSelectedUserProfilesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 240];

    public int $timeout = 300;

    /**
     * @param  array<int>  $userIds
     */
    public function __construct(
        private array $userIds,
        private int $startYear,
        private int $endYear,
        private bool $skipSip = false,
        private ?int $maintenanceRunId = null
    ) {
        $this->onQueue(QueueNames::OSU_ADMIN);
    }

    public function handle(UserProfileSyncService $profileSyncService, AdminMaintenanceRunRecorder $recorder): void
    {
        $users = User::query()
            ->whereIn('id', $this->userIds)
            ->get();

        if ($users->isEmpty()) {
            $recorder->incrementSummary($this->maintenanceRunId, [
                'failed' => count($this->userIds),
            ]);

            return;
        }

        $maintenanceRun = $this->maintenanceRunId
            ? AdminMaintenanceRun::query()->find($this->maintenanceRunId)
            : null;

        $winners = $users->filter(fn (User $user) => $user->tournamentWinners()->exists());
        $staffAndHosts = $users->filter(fn (User $user) => ! $user->tournamentWinners()->exists());
        $synced = 0;
        $failed = 0;
        $newBadgeUsers = 0;
        $newBadges = 0;

        if ($staffAndHosts->isNotEmpty()) {
            $result = $profileSyncService->syncBasicProfiles($staffAndHosts);
            $synced += $result['synced'];
            $failed += $result['failed'];

            $staffAndHosts->each(fn (User $user) => $recorder->recordUser(
                $maintenanceRun,
                $user,
                AdminMaintenanceRunItem::ACTION_SYNCED,
                ['sync_type' => 'basic']
            ));
        }

        if ($winners->isNotEmpty()) {
            $result = $this->syncWinnerProfiles($profileSyncService, $winners, $maintenanceRun, $recorder);
            $synced += $result['synced'];
            $failed += $result['failed'];
            $newBadgeUsers += $result['new_badge_users'];
            $newBadges += $result['new_badges'];
        }

        $recorder->incrementSummary($maintenanceRun, [
            'processed' => $users->count(),
            'synced' => $synced,
            'failed' => $failed,
            'new_badge_users' => $newBadgeUsers,
            'new_badges' => $newBadges,
        ]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{synced: int, failed: int, new_badge_users: int, new_badges: int}
     */
    private function syncWinnerProfiles(
        UserProfileSyncService $profileSyncService,
        Collection $users,
        ?AdminMaintenanceRun $maintenanceRun,
        AdminMaintenanceRunRecorder $recorder
    ): array {
        $synced = 0;
        $failed = 0;
        $newBadgeUsers = 0;
        $newBadges = 0;

        foreach ($users as $user) {
            try {
                $result = $profileSyncService->syncWinnerProfile($user, $this->startYear, $this->endYear, $this->skipSip, $maintenanceRun);
                $synced++;

                if ($result['new_badges'] > 0) {
                    $newBadgeUsers++;
                    $newBadges += $result['new_badges'];
                }

                $recorder->recordUser(
                    $maintenanceRun,
                    $user->fresh(),
                    AdminMaintenanceRunItem::ACTION_SYNCED,
                    ['sync_type' => 'winner', 'new_badges' => $result['new_badges']]
                );
            } catch (\Throwable $e) {
                $failed++;
                $recorder->recordError($maintenanceRun, $e->getMessage(), [
                    'user_id' => $user->id,
                    'osu_id' => $user->osu_id,
                    'username' => $user->username,
                ]);

                Log::error('Failed to sync selected winner profile', [
                    'user_id' => $user->id,
                    'osu_id' => $user->osu_id,
                    'username' => $user->username,
                    'exception' => $e,
                ]);
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'new_badge_users' => $newBadgeUsers,
            'new_badges' => $newBadges,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        app(AdminMaintenanceRunRecorder::class)->recordError($this->maintenanceRunId, $exception->getMessage(), [
            'user_ids' => $this->userIds,
        ]);
    }
}
