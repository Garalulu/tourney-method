<?php

namespace App\Models;

use Database\Factories\MatchGameFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_id
 * @property int $game_id
 * @property int|null $beatmap_id
 * @property string|null $beatmap_title
 * @property string|null $beatmap_version
 * @property array<int, string>|null $mods
 * @property string|null $mode
 * @property string|null $scoring_type
 * @property string|null $team_type
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read OsuMatch $match
 * @property-read Collection<int, MatchScore> $scores
 *
 * @method static Builder<static>|static query()
 * @method static \Database\Factories\MatchGameFactory factory(...$parameters)
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static static|null find($id)
 * @method static static findOrFail($id)
 */
class MatchGame extends Model
{
    /** @use HasFactory<MatchGameFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'game_id',
        'beatmap_id',
        'beatmap_title',
        'beatmap_version',
        'mods',
        'mode',
        'scoring_type',
        'team_type',
        'start_time',
        'end_time',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'mods' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the match this game belongs to.
     *
     * @return BelongsTo<OsuMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(OsuMatch::class);
    }

    /**
     * Get all scores for this game.
     *
     * @return HasMany<MatchScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(MatchScore::class);
    }
}
