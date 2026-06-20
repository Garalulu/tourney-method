<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AdminMaintenanceRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $command
 * @property string $label
 * @property string $source
 * @property string $status
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $summary
 * @property array<int, array<string, mixed>>|null $errors
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class AdminMaintenanceRun extends Model
{
    /** @use HasFactory<AdminMaintenanceRunFactory> */
    use HasFactory;

    public const COMMAND_TOURNAMENTS_PARSE = 'tournaments:parse';

    public const COMMAND_SYNC_TOURNAMENT = 'sync:tournament';

    public const COMMAND_SYNC_USER_PROFILES = 'sync:user-profiles';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function maintenanceCommands(): array
    {
        return [
            self::COMMAND_TOURNAMENTS_PARSE,
            self::COMMAND_SYNC_TOURNAMENT,
            self::COMMAND_SYNC_USER_PROFILES,
        ];
    }

    /** @var list<string> */
    protected $fillable = [
        'command',
        'label',
        'source',
        'status',
        'options',
        'summary',
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
            'options' => 'array',
            'summary' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AdminMaintenanceRunItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AdminMaintenanceRunItem::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSystemMaintenance(Builder $query): Builder
    {
        $query->where('source', 'cli')
            ->whereIn('command', self::maintenanceCommands());

        return $query;
    }

    public function durationForHumans(): string
    {
        if (! $this->started_at) {
            return 'Not started';
        }

        $end = $this->completed_at ?? now();

        return $this->started_at->diffForHumans($end, CarbonInterface::DIFF_ABSOLUTE);
    }
}
