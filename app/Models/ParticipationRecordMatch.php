<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tournament_participation_record_id
 * @property int $position
 * @property string|null $stage
 * @property string|null $result
 * @property int|null $score_for
 * @property int|null $score_against
 * @property string|null $mp_link
 * @property int|null $mp_id
 * @property bool $is_forfeit
 * @property bool $is_individual_qualifier
 * @property-read TournamentParticipationRecord $participationRecord
 */
class ParticipationRecordMatch extends Model
{
    protected $fillable = [
        'tournament_participation_record_id',
        'position',
        'stage',
        'result',
        'score_for',
        'score_against',
        'mp_link',
        'mp_id',
        'is_forfeit',
        'is_individual_qualifier',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'score_for' => 'integer',
            'score_against' => 'integer',
            'mp_id' => 'integer',
            'is_forfeit' => 'boolean',
            'is_individual_qualifier' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TournamentParticipationRecord, $this>
     */
    public function participationRecord(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipationRecord::class, 'tournament_participation_record_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toParticipationPayload(): array
    {
        return [
            'stage' => $this->stage,
            'result' => $this->result,
            'score_for' => $this->score_for,
            'score_against' => $this->score_against,
            'mp_link' => $this->mp_link,
            'mp_id' => $this->mp_id,
            'is_forfeit' => $this->is_forfeit,
            'is_individual_qualifier' => $this->is_individual_qualifier,
        ];
    }
}
