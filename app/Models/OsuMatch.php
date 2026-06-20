<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\OsuMatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $osu_match_id
 * @property string $name
 * @property int|null $tournament_id
 * @property CarbonInterface|null $start_time
 * @property CarbonInterface|null $end_time
 * @property string $status
 * @property int|null $submitted_by
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property array<string, mixed>|null $raw_data
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Tournament|null $tournament
 * @property-read User|null $submitter
 * @property-read User|null $reviewer
 * @property-read Collection<int, MatchGame> $games
 * @property-read Collection<int, UserMatchParticipation> $participations
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static Builder<static>|static whereIn(string $column, mixed $values)
 * @method static static|null find($id)
 * @method static static findOrFail($id)
 * @method static static first()
 * @method static Builder<static> pending()
 * @method static Builder<static> approved()
 */
class OsuMatch extends Model
{
    /** @use HasFactory<OsuMatchFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'matches';

    protected $fillable = [
        'osu_match_id',
        'name',
        'tournament_id',
        'start_time',
        'end_time',
        'status',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'raw_data',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'raw_data' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the tournament this match is associated with.
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the user who submitted this match.
     *
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the admin who reviewed this match.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get all games in this match.
     *
     * @return HasMany<MatchGame, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(MatchGame::class, 'match_id');
    }

    /**
     * Get all user participation records for this match.
     *
     * @return HasMany<UserMatchParticipation, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(UserMatchParticipation::class, 'match_id');
    }

    /**
     * Scope query to only include pending matches.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope query to only include approved matches.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Approve this match.
     */
    public function approve(int $reviewerId): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Reject this match.
     */
    public function reject(int $reviewerId): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }
}
