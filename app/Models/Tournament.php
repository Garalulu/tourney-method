<?php

namespace App\Models;

use App\Helpers\StaffRoleHelper;
use App\Services\BwsCalculator;
use App\Services\CldrService;
use Carbon\CarbonInterface;
use Database\Factories\TournamentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Jfcherng\Diff\DiffHelper;

/**
 * @property int $id
 * @property int|null $forum_topic_id
 * @property string|null $forum_post_sha256
 * @property-read string|null $forum_post_url Computed from forum_topic_id
 * @property string $title
 * @property string|null $description
 * @property int|null $host_osu_id
 * @property string|null $host_username
 * @property string $status
 * @property array<int, string>|null $modes
 * @property int|null $team_size_min
 * @property int|null $team_size_max
 * @property int|null $vs_size
 * @property CarbonInterface|null $registration_start
 * @property CarbonInterface|null $registration_end
 * @property CarbonInterface|null $tournament_start
 * @property CarbonInterface|null $tournament_end
 * @property int|null $rank_range_min
 * @property int|null $rank_range_max
 * @property bool $is_badge
 * @property string|null $badge_status
 * @property array<int, array<int, string>>|null $badge_urls
 * @property bool $is_bws
 * @property float|null $bws_base_exponent
 * @property float|null $bws_badge_power
 * @property float|null $bws_divisor
 * @property int|null $bws_badge_age_limit
 * @property float|null $star_rating_min
 * @property float|null $star_rating_max
 * @property string|null $format
 * @property string $team_formation_style
 * @property array<int, string>|null $format_tags
 * @property array<string, mixed>|null $format_structure
 * @property string|null $banner_url
 * @property string|null $discord_url
 * @property string|null $twitch_url
 * @property string|null $spreadsheet_url
 * @property string|null $bracket_url
 * @property string|null $registration_url
 * @property string|null $tcomm_url
 * @property int|null $tcomm_id
 * @property int|null $otr_id
 * @property string|null $import_source
 * @property string|null $rejection_reason
 * @property CarbonInterface|null $parsed_at
 * @property CarbonInterface|null $imported_at
 * @property CarbonInterface|null $viewed_at
 * @property CarbonInterface|null $reviewed_at
 * @property int|null $reviewed_by
 * @property int $parse_count
 * @property CarbonInterface|null $last_parsed_at
 * @property int|null $last_parsed_by
 * @property array<string, string|null>|null $field_sources
 * @property int|null $start_round_size
 * @property array<int, string>|null $restricted_countries
 * @property CarbonInterface|null $banner_image_cached_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property bool|null $isDetail Virtual property set in controllers
 * @property int|null $priority Virtual property for search sorting
 * @property int|null $year Virtual property for tournament year
 * @property-read User|null $reviewer
 * @property-read User|null $lastParsedBy
 * @property-read Collection<int, User> $staff
 * @property-read Collection<int, TournamentWatch> $watches
 * @property-read Collection<int, TournamentParseHistory> $parseHistories
 * @property-read Collection<int, TournamentCorrection> $corrections
 * @property-read Collection<int, array{mode: string, key_count: int|null}> $modes_with_details
 * @property-read Collection<int, string> $formatted_modes
 * @property-read Collection<int, string> $restricted_country_names
 * @property-read string $team_formation_style_label
 * @property-read Collection<int, string> $format_tag_labels
 * @property-read string|null $progression_summary
 * @property-read string|null $progression_chip
 * @property-read Collection<int, string> $progression_steps
 * @property-read Collection<int, string> $format_badges
 * @property-read string $cached_banner_url
 *
 * @method static Builder<static>|static query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static Builder<static>|static where($column, $operator = null, $value = null)
 * @method static Builder<static>|static whereIn(string $column, mixed $values)
 * @method static Builder<static>|static whereNotNull(string $column)
 * @method static Builder<static>|static whereYear(string $column, string $operator, string|int $value)
 * @method static static|null find($id)
 * @method static static findOrFail($id)
 * @method static static first()
 * @method static Builder<static> approved()
 * @method static Builder<static> pending()
 * @method static Builder<static> rejected()
 * @method static Builder<static> active()
 * @method static Builder<static> ended()
 * @method static Builder<static> mode(string $mode)
 * @method static Builder<static> badge()
 * @method static Builder<static> eligibleFor(User $user)
 * @method static Builder<static> search(?string $search)
 * @method static Builder<static> filterModes(?array<string> $modes)
 * @method static Builder<static> latestFirst()
 * @method static Builder<static> latestEditedFirst()
 * @method static Builder<static> unread()
 */
