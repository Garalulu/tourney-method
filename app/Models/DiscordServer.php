<?php

namespace App\Models;

use Database\Factories\DiscordServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $server_name
 * @property array<string|int, mixed> $role_mappings
 * @property array<int, string> $countries
 * @property bool $send_unmatched_rank_alerts
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DiscordServer extends Model
{
    /** @use HasFactory<DiscordServerFactory> */
    use HasFactory;

    protected $fillable = [
        'server_name',
        'role_mappings',
        'countries',
        'send_unmatched_rank_alerts',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'role_mappings' => 'array',
        'countries' => 'array',
        'send_unmatched_rank_alerts' => 'boolean',
    ];

    /**
     * @return HasMany<DiscordChannel, $this>
     */
    public function destinations(): HasMany
    {
        return $this->hasMany(DiscordChannel::class, 'discord_server_id');
    }

    /**
     * @return array<int, string>
     */
    public function getRolePings(?int $minRank, ?int $maxRank): array
    {
        if (empty($this->role_mappings) || ! is_array($this->role_mappings)) {
            return [];
        }

        if (! isset($this->role_mappings[0]['threshold'])) {
            return [];
        }

        if ($minRank === null) {
            $openRole = collect($this->role_mappings)->firstWhere('threshold', 1);

            return $openRole ? ["<@&{$openRole['role_id']}>"] : [];
        }

        foreach (collect($this->role_mappings)->sortByDesc('threshold')->values() as $role) {
            if ($role['threshold'] <= $minRank) {
                return ["<@&{$role['role_id']}>"];
            }
        }

        return [];
    }

    public function rankRangeStartsBeyondConfiguredRoles(?int $minRank): bool
    {
        if ($minRank === null || empty($this->role_mappings) || ! is_array($this->role_mappings)) {
            return false;
        }

        if (! isset($this->role_mappings[0]['threshold'])) {
            return false;
        }

        $largestThreshold = collect($this->role_mappings)
            ->pluck('threshold')
            ->map(fn (mixed $threshold): int => (int) $threshold)
            ->filter(fn (int $threshold): bool => $threshold > 0)
            ->max();

        if (! is_int($largestThreshold) || $largestThreshold < 1) {
            return false;
        }

        $boundary = 10 ** strlen((string) $largestThreshold);

        return $minRank >= $boundary;
    }

    /**
     * @param  array<int, string>  $restrictedCountries
     */
    public function matchesRestrictedCountries(array $restrictedCountries): bool
    {
        if ($restrictedCountries === []) {
            return true;
        }

        $serverCountries = collect($this->countries ?? [])
            ->map(fn (mixed $country): string => strtoupper((string) $country))
            ->all();

        if ($serverCountries === []) {
            return false;
        }

        $restrictedCountries = collect($restrictedCountries)
            ->map(fn (mixed $country): string => strtoupper((string) $country))
            ->all();

        return array_intersect($serverCountries, $restrictedCountries) !== [];
    }
}
