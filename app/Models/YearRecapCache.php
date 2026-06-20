<?php

namespace App\Models;

use Database\Factories\YearRecapCacheFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property int $year
 * @property string $image_path
 * @property array<string, mixed> $stats_json
 * @property Carbon $generated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 *
 * @method static Builder<self>|self query()
 * @method static Builder<self>|self where($column, $operator = null, $value = null)
 * @method static Builder<self>|self whereIn($column, $values, $boolean = 'and')
 * @method static Builder<self>|self whereHas($relation, $callback = null, $operator = '>=', $count = 1)
 * @method static Builder<self>|self forUserAndYear(int $userId, int $year)
 * @method static self|null first()
 * @method static self firstOrFail($columns = ['*'])
 * @method static self|null find($id, $columns = ['*'])
 * @method static self updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values)
 * @method static self create(array<string, mixed> $attributes)
 */
class YearRecapCache extends Model
{
    /** @use HasFactory<YearRecapCacheFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'year_recap_cache';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'year',
        'image_path',
        'stats_json',
        'generated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'stats_json' => 'array',
        'year' => 'integer',
        'generated_at' => 'datetime',
    ];

    /**
     * The user who owns this recap.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full URL for the cached image.
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->attributes['image_path'] ?? null) {
            return Storage::url($this->attributes['image_path']);
        }

        return null;
    }

    /**
     * Delete the model and also delete the image file.
     */
    public function delete(): ?bool
    {
        // Delete the image file if it exists
        if (isset($this->attributes['image_path']) && Storage::exists($this->attributes['image_path'])) {
            Storage::delete($this->attributes['image_path']);
        }

        return parent::delete();
    }

    /**
     * Scope: Get recap for a specific user and year.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUserAndYear($query, int $userId, int $year): Builder
    {
        return $query->where('user_id', $userId)->where('year', $year);
    }

    /**
     * Find or create a recap for a specific user and year.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public static function updateOrCreateForUser(array $attributes, array $values): self
    {
        return static::query()->updateOrCreate($attributes, $values);
    }
}
