<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_parse_batch_id
 * @property int $tournament_id
 * @property array<int, array<string, mixed>> $staff_payload
 * @property Carbon|null $parsed_at
 */
class TournamentParseStaffPayload extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tournament_parse_batch_id',
        'tournament_id',
        'staff_payload',
        'parsed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'staff_payload' => 'array',
            'parsed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TournamentParseBatch, $this>
     */
    public function parseBatch(): BelongsTo
    {
        return $this->belongsTo(TournamentParseBatch::class, 'tournament_parse_batch_id');
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
