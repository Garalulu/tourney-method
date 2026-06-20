<?php

namespace App\Models;

use Database\Factories\AdminMaintenanceRunItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminMaintenanceRunItem extends Model
{
    /** @use HasFactory<AdminMaintenanceRunItemFactory> */
    use HasFactory;

    public const TYPE_TOURNAMENT = 'tournament';

    public const TYPE_USER = 'user';

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_SYNCED = 'synced';

    public const ACTION_NEW_BADGES = 'new_badges';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'admin_maintenance_run_id',
        'item_type',
        'action',
        'status',
        'tournament_id',
        'tournament_title',
        'user_id',
        'osu_id',
        'username',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'osu_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AdminMaintenanceRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AdminMaintenanceRun::class, 'admin_maintenance_run_id');
    }
}
