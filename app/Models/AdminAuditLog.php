<?php

namespace App\Models;

use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $action
 * @property string $entity_type
 * @property int $entity_id
 * @property array<string, mixed>|null $details
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User $admin
 * @property-read Model $entity
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static static|null find($id)
 * @method static static findOrFail($id)
 */
class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function log(User $admin, string $action, Model $entity, array $details = []): self
    {
        return self::logRaw($admin, $action, get_class($entity), $entity->id, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function logRaw(User $admin, string $action, string $entityType, int $entityId, array $details = []): self
    {
        return self::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => request()->ip(),
        ]);
    }
}
