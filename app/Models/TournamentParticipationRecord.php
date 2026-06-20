<?php

namespace App\Models;

use App\Services\ParticipationMatchLinkService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tournament_id
 * @property string $source
 * @property string $review_status
 * @property string|null $selection_outcome
 * @property string|null $final_result
 * @property string|null $stage_type
 * @property string|null $stage_name
 * @property string|null $round_label
 * @property string|null $bracket_path
 * @property int|null $placement
 * @property int|null $seed
 * @property string|null $team_name
 * @property string|null $memo
 * @property-read array<int, array<string, mixed>> $matches
 * @property array<int, int>|null $pending_teammate_osu_ids
 * @property int|null $placement_min
 * @property int|null $placement_max
 * @property int|null $placement_override
 * @property array<string, mixed>|null $metadata
 * @property int|null $user_input_deleted_by
 * @property Carbon|null $profile_hidden_at
 * @property int|null $profile_hidden_by
 * @property-read string|null $selection_outcome_label
 * @property-read string|null $final_result_label
 * @property-read string $result_summary
 * @property-read User $user
 * @property-read Tournament $tournament
 * @property-read Collection<int, User> $teammates
 * @property-read Collection<int, ParticipationRecordReport> $reports
 * @property-read Collection<int, ParticipationRecordMatch> $participationMatches
 */
