<?php

namespace App\Services;

use App\Models\SipFetchQueue;
use App\Models\TournamentWinner;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SipService
{
    /**
     * Queue a single user for SIP fetch.
     */
    public function queueUserForSipFetch(User $user): void
    {
        // Only queue osu!standard players
        if ($user->main_mode !== 'osu') {
            return;
        }

        // Check if already queued recently (last 7 days)
        $existing = SipFetchQueue::where('osu_id', $user->osu_id)
            ->where('created_at', '>', now()->subDays(7))
            ->exists();

        if ($existing) {
            return;
        }

        // Add to queue
        SipFetchQueue::create([
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'status' => 'pending',
        ]);

        Log::info("Queued {$user->username} for SIP fetch");
    }

    /**
     * Queue all podium users from a specific year for SIP fetch.
     */
    public function queuePodiumUsers(int $year): int
    {
        return $this->queuePodiumUsersRange($year, $year);
    }

    /**
     * Queue all podium users from a year range for SIP fetch.
     */
    public function queuePodiumUsersRange(int $startYear, int $endYear): int
    {
        // Get user_ids from tournament_winners where tournament ended in the specified year range
        $winnerUserIds = TournamentWinner::whereHas('tournament', function ($query) use ($startYear, $endYear) {
            $query->whereYear('tournament_end', '>=', $startYear)
                ->whereYear('tournament_end', '<=', $endYear);
        })
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        $users = User::whereIn('id', $winnerUserIds)
            ->where('main_mode', 'osu')
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $this->queueUserForSipFetch($user);
            $count++;
        }

        $yearRange = $startYear === $endYear ? $startYear : "{$startYear}-{$endYear}";
        Log::info("Queued {$count} osu!standard podium users from {$yearRange} for SIP fetch");

        return $count;
    }

    /**
     * Queue a specific user by osu_id for SIP fetch.
     */
    public function queueUserByOsuId(int $osuId): ?SipFetchQueue
    {
        $user = User::where('osu_id', $osuId)->first();

        if (! $user || $user->main_mode !== 'osu') {
            return null;
        }

        // Check if already queued recently
        $existing = SipFetchQueue::where('osu_id', $osuId)
            ->where('created_at', '>', now()->subDays(7))
            ->first();

        if ($existing) {
            return $existing;
        }

        return SipFetchQueue::create([
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'status' => 'pending',
        ]);
    }

    /**
     * Get pending queue count.
     */
    public function getPendingCount(): int
    {
        return SipFetchQueue::where('status', 'pending')->count();
    }

    /**
     * Get failed queue items.
     *
     * @return Collection<int, SipFetchQueue>
     */
    public function getFailedItems(): Collection
    {
        return SipFetchQueue::where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }

    /**
     * Retry failed queue items.
     *
     * @return int Number of items retried
     */
    public function retryFailed(int $limit = 50): int
    {
        /** @var Collection<int, SipFetchQueue> $failedItems */
        $failedItems = SipFetchQueue::where('status', 'failed')
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($failedItems as $item) {
            $item->update(['status' => 'pending', 'error_message' => null]);
            $count++;
        }

        Log::info("Retrying {$count} failed SIP fetch items");

        return $count;
    }
}
