<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserRankHistory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class TournamentParticipantSyncService
{
    public function __construct(
        private OsuApiService $osuApi,
        private UserProfileSyncService $profileSyncService,
        private ?ExactUsernameResolver $usernameResolver = null,
    ) {}

    public function resolveUserByUsername(string $username, bool $syncStaffMetadata = true): User
    {
        $localUser = $this->findLocalUserByUsername($username);
        if ($localUser && $this->hasStaffFlag($localUser)) {
            return $localUser;
        }

        if ($localUser && $localUser->osu_id) {
            $apiUser = $this->osuApi->getUser($localUser->osu_id);
        } else {
            $apiUser = $this->osuApi->getUserByUsername($username);
        }

        if (! $apiUser || ! isset($apiUser['id'], $apiUser['username'])) {
            throw new \RuntimeException("User '{$username}' not found on osu!");
        }

        $user = $this->createOrRestoreUserFromApiData($apiUser);

        return $this->profileSyncService->syncBasicProfileFromApiData(
            $user,
            $apiUser,
            syncStaffGamemode: $syncStaffMetadata
        );
    }

    public function resolveStaffUserByUsername(string $username): User
    {
        return $this->resolveUserByUsername($username);
    }

    public function resolvePodiumUserByUsername(Tournament $tournament, string $username): User
    {
        $mode = $this->firstTournamentMode($tournament);
        $localUser = $this->findLocalUserByUsername($username);

        if ($localUser && $this->hasSufficientRankForTournament($localUser, $tournament)) {
            return $localUser;
        }

        if ($localUser && $localUser->osu_id) {
            $mainMode = $localUser->main_mode ?: $mode;
            $apiUser = $this->osuApi->getUserForMode($localUser->osu_id, $mainMode);
        } else {
            $apiUser = $this->osuApi->getUserByUsername($username);
        }

        if (! $apiUser || ! isset($apiUser['id'], $apiUser['username'])) {
            throw new \RuntimeException("User '{$username}' not found on osu!");
        }

        $user = $this->createOrRestoreUserFromApiData($apiUser);

        return $this->profileSyncService->syncBasicProfileFromApiData(
            $user,
            $apiUser,
            syncStaffGamemode: false
        );
    }

    public function resolvePodiumUserByOsuId(Tournament $tournament, int $osuId): User
    {
        $mode = $this->firstTournamentMode($tournament);
        $localUser = User::withTrashed()
            ->where('osu_id', $osuId)
            ->first();

        if ($localUser) {
            if ($localUser->trashed()) {
                $localUser->restore();
                $localUser = $localUser->fresh();
            }

            if ($this->hasSufficientRankForTournament($localUser, $tournament)) {
                return $localUser;
            }
        }

        $apiUser = $this->osuApi->getUserForMode($osuId, $mode);

        if (! $apiUser || ! isset($apiUser['id'], $apiUser['username'])) {
            throw new \RuntimeException("osu! user '{$osuId}' not found");
        }

        $user = $this->createOrRestoreUserFromApiData($apiUser);

        return $this->profileSyncService->syncBasicProfileFromApiData(
            $user,
            $apiUser,
            syncStaffGamemode: false
        );
    }

    public function addStaffByUsername(Tournament $tournament, string $username, string $role, int $adminId): TournamentStaff
    {
        $user = $this->resolveStaffUserByUsername($username);

        $existing = TournamentStaff::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->first();

        if ($existing) {
            throw new \RuntimeException("{$user->username} already has role '{$role}'");
        }

        $staff = TournamentStaff::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'approved',
            'source' => 'manual',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
        ]);

        $this->profileSyncService->syncStaffProfileMetadata($user->fresh());

        return $staff->load('user');
    }

    public function addPodiumByUsername(
        Tournament $tournament,
        string $username,
        int $placement,
        ?string $podiumGroupId = null,
        bool $backfill = true,
        bool $syncProfile = true
    ): TournamentWinner {
        $user = $this->resolvePodiumUserByUsername($tournament, $username);
        $podiumBackfill = app(ParticipationPodiumBackfillService::class);
        $podiumGroupId ??= $podiumBackfill->defaultGroupIdForNewWinner($tournament, $placement);

        $existing = TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->where('placement', $placement)
            ->first();

        if ($existing) {
            throw new \RuntimeException("User '{$user->username}' already exists in placement {$placement}");
        }

        $winner = TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'placement' => $placement,
            'username' => $user->username,
            'osu_id' => $user->osu_id,
            'gamemode' => $this->firstTournamentMode($tournament),
            'metadata' => [
                'podium_group_id' => $podiumGroupId,
                'podium_group_manual' => true,
            ],
        ]);

        if ($syncProfile && ! $this->hasSufficientRankForTournament($user->fresh(), $tournament)) {
            $year = (int) $tournament->tournament_end->year;
            $this->profileSyncService->syncWinnerProfile($user->fresh(), $year, $year);
        }

        $freshUser = $user->fresh();
        if ($backfill && $freshUser instanceof User) {
            $podiumBackfill->backfillFor($freshUser);
            $podiumBackfill->backfillTournamentGroup($tournament->id, $podiumGroupId);
        }

        return $winner->fresh()->load('user');
    }

    /**
     * @param  array<string, mixed>  $apiUser
     */
    private function createOrRestoreUserFromApiData(array $apiUser): User
    {
        $maxRetries = 2;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $user = User::withTrashed()
                    ->where('osu_id', $apiUser['id'])
                    ->first();

                if ($user) {
                    if ($user->trashed()) {
                        $user->restore();
                    }

                    return $user;
                }

                return User::query()->create([
                    'osu_id' => $apiUser['id'],
                    'username' => $apiUser['username'],
                    'role' => 'player',
                ]);
            } catch (QueryException $e) {
                $lastException = $e;

                if (! str_contains($e->getMessage(), 'users_osu_id_unique')) {
                    throw $e;
                }

                if ($attempt < $maxRetries) {
                    usleep(100000);
                }
            }
        }

        Log::error('Failed to create or restore osu! user', [
            'osu_id' => $apiUser['id'] ?? null,
            'username' => $apiUser['username'] ?? null,
            'exception' => $lastException,
        ]);

        throw $lastException;
    }

    private function firstTournamentMode(Tournament $tournament): string
    {
        $mode = $tournament->modes_with_details
            ->pluck('mode')
            ->filter()
            ->first();

        return (string) ($mode ?: 'osu');
    }

    /**
     * @return array{mode: string, key_count: int|null}
     */
    private function firstTournamentModeDetail(Tournament $tournament): array
    {
        $modeDetail = $tournament->modes_with_details
            ->filter(fn (array $modeDetail) => filled($modeDetail['mode']))
            ->first();

        if (! $modeDetail) {
            return [
                'mode' => 'osu',
                'key_count' => null,
            ];
        }

        return [
            'mode' => (string) $modeDetail['mode'],
            'key_count' => $modeDetail['key_count'] ?? null,
        ];
    }

    private function findLocalUserByUsername(string $username): ?User
    {
        $user = ($this->usernameResolver ?? app(ExactUsernameResolver::class))
            ->resolve($username, withTrashed: true);

        if ($user && $user->trashed()) {
            $user->restore();

            return $user->fresh();
        }

        return $user;
    }

    private function hasStaffFlag(User $user): bool
    {
        return filled($user->country_code);
    }

    private function hasSufficientRankForTournament(User $user, Tournament $tournament): bool
    {
        $modeDetail = $this->firstTournamentModeDetail($tournament);
        $mode = $modeDetail['mode'];
        $keyCount = $modeDetail['key_count'];

        if ($mode === 'mania' && $keyCount === 4) {
            return filled($user->rank_mania_4k);
        }

        if ($mode === 'mania' && $keyCount === 7) {
            return filled($user->rank_mania_7k);
        }

        return $this->hasRankForMode($user, $mode);
    }

    private function hasRankForMode(User $user, string $mode): bool
    {
        return UserRankHistory::query()
            ->where('user_id', $user->id)
            ->where('mode', $mode)
            ->whereNotNull('rank')
            ->exists();
    }
}
