<?php

namespace App\Models;

use Database\Factories\MatchScoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_game_id
 * @property int|null $user_id
 * @property int $osu_user_id
 * @property string $username
 * @property string|null $team
 * @property int $score
 * @property float $accuracy
 * @property int $max_combo
 * @property int $count_300
 * @property int $count_100
 * @property int $count_50
 * @property int $count_miss
 * @property int $count_geki
 * @property int $count_katu
 * @property bool $perfect
 * @property bool $passed
 * @property array<int, string>|null $mods
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read MatchGame $game
 * @property-read User|null $user
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static Builder<static>|static whereHas(string $relation, \Closure|null $callback = null, string $operator = '>=', int $count = 1)
 * @method static static|null find($id)
 * @method static static findOrFail($id)
 */
class MatchScore extends Model
{
    /** @use HasFactory<MatchScoreFactory> */
    use HasFactory;

    protected $fillable = [
        'match_game_id',
        'user_id',
        'osu_user_id',
        'username',
        'team',
        'score',
        'accuracy',
        'max_combo',
        'count_300',
        'count_100',
        'count_50',
        'count_miss',
        'count_geki',
        'count_katu',
        'perfect',
        'passed',
        'mods',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'accuracy' => 'decimal:4',
        'mods' => 'array',
        'perfect' => 'boolean',
        'passed' => 'boolean',
    ];

    /**
     * Get the game this score belongs to.
     *
     * @return BelongsTo<MatchGame, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(MatchGame::class, 'match_game_id');
    }

    /**
     * Get the user who achieved this score (nullable).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
