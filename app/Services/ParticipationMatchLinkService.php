<?php

namespace App\Services;

use App\Models\TournamentParticipationRecord;

class ParticipationMatchLinkService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeForRecord(array $payload, TournamentParticipationRecord $record, ?int $submittedBy = null): array
    {
        $mpId = $this->mpId($payload['mp_id'] ?? null, $payload['mp_link'] ?? null);
        if ($mpId === null) {
            $payload['mp_id'] = null;

            return $payload;
        }

        $payload['mp_id'] = $mpId;
        $payload['mp_link'] = $this->canonicalLink($mpId);

        return $payload;
    }

    public function canonicalLink(int $mpId): string
    {
        return "https://osu.ppy.sh/community/matches/{$mpId}";
    }

    public function mpId(mixed $mpId, mixed $mpLink = null): ?int
    {
        if ($mpId !== null && $mpId !== '' && is_numeric($mpId)) {
            return (int) $mpId;
        }

        if (! is_string($mpLink)) {
            return null;
        }

        $mpLink = trim($mpLink);
        if (ctype_digit($mpLink)) {
            return (int) $mpLink;
        }

        if (preg_match('~^https?://osu\.ppy\.sh/(?:community/matches|mp)/(\d+)$~', $mpLink, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
