<?php

namespace App\Models;

use Database\Factories\UserMatchParticipationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $match_id
 * @property string|null $team
 * @property int $games_played
 * @property int $games_won
 * @property int $total_score
 * @property float|null $avg_accuracy
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read OsuMatch $match
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static static|null find($id)
 */
class UserMatchParticipation extends Model
{
    /** @use HasFactory<UserMatchParticipationFactory> */
    use HasFactory;

    protected $table = 'user_match_participation';

    protected $fillable = [
        'user_id',
        'match_id',
        'team',
        'games_played',
        'games_won',
        'total_score',
        'avg_accuracy',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'avg_accuracy' => 'decimal:4',
    ];

    /**
     * Get the user who participated in this match.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the match this participation record belongs to.
     *
     * @return BelongsTo<OsuMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(OsuMatch::class);
    }
}
