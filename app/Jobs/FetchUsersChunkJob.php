<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BatchTransactionService;
use App\Services\OsuApiService;
use App\Services\UserProfileSyncService;
use App\Support\QueueNames;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchUsersChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CHUNK_SIZE = 50;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int<0, max>>
     */
    public array $backoff = [60, 120, 240];

    /**
     * @param  array<int, int>  $osuIds
     */
    public function __construct(
        private string $parseBatchId,
        private array $osuIds,
        string $queueName = QueueNames::OSU_ADMIN
    ) {
        $this->onQueue($queueName);
    }

    public function handle(
        OsuApiService $osuApi,
        BatchTransactionService $transaction,
        ?UserProfileSyncService $profileSyncService = null
    ): void {
        $profileSyncService ??= app(UserProfileSyncService::class);

        $osuIds = array_values(array_unique($this->osuIds));

        Log::info('FetchUsersChunkJob: Fetching users from osu! API', [
            'transaction_id' => $this->parseBatchId,
            'chunk_size' => count($osuIds),
            'sample_osu_ids' => array_slice($osuIds, 0, 10),
        ]);

        if (empty($osuIds)) {
            return;
        }

        $usersData = $osuApi->getUsers($osuIds);
        $userMapping = [];
        $restoredCount = 0;

        foreach ($usersData as $user) {
            if (! isset($user['id'], $user['username'])) {
                continue;
            }

            $existingUser = User::withTrashed()
                ->where('osu_id', $user['id'])
                ->first();

            if ($existingUser) {
                if ($existingUser->trashed()) {
                    $existingUser->restore();
                    $restoredCount++;
                }

                $existingUser = $profileSyncService->syncBasicProfileFromApiData(
                    $existingUser,
                    $user,
                    syncStaffGamemode: false
                );

                $userMapping[(int) $user['id']] = $existingUser->id;

                continue;
            }

            $newUser = User::create([
                'osu_id' => $user['id'],
                'username' => $user['username'],
            ]);

            $newUser = $profileSyncService->syncBasicProfileFromApiData(
                $newUser,
                $user,
                syncStaffGamemode: false
            );

            $userMapping[(int) $user['id']] = $newUser->id;
        }

        $transaction->mergeUserMapping($this->parseBatchId, $userMapping);

        $missingIds = array_diff($osuIds, array_keys($userMapping));

        if (! empty($missingIds)) {
            Log::warning('FetchUsersChunkJob: Some users were not returned by API', [
                'transaction_id' => $this->parseBatchId,
                'requested_count' => count($osuIds),
                'synced_count' => count($userMapping),
                'missing_count' => count($missingIds),
                'missing_ids' => array_slice($missingIds, 0, 20),
            ]);
        }

        Log::info('FetchUsersChunkJob: Users synced successfully', [
            'transaction_id' => $this->parseBatchId,
            'users_synced' => count($userMapping),
            'users_requested' => count($osuIds),
            'users_missing' => count($missingIds),
            'restored_count' => $restoredCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('FetchUsersChunkJob failed permanently', [
            'transaction_id' => $this->parseBatchId,
            'chunk_size' => count($this->osuIds),
            'error' => $exception->getMessage(),
        ]);

        app(BatchTransactionService::class)
            ->recordError($this->parseBatchId, 'users_chunk', $exception->getMessage());
    }
}
