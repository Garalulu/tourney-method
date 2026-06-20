<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property int|null $tournament_id
 * @property string $status
 * @property int $total
 * @property int $completed
 * @property int $failed
 * @property string|null $message
 * @property array<string, mixed>|null $result
 * @property array<int, array<string, mixed>>|null $errors
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read Tournament|null $tournament
 */
class AdminAsyncOperation extends Model
{
    public const TYPE_STAFF_BULK_ADD = 'staff_bulk_add';

    public const TYPE_PODIUM_BULK_ADD = 'podium_bulk_add';

    public const TYPE_TOURNAMENT_REPARSE = 'tournament_reparse';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'type',
        'tournament_id',
        'status',
        'total',
        'completed',
        'failed',
        'message',
        'result',
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
            'result' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function start(string $message): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'message' => $message,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function advance(string $message, int $completedBy = 1): void
    {
        $this->increment('completed', $completedBy);
        $this->update(['message' => $message]);
    }

    /**
     * @param  array<string, mixed>  $error
     */
    public function recordItemError(array $error, string $message): void
    {
        $errors = $this->errors ?? [];
        $errors[] = array_merge($error, [
            'recorded_at' => now()->toISOString(),
        ]);

        $this->update([
            'failed' => $this->failed + 1,
            'message' => $message,
            'errors' => $errors,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function finish(string $message, array $result = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'message' => $message,
            'result' => array_merge($this->result ?? [], $result),
            'completed_at' => now(),
        ]);
    }

    public function fail(string $message, ?\Throwable $exception = null): void
    {
        $errors = $this->errors ?? [];

        if ($exception) {
            $errors[] = [
                'message' => $exception->getMessage(),
                'recorded_at' => now()->toISOString(),
            ];
        }

        $this->update([
            'status' => self::STATUS_FAILED,
            'message' => $message,
            'errors' => $errors,
            'completed_at' => now(),
        ]);
    }

    public function percent(): int
    {
        if ($this->total === 0) {
            return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true) ? 100 : 0;
        }

        return (int) min(100, round(($this->completed / max(1, $this->total)) * 100));
    }
}
