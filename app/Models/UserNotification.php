<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $actor_id
 * @property int|null $tournament_id
 * @property int|null $tournament_participation_record_id
 * @property string $category
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string $action_url
 * @property array<string, mixed>|null $data
 * @property string|null $dedupe_key
 * @property Carbon|null $read_at
 * @property Carbon|null $dismissed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read User|null $actor
 * @property-read Tournament|null $tournament
 * @property-read TournamentParticipationRecord|null $participationRecord
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 */
class UserNotification extends Model
{
    public const CATEGORY_PARTICIPATION = 'participation_records';

    public const CATEGORY_TOURNAMENT = 'new_tournament_alert';

    public const TYPE_TEAMMATE_ADDED = 'teammate_added';

    public const TYPE_TEAMMATE_REMOVED = 'teammate_removed';

    public const TYPE_PARTICIPATION_UPDATED = 'participation_updated';

    public const TYPE_PARTICIPATION_ADD_APPROVED = 'participation_add_approved';

    public const TYPE_PARTICIPATION_ADD_DENIED = 'participation_add_denied';

    public const TYPE_TEAMMATE_OSU_ID_FAILED = 'teammate_osu_id_failed';

    public const TYPE_DELETION_APPROVED = 'deletion_approved';

    public const TYPE_DELETION_DENIED = 'deletion_denied';

    public const TYPE_ELIGIBLE_REGISTRATION_OPEN = 'eligible_registration_open';

    public const TYPE_ELIGIBLE_REGISTRATION_CLOSING = 'eligible_registration_closing_24h';

    public const PREF_TEAMMATE_CHANGES = 'teammate_changes';

    public const PREF_PARTICIPATION_UPDATES = 'participation_updates';

    public const PREF_DELETION_REQUESTS = 'deletion_requests';

    public const DEFAULT_PREFERENCES = [
        self::PREF_TEAMMATE_CHANGES => true,
        self::PREF_PARTICIPATION_UPDATES => true,
        self::PREF_DELETION_REQUESTS => true,
        self::TYPE_ELIGIBLE_REGISTRATION_OPEN => true,
        self::TYPE_ELIGIBLE_REGISTRATION_CLOSING => true,
    ];

    protected $fillable = [
        'user_id',
        'actor_id',
        'tournament_id',
        'tournament_participation_record_id',
        'category',
        'type',
        'title',
        'body',
        'action_url',
        'data',
        'dedupe_key',
        'read_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
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

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<TournamentParticipationRecord, $this>
     */
    public function participationRecord(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipationRecord::class, 'tournament_participation_record_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        $query->whereNull('dismissed_at');

        return $query;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function dismiss(): void
    {
        $this->forceFill([
            'read_at' => $this->read_at ?? now(),
            'dismissed_at' => now(),
        ])->save();
    }
}
