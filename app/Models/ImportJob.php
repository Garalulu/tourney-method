<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ImportJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $source
 * @property string $status
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property int $tournaments_imported
 * @property int $tournaments_updated
 * @property int $tournaments_failed
 * @property int $matches_imported
 * @property int $matches_failed
 * @property string|null $last_cursor
 * @property array<int, array<string, mixed>>|null $error_log
 * @property int|null $rate_limit_count
 * @property CarbonInterface|null $rate_limit_window_start
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 *
 * @method static Builder<static>|static query()
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static Builder<static> active()
 */
class ImportJob extends Model
{
    /** @use HasFactory<ImportJobFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'source',
        'status',
        'started_at',
        'completed_at',
        'tournaments_imported',
        'tournaments_updated',
        'tournaments_failed',
        'matches_imported',
        'matches_failed',
        'last_cursor',
        'error_log',
        'rate_limit_count',
        'rate_limit_window_start',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'source' => 'string',
            'status' => 'string',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'tournaments_imported' => 'integer',
            'tournaments_updated' => 'integer',
            'tournaments_failed' => 'integer',
            'matches_imported' => 'integer',
            'matches_failed' => 'integer',
            'error_log' => 'array',
            'rate_limit_count' => 'integer',
            'rate_limit_window_start' => 'datetime',
        ];
    }

    /**
     * Import job statuses
     */
    const STATUS_PENDING = 'pending';

    const STATUS_RUNNING = 'running';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    const STATUS_CANCELLED = 'cancelled';

    /**
     * Import sources
     */
    const SOURCE_TCOMM = 'tcomm';

    const SOURCE_OTR = 'otr';

    const SOURCE_COMBINED = 'combined';

    /**
     * Scope for active/running jobs
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Mark the job as started
     */
    public function markAsStarted(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->started_at = now();
        $this->save();
    }

    /**
     * Mark the job as completed
     */
    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark the job as failed with error details
     */
    public function markAsFailed(string $error): void
    {
        $this->status = self::STATUS_FAILED;
        $this->completed_at = now();

        $errors = $this->error_log ?? [];
        $errors[] = [
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->error_log = $errors;

        $this->save();
    }

    /**
     * Mark the job as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Update progress counters
     *
     * @param  array<string, mixed>  $stats
     */
    public function updateProgress(array $stats): void
    {
        if (isset($stats['tournaments_imported'])) {
            $this->tournaments_imported = $stats['tournaments_imported'];
        }
        if (isset($stats['tournaments_updated'])) {
            $this->tournaments_updated = $stats['tournaments_updated'];
        }
        if (isset($stats['tournaments_failed'])) {
            $this->tournaments_failed = $stats['tournaments_failed'];
        }
        if (isset($stats['matches_imported'])) {
            $this->matches_imported = $stats['matches_imported'];
        }
        if (isset($stats['matches_failed'])) {
            $this->matches_failed = $stats['matches_failed'];
        }
        if (isset($stats['last_cursor'])) {
            $this->last_cursor = $stats['last_cursor'];
        }

        $this->save();
    }

    /**
     * Add an error to the error log
     */
    public function addError(string $type, int|string $externalId, string $error): void
    {
        $errors = $this->error_log ?? [];
        $errors[] = [
            'type' => $type,
            'external_id' => $externalId,
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->error_log = $errors;
        $this->save();
    }

    /**
     * Get total processed items count
     */
    public function getTotalProcessedAttribute(): int
    {
        return $this->tournaments_imported
            + $this->tournaments_updated
            + $this->tournaments_failed;
    }

    /**
     * Get total items count
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->tournaments_imported
            + $this->tournaments_updated
            + $this->tournaments_failed;
    }

    /**
     * Check if job is complete
     */
    public function isComplete(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Check if job is running
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }
}
