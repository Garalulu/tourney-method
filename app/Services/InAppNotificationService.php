<?php

namespace App\Services;

use App\Models\ParticipationDeletionRequest;
use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class InAppNotificationService
{
    /**
     * @param  list<int>  $addedUserIds
     */
    public function notifyTeammatesAdded(TournamentParticipationRecord $record, User $actor, array $addedUserIds): void
    {
        if ($addedUserIds === []) {
            return;
        }

        $record->loadMissing('tournament');

        $recipients = User::query()
            ->whereIn('id', array_unique($addedUserIds))
            ->where('id', '!=', $actor->id)
            ->get();

        foreach ($recipients as $recipient) {
            $this->createForUser($recipient, [
                'actor_id' => $actor->id,
                'tournament_id' => $record->tournament_id,
                'tournament_participation_record_id' => $record->id,
                'category' => UserNotification::CATEGORY_PARTICIPATION,
                'type' => UserNotification::TYPE_TEAMMATE_ADDED,
                'title' => $this->notificationText($recipient, UserNotification::TYPE_TEAMMATE_ADDED, 'title'),
                'body' => $this->notificationText($recipient, UserNotification::TYPE_TEAMMATE_ADDED, 'body', [
                    'actor' => $actor->username,
                    'tournament' => $record->tournament->title,
                ]),
                'action_url' => $this->participationUrl($recipient, $record),
                'data' => [
                    'team_name' => $record->team_name,
                ],
            ]);
        }
    }

    /**
     * @param  list<int>  $removedUserIds
     */
    public function notifyTeammatesRemoved(TournamentParticipationRecord $record, User $actor, array $removedUserIds): void
    {
        if ($removedUserIds === []) {
            return;
        }

        $record->loadMissing('tournament');

        $recipients = User::query()
            ->whereIn('id', array_unique($removedUserIds))
            ->where('id', '!=', $actor->id)
            ->get();

        foreach ($recipients as $recipient) {
            $this->createForUser($recipient, [
                'actor_id' => $actor->id,
                'tournament_id' => $record->tournament_id,
                'tournament_participation_record_id' => $record->id,
                'category' => UserNotification::CATEGORY_PARTICIPATION,
                'type' => UserNotification::TYPE_TEAMMATE_REMOVED,
                'title' => $this->notificationText($recipient, UserNotification::TYPE_TEAMMATE_REMOVED, 'title'),
                'body' => $this->notificationText($recipient, UserNotification::TYPE_TEAMMATE_REMOVED, 'body', [
                    'actor' => $actor->username,
                    'tournament' => $record->tournament->title,
                ]),
                'action_url' => $this->participationUrl($recipient, $record),
                'data' => [
                    'team_name' => $record->team_name,
                ],
            ]);
        }
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function notifyParticipationUpdated(TournamentParticipationRecord $record, User $actor, array $changes): void
    {
        $trackedChanges = array_intersect_key($changes, array_flip(['team_name', 'stage_value', 'seed']));

        if ($trackedChanges === []) {
            return;
        }

        $record->loadMissing(['tournament', 'teammates']);
        $recipients = $this->teamRecipients($record)->reject(fn (User $user): bool => $user->id === $actor->id);
        $labels = [
            'team_name' => 'team name',
            'stage_value' => 'final stage',
            'seed' => 'seed',
        ];

        foreach ($recipients as $recipient) {
            $changedLabels = collect(array_keys($trackedChanges))
                ->map(fn (string $field): string => $this->fieldLabel($recipient, $field, $labels[$field]))
                ->join(', ');

            $this->createForUser($recipient, [
                'actor_id' => $actor->id,
                'tournament_id' => $record->tournament_id,
                'tournament_participation_record_id' => $record->id,
                'category' => UserNotification::CATEGORY_PARTICIPATION,
                'type' => UserNotification::TYPE_PARTICIPATION_UPDATED,
                'title' => $this->notificationText($recipient, UserNotification::TYPE_PARTICIPATION_UPDATED, 'title'),
                'body' => $this->notificationText($recipient, UserNotification::TYPE_PARTICIPATION_UPDATED, 'body', [
                    'actor' => $actor->username,
                    'fields' => $changedLabels,
                    'tournament' => $record->tournament->title,
                ]),
                'action_url' => $this->participationUrl($recipient, $record),
                'data' => [
                    'changes' => $trackedChanges,
                ],
            ]);
        }
    }

    public function notifyDeletionResolved(ParticipationDeletionRequest $deletionRequest, string $type): void
    {
        $deletionRequest->loadMissing(['requester', 'record.tournament']);
        $record = $deletionRequest->record;
        $requester = $deletionRequest->requester;

        $this->createForUser($requester, [
            'actor_id' => $deletionRequest->reviewed_by,
            'tournament_id' => $record->tournament_id,
            'tournament_participation_record_id' => $record->id,
            'category' => UserNotification::CATEGORY_PARTICIPATION,
            'type' => $type,
            'title' => $this->notificationText($requester, $type, 'title'),
            'body' => $this->notificationText($requester, $type, 'body', [
                'tournament' => $record->tournament->title,
            ]),
            'action_url' => $this->participationUrl($requester, $record),
            'data' => [
                'deletion_request_id' => $deletionRequest->id,
                'review_note' => $deletionRequest->review_note,
            ],
        ]);
    }

    public function notifyParticipationAddResolved(TournamentParticipationRecord $record, string $type): void
    {
        $record->loadMissing(['user', 'tournament']);

        $this->createForUser($record->user, [
            'actor_id' => auth()->id(),
            'tournament_id' => $record->tournament_id,
            'tournament_participation_record_id' => $record->id,
            'category' => UserNotification::CATEGORY_PARTICIPATION,
            'type' => $type,
            'title' => $this->notificationText($record->user, $type, 'title'),
            'body' => $this->notificationText($record->user, $type, 'body', [
                'tournament' => $record->tournament->title,
            ]),
            'action_url' => $this->participationUrl($record->user, $record),
            'data' => [
                'placement' => $record->placementRangeLabel(),
            ],
        ]);
    }

    /**
     * @param  list<int>  $failedOsuIds
     */
    public function notifyFailedTeammateOsuIds(User $user, TournamentParticipationRecord $record, array $failedOsuIds): void
    {
        if ($failedOsuIds === []) {
            return;
        }

        $record->loadMissing('tournament');
        $ids = implode(', ', array_unique($failedOsuIds));

        $this->createForUser($user, [
            'tournament_id' => $record->tournament_id,
            'tournament_participation_record_id' => $record->id,
            'category' => UserNotification::CATEGORY_PARTICIPATION,
            'type' => UserNotification::TYPE_TEAMMATE_OSU_ID_FAILED,
            'title' => $this->notificationText($user, UserNotification::TYPE_TEAMMATE_OSU_ID_FAILED, 'title'),
            'body' => $this->notificationText($user, UserNotification::TYPE_TEAMMATE_OSU_ID_FAILED, 'body', [
                'ids' => $ids,
                'tournament' => $record->tournament->title,
            ]),
            'action_url' => $this->participationUrl($user, $record),
            'data' => [
                'failed_osu_ids' => array_values(array_unique($failedOsuIds)),
            ],
        ]);
    }

    public function notifyEligibleTournament(User $user, Tournament $tournament, string $type): bool
    {
        return $this->createForUser($user, [
            'tournament_id' => $tournament->id,
            'category' => UserNotification::CATEGORY_TOURNAMENT,
            'type' => $type,
            'title' => $this->notificationText($user, $type, 'title'),
            'body' => $this->notificationText($user, $type, 'body', [
                'tournament' => $tournament->title,
            ]),
            'action_url' => route('tournaments.show', $tournament),
            'data' => [
                'registration_start' => $tournament->registration_start?->toIso8601String(),
                'registration_end' => $tournament->registration_end?->toIso8601String(),
            ],
            'dedupe_key' => "user:{$user->id}:tournament:{$tournament->id}:{$type}",
        ]) !== null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForUser(User $user, array $attributes): ?UserNotification
    {
        if (! $this->canReceive($user, (string) $attributes['type'])) {
            return null;
        }

        if (! empty($attributes['dedupe_key'])) {
            $notification = UserNotification::query()->firstOrCreate(
                ['dedupe_key' => $attributes['dedupe_key']],
                array_merge(['user_id' => $user->id], $attributes)
            );

            return $notification->wasRecentlyCreated ? $notification : null;
        }

        return UserNotification::query()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    public function canReceive(User $user, string $type): bool
    {
        if ($user->main_mode_source !== 'oauth_setup') {
            return false;
        }

        $preferences = array_merge(
            UserNotification::DEFAULT_PREFERENCES,
            $user->in_app_notification_preferences ?? []
        );

        return (bool) ($preferences[$this->preferenceKeyForType($type)] ?? true);
    }

    private function preferenceKeyForType(string $type): string
    {
        return match ($type) {
            UserNotification::TYPE_TEAMMATE_ADDED,
            UserNotification::TYPE_TEAMMATE_REMOVED,
            UserNotification::TYPE_TEAMMATE_OSU_ID_FAILED => UserNotification::PREF_TEAMMATE_CHANGES,
            UserNotification::TYPE_PARTICIPATION_UPDATED,
            UserNotification::TYPE_PARTICIPATION_ADD_APPROVED,
            UserNotification::TYPE_PARTICIPATION_ADD_DENIED => UserNotification::PREF_PARTICIPATION_UPDATES,
            UserNotification::TYPE_DELETION_APPROVED,
            UserNotification::TYPE_DELETION_DENIED => UserNotification::PREF_DELETION_REQUESTS,
            default => $type,
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function teamRecipients(TournamentParticipationRecord $record): Collection
    {
        $userIds = $record->teammates
            ->pluck('id')
            ->push($record->user_id)
            ->unique()
            ->values();

        /** @var EloquentCollection<int, User> $users */
        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('main_mode_source', 'oauth_setup')
            ->get();

        return $users->toBase();
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private function notificationText(User $user, string $type, string $part, array $replace = []): string
    {
        return trans(
            "common.notifications.messages.{$type}.{$part}",
            $replace,
            $user->locale ?? config('app.locale')
        );
    }

    private function fieldLabel(User $user, string $field, string $fallback): string
    {
        $key = "common.notifications.fields.{$field}";
        $locale = $user->locale ?? config('app.locale');
        $label = trans($key, [], $locale);

        return $label === $key ? $fallback : $label;
    }

    private function participationUrl(User $recipient, TournamentParticipationRecord $record): string
    {
        return route('users.show', [
            'user' => $recipient,
            'tab' => 'participation',
            'participation_tournament' => $record->tournament_id,
        ]);
    }
}
