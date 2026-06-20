<?php

namespace App\Models;

use Database\Factories\TournamentCorrectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tournament_id
 * @property int $submitted_by
 * @property string $status
 * @property string $kind
 * @property array<string, mixed> $payload
 * @property array<string, mixed> $current_snapshot
 * @property string|null $submitter_note
 * @property array<string, mixed>|null $admin_decisions
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property int $processing_total
 * @property int $processing_completed
 * @property-read Tournament|null $tournament
 * @property-read User $submitter
 * @property-read User|null $reviewer
 *
 * @method static Builder<static>|static query()
 */
class TournamentCorrection extends Model
{
    /** @use HasFactory<TournamentCorrectionFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';

    public const KIND_CORRECTION = 'correction';

    public const KIND_NEW_TOURNAMENT = 'new_tournament';

    /** @var list<string> */
    protected $fillable = [
        'tournament_id',
        'submitted_by',
        'status',
        'kind',
        'payload',
        'current_snapshot',
        'submitter_note',
        'admin_decisions',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'processing_total',
        'processing_completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'current_snapshot' => 'array',
            'admin_decisions' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<TournamentCorrectionComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TournamentCorrectionComment::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);

        return $query;
    }

    public function hasAcceptedChanges(): bool
    {
        $accepted = data_get($this->admin_decisions, 'accepted', []);

        return is_array($accepted) && $accepted !== [];
    }

    public function isNewTournamentRequest(): bool
    {
        return $this->kind === self::KIND_NEW_TOURNAMENT;
    }

    public function processingPercent(): int
    {
        if ($this->status !== self::STATUS_PROCESSING) {
            return 100;
        }

        if ($this->processing_total === 0) {
            return 0;
        }

        return (int) min(100, round(($this->processing_completed / $this->processing_total) * 100));
    }
}
