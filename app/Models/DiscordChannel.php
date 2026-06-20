<?php

namespace App\Models;

use Database\Factories\DiscordChannelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $discord_server_id
 * @property string $channel_name
 * @property string|null $webhook_url
 * @property string $mode
 * @property bool $is_badge
 * @property array<string|int, mixed> $role_mappings
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read DiscordServer|null $server
 */
class DiscordChannel extends Model
{
    /** @use HasFactory<DiscordChannelFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'discord_server_id',
        'channel_name',
        'webhook_url',
        'mode',
        'is_badge',
        'role_mappings',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_badge' => 'boolean',
        'role_mappings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get channels for a specific mode.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForMode($query, string $mode)
    {
        return $query->where('mode', $mode);
    }

    /**
     * Scope to get channels for badge tournaments.
     */
    public function scopeForBadge($query, bool $isBadge = true)
    {
        return $query->where('is_badge', $isBadge);
    }

    /**
     * Scope to get active channels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<DiscordServer, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class, 'discord_server_id');
    }

    /**
     * Get role mentions for a rank range.
     *
     * Uses threshold-based logic to find the highest role with threshold <= minRank.
     * Returns only ONE role ping (the highest applicable role).
     *
     * Examples:
     * - Tournament #100-#50000 → pings 100+ role (threshold 100 <= 100)
     * - Tournament #2500-#50000 → pings 1000+ role (threshold 1000 <= 2500)
     *
     * @param  int|null  $minRank  Minimum rank in tournament
     * @param  int|null  $maxRank  Maximum rank in tournament (unused in threshold-based system)
     * @return array<int, string> Array with single role mention (e.g., "<@&1234567890>") or empty
     */
    public function getRolePings(?int $minRank, ?int $maxRank): array
    {
        if (empty($this->role_mappings) || ! is_array($this->role_mappings)) {
            return [];
        }

        // Check if role_mappings use new threshold structure
        if (! isset($this->role_mappings[0]['threshold'])) {
            // Old format: try to convert or return empty
            return [];
        }

        // If Open Rank (minRank is null), ping the Open role
        if ($minRank === null) {
            $openRole = collect($this->role_mappings)->firstWhere('threshold', 1);
            if ($openRole) {
                return ["<@&{$openRole['role_id']}>"];
            }

            return [];
        }

        // Sort roles by threshold descending (highest first)
        $sortedRoles = collect($this->role_mappings)
            ->sortByDesc('threshold')
            ->values();

        // Find highest role with threshold <= minRank
        foreach ($sortedRoles as $role) {
            if ($role['threshold'] <= $minRank) {
                return ["<@&{$role['role_id']}>"];
            }
        }

        return [];
    }

    /**
     * Get the appropriate Discord channel for a tournament.
     *
     * @param  string  $mode  Game mode
     * @param  bool  $isBadge  Whether tournament is a badge tournament
     * @return static|null
     */
    public static function forTournament(string $mode, bool $isBadge): ?self
    {
        return static::active()
            ->forMode($mode)
            ->forBadge($isBadge)
            ->first();
    }

    /**
     * @param  array<int, string>  $restrictedCountries
     * @return Collection<int, static>
     */
    public static function forTournamentDestinations(string $mode, bool $isBadge, array $restrictedCountries = []): Collection
    {
        $destinations = static::active()
            ->forMode($mode)
            ->forBadge($isBadge)
            ->with('server')
            ->orderBy('channel_name')
            ->get();

        if ($restrictedCountries === []) {
            return $destinations;
        }

        return $destinations
            ->filter(fn (self $destination): bool => $destination->server?->matchesRestrictedCountries($restrictedCountries) ?? false)
            ->values();
    }
}
