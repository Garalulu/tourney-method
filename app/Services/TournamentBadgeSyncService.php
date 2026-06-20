<?php

namespace App\Services;

use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TournamentBadgeSyncService
{
    /** @var array<int, bool> */
    private array $duplicateForumTopicCache = [];

    /**
     * @return array{tournaments: int, winners: int, matched: int, unmatched: int, main_modes_updated: int, stale_badges_reset: int, auto_approved: int}
     */
    public function syncRange(
        int $startYear,
        int $endYear,
        bool $dryRun = false,
        bool $essentialOnly = false,
        ?AdminMaintenanceRun $maintenanceRun = null
    ): array {
        $stats = [
            'tournaments' => 0,
            'winners' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'main_modes_updated' => 0,
            'stale_badges_reset' => 0,
            'auto_approved' => 0,
        ];

        $stats['stale_badges_reset'] = $this->resetStaleTournamentBadgeLinks($dryRun);

        $this->queryBadgedTournaments($startYear, $endYear, $essentialOnly)
            ->chunkById(100, function (Collection $tournaments) use (&$stats, $dryRun, $maintenanceRun) {
                foreach ($tournaments as $tournament) {
                    $result = $this->syncTournament($tournament, $dryRun, $maintenanceRun);

                    $stats['tournaments']++;
                    $stats['winners'] += $result['winners'];
                    $stats['matched'] += $result['matched'];
                    $stats['unmatched'] += $result['unmatched'];
                    $stats['main_modes_updated'] += $result['main_modes_updated'];
                    $stats['stale_badges_reset'] += $result['stale_badges_reset'];
                    $stats['auto_approved'] += $result['auto_approved'];
                }
            });

        return $stats;
    }

    public function resetStaleTournamentBadgeLinks(bool $dryRun = false): int
    {
        $query = UserBadge::query()
            ->where(function ($query) {
                $query->where(function ($nullTournamentQuery) {
                    $nullTournamentQuery->whereNull('tournament_id')
                        ->where('is_bws_eligible', true);
                })->orWhereIn('tournament_id', Tournament::onlyTrashed()->select('id'));
            });

        $count = $query->count();

        if ($count > 0 && ! $dryRun) {
            $query->update([
                'tournament_id' => null,
                'is_bws_eligible' => false,
            ]);
        }

        $invalidWinnerMetadataCount = $this->resetInvalidTournamentWinnerBadgeMetadata($dryRun);
        $totalCount = $count + $invalidWinnerMetadataCount;

        Log::info('Stale tournament badge links reset', [
            'dry_run' => $dryRun,
            'user_badge_count' => $count,
            'winner_metadata_count' => $invalidWinnerMetadataCount,
            'count' => $totalCount,
        ]);

        return $totalCount;
    }

    /**
     * @return array{winners: int, matched: int, unmatched: int, main_modes_updated: int, stale_badges_reset: int, auto_approved: int}
     */
    public function syncTournament(
        Tournament $tournament,
        bool $dryRun = false,
        ?AdminMaintenanceRun $maintenanceRun = null
    ): array {
        $stats = [
            'winners' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'main_modes_updated' => 0,
            'stale_badges_reset' => 0,
            'auto_approved' => 0,
        ];

        if ($this->isRejectedForBadgeSync($tournament)) {
            Log::info('Tournament badge sync skipped for rejected tournament', [
                'tournament_id' => $tournament->id,
                'status' => $tournament->status,
                'badge_status' => $tournament->badge_status,
            ]);

            return $stats;
        }

        $autoApproved = false;

        $tournament->loadMissing([
            'winners' => fn ($query) => $query->where('placement', '<=', 3)->with(['user.badges', 'tournament']),
        ]);

        foreach ($tournament->winners as $winner) {
            $stats['winners']++;

            $user = $winner->user;

            if (! $user) {
                $stats['unmatched']++;

                continue;
            }

            $stats['stale_badges_reset'] += $this->resetInvalidLinkedBadges($tournament, $winner, $dryRun);
            $stats['stale_badges_reset'] += $this->resetInvalidWinnerBadgeMetadata($tournament, $winner, $dryRun);

            $matchedBadge = $this->matchWinnerBadge($tournament, $winner);

            if (! $matchedBadge) {
                $stats['unmatched']++;

                continue;
            }

            if (! $dryRun) {
                $isNewSync = ! $this->winnerAlreadySynced($tournament, $winner, $matchedBadge);

                $this->applyMatch($winner, $matchedBadge);
                $this->approveBadgeStatusWithMatchedBadge($tournament);

                if ($isNewSync) {
                    $this->recordMaintenanceMatch($maintenanceRun, $tournament, $winner, $matchedBadge);
                }

                if (! $autoApproved && $this->approvePendingTournamentWithMatchedBadge($tournament)) {
                    $autoApproved = true;
                    $stats['auto_approved']++;
                }

                if ($this->syncUserMainModeFromPodiums($user)) {
                    $stats['main_modes_updated']++;
                }
            } elseif (! $autoApproved && $tournament->status === Tournament::STATUS_PENDING) {
                $autoApproved = true;
                $stats['auto_approved']++;
            }

            $stats['matched']++;
        }

        Log::info('Tournament badge sync completed for tournament', [
            'tournament_id' => $tournament->id,
            'dry_run' => $dryRun,
            ...$stats,
        ]);

        return $stats;
    }

    /**
     * @return Builder<Tournament>
     */
    private function queryBadgedTournaments(int $startYear, int $endYear, bool $essentialOnly): Builder
    {
        $query = Tournament::query()
            ->whereIn('status', [
                Tournament::STATUS_APPROVED,
                Tournament::STATUS_PENDING,
            ])
            ->where('is_badge', true)
            ->whereNotNull('tournament_end')
            ->whereYear('tournament_end', '>=', $startYear)
            ->whereYear('tournament_end', '<=', $endYear)
            ->whereHas('winners', fn ($query) => $query->where('placement', '<=', 3))
            ->with([
                'winners' => fn ($query) => $query->where('placement', '<=', 3)->with(['user.badges', 'tournament']),
            ]);

        if ($essentialOnly) {
            $query->where(fn ($badgeStatusQuery) => $badgeStatusQuery
                ->whereNull('badge_status')
                ->orWhere('badge_status', '!=', 'rejected'))
                ->whereHas('winners', fn ($winnerQuery) => $winnerQuery
                    ->where('placement', 1)
                    ->where(fn ($missingBadgeQuery) => $missingBadgeQuery
                        ->whereNull('badge_url')
                        ->orWhere('badge_url', '')
                        ->orWhereNull('badge_description')
                        ->orWhere('badge_description', '')
                    )
                );
        } else {
            $query->where(fn ($badgeStatusQuery) => $badgeStatusQuery
                ->whereNull('badge_status')
                ->orWhere('badge_status', '!=', 'rejected'));
        }

        return $query;
    }

    private function matchWinnerBadge(Tournament $tournament, TournamentWinner $winner): ?UserBadge
    {
        /** @var Collection<int, UserBadge> $badges */
        $badges = $winner->user->badges;

        return $badges->first(fn (UserBadge $badge) => $this->matchesByUrl($tournament, $badge))
            ?? $badges->first(fn (UserBadge $badge) => $this->matchesByImage($tournament, $badge));
    }

    private function resetInvalidLinkedBadges(Tournament $tournament, TournamentWinner $winner, bool $dryRun): int
    {
        /** @var Collection<int, UserBadge> $invalidBadges */
        $invalidBadges = $winner->user->badges
            ->filter(fn (UserBadge $badge) => $badge->tournament_id === $tournament->id
                && ! $this->matchesCurrentRules($tournament, $badge)
            );

        if ($invalidBadges->isEmpty()) {
            return 0;
        }

        if (! $dryRun) {
            UserBadge::query()
                ->whereIn('id', $invalidBadges->pluck('id'))
                ->update([
                    'tournament_id' => null,
                    'is_bws_eligible' => false,
                ]);
        }

        Log::info('Invalid tournament badge links reset', [
            'tournament_id' => $tournament->id,
            'winner_id' => $winner->id,
            'dry_run' => $dryRun,
            'count' => $invalidBadges->count(),
        ]);

        return $invalidBadges->count();
    }

    private function matchesCurrentRules(Tournament $tournament, UserBadge $badge): bool
    {
        return $this->matchesBadgeData(
            $tournament,
            $badge->badge_url,
            $badge->image_url,
            $badge->image_2x_url,
        );
    }

    private function resetInvalidWinnerBadgeMetadata(Tournament $tournament, TournamentWinner $winner, bool $dryRun): int
    {
        if (! $this->winnerHasBadgeMetadata($winner)
            || $this->matchesBadgeData(
                $tournament,
                $winner->badge_url,
                $winner->badge_image_url,
                $winner->badge_image_2x_url,
            )
        ) {
            return 0;
        }

        if (! $dryRun) {
            $this->clearWinnerBadgeMetadata($winner);
        }

        Log::info('Invalid tournament winner badge metadata reset', [
            'tournament_id' => $tournament->id,
            'winner_id' => $winner->id,
            'dry_run' => $dryRun,
        ]);

        return 1;
    }

    private function resetInvalidTournamentWinnerBadgeMetadata(bool $dryRun): int
    {
        $count = 0;

        TournamentWinner::query()
            ->where(function ($query) {
                $query->whereNotNull('badge_url')
                    ->orWhereNotNull('badge_description')
                    ->orWhereNotNull('badge_image_url')
                    ->orWhereNotNull('badge_image_2x_url')
                    ->orWhereNotNull('badge_awarded_at');
            })
            ->with('tournament')
            ->chunkById(500, function (Collection $winners) use (&$count, $dryRun) {
                /** @var Collection<int, TournamentWinner> $invalidWinners */
                $invalidWinners = $winners->filter(fn (TournamentWinner $winner) => $winner->tournament === null
                    || (! $this->isRejectedForBadgeSync($winner->tournament)
                    && ! $this->matchesBadgeData(
                        $winner->tournament,
                        $winner->badge_url,
                        $winner->badge_image_url,
                        $winner->badge_image_2x_url,
                    ))
                );

                if ($invalidWinners->isEmpty()) {
                    return;
                }

                $count += $invalidWinners->count();

                if (! $dryRun) {
                    TournamentWinner::query()
                        ->whereIn('id', $invalidWinners->pluck('id'))
                        ->update($this->emptyWinnerBadgeMetadata());
                }
            });

        return $count;
    }

    private function matchesBadgeData(
        Tournament $tournament,
        ?string $badgeUrl,
        ?string $imageUrl,
        ?string $image2xUrl,
    ): bool {
        if ($this->requiresImageMatchForTopic($tournament)) {
            return $this->matchesByImageValue($tournament, $imageUrl, $image2xUrl);
        }

        return $this->matchesByUrlValue($tournament, $badgeUrl)
            || $this->matchesByImageValue($tournament, $imageUrl, $image2xUrl);
    }

    private function matchesByUrl(Tournament $tournament, UserBadge $badge): bool
    {
        if ($this->requiresImageMatchForTopic($tournament)) {
            return false;
        }

        return $this->matchesByUrlValue($tournament, $badge->badge_url);
    }

    private function matchesByUrlValue(Tournament $tournament, ?string $badgeUrl): bool
    {
        if (empty($badgeUrl)) {
            return false;
        }

        $badgeTopicId = $this->extractForumTopicId($badgeUrl);

        if ($badgeTopicId !== null && $tournament->forum_topic_id !== null) {
            return (int) $tournament->forum_topic_id === $badgeTopicId;
        }

        return $this->normalizeUrl($tournament->forum_post_url) === $this->normalizeUrl($badgeUrl);
    }

    private function matchesByImage(Tournament $tournament, UserBadge $badge): bool
    {
        return $this->matchesByImageValue($tournament, $badge->image_url, $badge->image_2x_url);
    }

    private function matchesByImageValue(Tournament $tournament, ?string $imageUrl, ?string $image2xUrl): bool
    {
        $tournamentBadgeUrls = collect($tournament->badge_urls ?? [])
            ->flatten()
            ->filter()
            ->map(fn ($url) => $this->normalizeUrl((string) $url))
            ->unique();

        if ($tournamentBadgeUrls->isEmpty()) {
            return false;
        }

        return $tournamentBadgeUrls->contains($this->normalizeUrl($imageUrl))
            || $tournamentBadgeUrls->contains($this->normalizeUrl($image2xUrl));
    }

    private function winnerHasBadgeMetadata(TournamentWinner $winner): bool
    {
        return ! empty($winner->badge_url)
            || ! empty($winner->badge_description)
            || ! empty($winner->badge_image_url)
            || ! empty($winner->badge_image_2x_url)
            || $winner->badge_awarded_at !== null;
    }

    private function winnerAlreadySynced(Tournament $tournament, TournamentWinner $winner, UserBadge $badge): bool
    {
        return $winner->badge_description === $badge->name
            && $winner->badge_url === $badge->badge_url
            && $winner->badge_image_url === $badge->image_url
            && $winner->badge_image_2x_url === $badge->image_2x_url
            && $badge->tournament_id === $tournament->id;
    }

    private function clearWinnerBadgeMetadata(TournamentWinner $winner): void
    {
        $winner->update($this->emptyWinnerBadgeMetadata());
    }

    /**
     * @return array<string, null>
     */
    private function emptyWinnerBadgeMetadata(): array
    {
        return [
            'badge_description' => null,
            'badge_image_url' => null,
            'badge_image_2x_url' => null,
            'badge_awarded_at' => null,
            'badge_url' => null,
        ];
    }

    private function applyMatch(TournamentWinner $winner, UserBadge $badge): void
    {
        $winner->update([
            'badge_description' => $badge->name,
            'badge_image_url' => $badge->image_url,
            'badge_image_2x_url' => $badge->image_2x_url,
            'badge_awarded_at' => $badge->awarded_at,
            'badge_url' => $badge->badge_url,
        ]);

        $mode = $this->getTournamentBadgeMode($winner);

        $winner->update([
            'gamemode' => $mode,
        ]);

        $badge->update([
            'tournament_id' => $winner->tournament_id,
            'is_bws_eligible' => $mode === 'osu',
        ]);
    }

    private function approveBadgeStatusWithMatchedBadge(Tournament $tournament): bool
    {
        if ($tournament->badge_status === 'approved' || $tournament->badge_status === 'rejected') {
            return false;
        }

        $tournament->update([
            'badge_status' => 'approved',
        ]);

        return true;
    }

    private function recordMaintenanceMatch(
        ?AdminMaintenanceRun $maintenanceRun,
        Tournament $tournament,
        TournamentWinner $winner,
        UserBadge $badge
    ): void {
        $user = $winner->user;

        if (! $maintenanceRun || ! $user) {
            return;
        }

        app(AdminMaintenanceRunRecorder::class)->recordTournament(
            $maintenanceRun,
            $tournament,
            AdminMaintenanceRunItem::ACTION_SYNCED,
            [
                'winner_id' => $winner->id,
                'placement' => $winner->placement,
                'user_id' => $user->id,
                'osu_id' => $user->osu_id,
                'username' => $user->username,
                'badge_id' => $badge->id,
                'badge_name' => $badge->name,
            ]
        );
    }

    private function approvePendingTournamentWithMatchedBadge(Tournament $tournament): bool
    {
        if ($tournament->status !== Tournament::STATUS_PENDING) {
            return false;
        }

        $tournament->update([
            'status' => Tournament::STATUS_APPROVED,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return true;
    }

    private function requiresImageMatchForTopic(Tournament $tournament): bool
    {
        if ($tournament->forum_topic_id === null) {
            return false;
        }

        $topicId = (int) $tournament->forum_topic_id;

        if (array_key_exists($topicId, $this->duplicateForumTopicCache)) {
            return $this->duplicateForumTopicCache[$topicId];
        }

        $count = Tournament::query()
            ->where('forum_topic_id', $topicId)
            ->where('is_badge', true)
            ->whereIn('status', [
                Tournament::STATUS_APPROVED,
                Tournament::STATUS_PENDING,
            ])
            ->where(fn ($query) => $query
                ->whereNull('badge_status')
                ->orWhere('badge_status', '!=', 'rejected'))
            ->limit(2)
            ->count();

        return $this->duplicateForumTopicCache[$topicId] = $count > 1;
    }

    private function isRejectedForBadgeSync(Tournament $tournament): bool
    {
        return $tournament->status === Tournament::STATUS_REJECTED || $tournament->badge_status === 'rejected';
    }

    private function syncUserMainModeFromPodiums(User $user): bool
    {
        if ($user->main_mode_source === 'oauth_setup') {
            return false;
        }

        $podiums = TournamentWinner::query()
            ->where('user_id', $user->id)
            ->where('placement', '<=', 3)
            ->whereHas('tournament', fn ($query) => $query->where('status', 'approved'))
            ->with('tournament')
            ->get();

        $modeCounts = $podiums
            ->map(fn (TournamentWinner $winner) => $this->getTournamentBadgeMode($winner))
            ->filter()
            ->countBy();

        if ($modeCounts->isEmpty()) {
            return false;
        }

        $currentModeCount = $user->main_mode ? (int) ($modeCounts[$user->main_mode] ?? 0) : 0;
        $highestCount = (int) $modeCounts->max();

        if ($currentModeCount === $highestCount && $user->main_mode !== null) {
            return false;
        }

        $mainMode = (string) $modeCounts
            ->sortDesc()
            ->keys()
            ->first();

        if ($user->main_mode === $mainMode && $user->main_mode_source === 'auto_detected') {
            return false;
        }

        $user->update([
            'main_mode' => $mainMode,
            'main_mode_source' => 'auto_detected',
        ]);

        return true;
    }

    private function getTournamentBadgeMode(TournamentWinner $winner): string
    {
        $tournamentMode = $winner->tournament->modes_with_details
            ->pluck('mode')
            ->filter()
            ->map(fn (string $mode) => $this->normalizeMode($mode))
            ->first();

        return $tournamentMode ?: $this->normalizeMode($winner->gamemode);
    }

    private function normalizeMode(?string $mode): string
    {
        return match ($mode) {
            'fruits' => 'catch',
            null, '' => 'osu',
            default => $mode,
        };
    }

    private function extractForumTopicId(string $url): ?int
    {
        if (preg_match('/forums\/topics\/(\d+)/', $url, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function normalizeUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return mb_strtolower($url);
        }

        $scheme = mb_strtolower($parts['scheme'] ?? 'https');
        $host = mb_strtolower($parts['host'] ?? '');
        $path = rtrim($parts['path'] ?? '', '/');

        return "{$scheme}://{$host}{$path}";
    }
}
