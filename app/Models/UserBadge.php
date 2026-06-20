<?php

namespace App\Models;

use Database\Factories\UserBadgeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $badge_url
 * @property string|null $image_url
 * @property string|null $image_2x_url
 * @property Carbon|null $awarded_at
 * @property bool $is_bws_eligible
 * @property int|null $tournament_id
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Tournament|null $tournament
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static static|null find($id)
 * @method static static updateOrCreate(array<string, mixed> $values, array<string, mixed> $updating = [])
 */
class UserBadge extends Model
{
    /** @use HasFactory<UserBadgeFactory> */
    use HasFactory;

    /**
     * Disable updated_at timestamp (only has created_at).
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'name',
        'badge_url',
        'image_url',
        'image_2x_url',
        'awarded_at',
        'is_bws_eligible',
        'tournament_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
            'is_bws_eligible' => 'boolean',
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
     * Tournament relationship
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
