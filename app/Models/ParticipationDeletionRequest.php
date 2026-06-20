<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_participation_record_id
 * @property int $requested_by
 * @property string $status
 * @property string|null $reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property-read TournamentParticipationRecord $record
 * @property-read User $requester
 * @property-read User|null $reviewer
 */
class ParticipationDeletionRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tournament_participation_record_id',
        'requested_by',
        'status',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
