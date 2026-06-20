<?php

namespace App\Jobs;

use App\Models\AdminMaintenanceRun;
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

class SyncUserProfilesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 240];

    public int $timeout = 300;

    public function __construct(
        private string $type,
        private int $startYear,
        private int $endYear,
        private bool $skipSip = false,
        private bool $essentialOnly = false,
        private int $offset = 0,
        private int $limit = 100,
        private ?int $maintenanceRunId = null
    ) {
        $this->onQueue(QueueNames::OSU_ADMIN);
    }

    public function handle(UserProfileSyncService $profileSyncService): void
    {
        Log::info('Processing user profile sync chunk', [
            'batch_id' => $this->batch()?->id,
            'type' => $this->type,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'start_year' => $this->startYear,
            'end_year' => $this->endYear,
            'essential_only' => $this->essentialOnly,
        ]);

        $users = User::getTournamentUsers($this->type, $this->startYear, $this->endYear, $this->essentialOnly)
            ->slice($this->offset, $this->limit)
            ->values();

        if ($users->isEmpty()) {
            Log::warning('No users found for sync criteria', [
                'type' => $this->type,
                'start_year' => $this->startYear,
                'end_year' => $this->endYear,
                'essential_only' => $this->essentialOnly,
                'offset' => $this->offset,
                'limit' => $this->limit,
            ]);

            return;
        }

        $winners = $users->filter(fn ($u) => $u->tournamentWinners()->exists());
        $staffAndHosts = $users->filter(fn ($u) => ! $u->tournamentWinners()->exists());

        $totalSynced = 0;
        $totalFailed = 0;
        $newBadgeUsers = 0;
        $newBadges = 0;
        $maintenanceRun = $this->maintenanceRunId
            ? AdminMaintenanceRun::query()->find($this->maintenanceRunId)
            : null;

        if ($staffAndHosts->isNotEmpty()) {
            $result = $profileSyncService->syncBasicProfiles($staffAndHosts);
            $totalSynced += $result['synced'];
            $totalFailed += $result['failed'];
        }

        if ($winners->isNotEmpty()) {
            $result = $this->syncWinnerProfiles($profileSyncService, $winners, $maintenanceRun);
            $totalSynced += $result['synced'];
            $totalFailed += $result['failed'];
            $newBadgeUsers += $result['new_badge_users'];
            $newBadges += $result['new_badges'];
        }

        app(AdminMaintenanceRunRecorder::class)->incrementSummary($maintenanceRun, [
            'processed' => $users->count(),
            'failed' => $totalFailed,
            'new_badge_users' => $newBadgeUsers,
            'new_badges' => $newBadges,
        ]);

        Log::info('User profile sync chunk completed', [
            'type' => $this->type,
            'total_users' => $users->count(),
            'total_synced' => $totalSynced,
            'total_failed' => $totalFailed,
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
        ?AdminMaintenanceRun $maintenanceRun = null
    ): array {
        $synced = 0;
        $failed = 0;
        $newBadgeUsers = 0;
        $newBadges = 0;

        foreach ($users as $user) {
            try {
                $result = $profileSyncService->syncWinnerProfile($user, $this->startYear, $this->endYear, $this->skipSip, $maintenanceRun);
                if ($result['new_badges'] > 0) {
                    $newBadgeUsers++;
                    $newBadges += $result['new_badges'];
                }
                $synced++;
            } catch (\Throwable $e) {
                $failed++;

                Log::error('Failed to sync winner profile', [
                    'user_id' => $user->id,
                    'osu_id' => $user->osu_id,
                    'username' => $user->username,
                    'error' => $e->getMessage(),
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
            'type' => $this->type,
            'offset' => $this->offset,
            'limit' => $this->limit,
        ]);
    }
}
