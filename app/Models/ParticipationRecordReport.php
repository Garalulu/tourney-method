<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_participation_record_id
 * @property int $reported_by
 * @property string $category
 * @property string|null $explanation
 * @property string $status
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_note
 * @property-read TournamentParticipationRecord $record
 * @property-read User $reporter
 * @property-read User|null $resolver
 */
class ParticipationRecordReport extends Model
{
    public const CATEGORY_RECORD_CORRECTION = 'record_correction';

    public const CATEGORY_REPORT_SPAM = 'report_spam';

    public const CATEGORY_INAPPROPRIATE_MEMO = 'inappropriate_memo';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'tournament_participation_record_id',
        'reported_by',
        'category',
        'explanation',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
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
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_RECORD_CORRECTION => __('users.participation.report.categories.record_correction'),
            self::CATEGORY_REPORT_SPAM => __('users.participation.report.categories.report_spam'),
            self::CATEGORY_INAPPROPRIATE_MEMO => __('users.participation.report.categories.inappropriate_memo'),
        ];
    }
}
