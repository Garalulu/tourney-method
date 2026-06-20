<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $mode
 * @property int|null $rank
 * @property int|null $global_rank Alias for rank property
 * @property int|null $country_rank
 * @property float|null $pp
 * @property Carbon|null $recorded_at
 * @property-read User $user
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static static|null find($id)
 */
class UserRankHistory extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'user_rank_history';

    /**
     * Disable timestamps (only has synced_at).
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'mode',
        'rank',
        'country_rank',
        'pp',
        'recorded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'country_rank' => 'integer',
            'pp' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor for global_rank (alias for rank).
     */
    public function getGlobalRankAttribute(): ?int
    {
        return $this->rank;
    }
}
