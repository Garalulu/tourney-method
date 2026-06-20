<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentCorrectionComment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tournament_correction_id',
        'user_id',
        'body',
    ];

    /**
     * @return BelongsTo<TournamentCorrection, $this>
     */
    public function correction(): BelongsTo
    {
        return $this->belongsTo(TournamentCorrection::class, 'tournament_correction_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
