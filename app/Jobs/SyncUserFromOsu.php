<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OsuApiService;
use App\Support\QueueNames;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncUserFromOsu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Exponential backoff: 60s, 120s, 240s
     *
     * @var array<int>
     */
    public array $backoff = [60, 120, 240];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user
    ) {
        $this->onQueue(QueueNames::OSU_USER);
    }

    /**
     * Execute the job.
     */
    public function handle(OsuApiService $osuApiService): void
    {
        try {
            Log::info('Syncing user data from osu!', [
                'user_id' => $this->user->id,
                'osu_id' => $this->user->osu_id,
            ]);

            // Sync full user data including badges, rank history, and stats
            $osuApiService->syncUserData($this->user);

            Log::info('User data synced successfully', [
                'user_id' => $this->user->id,
                'osu_id' => $this->user->osu_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync user data from osu!', [
                'user_id' => $this->user->id,
                'osu_id' => $this->user->osu_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('User sync job failed permanently', [
            'user_id' => $this->user->id,
            'osu_id' => $this->user->osu_id,
            'error' => $exception->getMessage(),
        ]);

        // Could send notification to admin or mark user as needing manual sync
    }
}
