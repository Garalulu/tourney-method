<?php

namespace App\Models;

use Database\Factories\TournamentStaffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $user_id
 * @property string $role
 * @property string $source
 * @property string|null $notes
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tournament $tournament
 * @property-read User $user
 * @property-read User|null $reviewer
 *
 * @method static Builder<static>|static query()
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static Builder<static>|static whereIn($column, $values, $boolean = 'and')
 * @method static Builder<static>|static whereHas($relation, $callback = null, $operator = '>=', $count = 1)
 * @method static static create(array<string, mixed> $attributes)
 * @method static static forceCreate(array<string, mixed> $attributes)
 * @method static static findOrFail($id, $columns = ['*'])
 */
class TournamentStaff extends Model
{
    /** @use HasFactory<TournamentStaffFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'tournament_id',
        'user_id',
        'role',
        'notes',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'source',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'manual',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * The tournament this staff role belongs to.
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * The user who holds this staff role.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who reviewed this staff role.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope: Only pending staff roles.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending($query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Only approved staff roles.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved($query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Only rejected staff roles.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRejected($query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Approve this staff role.
     */
    public function approve(?int $reviewerId = null): bool
    {
        return $this->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewerId ?? auth()->id(),
        ]);
    }

    /**
     * Reject this staff role.
     */
    public function reject(?int $reviewerId = null): bool
    {
        return $this->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewerId ?? auth()->id(),
        ]);
    }

    /**
     * Find or create a staff role.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public static function createForUser(array $attributes, array $values): self
    {
        return static::query()->create(array_merge($attributes, $values));
    }

    /**
     * Find a staff role by ID or fail.
     */
    public static function findByIdOrFail(int $id): self
    {
        return static::with(['tournament', 'user'])->findOrFail($id);
    }
}
