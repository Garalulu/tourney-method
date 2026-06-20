<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ParticipationPodiumBackfillService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BackfillPodiumParticipationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    /**
     * @param  list<int>  $userIds
     * @param  list<array{tournament_id: int, group_id: string, replace_team_name?: bool, team_name?: string|null}>  $tournamentGroups
     */
    public function __construct(
        private array $userIds = [],
        private array $tournamentGroups = [],
    ) {
        $this->onQueue(QueueNames::OSU_ADMIN_PRIORITY);
    }

    public function handle(ParticipationPodiumBackfillService $podiumBackfill): void
    {
        collect($this->userIds)
            ->map(fn ($userId): int => (int) $userId)
            ->filter()
            ->unique()
            ->each(function (int $userId) use ($podiumBackfill): void {
                $user = User::query()->find($userId);
                if ($user instanceof User) {
                    $podiumBackfill->backfillFor($user);
                }
            });

        collect($this->tournamentGroups)
            ->filter(fn (array $group): bool => filled($group['tournament_id'])
                && filled($group['group_id']))
            ->unique(fn (array $group): string => ((int) $group['tournament_id']).':'.((string) $group['group_id']))
            ->each(function (array $group) use ($podiumBackfill): void {
                $tournamentId = (int) $group['tournament_id'];
                $groupId = (string) $group['group_id'];

                $podiumBackfill->backfillTournamentGroup($tournamentId, $groupId);

                if ($group['replace_team_name'] ?? false) {
                    $podiumBackfill->replaceGroupTeamName(
                        $tournamentId,
                        $groupId,
                        is_string($group['team_name'] ?? null) ? $group['team_name'] : null
                    );
                }
            });
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('Deferred podium participation backfill failed', [
            'user_ids' => $this->userIds,
            'tournament_groups' => $this->tournamentGroups,
            'error' => $exception->getMessage(),
        ]);
    }
}