class TournamentParticipationRecord extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_SYSTEM = 'system';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    public const SELECTION_REGISTERED = 'registered';

    public const SELECTION_SEED_CUT = 'seed_cut';

    public const SELECTION_NOT_PICKED = 'not_picked';

    public const SELECTION_TRYOUT_FAILED = 'tryout_failed';

    public const SELECTION_RANDOM_POOL_MISSED = 'random_pool_missed';

    public const SELECTION_AUCTION_UNSOLD = 'auction_unsold';

    public const SELECTION_DISQUALIFIED = 'disqualified';

    public const RESULT_ACTIVE = 'active';

    public const RESULT_ELIMINATED = 'eliminated';

    public const RESULT_COMPLETED = 'completed';

    public const RESULT_PODIUM = 'podium';

    public const RESULT_WINNER = 'winner';

    public const BRACKET_WINNERS = 'winners';

    public const BRACKET_LOSERS = 'losers';

    public const BRACKET_GRAND_FINALS = 'grand_finals';

    protected $fillable = [
        'user_id',
        'tournament_id',
        'source',
        'review_status',
        'selection_outcome',
        'final_result',
        'stage_type',
        'stage_name',
        'round_label',
        'bracket_path',
        'placement',
        'seed',
        'team_name',
        'memo',
        'pending_teammate_osu_ids',
        'placement_min',
        'placement_max',
        'placement_override',
        'metadata',
        'user_input_deleted_at',
        'user_input_deleted_by',
        'user_input_deleted_reason',
        'profile_hidden_at',
        'profile_hidden_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'pending_teammate_osu_ids' => 'array',
            'placement' => 'integer',
            'placement_min' => 'integer',
            'placement_max' => 'integer',
            'placement_override' => 'integer',
            'seed' => 'integer',
            'user_input_deleted_at' => 'datetime',
            'profile_hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function teammates(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'participation_record_teammates')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ParticipationInputLog, $this>
     */
    public function inputLogs(): HasMany
    {
        return $this->hasMany(ParticipationInputLog::class);
    }

    /**
     * @return HasMany<ParticipationDeletionRequest, $this>
     */
    public function deletionRequests(): HasMany
    {
        return $this->hasMany(ParticipationDeletionRequest::class);
    }

    /**
     * @return HasMany<ParticipationRecordReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ParticipationRecordReport::class);
    }

    /**
     * @return HasMany<ParticipationRecordMatch, $this>
     */
    public function participationMatches(): HasMany
    {
        /** @var HasMany<ParticipationRecordMatch, $this> $relation */
        $relation = $this->hasMany(ParticipationRecordMatch::class);
        $relation->getQuery()->orderBy('position');

        return $relation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMatchesAttribute(mixed $value): array
    {
        if ($this->relationLoaded('participationMatches')) {
            /** @var Collection<int, ParticipationRecordMatch> $participationMatches */
            $participationMatches = $this->getRelation('participationMatches');

            return $participationMatches
                ->map(fn (ParticipationRecordMatch $match): array => $match->toParticipationPayload())
                ->values()
                ->all();
        }

        if ($this->exists && $this->participationMatches()->exists()) {
            return $this->participationMatches()
                ->get()
                ->map(fn (ParticipationRecordMatch $match): array => $match->toParticipationPayload())
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $matches
     */
    public function syncParticipationMatches(array $matches, ?int $submittedBy = null): void
    {
        $this->participationMatches()->delete();

        if ($matches === []) {
            $this->unsetRelation('participationMatches');

            return;
        }

        $linker = app(ParticipationMatchLinkService::class);
        $now = now();
        $rows = collect($matches)
            ->values()
            ->map(function (array $match, int $position) use ($linker, $now, $submittedBy): array {
                $match = $linker->normalizeForRecord($match, $this, $submittedBy);

                return [
                    'tournament_participation_record_id' => $this->id,
                    'position' => $position,
                    'stage' => $match['stage'] ?? null,
                    'result' => $match['result'] ?? null,
                    'score_for' => $match['score_for'] ?? null,
                    'score_against' => $match['score_against'] ?? null,
                    'mp_link' => $match['mp_link'] ?? null,
                    'mp_id' => $match['mp_id'] ?? null,
                    'is_forfeit' => (bool) ($match['is_forfeit'] ?? false),
                    'is_individual_qualifier' => (bool) ($match['is_individual_qualifier'] ?? false),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        ParticipationRecordMatch::query()->insert($rows);
        $this->unsetRelation('participationMatches');
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $matches
     */
    public function copyParticipationMatchesFrom(self $source, ?array $matches = null): void
    {
        if ($matches === null) {
            $matches = $source->participationMatches()
                ->get()
                ->map(fn (ParticipationRecordMatch $match): array => $match->toParticipationPayload())
                ->values()
                ->all();
        }

        $this->syncParticipationMatches($matches, $source->user_id);
    }

    public function isPodiumBacked(): bool
    {
        return $this->source === self::SOURCE_SYSTEM
            || data_get($this->metadata, 'autofilled_from') === 'tournament_winners';
    }

    public function isAdminApprovedPodium(): bool
    {
        $placement = $this->displayPlacement();

        return $this->review_status === self::REVIEW_APPROVED
            && $placement !== null
            && $placement <= 3;
    }

    public function canUserDeleteDirectly(): bool
    {
        return ! $this->isPodiumBacked()
            && ! $this->isAdminApprovedPodium()
            && $this->teammates->isEmpty();
    }

    public function displayPlacement(): ?int
    {
        return $this->placement_override ?? $this->placement;
    }

    public function placementRangeLabel(): ?string
    {
        if ($this->placement_override) {
            return '#'.$this->placement_override;
        }

        if ($this->placement_min && $this->placement_max && $this->placement_min !== $this->placement_max) {
            return "#{$this->placement_min}-{$this->placement_max}";
        }

        $placement = $this->placement ?? $this->placement_min;

        return $placement ? '#'.$placement : null;
    }

    /**
     * @return array<string, string>
     */
    public static function selectionOutcomeLabels(): array
    {
        return [
            self::SELECTION_REGISTERED => 'Registered',
            self::SELECTION_SEED_CUT => 'Cut by seed',
            self::SELECTION_NOT_PICKED => 'Not picked',
            self::SELECTION_TRYOUT_FAILED => 'Tryout failed',
            self::SELECTION_RANDOM_POOL_MISSED => 'Random pool missed',
            self::SELECTION_AUCTION_UNSOLD => 'Auction unsold',
            self::SELECTION_DISQUALIFIED => 'Disqualified',
            'dnq' => 'DNQ',
            'dnp' => 'DNP',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function finalResultLabels(): array
    {
        return [
            self::RESULT_ACTIVE => 'Active',
            self::RESULT_ELIMINATED => 'Eliminated',
            self::RESULT_COMPLETED => 'Completed',
            self::RESULT_PODIUM => 'Podium',
            self::RESULT_WINNER => 'Winner',
        ];
    }

    public function getSelectionOutcomeLabelAttribute(): ?string
    {
        if (! $this->selection_outcome) {
            return null;
        }

        return self::selectionOutcomeLabels()[$this->selection_outcome] ?? $this->selection_outcome;
    }

    public function getFinalResultLabelAttribute(): ?string
    {
        if (! $this->final_result) {
            return null;
        }

        return self::finalResultLabels()[$this->final_result] ?? $this->final_result;
    }

    public function getResultSummaryAttribute(): string
    {
        $parts = array_filter([
            $this->final_result_label,
            $this->stage_name && $this->stage_name !== $this->round_label ? $this->stage_name : null,
            $this->round_label,
            match ($this->bracket_path) {
                self::BRACKET_WINNERS => 'Winners',
                self::BRACKET_LOSERS => 'Losers',
                default => null,
            },
        ]);

        return $parts === [] ? ($this->selection_outcome_label ?? 'Participation recorded') : implode(' - ', $parts);
    }
}
