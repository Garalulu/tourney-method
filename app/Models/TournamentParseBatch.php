<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $transaction_id
 * @property int|null $admin_maintenance_run_id
 * @property string $stage
 * @property string $status
 * @property array<int, int>|null $tournament_ids
 * @property array<int|string, int>|null $user_mapping
 * @property array<int, array<string, mixed>>|null $errors
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class TournamentParseBatch extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'admin_maintenance_run_id',
        'stage',
        'status',
        'tournament_ids',
        'user_mapping',
        'errors',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tournament_ids' => 'array',
            'user_mapping' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<TournamentParseStaffPayload, $this>
     */
    public function staffPayloads(): HasMany
    {
        return $this->hasMany(TournamentParseStaffPayload::class);
    }
}
