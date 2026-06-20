<?php

namespace App\Services;

use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserRankHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserProfileSyncService
{
    public function __construct(
        private OsuApiService $osuApi,
        private SipService $sipService,
    ) {}

    /**
     * Sync basic profile fields for users that do not need full badge/rank data.
     *
     * @param  Collection<int, User>  $users
     * @return array{synced: int, failed: int}
     */
    public function syncBasicProfiles(Collection $users): array
    {
        $synced = 0;
        $failed = 0;

        $users->chunk(50)->each(function (Collection $chunk) use (&$synced, &$failed) {
            try {
                $apiUsers = $this->osuApi->getUsers(
                    $chunk->pluck('osu_id')->map(fn ($id) => (string) $id)->toArray()
                );

                foreach ($apiUsers as $apiUser) {
                    $user = $chunk->firstWhere('osu_id', $apiUser['id']);

                    if (! $user) {
                        continue;
                    }

                    $this->syncBasicProfileFromApiData($user, $apiUser);
                    $synced++;
                }
            } catch (\Throwable $e) {
                $failed += $chunk->count();

                Log::error('Failed to sync basic user profile batch', [
                    'error' => $e->getMessage(),
                    'user_ids' => $chunk->pluck('id')->all(),
                    'exception' => $e,
                ]);
            }

        });

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Apply the same basic profile fields used by staff/host sync to one API user payload.
     *
     * @param  array<string, mixed>  $apiUser
     */
    public function syncBasicProfileFromApiData(User $user, array $apiUser, bool $syncStaffGamemode = true): User
    {
        $newUsername = (string) $apiUser['username'];

        $user->update([
            'username' => $newUsername,
            'country_code' => $apiUser['country_code'] ?? $user->country_code,
            'previous_usernames' => $apiUser['previous_usernames'] ?? $user->previous_usernames,
            'osu_data_synced_at' => now(),
        ]);

        $freshUser = $user->fresh();

        if ($syncStaffGamemode) {
            $this->syncStaffGamemode($freshUser);
            $freshUser = $freshUser->fresh();
        }

        $this->syncHostUsernameInTournaments($freshUser, $newUsername);

        return $freshUser;
    }

    public function syncStaffProfileMetadata(User $user): User
    {
        $freshUser = $user->fresh();
        $this->syncStaffGamemode($freshUser);

        return $freshUser->fresh();
    }

    /**
     * Sync full osu! profile data for a tournament winner.
     *
     * @return array{username: string, main_mode: string|null, new_badges: int}
     */
    public function syncWinnerProfile(
        User $user,
        int $startYear,
        int $endYear,
        bool $skipSip = false,
        ?AdminMaintenanceRun $maintenanceRun = null
    ): array {
        $user = $user->fresh();

        if ($user->main_mode !== null) {
            $this->syncUserGamemode($user);
            $user = $user->fresh();
        }

        $mainMode = $user->main_mode;
        $userData = $mainMode === null
            ? $this->osuApi->getUser($user->osu_id)
            : $this->osuApi->getUserForMode($user->osu_id, $mainMode);

        if ($mainMode === null) {
            $mainMode = $this->convertApiModeToAppMode((string) ($userData['playmode'] ?? 'osu'));

            $user->update([
                'main_mode' => $mainMode,
                'main_mode_source' => 'auto_detected',
            ]);
        }

        $newUsername = $userData['username'];

        $user->update([
            'username' => $newUsername,
            'country_code' => $userData['country_code'] ?? $user->country_code,
            'previous_usernames' => $userData['previous_usernames'] ?? null,
            'osu_data_synced_at' => now(),
        ]);

        $newBadges = $this->syncUserBadges($user->fresh(), $userData['badges'] ?? []);
        $this->syncCurrentMainModeRankFromUserData($user->fresh(), $mainMode, $userData);
        $this->syncManiaVariantRanksFromUserData($user->fresh(), $mainMode, $userData);

        $freshUser = $user->fresh();
        $this->syncHostUsernameInTournaments($freshUser, $newUsername);

        if ($freshUser->main_mode === 'osu' && ! $skipSip) {
            $this->sipService->queueUserForSipFetch($freshUser);
        }

        if ($newBadges > 0) {
            app(AdminMaintenanceRunRecorder::class)->recordUser(
                $maintenanceRun,
                $freshUser,
                AdminMaintenanceRunItem::ACTION_NEW_BADGES,
                ['new_badges' => $newBadges]
            );
        }

        return [
            'username' => $freshUser->username,
            'main_mode' => $freshUser->main_mode,
            'new_badges' => $newBadges,
        ];
    }

    /**
     * Save every badge returned by osu! as user-owned source data.
     *
     * @param  array<int, array<string, mixed>>  $badges
     */
    private function syncUserBadges(User $user, array $badges): int
    {
        $apiBadgeKeys = [];
        $newBadges = 0;

        foreach ($badges as $badge) {
            if (empty($badge['description']) || empty($badge['awarded_at'])) {
                continue;
            }

            $awardedAt = Carbon::parse($badge['awarded_at']);
            $apiBadgeKeys[] = $this->badgeKey($badge['description'], $awardedAt);

            $userBadge = $user->badges()->firstOrNew([
                'name' => $badge['description'],
                'awarded_at' => $awardedAt,
            ]);

            $userBadge->fill([
                'badge_url' => $badge['url'] ?? null,
                'image_url' => $badge['image_url'] ?? null,
                'image_2x_url' => $badge['image@2x_url'] ?? null,
            ]);

            if (! $userBadge->exists) {
                $userBadge->tournament_id = null;
                $userBadge->is_bws_eligible = false;
                $newBadges++;
            }

            $userBadge->save();
        }

        $user->badges()
            ->get()
            ->reject(fn ($badge) => in_array($this->badgeKey($badge->name, $badge->awarded_at), $apiBadgeKeys, true))
            ->each
            ->delete();

        Log::info('Synced source badge data for user', [
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'badge_count' => count($apiBadgeKeys),
            'new_badges' => $newBadges,
        ]);

        return $newBadges;
    }

    private function badgeKey(string $name, Carbon $awardedAt): string
    {
        return $name.'|'.$awardedAt->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>  $userData
     */
    private function syncCurrentMainModeRankFromUserData(User $user, string $mainMode, array $userData): void
    {
        UserRankHistory::updateOrCreate(
            ['user_id' => $user->id, 'mode' => $this->convertApiModeToAppMode($mainMode)],
            [
                'rank' => $userData['statistics']['global_rank'] ?? null,
                'country_rank' => $userData['statistics']['country_rank'] ?? null,
                'pp' => $userData['statistics']['pp'] ?? null,
                'recorded_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $userData
     */
    private function syncManiaVariantRanksFromUserData(User $user, string $mainMode, array $userData): void
    {
        if ($this->convertApiModeToAppMode($mainMode) !== 'mania') {
            return;
        }

        $variants = $this->extractManiaVariantStats($userData['statistics']['variants'] ?? []);

        $user->update([
            'rank_mania_4k' => $variants['4k']['global_rank'],
            'rank_mania_7k' => $variants['7k']['global_rank'],
        ]);

        foreach ($variants as $variant => $stats) {
            UserRankHistory::updateOrCreate(
                ['user_id' => $user->id, 'mode' => $variant],
                [
                    'rank' => $stats['global_rank'],
                    'country_rank' => null,
                    'pp' => $stats['pp'],
                    'recorded_at' => now(),
                ]
            );
        }
    }

    /**
     * @return array{'4k': array{global_rank: int|null, pp: float|null}, '7k': array{global_rank: int|null, pp: float|null}}
     */
    private function extractManiaVariantStats(mixed $variants): array
    {
        $stats = [
            '4k' => ['global_rank' => null, 'pp' => null],
            '7k' => ['global_rank' => null, 'pp' => null],
        ];

        if (! is_array($variants)) {
            return $stats;
        }

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $variantKey = $variant['variant'] ?? null;

            if (! in_array($variantKey, ['4k', '7k'], true)) {
                continue;
            }

            $stats[$variantKey] = [
                'global_rank' => isset($variant['global_rank']) ? (int) $variant['global_rank'] : null,
                'pp' => isset($variant['pp']) ? (float) $variant['pp'] : null,
            ];
        }

        return $stats;
    }

    private function syncUserGamemode(User $user): void
    {
        if ($user->main_mode_source === 'oauth_setup') {
            return;
        }

        $podiums = TournamentWinner::query()
            ->where('user_id', $user->id)
            ->where('placement', '<=', 3)
            ->whereHas('tournament', fn ($query) => $query->where('status', 'approved'))
            ->with('tournament')
            ->get();

        $modeCounts = $podiums
            ->map(fn (TournamentWinner $winner) => $this->getTournamentPodiumMode($winner))
            ->filter()
            ->countBy();

        if ($modeCounts->isEmpty()) {
            return;
        }

        $currentModeCount = $user->main_mode ? (int) ($modeCounts[$user->main_mode] ?? 0) : 0;
        $highestCount = (int) $modeCounts->max();

        if ($currentModeCount === $highestCount && $user->main_mode !== null) {
            return;
        }

        $mode = (string) $modeCounts
            ->sortDesc()
            ->keys()
            ->first();

        if ($user->main_mode !== $mode) {
            $user->update([
                'main_mode' => $mode,
                'main_mode_source' => 'auto_detected',
            ]);
        }
    }

    private function getTournamentPodiumMode(TournamentWinner $winner): string
    {
        $tournamentMode = $winner->tournament->modes_with_details
            ->pluck('mode')
            ->filter()
            ->map(fn (string $mode) => $this->convertApiModeToAppMode($mode))
            ->first();

        return $tournamentMode ?: $this->convertApiModeToAppMode($winner->gamemode);
    }

    private function syncStaffGamemode(User $user): void
    {
        if ($user->main_mode_source === 'oauth_setup') {
            return;
        }

        $modeCounts = $user->staff()
            ->wherePivot('status', 'approved')
            ->get()
            ->flatMap(fn ($tournament) => $tournament->modes_with_details->pluck('mode'))
            ->filter()
            ->map(fn (string $mode) => $this->convertApiModeToAppMode($mode))
            ->countBy();

        if ($modeCounts->isEmpty()) {
            return;
        }

        $currentModeCount = $user->main_mode ? (int) ($modeCounts[$user->main_mode] ?? 0) : 0;
        $highestCount = (int) $modeCounts->max();

        if ($currentModeCount === $highestCount && $user->main_mode !== null) {
            return;
        }

        $mode = (string) $modeCounts
            ->sortDesc()
            ->keys()
            ->first();

        if ($user->main_mode !== $mode) {
            $user->update([
                'main_mode' => $mode,
                'main_mode_source' => 'auto_detected',
            ]);
        }
    }

    private function syncHostUsernameInTournaments(User $user, string $newUsername): int
    {
        return DB::table('tournaments')
            ->where('host_osu_id', $user->osu_id)
            ->where('host_username', '!=', $newUsername)
            ->update(['host_username' => $newUsername]);
    }

    private function convertApiModeToAppMode(string $apiMode): string
    {
        return match ($apiMode) {
            'fruits' => 'catch',
            default => $apiMode,
        };
    }
}
