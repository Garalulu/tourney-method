<?php

namespace App\Models;

use Database\Factories\TournamentWinnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int|null $user_id
 * @property int $placement
 * @property string $username
 * @property int $osu_id
 * @property array<string, mixed>|null $metadata
 * @property string $gamemode
 * @property string|null $badge_description
 * @property string|null $badge_image_url
 * @property string|null $badge_image_2x_url
 * @property Carbon|null $badge_awarded_at
 * @property string|null $badge_url
 * @property-read string $display_username
 * @property-read int $display_osu_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament $tournament
 * @property-read User|null $user
 * @property-read UserBadge|null $userBadge
 *
 * @method static Builder<static>|static query()
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static Builder<static>|static whereNotNull($column)
 * @method static Builder<static>|static whereHas(string $relation, callable $callback = null, string $operator = '>=', int $count = 1)
 * @method static Builder<static>|static distinct(string $column = '')
 */
class TournamentWinner extends Model
{
    /** @use HasFactory<TournamentWinnerFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'tournament_id',
        'user_id',
        'placement',
        'username',
        'osu_id',
        'metadata',
        'gamemode',
        'badge_description',
        'badge_image_url',
        'badge_image_2x_url',
        'badge_awarded_at',
        'badge_url',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'badge_awarded_at' => 'datetime',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User badge relationship (reverse)
     *
     * @return Builder<UserBadge>|UserBadge
     */
    public function userBadge()
    {
        return $this->hasOne(UserBadge::class, 'tournament_id', 'tournament_id')
            ->where('user_id', $this->user_id);
    }

    /**
     * Prefer the linked user's current username over the imported winner snapshot.
     */
    public function getDisplayUsernameAttribute(): string
    {
        return $this->user !== null ? $this->user->username : $this->username;
    }

    /**
     * Prefer the linked user's current osu! ID, falling back to the imported snapshot.
     */
    public function getDisplayOsuIdAttribute(): int
    {
        return $this->user !== null ? $this->user->osu_id : $this->osu_id;
    }
}
