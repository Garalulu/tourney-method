<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $tournament_participation_record_id
 * @property int $user_id
 * @property int|null $actor_id
 * @property string $action
 * @property array<string, mixed>|null $changed_fields
 * @property bool $flagged
 * @property string|null $flag_reason
 * @property-read TournamentParticipationRecord|null $record
 * @property-read User $user
 * @property-read User|null $actor
 */
class ParticipationInputLog extends Model
{
    protected $fillable = [
        'tournament_participation_record_id',
        'user_id',
        'actor_id',
        'action',
        'changed_fields',
        'flagged',
        'flag_reason',
    ];

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'flagged' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TournamentParticipationRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipationRecord::class, 'tournament_participation_record_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
