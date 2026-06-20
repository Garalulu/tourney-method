<?php

namespace App\Models;

use App\Services\CldrService;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int $osu_id
 * @property string $username
 * @property-read string $avatar_url
 * @property string|null $country_code
 * @property string $locale
 * @property-read string|null $country_name
 * @property string|null $main_mode
 * @property-read string|null $formatted_main_mode
 * @property string|null $main_mode_source
 * @property int|null $sip
 * @property CarbonInterface|null $sip_updated_at
 * @property string $role
 * @property string|null $discord_webhook_url
 * @property bool $discord_webhook_valid
 * @property bool $notify_registration
 * @property bool $notify_stream
 * @property array<string, bool>|null $in_app_notification_preferences
 * @property CarbonInterface|null $osu_data_synced_at
 * @property CarbonInterface|null $last_login_at
 * @property string|null $latest_rank_recorded_at
 * @property int|null $rank_mania_4k
 * @property int|null $rank_mania_7k
 * @property array<string>|null $previous_usernames
 * @property CarbonInterface|null $deleted_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Collection<int, UserBadge> $badges
 * @property-read Collection<int, UserRankHistory> $rankHistory
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read Collection<int, TournamentWatch> $watches
 * @property-read Collection<int, UserNotification> $notifications
 * @property-read Collection<int, ParticipationDeletionRequest> $participationDeletionRequests
 * @property-read Collection<int, ParticipationRecordReport> $participationRecordReports
 * @property-read Collection<int, TournamentCorrection> $tournamentCorrections
 * @property-read Pivot|null $pivot
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static firstOrNew(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 * @method static static firstOrCreate(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 * @method static static updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static Builder<static>|static whereIn(string $column, mixed $values)
 * @method static Builder<static>|static whereNotIn(string $column, mixed $values)
 * @method static static|null find($id)
 * @method static static findOrFail($id)
 * @method static static first()
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Bootstrap the model.
     */
    protected static function booted(): void
    {
        // Auto-promote master user based on env variable
        static::saving(function (User $user) {
            $masterOsuId = config('app.master_osu_id');

            if ($masterOsuId && $user->osu_id == $masterOsuId && $user->role !== 'master') {
                $user->role = 'master';
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'osu_id',
        'username',
        'country_code',
        'locale',
        'main_mode',
        'main_mode_source',
        'sip',
        'sip_updated_at',
        'role',
        'discord_webhook_url',
        'discord_webhook_valid',
        'notify_registration',
        'notify_stream',
        'in_app_notification_preferences',
        'osu_data_synced_at',
        'last_login_at',
        'rank_mania_4k',
        'rank_mania_7k',
        'previous_usernames',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discord_webhook_valid' => 'boolean',
            'notify_registration' => 'boolean',
            'notify_stream' => 'boolean',
            'in_app_notification_preferences' => 'array',
            'osu_data_synced_at' => 'datetime',
            'last_login_at' => 'datetime',
            'rank_mania_4k' => 'integer',
            'rank_mania_7k' => 'integer',
            'previous_usernames' => 'array',
        ];
    }

    /**
     * Accessor: Get localized country name from country_code.
     *
     * @return string|null Localized country name, or null if no country_code
     */
    protected function getCountryNameAttribute(): ?string
    {
        if (! $this->country_code) {
            return null;
        }

        $locale = $this->locale ?? app()->getLocale();
        $name = app(CldrService::class)->getCountryName($this->country_code, $locale);

        return $name ?: $this->country_code;
    }

    /**
     * Accessor: derive the current osu! avatar URL from the osu_id.
     */
    protected function getAvatarUrlAttribute(): string
    {
        if (! $this->osu_id) {
            return 'https://a.ppy.sh/';
        }

        return 'https://a.ppy.sh/'.$this->osu_id;
    }

    /**
     * Accessor: Get formatted main_mode for display
     * osu -> osu!
     * taiko -> osu!taiko
     * catch -> osu!catch
     * mania -> osu!mania
     */
    protected function getFormattedMainModeAttribute(): ?string
    {
        if (! $this->main_mode) {
            return null;
        }

        return match ($this->main_mode) {
            'osu' => 'osu!',
            'taiko' => 'osu!taiko',
            'catch', 'fruits' => 'osu!catch',
            'mania' => 'osu!mania',
            default => $this->main_mode,
        };
    }

    /**
     * Relationships
     *
     * @return HasMany<UserBadge, $this>
     */
    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /**
     * @return HasMany<UserRankHistory, $this>
     */
    public function rankHistory(): HasMany
    {
        return $this->hasMany(UserRankHistory::class);
    }

    /**
     * @return BelongsToMany<Tournament, $this>
     */
    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class, 'tournament_staff')
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    /**
     * @return HasMany<TournamentWinner, $this>
     */
    public function tournamentWinners(): HasMany
    {
        return $this->hasMany(TournamentWinner::class);
    }

    /**
     * @return HasMany<TournamentParticipationRecord, $this>
     */
    public function tournamentParticipationRecords(): HasMany
    {
        return $this->hasMany(TournamentParticipationRecord::class);
    }

    /**
     * @return HasOne<ParticipationInputLock, $this>
     */
    public function participationInputLock(): HasOne
    {
        return $this->hasOne(ParticipationInputLock::class);
    }

    /**
     * @return HasMany<ParticipationDeletionRequest, $this>
     */
    public function participationDeletionRequests(): HasMany
    {
        return $this->hasMany(ParticipationDeletionRequest::class, 'requested_by');
    }

    /**
     * @return HasMany<ParticipationRecordReport, $this>
     */
    public function participationRecordReports(): HasMany
    {
        return $this->hasMany(ParticipationRecordReport::class, 'reported_by');
    }

    /**
     * @return HasMany<TournamentCorrection, $this>
     */
    public function tournamentCorrections(): HasMany
    {
        return $this->hasMany(TournamentCorrection::class, 'submitted_by');
    }

    public function hasParticipationInputLock(): bool
    {
        return $this->participationInputLock()->exists();
    }

    /**
     * Tournament staff roles (alias for tournaments())
     *
     * @return BelongsToMany<Tournament, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->tournaments();
    }

    /**
     * Tournaments where this user is the host (via host_username)
     *
     * @return HasMany<Tournament, $this>
     */
    public function tournamentsHosted(): HasMany
    {
        return $this->hasMany(Tournament::class, 'host_username', 'username');
    }

    /**
     * @return HasMany<TournamentWatch, $this>
     */
    public function watches(): HasMany
    {
        return $this->hasMany(TournamentWatch::class);
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * Helper methods
     */
    public function hasCompletedSetup(): bool
    {
        return ! is_null($this->main_mode);
    }

    /**
     * Check if user should be able to access/setup main_mode
     * Returns true if main_mode was auto-detected (not manually selected)
     */
    public function needsManualModeSetup(): bool
    {
        // No main_mode set yet
        if (is_null($this->main_mode)) {
            return true;
        }

        // Has main_mode but it was auto-detected by command
        // (null or 'auto_detected' both mean auto-detected for existing users)
        if ($this->main_mode_source === null || $this->main_mode_source === 'auto_detected') {
            return true;
        }

        // Manually selected via OAuth setup
        return false;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'master']);
    }

    public function isMaster(): bool
    {
        return $this->role === 'master';
    }

    public function isAdminOrMaster(): bool
    {
        return $this->isAdmin() || $this->isMaster();
    }

    /**
     * Get appropriate rank based on tournament's mania variant
     */
    public function getRankForMode(string $mode, ?int $keyCount = null): ?int
    {
        // First, try to get from rank history
        $rankHistory = $this->rankHistory()->where('mode', $mode)->first();

        if ($rankHistory) {
            $rank = $rankHistory->rank;

            // If it's mania and we have a specific key count, try to get specific rank
            if ($mode === 'mania' && $keyCount) {
                $specificRank = $this->getManiaRank($keyCount);

                return $specificRank ?? $rank;
            }

            return $rank;
        }

        // Fallback: check specific mania rankings stored in user table
        if ($mode === 'mania') {
            return $this->getManiaRank($keyCount);
        }

        return null;
    }

    /**
     * Get mania rank for specific key count
     */
    public function getManiaRank(?int $keyCount = null): ?int
    {
        if ($keyCount === 4) {
            return $this->rank_mania_4k;
        }

        if ($keyCount === 7) {
            return $this->rank_mania_7k;
        }

        // If specific key count was requested but not found (e.g., 8K, 9K), return null
        if ($keyCount !== null) {
            return null;
        }

        // Fallback to global mania rank if no specific key count
        $rankHistory = $this->rankHistory()->where('mode', 'mania')->first();

        return $rankHistory?->rank;
    }

    /**
     * Check if user is eligible for tournament based on rank and mode
     */
    public function isEligibleForTournament(Tournament $tournament): bool
    {
        foreach ($tournament->modes_with_details as $modeDetail) {
            $mode = $modeDetail['mode'];
            $keyCount = $modeDetail['key_count'] ?? null;

            $userRank = $this->getRankForMode($mode, $keyCount);

            if ($userRank === null) {
                continue; // User doesn't have ranking for this mode
            }

            // Check if user's rank is within tournament's range
            if ($tournament->rank_range_min !== null && $userRank < $tournament->rank_range_min) {
                return false;
            }

            if ($tournament->rank_range_max !== null && $userRank > $tournament->rank_range_max) {
                return false;
            }

            // Check regional restriction
            if (! $tournament->isUserEligibleByCountry($this->country_code)) {
                return false;
            }

            return true; // Passed all checks for at least one mode
        }

        return false; // User doesn't have required rankings
    }

    /**
     * Check if user has any approved tournament staff roles in the last 12 months
     */
    public function hasRecentStaffRoles(): bool
    {
        $twelveMonthsAgo = now()->subMonths(12);

        return $this->tournaments()
            ->wherePivot('status', 'approved')
            ->where('tournaments.status', 'approved')
            ->where('tournament_end', '>=', $twelveMonthsAgo)
            ->exists();
    }

    /**
     * Check if user has any podium placements (1st, 2nd, 3rd) in the last 12 months
     */
    public function hasRecentPodiumPlacements(): bool
    {
        $twelveMonthsAgo = now()->subMonths(12);

        return TournamentWinner::where('user_id', $this->id)
            ->where('placement', '<=', 3)
            ->whereHas('tournament', function ($query) use ($twelveMonthsAgo) {
                $query->where('status', 'approved')
                    ->where('tournament_end', '>=', $twelveMonthsAgo);
            })
            ->exists();
    }

    /**
     * Check if user has any approved tournament participation records in the last 12 months.
     */
    public function hasRecentParticipationRecords(): bool
    {
        $twelveMonthsAgo = now()->subMonths(12);

        return $this->tournamentParticipationRecords()
            ->where('review_status', TournamentParticipationRecord::REVIEW_APPROVED)
            ->whereHas('tournament', function ($query) use ($twelveMonthsAgo): void {
                $query->where('status', Tournament::STATUS_APPROVED)
                    ->where('tournament_end', '>=', $twelveMonthsAgo);
            })
            ->exists();
    }

    /**
     * Get users by tournament role and year range.
     *
     * @param  'winners'|'staff'|'hosts'|'all'  $type
     * @return Collection<int, static>
     */
    public static function getTournamentUsers(string $type, int $startYear, int $endYear, bool $essentialOnly = false): Collection
    {
        $query = static::query();

        match ($type) {
            'winners' => self::applyWinnerUserCriteria($query, $startYear, $endYear, $essentialOnly),
            'staff' => $query->whereHas('staff', function ($q) use ($startYear, $endYear) {
                $q->whereYear('tournament_end', '>=', $startYear)
                    ->whereYear('tournament_end', '<=', $endYear);
            })->whereNotIn('id', function ($subQuery) {
                // Exclude winners (they're synced separately)
                $subQuery->select('user_id')
                    ->from('tournament_winners')
                    ->whereNotNull('user_id');
            }),
            'hosts' => $query->whereHas('tournamentsHosted', function ($q) use ($startYear, $endYear) {
                $q->whereYear('tournament_end', '>=', $startYear)
                    ->whereYear('tournament_end', '<=', $endYear);
            })->whereNotIn('id', function ($subQuery) {
                // Exclude winners and staff (already synced)
                $subQuery->select('user_id')
                    ->from('tournament_winners')
                    ->whereNotNull('user_id')
                    ->union(
                        \DB::table('tournament_staff')
                            ->select('user_id')
                            ->whereNotNull('user_id')
                    );
            }),
            'all' => self::applyAllTournamentUserCriteria($query, $startYear, $endYear, $essentialOnly),
        };

        if ($essentialOnly && in_array($type, ['staff', 'hosts'], true)) {
            $query->whereNull('country_code');
        }

        return $query->get();
    }

    /**
     * @param  Builder<static>  $query
     */
    private static function applyWinnerUserCriteria(Builder $query, int $startYear, int $endYear, bool $essentialOnly): void
    {
        $query->whereHas('tournamentWinners', function ($winnerQuery) use ($startYear, $endYear, $essentialOnly) {
            $winnerQuery->whereHas('tournament', function ($tournamentQuery) use ($startYear, $endYear, $essentialOnly) {
                $tournamentQuery->whereYear('tournament_end', '>=', $startYear)
                    ->whereYear('tournament_end', '<=', $endYear);

                if ($essentialOnly) {
                    $tournamentQuery->where('is_badge', true)
                        ->whereNotNull('tournament_end')
                        ->where('tournament_end', '<', now())
                        ->where(fn ($badgeStatusQuery) => $badgeStatusQuery
                            ->whereIn('badge_status', ['approved', 'pending'])
                            ->orWhereNull('badge_status')
                        );
                }
            });

            if ($essentialOnly) {
                $winnerQuery->where('placement', '<=', 3)
                    ->where(fn ($missingBadgeQuery) => $missingBadgeQuery
                        ->whereNull('badge_url')
                        ->orWhere('badge_url', '')
                        ->orWhereNull('badge_description')
                        ->orWhere('badge_description', '')
                    );
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    private static function applyAllTournamentUserCriteria(Builder $query, int $startYear, int $endYear, bool $essentialOnly): void
    {
        if (! $essentialOnly) {
            $query->where(function ($q) use ($startYear, $endYear) {
                $q->whereHas('tournamentWinners', function ($subQ) use ($startYear, $endYear) {
                    $subQ->whereHas('tournament', function ($tq) use ($startYear, $endYear) {
                        $tq->whereYear('tournament_end', '>=', $startYear)
                            ->whereYear('tournament_end', '<=', $endYear);
                    });
                })->orWhereHas('staff', function ($subQ) use ($startYear, $endYear) {
                    $subQ->whereYear('tournament_end', '>=', $startYear)
                        ->whereYear('tournament_end', '<=', $endYear);
                })->orWhereHas('tournamentsHosted', function ($subQ) use ($startYear, $endYear) {
                    $subQ->whereYear('tournament_end', '>=', $startYear)
                        ->whereYear('tournament_end', '<=', $endYear);
                });
            });

            return;
        }

        $query->where(function ($q) use ($startYear, $endYear) {
            $q->where(function ($winnerUserQuery) use ($startYear, $endYear) {
                self::applyWinnerUserCriteria($winnerUserQuery, $startYear, $endYear, true);
            })->orWhere(function ($staffUserQuery) use ($startYear, $endYear) {
                $staffUserQuery->whereNull('country_code')
                    ->whereHas('staff', function ($subQ) use ($startYear, $endYear) {
                        $subQ->whereYear('tournament_end', '>=', $startYear)
                            ->whereYear('tournament_end', '<=', $endYear);
                    })
                    ->whereNotIn('id', function ($subQuery) {
                        $subQuery->select('user_id')
                            ->from('tournament_winners')
                            ->whereNotNull('user_id');
                    });
            })->orWhere(function ($hostUserQuery) use ($startYear, $endYear) {
                $hostUserQuery->whereNull('country_code')
                    ->whereHas('tournamentsHosted', function ($subQ) use ($startYear, $endYear) {
                        $subQ->whereYear('tournament_end', '>=', $startYear)
                            ->whereYear('tournament_end', '<=', $endYear);
                    })
                    ->whereNotIn('id', function ($subQuery) {
                        $subQuery->select('user_id')
                            ->from('tournament_winners')
                            ->whereNotNull('user_id')
                            ->union(
                                \DB::table('tournament_staff')
                                    ->select('user_id')
                                    ->whereNotNull('user_id')
                            );
                    });
            });
        });
    }
}
