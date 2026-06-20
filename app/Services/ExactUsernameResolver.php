<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ExactUsernameResolver
{
    public function resolve(string $username, bool $withTrashed = false): ?User
    {
        $username = trim($username);

        if ($username === '') {
            return null;
        }

        /** @var User|null $currentMatch */
        $currentMatch = $this->query($withTrashed)
            ->whereRaw('LOWER(username) = ?', [Str::lower($username)])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($currentMatch) {
            return $currentMatch;
        }

        $needle = Str::lower($username);
        $encodedNeedle = json_encode($needle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $previousQuery = $this->query($withTrashed)
            ->whereNotNull('previous_usernames');

        if (is_string($encodedNeedle)) {
            $previousQuery->whereRaw(
                'LOWER(previous_usernames::text) LIKE ?',
                ['%'.addcslashes($encodedNeedle, '%_\\').'%']
            );
        }

        /** @var Collection<int, User> $previousMatches */
        $previousMatches = $previousQuery
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return $previousMatches->first(fn (User $user): bool => collect($user->previous_usernames ?? [])
            ->contains(fn (string $previousUsername): bool => Str::lower($previousUsername) === $needle));
    }

    /**
     * @return Builder<User>
     */
    private function query(bool $withTrashed): Builder
    {
        /** @var Builder<User> $query */
        $query = $withTrashed ? User::withTrashed() : User::query();

        return $query;
    }
}
