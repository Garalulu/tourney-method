<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;

class OrphanedUserCleanupService
{
    /**
     * @param  array<string>  $whitelist
     * @return Builder<User>
     */
    public function query(array $whitelist = [], int $minAge = 0): Builder
    {
        $protectedRoles = Config::get('user-cleanup.protection_rules.roles', ['admin', 'master']);

        $query = User::query()
            ->whereNotIn('role', $protectedRoles)
            ->whereNull('deleted_at')
            ->whereDoesntHave('tournamentWinners')
            ->whereDoesntHave('tournamentParticipationRecords')
            ->whereDoesntHave('tournamentCorrections')
            ->whereDoesntHave('tournaments')
            ->whereDoesntHave('tournamentsHosted')
            ->where(function (Builder $q) {
                $q->where('main_mode_source', '!=', 'oauth_setup')
                    ->orWhereNull('main_mode_source');
            })
            ->whereNotIn('username', $whitelist);

        if ($minAge > 0) {
            $query->where('created_at', '<=', now()->subDays($minAge));
        }

        return $query;
    }

    /**
     * @param  array<string>  $whitelist
     */
    public function isProtected(User $user, array $whitelist = []): bool
    {
        $protectedRoles = Config::get('user-cleanup.protection_rules.roles', ['admin', 'master']);

        if (in_array($user->role, $protectedRoles, true)) {
            return true;
        }

        if ($user->main_mode_source === 'oauth_setup') {
            return true;
        }

        if ($user->tournamentParticipationRecords()->exists()) {
            return true;
        }

        if ($user->tournamentCorrections()->exists()) {
            return true;
        }

        return in_array($user->username, $whitelist, true);
    }
}
