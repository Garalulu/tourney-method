<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $osu_id
 * @property string $username
 * @property string $status
 * @property int|null $sip
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<SipFetchQueue>|SipFetchQueue query()
 * @method static \Illuminate\Database\Eloquent\Builder<SipFetchQueue>|SipFetchQueue where(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder<SipFetchQueue>|SipFetchQueue whereIn(string $column, mixed $values)
 * @method static \Illuminate\Database\Eloquent\Builder<SipFetchQueue>|SipFetchQueue whereStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<SipFetchQueue>|SipFetchQueue whereOsuId(int $osuId)
 * @method static SipFetchQueue create(array<string, mixed> $attributes = [])
 * @method static SipFetchQueue|null find(mixed $id)
 */
class SipFetchQueue extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sip_fetch_queue';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'osu_id',
        'username',
        'status',
        'sip',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'osu_id' => 'integer',
            'sip' => 'integer',
        ];
    }
}