class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;

    use SoftDeletes;

    public const TEAM_FORMATION_STANDARD = 'standard';

    public const TEAM_FORMATION_DRAFT = 'draft';

    public const TEAM_FORMATION_AUCTION = 'auction';

    public const TEAM_FORMATION_WORLD_CUP = 'world_cup';

    public const TEAM_FORMATION_SUIJI = 'suiji';

    public const FORMAT_TAG_DRAFT = 'draft';

    public const FORMAT_TAG_AUCTION = 'auction';

    public const FORMAT_TAG_WORLD_CUP = 'world_cup';

    public const FORMAT_TAG_SUIJI = 'suiji';

    public const FORMAT_TAG_BATTLE_ROYALE = 'battle_royale';

    public const STAGE_QUALIFIER = 'qualifier';

    public const STAGE_GROUP = 'group_stage';

    public const STAGE_SWISS = 'swiss_round';

    public const STAGE_BRACKET = 'bracket';

    public const STAGE_BATTLE_ROYALE = 'battle_royale';

    public const BRACKET_ENTRY_WINNER_ONLY = 'winner_only';

    public const BRACKET_ENTRY_WINNER_LOSER_HYBRID = 'winner_loser_hybrid';

    public const BRACKET_ELIMINATION_SINGLE = 'single_elimination';

    public const BRACKET_ELIMINATION_DOUBLE = 'double_elimination';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function () {
            self::clearTournamentListCache();
        });

        static::updated(function () {
            self::clearTournamentListCache();
        });

        static::deleted(function () {
            self::clearTournamentListCache();
        });
    }

    /**
     * Clear tournament list cache
     *
     * Clears all tournament list cache keys when tournaments are modified.
     * Uses cache tags if configured, otherwise clears by pattern.
     */
    public static function clearTournamentListCache(): void
    {
        try {
            Cache::increment('tournaments.list.version');

            $cache = Cache::store();
            if ($cache->supportsTags()) {
                $cache->tags(['tournaments'])->flush();
            }
        } catch (\Exception $e) {
            // Skip logging known Laravel framework bug with Redis cache tags
            // Error: "Cannot use bool as array" in RedisTagSet.php:54
            // This is a framework issue, not a workflow problem - cache still works
            if ($e->getMessage() !== 'Cannot use bool as array') {
                Log::warning('Failed to clear tournament cache', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'forum_topic_id',
        'forum_post_sha256',
        'forum_post_url', // Can store wiki URLs for official tournaments
        'title',
        'description',
        'host_osu_id',
        'host_username',
        'status',
        'modes',
        'team_size_min',
        'team_size_max',
        'vs_size',
        'registration_start',
        'registration_end',
        'tournament_start',
        'tournament_end',
        'rank_range_min',
        'rank_range_max',
        'is_badge',
        'badge_status',
        'badge_urls',
        'is_bws',
        'bws_base_exponent',
        'bws_badge_power',
        'bws_divisor',
        'bws_badge_age_cutoff',
        'star_rating_min',
        'star_rating_max',
        'star_rating_first',
        'star_rating_last',
        'star_rating_qualifier',
        'format',
        'team_formation_style',
        'format_tags',
        'format_structure',
        'banner_url',
        'discord_url',
        'twitch_url',
        'spreadsheet_url',
        'bracket_url',
        'registration_url',
        'tcomm_url',
        'tcomm_id',
        'otr_id',
        'import_source',
        'rejection_reason',
        'parsed_at',
        'imported_at',
        'reviewed_at',
        'reviewed_by',
        'viewed_at',
        'parse_count',
        'last_parsed_at',
        'last_parsed_by',
        'field_sources',
        'start_round_size',
        'restricted_countries',
        'banner_image_cached_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'star_rating_display',
        'forum_post_url',
        'rank_range',
        'team_size_display',
        'modes_with_details',
        'formatted_modes',
        'team_formation_style_label',
        'format_tag_labels',
        'progression_summary',
        'progression_chip',
        'progression_steps',
        'format_badges',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'parse_count' => 0,
        'team_formation_style' => self::TEAM_FORMATION_STANDARD,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modes' => 'array',
            'field_sources' => 'array',
            'badge_urls' => 'array',
            'format_tags' => 'array',
            'format_structure' => 'array',
            'team_size_min' => 'integer',
            'team_size_max' => 'integer',
            'vs_size' => 'integer',
            'rank_range_min' => 'integer',
            'rank_range_max' => 'integer',
            'start_round_size' => 'integer',
            'restricted_countries' => 'array',
            'banner_image_cached_at' => 'datetime',
            'is_badge' => 'boolean',
            'is_bws' => 'boolean',
            'bws_base_exponent' => 'decimal:4',
            'bws_badge_power' => 'decimal:2',
            'bws_divisor' => 'decimal:4',
            'bws_badge_age_cutoff' => 'datetime',
            'star_rating_min' => 'decimal:2',
            'star_rating_max' => 'decimal:2',
            'star_rating_first' => 'decimal:2',
            'star_rating_last' => 'decimal:2',
            'star_rating_qualifier' => 'decimal:2',
            'registration_start' => 'datetime',
            'registration_end' => 'datetime',
            'tournament_start' => 'datetime',
            'tournament_end' => 'datetime',
            'parsed_at' => 'datetime',
            'imported_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'viewed_at' => 'datetime',
            'parse_count' => 'integer',
            'last_parsed_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * User who last parsed this tournament
     *
     * @return BelongsTo<User, $this>
     */
    public function lastParsedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_parsed_by');
    }

    /**
     * Tournament host user
     *
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_osu_id', 'osu_id');
    }

    /**
     * Parse history records
     *
     * @return HasMany<TournamentParseHistory, $this>
     */
    public function parseHistories(): HasMany
    {
        /** @var HasMany<TournamentParseHistory, $this> */
        return $this->hasMany(TournamentParseHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * @return HasMany<TournamentCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(TournamentCorrection::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_staff')
            ->withPivot('id', 'role', 'status', 'source') // PHASE 3: Include source in pivot
            ->withTimestamps();
    }

    /**
     * @return HasMany<TournamentWatch, $this>
     */
    public function watches(): HasMany
    {
        return $this->hasMany(TournamentWatch::class);
    }

    /**
     * @return HasMany<TournamentWinner, $this>
     */
    public function winners(): HasMany
    {
        return $this->hasMany(TournamentWinner::class)->orderBy('placement');
    }

    /**
     * Badge relationship
     *
     * @return HasMany<UserBadge, $this>
     */
    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /**
     * @return HasMany<TournamentParticipationRecord, $this>
     */
    public function participationRecords(): HasMany
    {
        return $this->hasMany(TournamentParticipationRecord::class);
    }

    /**
     * Query scopes for status filtering
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending_review');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('tournament_end')
                    ->orWhere('tournament_end', '>=', now());
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEnded(Builder $query): Builder
    {
        $query->where('status', 'approved')
            ->whereNotNull('tournament_end')
            ->where('tournament_end', '<', now());

        return $query;
    }

    /**
     * Scope for mode filtering
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMode(Builder $query, string $mode): Builder
    {
        $query->whereRaw("EXISTS (
            SELECT 1
            FROM jsonb_array_elements(modes::jsonb) AS mode_elem
            WHERE mode_elem->>'mode' = ?
        )", [$mode]);

        return $query;
    }

    /**
     * Scope for badge tournaments
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBadge(Builder $query): Builder
    {
        return $query->where('is_badge', true);
    }

    /**
     * Scope for unread tournaments (viewed_at is null)
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('viewed_at');
    }

    /**
     * Scope for latest parsed tournaments first
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('parsed_at', 'desc');
    }

    /**
     * Scope for most recently edited tournaments first.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestEditedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * Scope to eager load staff relationship sorted by role priority
     * Roles are sorted: organizer > mappooler > playtester > mapper > gfx > sheeter > referee > streamer > commentator > other
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithStaffSortedByRole(Builder $query): Builder
    {
        return $query->with(['staff' => function ($query) {
            $query->orderByRaw(StaffRoleHelper::roleOrderSql());
        }]);
    }

    /**
     * Scope for filtering tournaments eligible for a specific user
     * Applies rank range filtering with BWS calculation if applicable
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEligibleFor(Builder $query, User $user): Builder
    {
        // Get user's rank for their main mode
        $userRank = $user->rankHistory()->where('mode', $user->main_mode)->first();

        if (! $userRank) {
            // No rank data - user not eligible for any tournaments
            $query->whereRaw('1 = 0');

            return $query;
        }

        // Filter by mode - user can only join tournaments matching their main mode
        // Use raw JSON query to handle both legacy and enhanced mode formats
        $query->whereRaw("EXISTS (
            SELECT 1
            FROM jsonb_array_elements(modes::jsonb) AS mode_elem
            WHERE mode_elem->>'mode' = ?
        )", [$user->main_mode]);

        // Apply rank eligibility check
        $query->where(function ($rankQuery) use ($user, $userRank) {
            // Non-BWS tournaments: use raw rank
            $rankQuery->where(function ($nonBwsQuery) use ($userRank) {
                $nonBwsQuery->where('is_bws', false)
                    ->where(function ($openRankOrRanked) use ($userRank) {
                        // Open rank: both min and max are NULL (anyone can join)
                        $openRankOrRanked->where(function ($openRank) {
                            $openRank->whereNull('rank_range_min')
                                ->whereNull('rank_range_max');
                        })->orWhere(function ($ranked) use ($userRank) {
                            // Ranked: check min and max with NULL safety
                            $ranked->where(function ($minCheck) use ($userRank) {
                                $minCheck->whereNull('rank_range_min')
                                    ->orWhere('rank_range_min', '<=', $userRank->global_rank);
                            })
                                ->where(function ($maxCheck) use ($userRank) {
                                    $maxCheck->whereNull('rank_range_max')
                                        ->orWhere('rank_range_max', '>=', $userRank->global_rank);
                                });
                        });
                    });
            });

            // BWS tournaments: calculate BWS rank
            $rankQuery->orWhere(function ($bwsQuery) use ($user, $userRank) {
                $eligibleBadges = $user->badges()
                    ->where('is_bws_eligible', true)
                    ->count();

                // Use BwsCalculator service to calculate BWS rank
                $bwsCalculator = app(BwsCalculator::class);
                $bwsRank = $bwsCalculator->calculate(
                    $userRank->global_rank,
                    $eligibleBadges
                );

                $bwsQuery->where('is_bws', true)
                    ->where(function ($openRankOrRanked) use ($bwsRank) {
                        // Open rank: both min and max are NULL
                        $openRankOrRanked->where(function ($openRank) {
                            $openRank->whereNull('rank_range_min')
                                ->whereNull('rank_range_max');
                        })->orWhere(function ($ranked) use ($bwsRank) {
                            // Ranked: check BWS rank is within range
                            $ranked->where('rank_range_min', '<=', ceil($bwsRank))
                                ->where('rank_range_max', '>=', ceil($bwsRank));
                        });
                    });
            });
        });

        return $query;
    }

    /**
     * Search tournaments by title or host username
     *
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'ilike', "%{$search}%")
                ->orWhere('host_username', 'ilike', "%{$search}%");
        });
    }

    /**
     * Filter tournaments by game modes (OR logic - matches any mode)
     * Handles both legacy format ["osu"] and enhanced format [{"mode":"osu","key_count":null}]
     *
     * @param  Builder<Tournament>  $query
     * @param  array<string>  $modes
     * @return Builder<Tournament>
     */
    public function scopeFilterModes(Builder $query, ?array $modes): Builder
    {
        if (empty($modes)) {
            return $query;
        }

        return $query->where(function ($q) use ($modes) {
            foreach ($modes as $mode) {
                // Check for legacy format: ["osu", "taiko"]
                $q->orWhereJsonContains('modes', $mode);

                // Check for enhanced format: [{"mode":"osu","key_count":null}]
                // Use raw JSON query to check if any array element has mode = $mode
                $q->orWhereRaw('EXISTS (
                    SELECT 1
                    FROM jsonb_array_elements(modes::jsonb) AS elem
                    WHERE elem->>\'mode\' = ?
                )', [$mode]);
            }
        });
    }

    /**
     * Tournament status transitions
     */
    const STATUS_PENDING = 'pending_review';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    public function approve(User $admin): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \Exception('Only pending tournaments can be approved');
        }

        $this->status = self::STATUS_APPROVED;
        $this->reviewed_by = $admin->id;
        $this->reviewed_at = now();
        $this->save();

        // Clear tournament list cache
        try {
            Cache::tags(['tournaments'])->flush();
        } catch (\Exception $e) {
            // Skip logging known Laravel framework bug with Redis cache tags
            // Error: "Cannot use bool as array" in RedisTagSet.php:54
            if ($e->getMessage() !== 'Cannot use bool as array') {
                \Log::warning('Failed to clear tournament cache during approval', [
                    'tournament_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function reject(string $reason, User $admin): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \Exception('Only pending tournaments can be rejected');
        }

        $this->status = self::STATUS_REJECTED;
        $this->rejection_reason = $reason;
        $this->reviewed_by = $admin->id;
        $this->reviewed_at = now();
        $this->save();
    }

    public function restoreToPending(User $admin): void
    {
        if ($this->status !== self::STATUS_REJECTED && $this->status !== self::STATUS_APPROVED) {
            throw new \Exception('Only rejected or approved tournaments can be restored to pending');
        }

        $this->status = self::STATUS_PENDING;
        $this->rejection_reason = null; // Clear rejection reason if exists
        $this->reviewed_by = $admin->id;
        $this->reviewed_at = now();
        $this->save();

        // Clear tournament list cache
        try {
            Cache::tags(['tournaments'])->flush();
        } catch (\Exception $e) {
            // Skip logging known Laravel framework bug with Redis cache tags
            // Error: "Cannot use bool as array" in RedisTagSet.php:54
            if ($e->getMessage() !== 'Cannot use bool as array') {
                \Log::warning('Failed to clear tournament cache during restore', [
                    'tournament_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Mark tournament as viewed by admin
     */
    public function markAsViewed(): void
    {
        $viewedAt = now();

        DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update(['viewed_at' => $viewedAt]);

        $this->viewed_at = $viewedAt;
        $this->syncOriginalAttribute('viewed_at');
    }

    /**
     * Check if tournament is unread by admins
     */
    public function isUnread(): bool
    {
        return $this->viewed_at === null;
    }

    /**
     * Reset viewed_at to null (mark as unread)
     * Used when tournament is re-parsed with meaningful changes
     */
    public function markAsUnread(): void
    {
        DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update(['viewed_at' => null]);

        $this->viewed_at = null;
        $this->syncOriginalAttribute('viewed_at');
    }

    /**
     * Check if latest parse has meaningful changes since last view
     * Compares latest parse history changes with current data
     */
    public function hasMeaningfulChangesSinceLastView(): bool
    {
        // If never viewed, definitely has changes
        if ($this->isUnread()) {
            return true;
        }

        // Get latest parse history after viewed_at timestamp
        $latestHistory = $this->parseHistories()
            ->where('parsed_at', '>', $this->viewed_at)
            ->first();

        // No new parses since last view
        if (! $latestHistory) {
            return false;
        }

        // Check if parse has meaningful changes
        $changes = $latestHistory->getAttribute('changes');
        if (! is_array($changes)) {
            $changes = [];
        }

        // Define significant fields that admins care about
        $significantFields = [
            'title', 'description', 'host_username',
            'team_size_min', 'team_size_max', 'format',
            'registration_start', 'registration_end',
            'tournament_start', 'tournament_end',
            'rank_range_min', 'rank_range_max',
            'star_rating_min', 'star_rating_max',
            'banner_url', 'discord_url', 'spreadsheet_url', 'bracket_url',
        ];

        // Check if any significant field changed
        foreach ($significantFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fields that should never be overwritten during re-parse
     * These preserve admin decisions and workflow state
     *
     * @var array<int, string>
     */
    private static array $protectedFields = [
        'id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'created_at',
        'updated_at',
        'deleted_at',
        'parse_count',
        'last_parsed_at',
        'last_parsed_by',
        'import_source',
    ];

    /**
     * Fields that are always overwritten during re-parse
     * These contain parsed data from the forum post
     *
     * @var array<int, string>
     */
    private static array $parsedFields = [
        'forum_topic_id',
        'forum_post_sha256',
        // forum_post_url removed - computed via accessor from forum_topic_id
        'title',
        'description',
        'host_osu_id',
        'host_username',
        'modes',
        'team_size_min',
        'team_size_max',
        'vs_size',
        'registration_start',
        'registration_end',
        'tournament_start',
        'tournament_end',
        'rank_range_min',
        'rank_range_max',
        'is_badge',
        'is_bws',
        'banner_url',
        'discord_url',
        'twitch_url',
        'spreadsheet_url',
        'bracket_url',
        'registration_url',
        'tcomm_url',
        'star_rating_min',
        'star_rating_max',
        'star_rating_first',
        'star_rating_last',
        'star_rating_qualifier',
        'team_formation_style',
    ];

    /**
     * Update tournament from parsed data with smart field protection.
     *
     * BEHAVIOR:
     * - Tracks changes using character-level diff algorithm
     * - Creates parse history entry with before/after comparison
     * - Preserves manually edited fields (see field_sources below)
     * - Does NOT remove protected fields (status, reviewed_by, etc.)
     *
     * STATUS CHECK:
     * - Status filtering happens in ParseForumTopicJob BEFORE calling this method
     * - This method does NOT check tournament status
     * - Job only calls this for "pending" tournaments (skips approved/rejected)
     * - See: ParseForumTopicJob::handle() lines 123-132
     *
     * SMART FIELD PROTECTION (field_sources):
     * - Fields marked as 'manual' are NEVER overwritten by forum parser
     * - Fields marked as 'otr' are NEVER overwritten (protected OTR dump imports)
     * - Fields marked as 'parsed' can be updated from forum data
     * - When a field is updated, it's marked as 'parsed' in field_sources
     *
     * USE CASES:
     * 1. Initial parse: All fields marked 'parsed' (default)
     * 2. Admin edit: Edited fields marked 'manual' (protected from future updates)
     * 3. OTR dump import: Title marked 'otr' (protected from forum parser overwrites)
     * 4. Re-parse: Only 'parsed' fields updated, 'manual'/'otr' fields preserved
     * 5. Conflict resolution: Admin can manually mark fields as 'manual' to lock them
     *
     * FIELD SOURCES EXAMPLES:
     * ```php
     * // All fields can be updated (newly created tournament)
     * field_sources: ['title' => 'parsed', 'description' => 'parsed', ...]
     *
     * // Title is locked by admin, description can be updated
     * field_sources: ['title' => 'manual', 'description' => 'parsed', ...]
     *
     * // Title is locked by OTR import, description can be updated
     * field_sources: ['title' => 'otr', 'description' => 'parsed', ...]
     *
     * // Most fields locked, only modes/dates update
     * field_sources: ['title' => 'otr', 'description' => 'manual', 'modes' => 'parsed', ...]
     * ```
     *
     * CONFLICT DETECTION (PHASE 3):
     * - Detects when forum data differs from current manual edits
     * - Stores conflicts in parse history for admin review
     * - Resolution choices: keep_current, use_parsed, keep_manual
     *
     * @param  array<string, mixed>  $parsedData  Parsed tournament data from ForumParser
     * @param  int|null  $parsedByUserId  User ID who triggered parse (null = automated)
     * @param  array<string, mixed>  $resolution  Conflict resolution choices (key => 'keep_current'|'use_parsed'|'keep_manual')
     * @return TournamentParseHistory Created history entry with changes and diffs
     *
     * @see TournamentParseHistory
     * @see ForumParser::parseForumTopic()
     * @see ParseForumTopicJob::handle()
     */
    public function updateFromParsedData(array $parsedData, ?int $parsedByUserId = null, array $resolution = []): TournamentParseHistory
    {
        // Initialize field_sources as array if null
        $currentFieldSources = $this->field_sources ?? [];

        // Filter out null values to prevent overwriting existing data
        // When parser returns null for a field, preserve the existing database value
        // This must happen BEFORE trackChanges() to prevent false change records
        foreach (self::$parsedFields as $field) {
            if (array_key_exists($field, $parsedData) && $parsedData[$field] === null && ! $this->canApplyParsedNull($field, $parsedData)) {
                unset($parsedData[$field]);
            }
        }

        // PHASE 3: Detect conflicts before updating
        $conflicts = $this->detectReparseConflicts($parsedData);

        // Track changes before updating (only for non-manual/non-otr fields)
        $changes = $this->trackChanges($parsedData, $conflicts);

        // Update only parsed fields that are NOT marked as manual or otr
        foreach (self::$parsedFields as $field) {
            if (! array_key_exists($field, $parsedData)) {
                continue;
            }

            // Skip fields marked as manual (admin edited) or otr (imported from OTR dump)
            $fieldSource = $currentFieldSources[$field] ?? null;
            if ($fieldSource === 'otr' || ($fieldSource === 'manual' && ! $this->canFillBlankManualField($field, $parsedData[$field]))) {
                continue;
            }

            // Update field (null values already filtered out above)
            $this->$field = $parsedData[$field];

            // Mark field as parsed
            $currentFieldSources[$field] = 'parsed';
        }

        // Set field_sources after all modifications
        $this->setAttribute('field_sources', $currentFieldSources);

        // Update parsed_at timestamp
        $this->parsed_at = now();

        // Increment parse count and track who parsed
        $this->parse_count = ($this->parse_count ?? 0) + 1;
        $this->last_parsed_at = now();
        $this->last_parsed_by = $parsedByUserId;

        // When saving draft for approved tournaments, update reviewed_at
        if ($this->status === 'approved' && $this->reviewed_at === null) {
            $this->reviewed_at = now();
        } elseif ($this->status === 'approved' && $this->isDirty()) {
            // Tournament was already approved, just update the timestamp
            $this->reviewed_at = now();
        }

        $this->save();

        // Validate changes structure before creating history
        if (! isset($changes) || ! is_array($changes)) {
            $changes = [];
        }

        // Create parse history entry with conflict resolution details
        $history = TournamentParseHistory::create([
            'tournament_id' => $this->id,
            'parsed_by' => $parsedByUserId,
            'changes' => $changes,
            'conflicts' => $conflicts,
            'resolution' => $resolution, // PHASE 3: Track resolution
            'parsed_at' => now(),
            'parse_source' => $parsedData['import_source'] ?? 'forum',
            'parsed_data' => $parsedData,
        ]);

        // If meaningful changes detected and tournament was already viewed, mark as unread
        if (! empty($changes) && $this->viewed_at !== null) {
            $this->markAsUnread();
        }

        return $history;
    }

    private function canFillBlankManualField(string $field, mixed $parsedValue): bool
    {
        if (in_array($field, ['rank_range_min', 'rank_range_max'], true)) {
            return false;
        }

        if ($this->isBlankFieldValue($parsedValue, $field)) {
            return false;
        }

        return $this->isBlankFieldValue($this->$field, $field);
    }

    /**
     * Parser nulls normally mean "not found"; explicit Open Rank is the only
     * rank parse that is allowed to clear previously parsed rank bounds.
     *
     * @param  array<string, mixed>  $parsedData
     */
    private function canApplyParsedNull(string $field, array $parsedData): bool
    {
        return in_array($field, ['rank_range_min', 'rank_range_max'], true)
            && ($parsedData['rank_range_is_open'] ?? false) === true;
    }

    private function isBlankFieldValue(mixed $value, string $field): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        if (in_array($field, ['is_badge', 'is_bws'], true)) {
            return $value === false;
        }

        return false;
    }

    /**
     * Track changes between current data and parsed data using improved diff algorithm
     * Uses character-level diff for accurate change detection
     * PHASE 3: Optionally filter out conflicts to only track non-conflicting changes
     *
     * @param  array<string, mixed>  $parsedData
     * @param  array<string, mixed>  $conflicts  Conflicts to exclude from changes tracking
     * @return array<string, mixed> Array of changes with 'old', 'new', and optionally 'diff_html' keys
     */
    private function trackChanges(array $parsedData, array $conflicts = []): array
    {
        $changes = [];

        foreach (self::$parsedFields as $field) {
            if (! array_key_exists($field, $parsedData)) {
                continue;
            }

            // PHASE 3: Skip fields with conflicts (handled separately via resolution)
            if (array_key_exists($field, $conflicts)) {
                continue;
            }

            $oldValue = $this->$field;
            $newValue = $parsedData[$field];

            // Format values for diff comparison
            $oldFormatted = $this->formatForDiff($oldValue);
            $newFormatted = $this->formatForDiff($newValue);

            // Check if values actually changed
            if ($oldFormatted === $newFormatted) {
                continue;
            }

            // Handle array comparisons (modes, etc)
            if (is_array($oldValue) || is_array($newValue)) {
                $oldCopy = is_array($oldValue) ? $oldValue : [];
                $newCopy = is_array($newValue) ? $newValue : [];

                sort($oldCopy);
                sort($newCopy);

                if ($oldCopy !== $newCopy) {
                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            } else {
                // Generate character-level diff for scalar values
                try {
                    $diffHtml = DiffHelper::calculate(
                        $oldFormatted,
                        $newFormatted,
                        'JsonHtml',
                        [
                            'context' => 3,
                            'ignoreCase' => false,
                            'ignoreWhitespace' => false,
                        ],
                        [
                            'detailLevel' => 'char',
                            'language' => 'eng',
                        ]
                    );

                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                        'diff_html' => $diffHtml,
                    ];
                } catch (\Exception $e) {
                    // Fallback to simple comparison if diff generation fails
                    Log::warning('Failed to generate diff for field', [
                        'field' => $field,
                        'error' => $e->getMessage(),
                    ]);

                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Format value for diff comparison
     *
     * @param  mixed  $value
     */
    private function formatForDiff($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Detect re-parse conflicts between current and parsed data
     * PHASE 3: Identify fields that would overwrite manual edits
     * NOTE: Only checks 'manual' fields. 'otr' fields are protected at update level and don't generate conflicts.
     *
     * @param  array<string, mixed>  $parsedData
     * @return array<string, array{current: mixed, parsed: mixed, source: string}>
     */
    public function detectReparseConflicts(array $parsedData): array
    {
        $conflicts = [];

        // Get current field sources
        $currentFieldSources = $this->field_sources ?? [];

        // Check each parsed field for conflicts
        foreach (self::$parsedFields as $field) {
            if (! array_key_exists($field, $parsedData)) {
                continue;
            }

            // Only check fields marked as manual
            if (($currentFieldSources[$field] ?? null) !== 'manual') {
                continue;
            }

            $currentValue = $this->$field;
            $parsedValue = $parsedData[$field];

            // Handle array comparisons
            if (is_array($currentValue) || is_array($parsedValue)) {
                $currentCopy = is_array($currentValue) ? $currentValue : [];
                $parsedCopy = is_array($parsedValue) ? $parsedValue : [];

                sort($currentCopy);
                sort($parsedCopy);

                if ($currentCopy !== $parsedCopy) {
                    $conflicts[$field] = [
                        'current' => $currentValue,
                        'parsed' => $parsedValue,
                        'source' => 'manual',
                    ];
                }
            } else {
                // Simple comparison for scalar values
                if ($currentValue != $parsedValue) {
                    $conflicts[$field] = [
                        'current' => $currentValue,
                        'parsed' => $parsedValue,
                        'source' => 'manual',
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Merge staff from parsed data with existing staff
     * - Adds new staff members with their roles
     * - Adds additional roles to existing staff members
     * - Removes roles not present in parsed data
     * - Supports multiple roles per user
     * PHASE 3: Preserves staff where source='manual'
     *
     * @param  array<int, array{osu_id: int, username: string, role: string}>  $parsedStaff
     * @return array{added: int, updated: int, removed: int}
     */
    public function mergeStaff(array $parsedStaff): array
    {
        $addedCount = 0;
        $updatedCount = 0;
        $removedCount = 0;

        // Get current staff (user_id, role) combinations with source data
        $currentStaff = [];
        foreach ($this->staff()->withPivot('source')->get() as $staff) {
            $key = $staff->pivot->user_id.':'.$staff->pivot->role;
            $currentStaff[$key] = [
                'source' => $staff->pivot->source,
                'exists' => true,
            ];
        }

        // Build parsed staff lookup
        $parsedStaffLookup = [];
        foreach ($parsedStaff as $staffMember) {
            $osuId = $staffMember['osu_id'];
            $newRole = $staffMember['role'];

            $user = User::where('osu_id', $osuId)->first();
            if (! $user) {
                continue;
            }

            $key = $user->id.':'.$newRole;
            $parsedStaffLookup[$key] = [
                'user_id' => $user->id,
                'role' => $newRole,
            ];
        }

        // Add new (user_id, role) combinations
        foreach ($parsedStaffLookup as $key => $parsed) {
            if (! isset($currentStaff[$key])) {
                $this->staff()->attach($parsed['user_id'], [
                    'role' => $parsed['role'],
                    'status' => 'approved',
                    'submitted_at' => now(),
                    'reviewed_at' => now(),
                    'source' => 'parsed', // Mark as parsed source
                ]);
                $addedCount++;
            }
        }

        // Remove (user_id, role) combinations not in parsed data
        // PHASE 3: Skip removal if source='manual'
        foreach ($currentStaff as $key => $data) {
            if (! isset($parsedStaffLookup[$key])) {
                // Skip removal if source='manual'
                if (($data['source'] ?? null) === 'manual') {
                    continue;
                }

                [$userId, $role] = explode(':', $key);
                $this->staff()
                    ->where('user_id', $userId)
                    ->wherePivot('role', $role)
                    ->detach();
                $removedCount++;
            }
        }

        return [
            'added' => $addedCount,
            'updated' => $updatedCount,
            'removed' => $removedCount,
        ];
    }

    /**
     * Calculate staff changes between current state and parsed data
     * Tracks individual (user_id, role) combinations to support multi-role users
     *
     * @param  array<int, array{osu_id: int, username: string, role: string}>  $parsedStaff
     * @return array{added: array, removed: array, role_changed: array, stats: array}
     */
    public function calculateStaffDiffs(array $parsedStaff): array
    {
        // Get current staff with pivot data, keyed by (user_id, role)
        $currentStaff = [];
        foreach ($this->staff()->withPivot('role')->get() as $user) {
            $key = $user->pivot->user_id.':'.$user->pivot->role;
            $currentStaff[$key] = [
                'user_id' => $user->pivot->user_id,
                'osu_id' => $user->osu_id,
                'username' => $user->username,
                'role' => $user->pivot->role,
            ];
        }

        // Build parsed staff lookup, keyed by (user_id, role)
        $parsedStaffLookup = [];
        foreach ($parsedStaff as $staffMember) {
            $user = User::where('osu_id', $staffMember['osu_id'])->first();
            if ($user) {
                $key = $user->id.':'.$staffMember['role'];
                $parsedStaffLookup[$key] = [
                    'osu_id' => $staffMember['osu_id'],
                    'username' => $user->username,
                    'role' => $staffMember['role'],
                    'user_id' => $user->id,
                ];
            }
        }

        $added = [];
        $removed = [];

        // Find added (user_id, role) combinations
        foreach ($parsedStaffLookup as $key => $parsed) {
            if (! isset($currentStaff[$key])) {
                $added[] = $parsed;
            }
        }

        // Find removed (user_id, role) combinations
        foreach ($currentStaff as $key => $current) {
            if (! isset($parsedStaffLookup[$key])) {
                $removed[] = [
                    'user_id' => $current['user_id'],
                    'osu_id' => $current['osu_id'],
                    'username' => $current['username'],
                    'role' => $current['role'],
                    'reason' => 'not_in_parsed_data',
                ];
            }
        }

        // Calculate stats
        $stats = [
            'before' => $this->calculateRoleStatsFromDiffs($currentStaff),
            'after' => $this->calculateRoleStatsFromDiffs($parsedStaffLookup),
        ];

        return [
            'added' => $added,
            'removed' => $removed,
            'role_changed' => [], // Not tracked in multi-role model
            'stats' => $stats,
        ];
    }

    /**
     * Merge staff and create parse history with changes
     *
     * @param  array<int, array{osu_id: int, username: string, role: string}>  $parsedStaff
     * @param  array<string, mixed>  $additionalData
     * @return array{added: int, updated: int, removed: int}
     */
    public function mergeStaffWithHistory(array $parsedStaff, string $parseSource = 'unknown', array $additionalData = [], ?int $parsedBy = null): array
    {
        // SAFETY CHECK: Prevent data loss when parser returns empty results
        $currentStaffCount = $this->staff()->count();
        if (empty($parsedStaff) && $currentStaffCount > 0) {
            Log::warning('Tournament::mergeStaffWithHistory() - Parser returned empty staff, skipping merge to prevent data loss', [
                'tournament_id' => $this->id,
                'tournament_title' => $this->title,
                'current_staff_count' => $currentStaffCount,
                'parse_source' => $parseSource,
                'parsed_by' => $parsedBy,
            ]);

            // Prepare changes array with validation
            $skipChanges = [
                'staff' => [
                    'added' => [],
                    'removed' => [],
                    'role_changed' => [],
                    'stats' => [
                        'before' => $this->calculateRoleStats($this->staff()->withPivot('role')->get()),
                        'after' => ['total' => $currentStaffCount],
                    ],
                    'warning' => 'Parser returned empty results - merge skipped to prevent data loss',
                ],
            ];

            // Ensure changes is always an array
            if (! is_array($skipChanges)) {
                $skipChanges = [];
            }

            // Create parse history showing the skipped operation
            TournamentParseHistory::create([
                'tournament_id' => $this->id,
                'parsed_by' => $parsedBy,
                'changes' => $skipChanges,
                'parsed_data' => array_merge([
                    'staff' => [],
                    'staff_count' => 0,
                ], $additionalData),
                'parsed_at' => now(),
                'parse_source' => $parseSource,
                'parse_notes' => 'SAFETY: Skipped merge to prevent deleting all '.$currentStaffCount.' existing staff members',
            ]);

            // Return empty result (no changes made)
            return [
                'added' => 0,
                'updated' => 0,
                'removed' => 0,
            ];
        }

        // Calculate diffs BEFORE merging
        $changes = $this->calculateStaffDiffs($parsedStaff);

        if (
            empty($changes['added'])
            && empty($changes['removed'])
            && empty($changes['role_changed'])
        ) {
            Log::info('Tournament::mergeStaffWithHistory() - Staff unchanged, skipping no-op merge history', [
                'tournament_id' => $this->id,
                'tournament_title' => $this->title,
                'parse_source' => $parseSource,
                'parsed_by' => $parsedBy,
            ]);

            return [
                'added' => 0,
                'updated' => 0,
                'removed' => 0,
            ];
        }

        // Merge staff using proven method
        $result = $this->mergeStaff($parsedStaff);

        // Increment parse count
        $this->parse_count = ($this->parse_count ?? 0) + 1;
        $this->last_parsed_at = now();
        $this->last_parsed_by = $parsedBy;
        $this->save();

        // Prepare and validate changes structure
        $staffChanges = [
            'staff' => [
                'added' => $changes['added'],
                'removed' => $changes['removed'],
                'role_changed' => $changes['role_changed'],
                'stats' => $changes['stats'],
            ],
        ];

        // Ensure changes is always an array
        if (! is_array($staffChanges)) {
            $staffChanges = [];
        }

        // Create parse history with staff changes
        TournamentParseHistory::create([
            'tournament_id' => $this->id,
            'parsed_by' => $parsedBy,
            'changes' => $staffChanges,
            'parsed_data' => array_merge([
                'staff' => $parsedStaff,
                'staff_count' => count($parsedStaff),
            ], $additionalData),
            'parsed_at' => now(),
            'parse_source' => $parseSource,
        ]);

        return [
            'added' => $result['added'],
            'updated' => $result['updated'],
            'removed' => count($changes['removed']),
        ];
    }

    /**
     * Calculate role statistics from staff collection
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $staff
     * @return array{total: int}
     */
    private function calculateRoleStats($staff): array
    {
        $stats = ['total' => $staff->count()];

        // Add individual role counts to stats array
        foreach ($staff as $staffMember) {
            $role = $staffMember->pivot->role;
            $stats[$role] = ($stats[$role] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Calculate role statistics from array
     *
     * @param  array<string, array{role: string}>  $parsedStaffLookup
     * @return array{total: int}
     */
    private function calculateRoleStatsFromArray(array $parsedStaffLookup): array
    {
        $stats = ['total' => count($parsedStaffLookup)];

        // Add individual role counts to stats array
        foreach ($parsedStaffLookup as $staff) {
            $role = $staff['role'];
            $stats[$role] = ($stats[$role] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Calculate role statistics from diffs array
     * Used by calculateStaffDiffs for multi-role support
     *
     * @param  array<string, array{role: string}>  $staffLookup
     * @return array{total: int}
     */
    private function calculateRoleStatsFromDiffs(array $staffLookup): array
    {
        $stats = ['total' => count($staffLookup)];

        // Add individual role counts to stats array
        foreach ($staffLookup as $staff) {
            $role = $staff['role'];
            $stats[$role] = ($stats[$role] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Get formatted rank range for display
     * Returns human-readable rank range based on min/max values
     */
    public function getRankRangeAttribute(): string
    {
        // Both null = Open Rank
        if (is_null($this->rank_range_min) && is_null($this->rank_range_max)) {
            return 'Open Rank';
        }

        // Min only = #min+
        if (is_null($this->rank_range_max)) {
            return '#'.number_format($this->rank_range_min).'+';
        }

        // Max only = #1-#max
        if (is_null($this->rank_range_min)) {
            return '#1-#'.number_format($this->rank_range_max);
        }

        // Both values = #min-#max
        return '#'.number_format($this->rank_range_min).'-#'.number_format($this->rank_range_max);
    }

    /**
     * Get team size display string (e.g., "2v2", "TS3-6", "2v2 / TS3-6")
     */
    public function getTeamSizeDisplayAttribute(): string
    {
        $parts = [];

        if ($this->vs_size) {
            $parts[] = "{$this->vs_size}v{$this->vs_size}";
        }

        if ($this->team_size_min && $this->team_size_max) {
            if ($this->team_size_min === $this->team_size_max) {
                $parts[] = "TS{$this->team_size_min}";
            } else {
                $parts[] = "TS{$this->team_size_min}-{$this->team_size_max}";
            }
        }

        return $parts ? implode(' / ', $parts) : '';
    }

    /**
     * Get star rating display string (e.g., "*4.0~*5.0" or "*4.0~*5.0 (QL *3.0)")
     */
    public function getStarRatingDisplayAttribute(): ?string
    {
        $firstValue = $this->star_rating_first ?? $this->star_rating_min;
        $lastValue = $this->star_rating_last ?? $this->star_rating_max;

        if (! $firstValue || ! $lastValue) {
            return null;
        }

        // Format numbers to remove trailing zeros
        $first = (float) $firstValue;
        $last = (float) $lastValue;

        $display = "*{$first}~*{$last}";

        if ($this->star_rating_qualifier) {
            $display .= ' (QL *'.(float) $this->star_rating_qualifier.')';
        }

        return $display;
    }

    /**
     * @return array<string, string>
     */
    public static function teamFormationStyleLabels(): array
    {
        return [
            self::TEAM_FORMATION_STANDARD => __('tournaments.format.team_formation.standard'),
            self::TEAM_FORMATION_DRAFT => __('tournaments.format.team_formation.draft'),
            self::TEAM_FORMATION_AUCTION => __('tournaments.format.team_formation.auction'),
            self::TEAM_FORMATION_WORLD_CUP => __('tournaments.format.team_formation.world_cup'),
            self::TEAM_FORMATION_SUIJI => __('tournaments.format.team_formation.suiji'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function searchableFormatTagLabels(): array
    {
        return array_intersect_key(self::teamFormationStyleLabels(), array_flip([
            self::FORMAT_TAG_DRAFT,
            self::FORMAT_TAG_AUCTION,
            self::FORMAT_TAG_WORLD_CUP,
            self::FORMAT_TAG_SUIJI,
        ])) + [self::FORMAT_TAG_BATTLE_ROYALE => __('tournaments.format.stage.battle_royale')];
    }

    /**
     * @return array<string, string>
     */
    public static function bracketEliminationLabels(): array
    {
        return [
            self::BRACKET_ELIMINATION_SINGLE => __('tournaments.format.bracket_elimination.single_elimination'),
            self::BRACKET_ELIMINATION_DOUBLE => __('tournaments.format.bracket_elimination.double_elimination'),
        ];
    }

    public function getTeamFormationStyleLabelAttribute(): string
    {
        return self::teamFormationStyleLabels()[$this->team_formation_style] ?? 'Standard';
    }

    /**
     * @return Collection<int, string>
     */
    public function getFormatTagLabelsAttribute(): Collection
    {
        $labels = self::searchableFormatTagLabels();

        return collect($this->format_tags ?? [])
            ->map(fn (string $tag): ?string => $labels[$tag] ?? null)
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function getFormatBadgesAttribute(): Collection
    {
        $badges = collect();

        if ($this->team_formation_style !== self::TEAM_FORMATION_STANDARD) {
            $badges->push($this->team_formation_style_label);
        }

        if (in_array(self::FORMAT_TAG_BATTLE_ROYALE, $this->format_tags ?? [], true)) {
            $badges->push(self::searchableFormatTagLabels()[self::FORMAT_TAG_BATTLE_ROYALE]);
        }

        return $badges->unique()->values();
    }

    public function getProgressionSummaryAttribute(): ?string
    {
        $stages = $this->progression_steps;

        if ($stages->isEmpty()) {
            return null;
        }

        return $stages->implode(' -> ');
    }

    public function getProgressionChipAttribute(): ?string
    {
        $stages = $this->formatStages();

        if ($stages->isEmpty()) {
            return null;
        }

        $lastStage = $stages->last();

        return is_array($lastStage) ? $this->formatStageSummary($lastStage, true) : null;
    }

    /**
     * @return Collection<int, string>
     */
    public function getProgressionStepsAttribute(): Collection
    {
        return $this->formatStages()
            ->map(fn (array $stage): ?string => $this->formatStageSummary($stage, false))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function formatStages(): Collection
    {
        $stages = data_get($this->format_structure, 'stages', []);

        if (is_array($this->format_structure) && array_key_exists('stages', $this->format_structure)) {
            return collect($stages)
                ->filter(fn ($stage): bool => is_array($stage) && filled($stage['type'] ?? null))
                ->values();
        }

        if ($this->start_round_size) {
            return collect([[
                'type' => self::STAGE_BRACKET,
                'start_round_size' => $this->start_round_size,
                'entry_type' => self::BRACKET_ENTRY_WINNER_ONLY,
                'elimination_type' => self::BRACKET_ELIMINATION_DOUBLE,
            ]]);
        }

        if ($this->format && str_contains(strtolower($this->format), 'battle royale')) {
            return collect([[
                'type' => self::STAGE_BATTLE_ROYALE,
                'name' => 'Battle Royale',
            ]]);
        }

        if ($this->format && str_contains(strtolower($this->format), 'swiss')) {
            return collect([[
                'type' => self::STAGE_SWISS,
            ]]);
        }

        return collect();
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function formatStageSummary(array $stage, bool $compact): ?string
    {
        $type = $stage['type'] ?? null;

        return match ($type) {
            self::STAGE_QUALIFIER => $this->formatQualifierStage($stage, $compact),
            self::STAGE_GROUP => $this->formatGroupStage($stage, $compact),
            self::STAGE_SWISS => $this->formatSwissStage($stage, $compact),
            self::STAGE_BRACKET => $this->formatBracketStage($stage, $compact),
            self::STAGE_BATTLE_ROYALE => $this->formatBattleRoyaleStage($stage, $compact),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function formatQualifierStage(array $stage, bool $compact): string
    {
        $advanceCount = $this->positiveInt($stage['advance_count'] ?? null);
        $stageLabel = $compact
            ? __('tournaments.format.stage_short.qualifier')
            : __('tournaments.format.stage.qualifier');

        return $advanceCount
            ? $this->formatStageWithTop($stageLabel, $advanceCount, $compact)
            : $stageLabel;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function formatGroupStage(array $stage, bool $compact): string
    {
        $advanceCount = $this->positiveInt($stage['advance_count'] ?? null);
        $stageLabel = $compact
            ? __('tournaments.format.stage_short.group_stage')
            : __('tournaments.format.stage.group_stage');

        return $advanceCount
            ? $this->formatStageWithTop($stageLabel, $advanceCount, $compact)
            : $stageLabel;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function formatSwissStage(array $stage, bool $compact): string
    {
        $roundCount = $this->positiveInt($stage['round_count'] ?? null);
        $advanceCount = $this->positiveInt($stage['advance_count'] ?? null);
        $stageLabel = $compact
            ? __('tournaments.format.stage_short.swiss_round')
            : __('tournaments.format.stage.swiss_round');

        if ($compact) {
            if ($roundCount && $advanceCount) {
                return __('tournaments.format.stage_short.rounds_top', [
                    'stage' => $stageLabel,
                    'rounds' => $roundCount,
                    'count' => $advanceCount,
                ]);
            }

            if ($roundCount) {
                return __('tournaments.format.stage_short.rounds', [
                    'stage' => $stageLabel,
                    'rounds' => $roundCount,
                ]);
            }

            return $advanceCount
                ? $this->formatStageWithTop($stageLabel, $advanceCount, true)
                : $stageLabel;
        }

        if ($roundCount && $advanceCount) {
            return trans_choice('tournaments.format.stage.rounds_top', $roundCount, [
                'stage' => $stageLabel,
                'rounds' => $roundCount,
                'count' => $advanceCount,
            ]);
        }

        if ($roundCount) {
            return trans_choice('tournaments.format.stage.rounds', $roundCount, [
                'stage' => $stageLabel,
                'rounds' => $roundCount,
            ]);
        }

        return $advanceCount
            ? $this->formatStageWithTop($stageLabel, $advanceCount, false)
            : $stageLabel;
    }

    private function formatStageWithTop(string $stageLabel, int $advanceCount, bool $compact): string
    {
        return __($compact ? 'tournaments.format.stage_short.top' : 'tournaments.format.stage.top', [
            'stage' => $stageLabel,
            'count' => $advanceCount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function formatBracketStage(array $stage, bool $compact): string
    {
        $roundSize = $this->positiveInt($stage['start_round_size'] ?? null);
        $entryType = $stage['entry_type'] ?? self::BRACKET_ENTRY_WINNER_ONLY;
        $roundText = $roundSize
            ? __('tournaments.format.stage.round_of', ['size' => $roundSize])
            : __('tournaments.format.stage.bracket');
        $eliminationText = $this->bracketEliminationText($stage);

        if ($compact) {
            return $this->joinSummaryParts([$roundText, $eliminationText]);
        }

        $label = $this->joinSummaryParts([$roundText, $eliminationText]);

        if ($entryType === self::BRACKET_ENTRY_WINNER_LOSER_HYBRID) {
            return __('tournaments.format.stage.hybrid_bracket', ['label' => $label]);
        }

        return $label;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function bracketEliminationText(array $stage): string
    {
        $eliminationType = (string) ($stage['elimination_type'] ?? '');

        return self::bracketEliminationLabels()[$eliminationType] ?? '';
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function joinSummaryParts(array $parts): string
    {
        return collect($parts)
            ->filter(fn (string $part): bool => $part !== '')
            ->implode(' ');
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function formatBattleRoyaleStage(array $stage, bool $compact): string
    {
        $roundCount = $this->battleRoyaleRoundCount($stage);

        if ($roundCount) {
            return $compact
                ? trans_choice('tournaments.format.stage_short.battle_royale_rounds', $roundCount, ['count' => $roundCount])
                : trans_choice('tournaments.format.stage.rounds', $roundCount, [
                    'stage' => __('tournaments.format.stage.battle_royale'),
                    'rounds' => $roundCount,
                ]);
        }

        return __('tournaments.format.stage.battle_royale');
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function battleRoyaleRoundCount(array $stage): ?int
    {
        $lobbyCount = $this->positiveInt($stage['lobby_count'] ?? null);
        $playersPerLobby = $this->positiveInt($stage['players_per_lobby'] ?? null);
        $advancePerLobby = $this->positiveInt($stage['advance_per_lobby'] ?? null);

        if (! $lobbyCount || ! $playersPerLobby || ! $advancePerLobby || $advancePerLobby >= $playersPerLobby) {
            return null;
        }

        $currentPlayers = $lobbyCount * $playersPerLobby;
        $rounds = 1;

        while ($currentPlayers > $playersPerLobby && $rounds < 100) {
            $currentLobbyCount = (int) ceil($currentPlayers / $playersPerLobby);
            $currentPlayers = $currentLobbyCount * $advancePerLobby;
            $rounds++;
        }

        return $rounds;
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    /**
     * Check if registration is closed but tournament hasn't started yet
     */
    public function getRegistrationClosedButNotStartedAttribute(): bool
    {
        if (! $this->registration_end || ! $this->tournament_start) {
            return false;
        }

        return now()->gte($this->registration_end) && now()->lt($this->tournament_start);
    }

    /**
     * Check if tournament is ongoing (registration closed but event is active)
     */
    public function getIsOngoingAttribute(): bool
    {
        // Tournament must be approved
        if ($this->status !== 'approved') {
            return false;
        }

        // Registration must be closed
        if (! $this->registration_end || now() <= $this->registration_end) {
            return false;
        }

        // Tournament must be within date range (started but not ended)
        if (! $this->tournament_start || now() < $this->tournament_start) {
            return false;
        }

        if ($this->tournament_end && now() >= $this->tournament_end) {
            return false;
        }

        return true;
    }

    /**
     * Get ongoing status based on registration and tournament dates
     */
    public function getOngoingStatusAttribute(): string
    {
        // Tournament must be approved
        if ($this->status !== 'approved') {
            return 'not_approved';
        }

        // Check if tournament has ended
        if ($this->tournament_end && $this->tournament_end < now()) {
            return 'ended';
        }

        // Check if tournament is upcoming (registration open or not started yet)
        if (($this->registration_start && now() < $this->registration_start) ||
            ($this->tournament_start && now() < $this->tournament_start)) {
            return 'upcoming';
        }

        // Otherwise tournament is ongoing
        return 'ongoing';
    }

    /**
     * Get tournament status based on dates
     */
    public function getTournamentStatusAttribute(): string
    {
        if ($this->tournament_end && $this->tournament_end < now()) {
            return 'ended';
        }

        if ($this->tournament_start && $this->tournament_start > now()) {
            return 'upcoming';
        }

        return 'currently_running';
    }

    /**
     * Get registration status based on dates
     */
    public function getRegistrationStatusAttribute(): string
    {
        if (! $this->registration_start || ! $this->registration_end) {
            return 'not_available';
        }

        if (now() < $this->registration_start) {
            return 'upcoming';
        }

        if (now() > $this->registration_end) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * Get time until registration closes
     */
    public function getRegistrationClosesInAttribute(): ?string
    {
        if (! $this->registration_end || $this->registration_end < now()) {
            return null;
        }

        return $this->registration_end->diffForHumans();
    }

    /**
     * Accessor: Get modes with parsed details
     *
     * @return Collection<int, array{mode: string, key_count: int|null}>
     */
    public function getModesWithDetailsAttribute(): Collection
    {
        return collect($this->modes)->map(function ($mode) {
            if (is_string($mode)) {
                return ['mode' => $mode, 'key_count' => null];
            }

            return $mode;
        });
    }

    /**
     * Accessor: Get formatted mode names for display
     * osu -> osu!
     * taiko -> osu!taiko
     * catch -> osu!catch
     * mania -> osu!mania (with key setting if present)
     *
     * @return Collection<int, string>
     */
    public function getFormattedModesAttribute(): Collection
    {
        return $this->modes_with_details->map(function ($detail) {
            $mode = $detail['mode'];
            $keyCount = $detail['key_count'];

            $text = match ($mode) {
                'osu' => 'osu!',
                'taiko' => 'osu!taiko',
                'catch', 'fruits' => 'osu!catch',
                'mania' => 'osu!mania'.($keyCount ? " {$keyCount}K" : ''),
                default => $mode,
            };

            return $text;
        });
    }

    /**
     * Mutator: Store modes in enhanced format
     */
    public function setModesAttribute($value): void
    {
        // Normalize modes to enhanced format
        $this->attributes['modes'] = json_encode(
            collect($value)->map(function ($mode) {
                if (is_string($mode)) {
                    return ['mode' => $mode, 'key_count' => null];
                }

                return $mode;
            })->toArray()
        );
    }

    /**
     * Mutator: Store searchable format tags in a normalized form.
     */
    public function setFormatTagsAttribute(mixed $value): void
    {
        $allowed = array_keys(self::searchableFormatTagLabels());

        $this->attributes['format_tags'] = json_encode(
            collect($value ?? [])
                ->map(fn ($tag) => strtolower(str_replace('-', '_', (string) $tag)))
                ->filter(fn (string $tag): bool => in_array($tag, $allowed, true))
                ->unique()
                ->values()
                ->toArray()
        );
    }

    /**
     * Helper: Check if tournament has specific mania variant
     */
    public function hasManiaVariant(?int $keyCount = null): bool
    {
        return collect($this->modes_with_details)->contains(function ($mode) use ($keyCount) {
            if ($mode['mode'] !== 'mania') {
                return false;
            }

            return $keyCount === null || $mode['key_count'] === $keyCount;
        });
    }

    /**
     * Helper: Check if tournament is restricted to specific countries
     */
    public function isRegionRestricted(): bool
    {
        return ! empty($this->restricted_countries);
    }

    /**
     * Helper: Check if user's country is eligible
     */
    public function isUserEligibleByCountry(?string $userCountryCode): bool
    {
        if (! $this->isRegionRestricted()) {
            return true; // No restrictions
        }

        if (! $userCountryCode) {
            return false; // User has no country but tournament requires one
        }

        return in_array(strtoupper($userCountryCode), $this->restricted_countries ?? []);
    }

    /**
     * Helper: Check if tournament has podium (winners)
     */
    public function hasPodium(): bool
    {
        return $this->winners()->where('placement', '<=', 3)->exists();
    }

    /**
     * Helper: Check if tournament has ended
     */
    public function isEnded(): bool
    {
        return $this->tournament_end && $this->tournament_end->isPast();
    }

    /**
     * Helper: Check if registration period has ended
     */
    public function isRegistrationEnded(): bool
    {
        return in_array($this->registration_status, ['closed', 'not_available']);
    }

    /**
     * Helper: Check if tournament is Open Rank (no rank restrictions)
     */
    public function isOpenRank(): bool
    {
        return is_null($this->rank_range_min) && is_null($this->rank_range_max);
    }

    /**
     * Helper: Check if user's main mode matches tournament modes
     */
    public function matchesUserMode(User $user): bool
    {
        if (! $user->main_mode) {
            return false;
        }

        // Handle both array (from cast) and string (raw JSON)
        $modes = is_array($this->modes)
            ? $this->modes
            : json_decode($this->modes ?? '[]', true);

        if (! is_array($modes)) {
            return false;
        }

        foreach ($modes as $mode) {
            $modeValue = is_array($mode) ? ($mode['mode'] ?? null) : $mode;

            if (! $modeValue) {
                continue;
            }

            // Direct match
            if ($modeValue === $user->main_mode) {
                return true;
            }

            // Special case: "fruits" is alias for "catch"
            if ($modeValue === 'fruits' && $user->main_mode === 'catch') {
                return true;
            }

            if ($modeValue === 'catch' && $user->main_mode === 'fruits') {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper: Check if a user is eligible for this tournament
     * Handles open rank, BWS, regional restrictions, and variant-specific mania ranks (4k/7k)
     */
    public function isEligibleForUser(User $user): bool
    {
        // Check mode matching (with variant-specific mania support)
        $eligibleMode = $user->main_mode;
        $variantRank = null;

        // SPECIAL CASE: Check for variant-specific mania tournaments (4k/7k)
        if ($this->hasManiaVariant()) {
            foreach ($this->modes_with_details as $modeDetail) {
                if ($modeDetail['mode'] === 'mania' && $modeDetail['key_count']) {
                    // Check if user has the corresponding variant rank
                    if ($modeDetail['key_count'] === 4 && $user->rank_mania_4k) {
                        $eligibleMode = 'mania';
                        $variantRank = $user->rank_mania_4k;
                        break;
                    }
                    if ($modeDetail['key_count'] === 7 && $user->rank_mania_7k) {
                        $eligibleMode = 'mania';
                        $variantRank = $user->rank_mania_7k;
                        break;
                    }
                }
            }
        }

        // Check if mode matches (either main_mode or variant-specific)
        if (! $this->matchesUserMode($user) && $eligibleMode === $user->main_mode) {
            return false;
        }

        // For variant-specific mania, verify the mode actually matches
        if ($eligibleMode === 'mania' && ! $this->matchesMode('mania')) {
            return false;
        }

        // Open rank tournaments (both min and max are null)
        if ($this->rank_range_min === null && $this->rank_range_max === null) {
            // Check regional restrictions
            return $this->isUserEligibleByCountry($user->country_code);
        }

        // Get user's rank (use variant rank if available, otherwise get from rank history)
        if ($variantRank !== null) {
            $effectiveRank = $variantRank;
        } else {
            $userRank = $user->relationLoaded('rankHistory')
                ? $user->rankHistory
                    ->where('mode', $eligibleMode)
                    ->sortByDesc('recorded_at')
                    ->first()
                : $user->rankHistory()
                    ->where('mode', $eligibleMode)
                    ->latest('recorded_at')
                    ->first();

            if (! $userRank) {
                return false;
            }

            // Calculate effective rank (BWS or raw)
            $effectiveRank = $this->is_bws
                ? app(BwsCalculator::class)->calculateForUser($user, $this, $eligibleMode)
                : $userRank->global_rank;

            if (! $effectiveRank) {
                return false;
            }
        }

        // Check rank range
        if ($this->rank_range_min && $effectiveRank < $this->rank_range_min) {
            return false;
        }

        if ($this->rank_range_max && $effectiveRank > $this->rank_range_max) {
            return false;
        }

        // Check regional restrictions
        return $this->isUserEligibleByCountry($user->country_code);
    }

    /**
     * Helper: Check if tournament matches a specific mode
     */
    protected function matchesMode(string $mode): bool
    {
        return collect($this->modes_with_details)->contains(function ($modeDetail) use ($mode) {
            return $modeDetail['mode'] === $mode;
        });
    }

    /**
     * Accessor: Get restricted country names
     *
     * @return Collection<int, string>
     */
    public function getRestrictedCountryNamesAttribute(): Collection
    {
        if (empty($this->restricted_countries)) {
            return collect();
        }

        return collect($this->restricted_countries)
            ->map(fn ($code) => $this->getCountryName($code, app()->getLocale()))
            ->filter();
    }

    /**
     * Get country name from code
     *
     * @param  string|null  $code  Territory code (ISO 3166-1 alpha-2)
     * @param  string|null  $locale  Locale code (e.g., 'en', 'ko', 'ja')
     * @return string|null Localized country name, or null if code is empty
     */
    protected function getCountryName(?string $code, ?string $locale = null): ?string
    {
        if (! $code) {
            return null;
        }

        $locale = $locale ?? app()->getLocale();

        return app(CldrService::class)->getCountryName($code, $locale);
    }

    /**
     * Accessor: Generate forum post URL from forum topic ID or return wiki URL
     *
     * Returns:
     * - Wiki URL if stored (for official tournaments like OWC, MWC)
     * - Forum URL computed from forum_topic_id (for community tournaments)
     * - null if neither exists
     */
    public function getForumPostUrlAttribute(): ?string
    {
        // If wiki URL is stored, return it
        if (! empty($this->attributes['forum_post_url'] ?? null)) {
            return $this->attributes['forum_post_url'];
        }

        // Otherwise compute from forum_topic_id
        if (empty($this->forum_topic_id)) {
            return null;
        }

        return "https://osu.ppy.sh/community/forums/topics/{$this->forum_topic_id}";
    }

    /**
     * Accessor: Get cached banner URL
     */
    public function getCachedBannerUrlAttribute(): string
    {
        if (! $this->banner_url) {
            return $this->getDefaultBannerUrl();
        }

        // If cache is stale or missing, return original URL
        if (! $this->banner_image_cached_at || $this->needsBannerRefresh()) {
            return $this->banner_url;
        }

        // Check for cached copy with any extension
        $extensions = ['jpg', 'png', 'gif', 'webp'];
        foreach ($extensions as $ext) {
            $cachedPath = "banners/{$this->id}.{$ext}";
            if (Storage::disk('public')->exists($cachedPath)) {
                return Storage::disk('public')->url($cachedPath);
            }
        }

        // No cache found, return original URL
        return $this->banner_url;
    }

    /**
     * Get default banner URL
     */
    public function getDefaultBannerUrl(): string
    {
        return asset('images/default-tournament-banner.png');
    }

    /**
     * Check if banner should be cached
     */
    public function shouldCacheBanner(): bool
    {
        return $this->banner_url &&
               ! $this->isLocalBanner() &&
               $this->needsBannerRefresh();
    }

    /**
     * Check if banner is a local URL
     */
    protected function isLocalBanner(): bool
    {
        if (! $this->banner_url) {
            return false;
        }

        return str_starts_with($this->banner_url, '/') ||
               str_starts_with($this->banner_url, asset(''));
    }

    /**
     * Check if banner cache needs refresh
     */
    protected function needsBannerRefresh(): bool
    {
        if (! $this->banner_image_cached_at) {
            return true;
        }

        return $this->banner_image_cached_at->lt(now()->subWeek());
    }

    /**
     * Get the URL to the tournament show page
     */
    public function showRoute(): string
    {
        return route('tournaments.show', $this);
    }
}
