<?php

namespace App\Services;

use App\Models\ParticipationInputLog;
use App\Models\TournamentParticipationRecord;
use App\Models\User;

class ParticipationInputAuditService
{
    /**
     * @param  array<string, mixed>  $changedFields
     */
    public function log(TournamentParticipationRecord $record, User $user, ?User $actor, string $action, array $changedFields): ParticipationInputLog
    {
        $flagReason = $this->flagReason($changedFields);

        return ParticipationInputLog::query()->create([
            'tournament_participation_record_id' => $record->id,
            'user_id' => $user->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'changed_fields' => $changedFields,
            'flagged' => $flagReason !== null,
            'flag_reason' => $flagReason,
        ]);
    }

    public function sanitizeText(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $changedFields
     */
    private function flagReason(array $changedFields): ?string
    {
        $patterns = config('participation_moderation.flag_patterns', []);

        if (! is_array($patterns) || $patterns === []) {
            return null;
        }

        $haystack = mb_strtolower(json_encode($changedFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && str_contains($haystack, mb_strtolower($pattern))) {
                return 'flag_pattern:'.$pattern;
            }
        }

        return null;
    }
}
