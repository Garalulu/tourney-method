<?php

namespace App\Models;

use Database\Factories\TournamentParseHistoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int|null $parsed_by
 * @property array<string, mixed>|null $changes
 * @property array<string, mixed>|null $conflicts PHASE 3: Conflicts detected during re-parse
 * @property array<string, string>|null $resolution PHASE 3: Conflict resolution choices ['field' => 'manual'|'parsed']
 * @property array<string, mixed>|null $parsed_data
 * @property string|null $parse_source
 * @property Carbon|null $parsed_at
 * @property Carbon|null $compacted_at
 * @property string|null $parse_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tournament $tournament
 * @property-read User|null $parsedBy
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static static|null find($id)
 * @method static Builder<static> forTournament(int $tournamentId)
 */
class TournamentParseHistory extends Model
{
    /** @use HasFactory<TournamentParseHistoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tournament_id',
        'parsed_by',
        'changes',
        'conflicts',
        'resolution',
        'parsed_data',
        'parse_source',
        'parsed_at',
        'compacted_at',
        'parse_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'conflicts' => 'array',
            'resolution' => 'array',
            'parsed_data' => 'array',
            'parse_source' => 'string',
            'parsed_at' => 'datetime',
            'compacted_at' => 'datetime',
            'parse_notes' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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

    /**
     * User who parsed the data
     *
     * @return BelongsTo<User, $this>
     */
    public function parsedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parsed_by');
    }

    /**
     * Scope for filtering by tournament
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTournament(Builder $query, int $tournamentId): Builder
    {
        return $query->where('tournament_id', $tournamentId);
    }
}
