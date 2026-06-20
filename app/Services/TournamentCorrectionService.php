<?php

namespace App\Services;

use App\Helpers\StaffRoleHelper;
use App\Jobs\BackfillPodiumParticipationJob;
use App\Jobs\ProcessTournamentCorrectionJob;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TournamentCorrectionService
{
    /** @var list<string> */
    public const UTC_DATETIME_FIELDS = [
        'registration_start',
        'registration_end',
        'tournament_start',
        'tournament_end',
    ];

    /** @var list<string> */
    private const INTEGER_METADATA_FIELDS = [
        'vs_size',
        'team_size_min',
        'team_size_max',
        'rank_range_min',
        'rank_range_max',
        'start_round_size',
    ];

    /** @var list<string> */
    private const DECIMAL_METADATA_FIELDS = [
        'star_rating_first',
        'star_rating_last',
        'star_rating_qualifier',
        'bws_base_exponent',
        'bws_badge_power',
        'bws_divisor',
    ];

    /** @var list<string> */
    private const BOOLEAN_METADATA_FIELDS = [
        'is_badge',
        'is_bws',
    ];

    /** @var list<string> */
    private const CORRECTION_GROUP_ORDER = [
        'metadata',
        'format_progression',
        'eligibility_schedule',
        'staff',
        'podium',
        'flags_bws',
        'links_badges',
        'other',
    ];

    /** @var array<string, list<string>> */
    private const CORRECTION_FIELD_GROUPS = [
        'metadata' => [
            'banner_url',
            'title',
            'modes',
            'vs_size',
            'team_size_min',
            'team_size_max',
        ],
        'format_progression' => [
            'team_formation_style',
            'format_tags',
            'format_structure',
            'format',
            'start_round_size',
        ],
        'eligibility_schedule' => [
            'restricted_countries',
            'rank_range_min',
            'rank_range_max',
            'registration_start',
            'registration_end',
            'tournament_start',
            'tournament_end',
            'star_rating_qualifier',
            'star_rating_first',
            'star_rating_last',
        ],
        'flags_bws' => [
            'is_badge',
            'is_bws',
            'bws_base_exponent',
            'bws_badge_power',
            'bws_divisor',
            'bws_badge_age_cutoff',
        ],
        'links_badges' => [
            'forum_post_url',
            'spreadsheet_url',
            'discord_url',
            'twitch_url',
            'registration_url',
            'bracket_url',
            'badge_status',
            'badge_urls',
        ],
    ];

    /** @var list<string> */
    public const METADATA_FIELDS = [
        'title',
        'modes',
        'vs_size',
        'team_size_min',
        'team_size_max',
        'rank_range_min',
        'rank_range_max',
        'registration_start',
        'registration_end',
        'tournament_start',
        'tournament_end',
        'is_badge',
        'is_bws',
        'badge_status',
        'banner_url',
        'forum_post_url',
        'spreadsheet_url',
        'discord_url',
        'twitch_url',
        'registration_url',
        'bracket_url',
        'format',
        'star_rating_first',
        'star_rating_last',
        'star_rating_qualifier',
        'start_round_size',
        'restricted_countries',
        'team_formation_style',
        'format_tags',
        'format_structure',
        'bws_base_exponent',
        'bws_badge_power',
        'bws_divisor',
        'bws_badge_age_cutoff',
        'badge_urls',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildPayload(Tournament $tournament, array $input): array
    {
        $tournament->loadMissing(['staff', 'winners.user']);

        $changes = [];
        $metadata = $this->metadataFromInput($input);
        $inputFields = array_keys($input);
        foreach ($metadata as $field => $newValue) {
            $oldValue = $this->valueForField($tournament, $field);
            $castNewValue = $this->castTournamentValue($tournament, $field, $newValue);
            $oldDisplayValue = $this->normalizedMetadataValue($oldValue, $field);
            $newDisplayValue = $this->normalizedMetadataValue(
                in_array($field, self::UTC_DATETIME_FIELDS, true) ? $newValue : $castNewValue,
                $field
            );
            $applyValue = $field === 'format_structure'
                ? $this->stripLegacyFormat($castNewValue)
                : $castNewValue;

            if ($oldDisplayValue === $newDisplayValue) {
                if ($field === 'format_structure'
                    && $this->containsLegacyFormat($oldValue)
                    && ! $this->containsLegacyFormat($applyValue)) {
                    $changes["metadata.{$field}"] = [
                        'domain' => 'metadata',
                        'field' => $field,
                        'label' => $this->label($field),
                        'old' => $oldDisplayValue,
                        'new' => $newDisplayValue,
                        'apply' => $applyValue,
                        'visible' => false,
                        'auto_accept' => true,
                    ];
                }

                continue;
            }

            $changes["metadata.{$field}"] = [
                'domain' => 'metadata',
                'field' => $field,
                'label' => $this->label($field),
                'old' => $oldDisplayValue,
                'new' => $newDisplayValue,
                'apply' => $applyValue,
            ];

            if ($this->isDerivedMetadataChange($field, $inputFields)) {
                $changes["metadata.{$field}"]['visible'] = false;
                $changes["metadata.{$field}"]['derived_from'] = $this->derivedSourceKeys($field, $inputFields);
            }
        }

        $changes = $this->scopeDerivedSourcesToVisibleChanges($changes);

        foreach ($this->staffChanges($tournament, $input) as $key => $change) {
            $changes[$key] = $change;
        }

        foreach ($this->podiumChanges($tournament, $input) as $key => $change) {
            $changes[$key] = $change;
        }

        return [
            'changes' => $changes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Tournament $tournament): array
    {
        $tournament->loadMissing(['staff', 'winners.user']);

        return [
            'metadata' => collect(self::METADATA_FIELDS)
                ->mapWithKeys(fn (string $field): array => [
                    $field => $this->displayValueForField($this->valueForField($tournament, $field), $field),
                ])
                ->all(),
            'staff' => $this->staffSnapshot($tournament),
            'podium' => $this->podiumSnapshot($tournament),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createCorrection(Tournament $tournament, User $user, array $input): TournamentCorrection
    {
        if (! in_array($tournament->status, [Tournament::STATUS_APPROVED, Tournament::STATUS_PENDING], true)) {
            throw new \InvalidArgumentException('Only approved or pending tournaments can receive corrections.');
        }

        if ($tournament->corrections()->active()->exists()) {
            throw new \InvalidArgumentException('This tournament already has a pending correction.');
        }

        $payload = $this->buildPayload($tournament, $input);
        if (empty($payload['changes']) || $this->visibleChangesFromArray($payload['changes']) === []) {
            throw new \InvalidArgumentException('No changes were suggested.');
        }

        return TournamentCorrection::query()->create([
            'tournament_id' => $tournament->id,
            'submitted_by' => $user->id,
            'status' => TournamentCorrection::STATUS_PENDING,
            'kind' => TournamentCorrection::KIND_CORRECTION,
            'payload' => $payload,
            'current_snapshot' => $this->snapshot($tournament),
            'submitter_note' => $this->submitterNoteFromInput($input),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createTournamentRequest(User $user, array $input, ?int $topicId = null): TournamentCorrection
    {
        $blank = new Tournament([
            'modes' => [],
            'format_tags' => [],
            'format_structure' => ['stages' => []],
            'restricted_countries' => [],
            'badge_urls' => [],
        ]);
        $blank->setRelation('staff', collect());
        $blank->setRelation('winners', collect());

        $payload = $this->buildPayload($blank, $input);
        $payload['proposal'] = Arr::except($input, [
            'creation_confirmed',
            'selected_tournament_id',
        ]);
        $payload['forum_topic_id'] = $topicId;

        return TournamentCorrection::query()->create([
            'tournament_id' => null,
            'submitted_by' => $user->id,
            'status' => TournamentCorrection::STATUS_PENDING,
            'kind' => TournamentCorrection::KIND_NEW_TOURNAMENT,
            'payload' => $payload,
            'current_snapshot' => [],
            'submitter_note' => $this->submitterNoteFromInput($input),
        ]);
    }

    /**
     * @param  list<string>  $acceptedKeys
     */
    public function review(TournamentCorrection $correction, User $admin, array $acceptedKeys, ?string $reviewNote = null): TournamentCorrection
    {
        $changes = $this->changes($correction);
        $visibleChanges = $this->visibleChanges($correction);
        $acceptedKeys = array_values(array_intersect($acceptedKeys, array_keys($visibleChanges)));
        $acceptedKeys = $this->expandAcceptedKeys($acceptedKeys, $changes);
        $rejectedKeys = array_values(array_diff(array_keys($changes), $acceptedKeys));
        $processingTotal = collect($acceptedKeys)
            ->map(fn (string $key): ?string => $changes[$key]['domain'] ?? null)
            ->filter(fn (?string $domain): bool => in_array($domain, ['staff', 'podium'], true))
            ->unique()
            ->count();

        DB::transaction(function () use ($correction, $admin, $reviewNote, $changes, $acceptedKeys, $rejectedKeys, $processingTotal): void {
            $tournament = $correction->tournament()->lockForUpdate()->first();
            if ($correction->isNewTournamentRequest()) {
                $required = ['metadata.title', 'metadata.modes'];
                if ($acceptedKeys !== [] && array_diff($required, $acceptedKeys) !== []) {
                    throw new \InvalidArgumentException('Title and game modes must both be accepted before creating a tournament.');
                }

                if ($acceptedKeys !== []) {
                    $titleChange = $changes['metadata.title'];
                    $modesChange = $changes['metadata.modes'];
                    $tournament = Tournament::query()->create([
                        'title' => $titleChange['apply'] ?? $titleChange['new'],
                        'modes' => $modesChange['apply'] ?? $modesChange['new'],
                        'forum_topic_id' => data_get($correction->payload, 'forum_topic_id'),
                        'status' => Tournament::STATUS_PENDING,
                        'import_source' => 'user_correction',
                        'field_sources' => [],
                    ]);
                    $correction->tournament()->associate($tournament);
                    $correction->save();
                }
            }

            if (! $tournament && $acceptedKeys !== []) {
                throw new \InvalidArgumentException('The target tournament no longer exists.');
            }

            $metadataUpdate = [];
            $fieldSources = $tournament ? ($tournament->field_sources ?? []) : [];

            foreach ($acceptedKeys as $key) {
                $change = $changes[$key];
                if (($change['domain'] ?? null) === 'metadata') {
                    $field = (string) $change['field'];
                    $metadataUpdate[$field] = $change['apply'] ?? $change['new'] ?? null;
                    $fieldSources[$field] = 'user_correction';
                }
            }

            if ($tournament && $metadataUpdate !== []) {
                $metadataUpdate['field_sources'] = $fieldSources;
                $tournament->update($metadataUpdate);
            }

            $status = $acceptedKeys === []
                ? TournamentCorrection::STATUS_REJECTED
                : TournamentCorrection::STATUS_PROCESSING;

            $correction->update([
                'status' => $status,
                'admin_decisions' => [
                    'accepted' => $acceptedKeys,
                    'rejected' => $rejectedKeys,
                    'apply_failures' => [],
                ],
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
                'processing_total' => $processingTotal,
                'processing_completed' => 0,
            ]);

            if ($tournament) {
                AdminAuditLog::log(
                    $admin,
                    'tournament.correction_reviewed',
                    $tournament,
                    [
                        'correction_id' => $correction->id,
                        'status' => $status,
                        'accepted' => $acceptedKeys,
                        'rejected' => $rejectedKeys,
                        'processing_total' => $processingTotal,
                    ]
                );
            } else {
                AdminAuditLog::logRaw($admin, 'tournament.correction_reviewed', TournamentCorrection::class, $correction->id, [
                    'correction_id' => $correction->id,
                    'status' => $status,
                    'accepted' => [],
                    'rejected' => $rejectedKeys,
                ]);
            }
        });

        if ($acceptedKeys !== []) {
            ProcessTournamentCorrectionJob::dispatch($correction->id)->afterCommit();
        }

        return $correction->refresh();
    }

    public function processAcceptedParticipants(TournamentCorrection $correction): TournamentCorrection
    {
        $correction->refresh();
        if ($correction->status !== TournamentCorrection::STATUS_PROCESSING) {
            return $correction;
        }

        $tournament = $correction->tournament;
        if (! $tournament) {
            return $this->failProcessing($correction->id, 'The target tournament no longer exists.');
        }

        $changes = $this->changes($correction);
        $acceptedKeys = data_get($correction->admin_decisions, 'accepted', []);
        $acceptedKeys = is_array($acceptedKeys) ? $acceptedKeys : [];
        $acceptedLookup = array_fill_keys($acceptedKeys, true);
        $admin = $correction->reviewer;
        $failures = [];
        $pendingPodiumBackfills = [
            'user_ids' => [],
            'tournament_groups' => [],
        ];

        if ($this->hasAcceptedDomain($changes, $acceptedLookup, 'staff')) {
            if ($admin) {
                $failures = [
                    ...$failures,
                    ...$this->applyStaffChanges($tournament, $changes, $acceptedLookup, $admin),
                ];
            } else {
                $failures[] = [
                    'key' => 'staff.processing',
                    'domain' => 'staff',
                    'username' => null,
                    'message' => 'The reviewing administrator is no longer available.',
                ];
            }
            $this->advanceProcessing($correction);
        }

        if ($this->hasAcceptedDomain($changes, $acceptedLookup, 'podium')) {
            $failures = [
                ...$failures,
                ...$this->applyPodiumChanges($tournament, $changes, $acceptedLookup, $pendingPodiumBackfills),
            ];
            $this->advanceProcessing($correction);
        }

        $this->dispatchPendingPodiumBackfills($pendingPodiumBackfills);

        $visibleChanges = $this->visibleChanges($correction);
        $acceptedVisibleCount = count(array_intersect($acceptedKeys, array_keys($visibleChanges)));
        $status = $failures === [] && $acceptedVisibleCount === count($visibleChanges)
            ? TournamentCorrection::STATUS_APPROVED
            : TournamentCorrection::STATUS_PARTIALLY_APPROVED;
        $decisions = $correction->admin_decisions ?? [];
        $decisions['apply_failures'] = $failures;

        $correction->update([
            'status' => $status,
            'admin_decisions' => $decisions,
            'processing_completed' => $correction->processing_total,
        ]);

        if ($admin) {
            AdminAuditLog::log(
                $admin,
                'tournament.correction_processing_completed',
                $tournament,
                [
                    'correction_id' => $correction->id,
                    'status' => $status,
                    'apply_failures' => $failures,
                ]
            );
        }

        return $correction->refresh();
    }

    public function failProcessing(int $correctionId, string $message): TournamentCorrection
    {
        $correction = TournamentCorrection::query()->findOrFail($correctionId);
        $decisions = $correction->admin_decisions ?? [];
        $failures = is_array($decisions['apply_failures'] ?? null) ? $decisions['apply_failures'] : [];
        $failures[] = [
            'key' => 'processing',
            'domain' => 'correction',
            'username' => null,
            'message' => $message,
        ];
        $decisions['apply_failures'] = $failures;

        $correction->update([
            'status' => TournamentCorrection::STATUS_PARTIALLY_APPROVED,
            'admin_decisions' => $decisions,
            'processing_completed' => $correction->processing_total,
        ]);

        return $correction->refresh();
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     * @param  array<string, bool>  $acceptedLookup
     */
    private function hasAcceptedDomain(array $changes, array $acceptedLookup, string $domain): bool
    {
        foreach ($changes as $key => $change) {
            if (isset($acceptedLookup[$key]) && ($change['domain'] ?? null) === $domain) {
                return true;
            }
        }

        return false;
    }

    private function advanceProcessing(TournamentCorrection $correction): void
    {
        $correction->refresh();
        $correction->update([
            'processing_completed' => min(
                $correction->processing_total,
                $correction->processing_completed + 1
            ),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function changes(TournamentCorrection $correction): array
    {
        $changes = data_get($correction->payload, 'changes', []);

        if (! is_array($changes)) {
            return [];
        }

        $normalized = [];
        foreach ($changes as $key => $change) {
            if (! is_array($change) || ($change['domain'] ?? null) !== 'metadata') {
                $normalized[$key] = $change;

                continue;
            }

            $field = (string) ($change['field'] ?? '');
            if (! in_array($field, self::METADATA_FIELDS, true)) {
                $normalized[$key] = $change;

                continue;
            }

            if (! array_key_exists('old', $change) || ! array_key_exists('new', $change)) {
                $normalized[$key] = $change;

                continue;
            }

            $oldValue = $change['old'] ?? null;
            $newValue = $change['new'] ?? null;
            $change['old'] = $this->normalizedMetadataValue($oldValue, $field);
            $change['new'] = $this->normalizedMetadataValue($newValue, $field);

            if ($field === 'format_structure') {
                $applyValue = $change['apply'] ?? $newValue;
                $change['apply'] = $this->stripLegacyFormat($applyValue);

                if ($change['old'] === $change['new']
                    && (($change['auto_accept'] ?? false) === true
                        || ($this->containsLegacyFormat($oldValue)
                            && ! $this->containsLegacyFormat($applyValue)))) {
                    $change['visible'] = false;
                    $change['auto_accept'] = true;
                    $normalized[$key] = $change;

                    continue;
                }
            }

            if ($change['old'] === $change['new']) {
                continue;
            }

            $normalized[$key] = $change;
        }

        return $normalized;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function visibleChanges(TournamentCorrection $correction): array
    {
        return $this->visibleChangesFromArray($this->changes($correction));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function orderedChanges(TournamentCorrection $correction): array
    {
        return $this->sortChanges($this->visibleChanges($correction));
    }

    /**
     * @return Collection<string, Collection<string, array<string, mixed>>>
     */
    public function groupedOrderedChanges(TournamentCorrection $correction): Collection
    {
        return collect($this->orderedChanges($correction))->groupBy(
            fn (array $change): string => $this->groupForChange($change),
            preserveKeys: true
        );
    }

    /**
     * @return array<string, array{role: string, label: string, changes: array<string, array<string, mixed>>}>
     */
    public function groupedStaffHistoryChanges(TournamentCorrection $correction): array
    {
        return collect($this->orderedChanges($correction))
            ->filter(fn (array $change): bool => ($change['domain'] ?? null) === 'staff')
            ->groupBy(fn (array $change): string => (string) ($change['field'] ?? 'other'), preserveKeys: true)
            ->map(fn (Collection $changes, string $role): array => [
                'role' => $role,
                'label' => StaffRoleHelper::getRoleLabel($role),
                'changes' => $changes->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, array{placement: int, label: string, changes: array<string, array<string, mixed>>}>
     */
    public function groupedPodiumHistoryChanges(TournamentCorrection $correction): array
    {
        return collect($this->orderedChanges($correction))
            ->filter(fn (array $change): bool => ($change['domain'] ?? null) === 'podium')
            ->groupBy(fn (array $change): string => (string) ($change['field'] ?? '0'), preserveKeys: true)
            ->map(fn (Collection $changes, string $placement): array => [
                'placement' => (int) $placement,
                'label' => match ((int) $placement) {
                    1 => '1st Place',
                    2 => '2nd Place',
                    3 => '3rd Place',
                    default => "Placement {$placement}",
                },
                'changes' => $changes->all(),
            ])
            ->all();
    }

    /**
     * @return list<array{domain: string, message: string, usernames: list<string>}>
     */
    public function groupedApplyFailures(TournamentCorrection $correction): array
    {
        $failures = data_get($correction->admin_decisions, 'apply_failures', []);
        if (! is_array($failures)) {
            return [];
        }

        /** @var array<string, array{domain: string, message: string, usernames: list<string>}> $grouped */
        $grouped = [];
        foreach ($failures as $failure) {
            if (! is_array($failure)) {
                continue;
            }

            $domain = (string) ($failure['domain'] ?? 'correction');
            $message = $this->normalizedApplyFailureMessage(
                (string) ($failure['message'] ?? 'Unable to apply this change.')
            );
            $groupKey = $domain.'|'.$message;
            $grouped[$groupKey] ??= [
                'domain' => $domain,
                'message' => $message,
                'usernames' => [],
            ];

            $username = $failure['username'] ?? null;
            if (is_string($username) && $username !== '' && ! in_array($username, $grouped[$groupKey]['usernames'], true)) {
                $grouped[$groupKey]['usernames'][] = $username;
            }
        }

        return array_values($grouped);
    }

    private function normalizedApplyFailureMessage(string $message): string
    {
        if (str_contains(strtolower($message), 'not found on osu!')) {
            return 'User not found on osu!';
        }

        if (preg_match("/already has role '([^']+)'/i", $message, $matches) === 1) {
            return "Already has role '{$matches[1]}'";
        }

        if (preg_match('/already exists in placement ([0-9]+)/i', $message, $matches) === 1) {
            return "Already exists in placement {$matches[1]}";
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function metadataFromInput(array $input): array
    {
        return collect(self::METADATA_FIELDS)
            ->filter(fn (string $field): bool => array_key_exists($field, $input))
            ->mapWithKeys(fn (string $field): array => [$field => $this->normalizeEmpty($input[$field])])
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     * @return array<string, array<string, mixed>>
     */
    private function visibleChangesFromArray(array $changes): array
    {
        return collect($changes)
            ->reject(fn (array $change): bool => ($change['visible'] ?? true) === false)
            ->all();
    }

    /**
     * @param  list<string>  $inputFields
     */
    private function isDerivedMetadataChange(string $field, array $inputFields): bool
    {
        if ($field === 'format_tags') {
            return true;
        }

        return $field === 'start_round_size'
            && in_array('format_structure', $inputFields, true);
    }

    /**
     * @param  list<string>  $inputFields
     * @return list<string>
     */
    private function derivedSourceKeys(string $field, array $inputFields): array
    {
        if ($field === 'start_round_size') {
            return ['metadata.format_structure'];
        }

        if ($field !== 'format_tags') {
            return [];
        }

        return collect(['team_formation_style', 'format_structure', 'format'])
            ->filter(fn (string $sourceField): bool => in_array($sourceField, $inputFields, true))
            ->map(fn (string $sourceField): string => "metadata.{$sourceField}")
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     * @return array<string, array<string, mixed>>
     */
    private function scopeDerivedSourcesToVisibleChanges(array $changes): array
    {
        foreach ($changes as $key => $change) {
            if (($change['visible'] ?? true) !== false || ! is_array($change['derived_from'] ?? null)) {
                continue;
            }

            $changes[$key]['derived_from'] = collect($change['derived_from'])
                ->filter(fn (string $sourceKey): bool => isset($changes[$sourceKey]) && ($changes[$sourceKey]['visible'] ?? true) !== false)
                ->values()
                ->all();
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function submitterNoteFromInput(array $input): ?string
    {
        $note = $this->normalizeEmpty($input['submitter_note'] ?? null);

        return is_string($note) ? $note : null;
    }

    /**
     * @param  list<string>  $acceptedKeys
     * @param  array<string, array<string, mixed>>  $changes
     * @return list<string>
     */
    private function expandAcceptedKeys(array $acceptedKeys, array $changes): array
    {
        $acceptedLookup = array_fill_keys($acceptedKeys, true);

        foreach ($changes as $key => $change) {
            if (($change['visible'] ?? true) !== false || ($change['domain'] ?? null) !== 'metadata') {
                continue;
            }

            if (($change['auto_accept'] ?? false) === true && $acceptedKeys !== []) {
                $acceptedKeys[] = $key;
                $acceptedLookup[$key] = true;

                continue;
            }

            $field = (string) ($change['field'] ?? '');
            $hasExplicitSources = is_array($change['derived_from'] ?? null);
            $sourceKeys = $hasExplicitSources ? $change['derived_from'] : match ($field) {
                'format_tags' => ['metadata.team_formation_style', 'metadata.format_structure'],
                'start_round_size' => ['metadata.format_structure'],
                default => [],
            };

            $shouldAccept = $hasExplicitSources
                ? collect($sourceKeys)->every(fn (string $sourceKey): bool => isset($acceptedLookup[$sourceKey]))
                : collect($sourceKeys)->contains(fn (string $sourceKey): bool => isset($acceptedLookup[$sourceKey]));

            if ($sourceKeys !== [] && $shouldAccept) {
                $acceptedKeys[] = $key;
                $acceptedLookup[$key] = true;
            }
        }

        return array_values(array_unique($acceptedKeys));
    }

    private function valueForField(Tournament $tournament, string $field): mixed
    {
        if ($field === 'forum_post_url') {
            return $tournament->forum_post_url;
        }

        return $tournament->getAttribute($field);
    }

    private function castTournamentValue(Tournament $tournament, string $field, mixed $value): mixed
    {
        if (in_array($field, ['modes', 'format_tags', 'format_structure', 'restricted_countries', 'badge_urls'], true)) {
            return $field === 'format_structure' ? $this->stripLegacyFormat($value) : $value;
        }

        if (in_array($field, self::UTC_DATETIME_FIELDS, true) && $value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d H:i:s');
        }

        $probe = $tournament->replicate();
        $probe->setAttribute($field, $value);

        return $probe->getAttribute($field);
    }

    private function displayValueForField(mixed $value, string $field): mixed
    {
        if (in_array($field, self::UTC_DATETIME_FIELDS, true) && $value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)
                ->utc()
                ->format('Y-m-d H:i:s');
        }

        if ($field === 'format_structure') {
            $value = $this->stripLegacyFormat($value);
        }

        return $this->displayValue($value);
    }

    private function normalizedMetadataValue(mixed $value, string $field): mixed
    {
        $value = $this->normalizeEmpty($value);

        if ($value === null) {
            return null;
        }

        if (in_array($field, self::INTEGER_METADATA_FIELDS, true) && is_numeric($value)) {
            return (int) $value;
        }

        if (in_array($field, self::DECIMAL_METADATA_FIELDS, true) && is_numeric($value)) {
            $normalized = rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.');

            return $normalized === '-0' ? '0' : $normalized;
        }

        if (in_array($field, self::BOOLEAN_METADATA_FIELDS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
        }

        return $this->displayValueForField($value, $field);
    }

    private function containsLegacyFormat(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if ($key === 'legacy_format' || $this->containsLegacyFormat($item)) {
                return true;
            }
        }

        return false;
    }

    private function stripLegacyFormat(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if ($key === 'legacy_format') {
                continue;
            }

            $normalized[$key] = $this->stripLegacyFormat($item);
        }

        return $normalized;
    }

    private function normalizeEmpty(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                return collect($value)
                    ->map(fn ($item) => $this->normalizeEmpty($item))
                    ->reject(fn ($item) => $item === '' || $item === null)
                    ->all();
            }

            return collect($value)
                ->map(fn ($item) => is_string($item) ? trim($item) : $item)
                ->reject(fn ($item) => $item === '' || $item === null)
                ->values()
                ->all();
        }

        return $value;
    }

    private function valuesAreEquivalent(mixed $oldValue, mixed $newValue): bool
    {
        return $this->displayValue($oldValue) === $this->displayValue($newValue);
    }

    private function displayValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            if ($value === [] || (array_key_exists('stages', $value) && ($value['stages'] ?? []) === [] && count($value) === 1)) {
                return null;
            }

            return $this->canonicalizeArray($value);
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalizeArray(array $value): array
    {
        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[$key] = is_array($item) ? $this->canonicalizeArray($item) : $this->displayValue($item);
        }

        if (! array_is_list($canonical)) {
            ksort($canonical);
        }

        return $canonical;
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     * @return array<string, array<string, mixed>>
     */
    private function sortChanges(array $changes): array
    {
        $positions = collect(self::CORRECTION_GROUP_ORDER)
            ->flip()
            ->map(fn (int $index): int => $index)
            ->all();

        uksort($changes, function (string $left, string $right) use ($changes, $positions): int {
            $leftChange = $changes[$left];
            $rightChange = $changes[$right];
            $leftGroup = $this->groupForChange($leftChange);
            $rightGroup = $this->groupForChange($rightChange);

            return [
                $positions[$leftGroup] ?? PHP_INT_MAX,
                $this->fieldPosition($leftChange),
                $left,
            ] <=> [
                $positions[$rightGroup] ?? PHP_INT_MAX,
                $this->fieldPosition($rightChange),
                $right,
            ];
        });

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function groupForChange(array $change): string
    {
        $domain = (string) ($change['domain'] ?? 'other');
        if (in_array($domain, ['staff', 'podium'], true)) {
            return $domain;
        }

        $field = (string) ($change['field'] ?? '');
        foreach (self::CORRECTION_FIELD_GROUPS as $group => $fields) {
            if (in_array($field, $fields, true)) {
                return $group;
            }
        }

        return $domain === 'metadata' ? 'other' : $domain;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function fieldPosition(array $change): int
    {
        $group = $this->groupForChange($change);
        $field = (string) ($change['field'] ?? '');
        $fields = self::CORRECTION_FIELD_GROUPS[$group] ?? [];
        $position = array_search($field, $fields, true);

        return $position === false ? PHP_INT_MAX : $position;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, mixed>>
     */
    private function staffChanges(Tournament $tournament, array $input): array
    {
        $roles = array_keys(StaffRoleHelper::getAvailableRoles());
        $changes = [];
        $existing = $this->staffSnapshot($tournament);

        foreach ($roles as $role) {
            if (! array_key_exists("staff_{$role}", $input)) {
                continue;
            }

            $usernames = $this->namesFromText((string) ($input["staff_{$role}"] ?? ''));
            $proposed = collect($usernames)
                ->mapWithKeys(fn (string $username): array => [strtolower($role.'|'.$username) => $username]);
            $existingForRole = collect($existing)
                ->filter(fn (array $staff): bool => ($staff['role'] ?? null) === $role);

            foreach ($usernames as $username) {
                $key = strtolower($role.'|'.$username);
                if (isset($existing[$key])) {
                    continue;
                }

                $changeKey = 'staff.add.'.$role.'.'.Str::slug($username);
                $changes[$changeKey] = [
                    'domain' => 'staff',
                    'field' => $role,
                    'label' => 'Add Staff: '.StaffRoleHelper::getRoleLabel($role),
                    'old' => null,
                    'new' => $username,
                    'apply' => [
                        'action' => 'add',
                        'role' => $role,
                        'username' => $username,
                    ],
                ];
            }

            foreach ($existingForRole as $key => $staff) {
                if ($proposed->has($key)) {
                    continue;
                }

                $username = (string) ($staff['username'] ?? '');
                $changeKey = 'staff.remove.'.$role.'.'.Str::slug($username ?: (string) ($staff['user_id'] ?? 'user'));
                $changes[$changeKey] = [
                    'domain' => 'staff',
                    'field' => $role,
                    'label' => 'Remove Staff: '.StaffRoleHelper::getRoleLabel($role),
                    'old' => $username,
                    'new' => null,
                    'apply' => [
                        'action' => 'remove',
                        'role' => $role,
                        'user_id' => $staff['user_id'] ?? null,
                        'username' => $username,
                    ],
                ];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, mixed>>
     */
    private function podiumChanges(Tournament $tournament, array $input): array
    {
        if (! array_key_exists('podium_groups', $input) || ! is_array($input['podium_groups'])) {
            return [];
        }

        return $this->groupedPodiumChanges($tournament, $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, mixed>>
     */
    private function groupedPodiumChanges(Tournament $tournament, array $input): array
    {
        $changes = [];
        $existingGroups = $this->podiumGroupedSnapshot($tournament);
        $proposedGroups = $this->proposedPodiumGroups($input);

        foreach ($existingGroups as $groupKey => $existingGroup) {
            if (isset($proposedGroups[$groupKey])) {
                continue;
            }

            $placement = (int) ($existingGroup['placement'] ?? 0);
            $changes['podium.group.remove.'.$placement.'.'.Str::slug($groupKey)] = [
                'domain' => 'podium',
                'field' => (string) $placement,
                'label' => "Remove Podium Team: {$placement}",
                'old' => Arr::only($existingGroup, ['team_name', 'usernames']),
                'new' => null,
                'apply' => [
                    'action' => 'group_remove',
                    'placement' => $placement,
                    'group_key' => $groupKey,
                    'group_id' => $existingGroup['group_id'] ?? null,
                    'winner_ids' => $existingGroup['winner_ids'] ?? [],
                    'user_ids' => $existingGroup['user_ids'] ?? [],
                ],
            ];
        }

        foreach ($proposedGroups as $groupKey => $proposedGroup) {
            $existingGroup = $existingGroups[$groupKey] ?? null;
            if ($existingGroup === null && ($proposedGroup['usernames'] ?? []) === []) {
                continue;
            }

            $oldValue = $existingGroup ? Arr::only($existingGroup, ['team_name', 'usernames']) : null;
            $newValue = Arr::only($proposedGroup, ['team_name', 'usernames']);
            if ($this->valuesAreEquivalent($oldValue, $newValue)) {
                continue;
            }

            $placement = (int) ($proposedGroup['placement'] ?? 0);
            $changes['podium.group.'.$placement.'.'.Str::slug($groupKey)] = [
                'domain' => 'podium',
                'field' => (string) $placement,
                'label' => "Podium Team: {$placement}",
                'old' => $oldValue,
                'new' => $newValue,
                'apply' => [
                    'action' => 'group',
                    'placement' => $placement,
                    'group_key' => $groupKey,
                    'group_id' => $proposedGroup['group_id'] ?? null,
                    'team_name' => $proposedGroup['team_name'] ?? null,
                    'usernames' => $proposedGroup['usernames'] ?? [],
                    'existing_winner_ids' => $existingGroup['winner_ids'] ?? [],
                    'existing_user_ids' => $existingGroup['user_ids'] ?? [],
                ],
            ];
        }

        return $changes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function staffSnapshot(Tournament $tournament): array
    {
        $snapshot = [];

        foreach ($tournament->staff as $user) {
            if (! $user->pivot) {
                continue;
            }

            $role = (string) $user->pivot->role;
            $snapshot[strtolower($role.'|'.$user->username)] = [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $role,
            ];
        }

        return $snapshot;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function podiumSnapshot(Tournament $tournament): array
    {
        return $tournament->winners
            ->filter(fn (TournamentWinner $winner): bool => $winner->placement <= 3)
            ->mapWithKeys(fn (TournamentWinner $winner): array => [
                strtolower($winner->placement.'|'.$winner->display_username) => [
                    'winner_id' => $winner->id,
                    'user_id' => $winner->user_id,
                    'username' => $winner->display_username,
                    'placement' => $winner->placement,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function podiumGroupedSnapshot(Tournament $tournament): array
    {
        $podiumBackfill = app(ParticipationPodiumBackfillService::class);

        return $tournament->winners
            ->filter(fn (TournamentWinner $winner): bool => $winner->placement <= 3)
            ->groupBy(function (TournamentWinner $winner) use ($podiumBackfill): string {
                $groupId = $podiumBackfill->groupIdForWinner($winner);

                return $groupId ?: 'legacy-placement-'.$winner->placement;
            })
            ->map(function (Collection $winners, string $groupKey) use ($tournament, $podiumBackfill): array {
                /** @var TournamentWinner|null $firstWinner */
                $firstWinner = $winners->first();
                $placement = $firstWinner instanceof TournamentWinner ? (int) $firstWinner->placement : 0;
                $groupId = $firstWinner instanceof TournamentWinner ? $podiumBackfill->groupIdForWinner($firstWinner) : null;
                $userIds = $winners
                    ->pluck('user_id')
                    ->filter()
                    ->map(fn ($userId): int => (int) $userId)
                    ->values()
                    ->all();
                $teamName = $userIds === []
                    ? null
                    : TournamentParticipationRecord::query()
                        ->where('tournament_id', $tournament->id)
                        ->whereIn('user_id', $userIds)
                        ->pluck('team_name')
                        ->filter(fn ($teamName): bool => filled($teamName))
                        ->first();

                return [
                    'group_key' => $groupKey,
                    'group_id' => $groupId,
                    'placement' => $placement,
                    'team_name' => $teamName,
                    'usernames' => $winners
                        ->map(fn (TournamentWinner $winner): string => (string) $winner->display_username)
                        ->filter()
                        ->values()
                        ->all(),
                    'winner_ids' => $winners->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                    'user_ids' => $userIds,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, mixed>>
     */
    private function proposedPodiumGroups(array $input): array
    {
        $groups = [];
        $podiumGroups = $input['podium_groups'] ?? [];
        if (! is_array($podiumGroups)) {
            return [];
        }

        foreach ([1, 2, 3] as $placement) {
            $placementGroups = $podiumGroups[$placement] ?? $podiumGroups[(string) $placement] ?? [];
            if (! is_array($placementGroups)) {
                continue;
            }

            foreach (array_values($placementGroups) as $index => $group) {
                if (! is_array($group)) {
                    continue;
                }

                $groupKeyInput = $this->normalizeEmpty($group['group_key'] ?? null);
                $groupId = $this->normalizeEmpty($group['group_id'] ?? null);
                $teamName = $this->normalizeEmpty($group['team_name'] ?? null);
                $usernames = $this->namesFromText((string) ($group['usernames'] ?? ''));
                if ($groupKeyInput === null && $groupId === null && $teamName === null && $usernames === []) {
                    continue;
                }

                $groupKey = is_string($groupKeyInput) && $groupKeyInput !== ''
                    ? $groupKeyInput
                    : (is_string($groupId) && $groupId !== ''
                    ? $groupId
                    : 'new-placement-'.$placement.'-'.$index);
                $groups[$groupKey] = [
                    'group_key' => $groupKey,
                    'group_id' => $groupId,
                    'placement' => $placement,
                    'team_name' => $teamName,
                    'usernames' => $usernames,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function namesFromText(string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', $value) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => strtolower($name))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     * @param  array<string, bool>  $acceptedLookup
     * @return list<array<string, mixed>>
     */
    private function applyStaffChanges(Tournament $tournament, array $changes, array $acceptedLookup, User $admin): array
    {
        $failures = [];
        $syncService = app(TournamentParticipantSyncService::class);

        foreach ($changes as $key => $change) {
            if (! isset($acceptedLookup[$key]) || ($change['domain'] ?? null) !== 'staff') {
                continue;
            }

            $apply = Arr::get($change, 'apply', []);
            $action = (string) ($apply['action'] ?? 'add');
            $role = (string) ($apply['role'] ?? '');

            if ($action === 'remove') {
                TournamentStaff::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('role', $role)
                    ->when($apply['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
                    ->when(! ($apply['user_id'] ?? null), function ($query) use ($apply): void {
                        $query->whereHas('user', fn ($userQuery) => $userQuery->whereRaw('lower(username) = ?', [strtolower((string) ($apply['username'] ?? ''))]));
                    })
                    ->delete();

                continue;
            }

            try {
                $user = $this->findUserByUsername((string) ($apply['username'] ?? ''));
                if ($user && TournamentStaff::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('user_id', $user->id)
                    ->where('role', $role)
                    ->exists()) {
                    continue;
                }

                $syncService->addStaffByUsername(
                    $tournament,
                    (string) ($apply['username'] ?? ''),
                    $role,
                    $admin->id
                );
            } catch (\Throwable $exception) {
                $failures[] = [
                    'key' => $key,
                    'domain' => 'staff',
                    'username' => $apply['username'] ?? null,
                    'message' => $this->safeApplyFailureMessage($exception),
                ];
            }
        }

        return $failures;
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     * @param  array<string, bool>  $acceptedLookup
     * @param  array{user_ids: list<int>, tournament_groups: list<array{tournament_id: int, group_id: string, replace_team_name?: bool, team_name?: string|null}>}  $pendingBackfills
     * @return list<array<string, mixed>>
     */
    private function applyPodiumChanges(Tournament $tournament, array $changes, array $acceptedLookup, array &$pendingBackfills): array
    {
        $failures = [];
        $syncService = app(TournamentParticipantSyncService::class);
        $podiumBackfill = app(ParticipationPodiumBackfillService::class);

        foreach ($changes as $key => $change) {
            if (! isset($acceptedLookup[$key]) || ($change['domain'] ?? null) !== 'podium') {
                continue;
            }

            $apply = Arr::get($change, 'apply', []);
            $action = (string) ($apply['action'] ?? 'add');
            $placement = (int) ($apply['placement'] ?? 0);
            $usernames = $apply['usernames'] ?? null;

            if ($action === 'group_remove') {
                $groupId = is_string($apply['group_id'] ?? null) ? (string) $apply['group_id'] : null;
                $winnerIds = collect($apply['winner_ids'] ?? [])->map(fn ($winnerId): int => (int) $winnerId)->all();
                $userIds = collect($apply['user_ids'] ?? [])->map(fn ($userId): int => (int) $userId)->all();

                TournamentWinner::query()
                    ->where('tournament_id', $tournament->id)
                    ->whereIn('id', $winnerIds)
                    ->delete();

                foreach ($userIds as $userId) {
                    $podiumBackfill->deletePodiumParticipationRecord($tournament->id, $userId, $groupId);
                }

                if ($groupId !== null) {
                    $podiumBackfill->backfillTournamentGroup($tournament->id, $groupId);
                }

                continue;
            }

            if ($action === 'group' && is_array($usernames)) {
                $groupId = is_string($apply['group_id'] ?? null) && $apply['group_id'] !== ''
                    ? (string) $apply['group_id']
                    : $podiumBackfill->createPodiumGroupId();
                $winnerIds = [];
                $desiredUserIds = [];
                $deferGroupBackfill = false;

                foreach ($usernames as $username) {
                    try {
                        $user = $this->findUserByUsername((string) $username);
                        if ($user) {
                            $winner = TournamentWinner::query()
                                ->where('tournament_id', $tournament->id)
                                ->where('user_id', $user->id)
                                ->where('placement', $placement)
                                ->first();
                            if (! $winner) {
                                $winner = $syncService->addPodiumByUsername(
                                    $tournament,
                                    (string) $username,
                                    $placement,
                                    $groupId,
                                    backfill: false
                                );
                                $deferGroupBackfill = true;
                            }
                        } else {
                            $winner = $syncService->addPodiumByUsername($tournament, (string) $username, $placement, $groupId, backfill: false);
                            $deferGroupBackfill = true;
                        }
                    } catch (\Throwable $exception) {
                        $failures[] = [
                            'key' => $key,
                            'domain' => 'podium',
                            'username' => $username,
                            'message' => $this->safeApplyFailureMessage($exception),
                        ];

                        continue;
                    }

                    if ($winner) {
                        $winnerIds[] = $winner->id;
                        if ($winner->user_id !== null) {
                            $desiredUserIds[] = (int) $winner->user_id;
                            $this->queuePodiumUserBackfill($pendingBackfills, (int) $winner->user_id);
                        }
                    }
                }

                $removedUserIds = collect($apply['existing_user_ids'] ?? [])
                    ->map(fn ($userId): int => (int) $userId)
                    ->diff($desiredUserIds)
                    ->values();
                $removedWinnerIds = collect($apply['existing_winner_ids'] ?? [])
                    ->map(fn ($winnerId): int => (int) $winnerId)
                    ->diff($winnerIds)
                    ->values()
                    ->all();

                if ($removedWinnerIds !== []) {
                    TournamentWinner::query()
                        ->where('tournament_id', $tournament->id)
                        ->whereIn('id', $removedWinnerIds)
                        ->delete();
                }

                $removedUserIds->each(fn (int $userId) => $podiumBackfill->deletePodiumParticipationRecord($tournament->id, $userId, $groupId));

                if ($winnerIds !== []) {
                    $assignedGroupId = $podiumBackfill->assignWinnersToGroup(
                        $tournament,
                        $winnerIds,
                        $winnerIds[0] ?? null,
                        filled($apply['team_name'] ?? null) ? (string) $apply['team_name'] : null,
                        backfill: ! $deferGroupBackfill
                    );
                    if ($deferGroupBackfill) {
                        $this->queuePodiumGroupBackfill(
                            $pendingBackfills,
                            $tournament->id,
                            $assignedGroupId,
                            $apply['team_name'] ?? null,
                            replaceTeamName: true
                        );
                    } else {
                        $podiumBackfill->replaceGroupTeamName(
                            $tournament->id,
                            $assignedGroupId,
                            is_string($apply['team_name'] ?? null) ? $apply['team_name'] : null
                        );
                    }
                }

                continue;
            }

            if ($action === 'remove') {
                $winner = TournamentWinner::query()
                    ->where('tournament_id', $tournament->id)
                    ->when($apply['winner_id'] ?? null, fn ($query, $winnerId) => $query->where('id', $winnerId))
                    ->when(! ($apply['winner_id'] ?? null), function ($query) use ($apply, $placement): void {
                        $query->where('placement', $placement)
                            ->where(function ($query) use ($apply): void {
                                $username = strtolower((string) ($apply['username'] ?? ''));
                                $query->whereRaw('lower(username) = ?', [$username])
                                    ->orWhereHas('user', fn ($userQuery) => $userQuery->whereRaw('lower(username) = ?', [$username]));
                            });
                    })
                    ->first();

                if ($winner) {
                    $groupId = $podiumBackfill->groupIdForWinner($winner);
                    $userId = $winner->user_id;
                    $winner->delete();
                    $podiumBackfill->deletePodiumParticipationRecord($tournament->id, $userId, $groupId);
                    if ($groupId !== null) {
                        $podiumBackfill->backfillTournamentGroup($tournament->id, $groupId);
                    }
                }

                continue;
            }

            if ($action === 'team' && is_array($usernames)) {
                $winnerIds = [];
                $deferGroupBackfill = false;
                foreach ($usernames as $username) {
                    try {
                        $user = $this->findUserByUsername((string) $username);
                        if ($user) {
                            $winner = TournamentWinner::query()
                                ->where('tournament_id', $tournament->id)
                                ->where('user_id', $user->id)
                                ->where('placement', $placement)
                                ->first();
                            if (! $winner) {
                                $winner = $syncService->addPodiumByUsername(
                                    $tournament,
                                    (string) $username,
                                    $placement,
                                    backfill: false
                                );
                                $deferGroupBackfill = true;
                            }
                        } else {
                            $winner = $syncService->addPodiumByUsername($tournament, (string) $username, $placement, backfill: false);
                            $deferGroupBackfill = true;
                        }
                    } catch (\Throwable $exception) {
                        $failures[] = [
                            'key' => $key,
                            'domain' => 'podium',
                            'username' => $username,
                            'message' => $this->safeApplyFailureMessage($exception),
                        ];

                        continue;
                    }

                    if ($winner) {
                        $winnerIds[] = $winner->id;
                        if ($winner->user_id !== null) {
                            $this->queuePodiumUserBackfill($pendingBackfills, (int) $winner->user_id);
                        }
                    }
                }

                if ($winnerIds !== []) {
                    $assignedGroupId = $podiumBackfill->assignWinnersToGroup(
                        $tournament,
                        $winnerIds,
                        null,
                        filled($apply['team_name'] ?? null) ? (string) $apply['team_name'] : null,
                        backfill: ! $deferGroupBackfill
                    );
                    if ($deferGroupBackfill) {
                        $this->queuePodiumGroupBackfill(
                            $pendingBackfills,
                            $tournament->id,
                            $assignedGroupId,
                            $apply['team_name'] ?? null,
                            replaceTeamName: true
                        );
                    } else {
                        $podiumBackfill->replaceGroupTeamName(
                            $tournament->id,
                            $assignedGroupId,
                            is_string($apply['team_name'] ?? null) ? $apply['team_name'] : null
                        );
                    }
                }

                continue;
            }

            try {
                $user = $this->findUserByUsername((string) ($apply['username'] ?? ''));
                if ($user) {
                    $winner = TournamentWinner::query()
                        ->where('tournament_id', $tournament->id)
                        ->where('user_id', $user->id)
                        ->where('placement', $placement)
                        ->first();
                    if (! $winner) {
                        $winner = $syncService->addPodiumByUsername(
                            $tournament,
                            (string) ($apply['username'] ?? ''),
                            $placement,
                            backfill: false
                        );
                    }
                    $podiumBackfill->backfillFor($user);
                    $groupId = $podiumBackfill->groupIdForWinner($winner)
                        ?? $podiumBackfill->assignWinnersToGroup($tournament, [$winner->id]);
                    $podiumBackfill->backfillTournamentGroup($tournament->id, $groupId);

                    continue;
                }

                $winner = $syncService->addPodiumByUsername($tournament, (string) ($apply['username'] ?? ''), $placement, backfill: false);
                if ($winner->user_id !== null) {
                    $this->queuePodiumUserBackfill($pendingBackfills, (int) $winner->user_id);
                }
                $groupId = $podiumBackfill->groupIdForWinner($winner);
                if ($groupId !== null) {
                    $this->queuePodiumGroupBackfill($pendingBackfills, $tournament->id, $groupId);
                }
            } catch (\Throwable $exception) {
                $failures[] = [
                    'key' => $key,
                    'domain' => 'podium',
                    'username' => $apply['username'] ?? null,
                    'message' => $this->safeApplyFailureMessage($exception),
                ];
            }
        }

        return $failures;
    }

    /**
     * @param  array{user_ids: list<int>, tournament_groups: list<array{tournament_id: int, group_id: string, replace_team_name?: bool, team_name?: string|null}>}  $pendingBackfills
     */
    private function queuePodiumUserBackfill(array &$pendingBackfills, int $userId): void
    {
        $pendingBackfills['user_ids'][] = $userId;
    }

    /**
     * @param  array{user_ids: list<int>, tournament_groups: list<array{tournament_id: int, group_id: string, replace_team_name?: bool, team_name?: string|null}>}  $pendingBackfills
     */
    private function queuePodiumGroupBackfill(
        array &$pendingBackfills,
        int $tournamentId,
        string $groupId,
        ?string $teamName = null,
        bool $replaceTeamName = false
    ): void {
        if ($groupId === '') {
            return;
        }

        $pendingBackfills['tournament_groups'][] = [
            'tournament_id' => $tournamentId,
            'group_id' => $groupId,
            'replace_team_name' => $replaceTeamName,
            'team_name' => $teamName,
        ];
    }

    /**
     * @param  array{user_ids: list<int>, tournament_groups: list<array{tournament_id: int, group_id: string, replace_team_name?: bool, team_name?: string|null}>}  $pendingBackfills
     */
    private function dispatchPendingPodiumBackfills(array $pendingBackfills): void
    {
        $userIds = collect($pendingBackfills['user_ids'] ?? [])
            ->map(fn ($userId): int => (int) $userId)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $tournamentGroups = collect($pendingBackfills['tournament_groups'] ?? [])
            ->filter(fn (array $group): bool => filled($group['tournament_id'])
                && filled($group['group_id']))
            ->unique(fn (array $group): string => ((int) $group['tournament_id']).':'.((string) $group['group_id']))
            ->values()
            ->all();

        if ($userIds === [] && $tournamentGroups === []) {
            return;
        }

        BackfillPodiumParticipationJob::dispatch($userIds, $tournamentGroups);
    }

    private function findUserByUsername(string $username): ?User
    {
        return app(ExactUsernameResolver::class)->resolve($username);
    }

    private function safeApplyFailureMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if ($exception instanceof \RuntimeException
            && (str_contains($message, 'not found on osu!')
                || str_contains($message, 'already has role')
                || str_contains($message, 'already exists in placement'))) {
            return $message;
        }

        Log::warning('Tournament correction member change failed', [
            'exception' => $exception,
        ]);

        return 'Unable to apply this member. Please ask an administrator to check the server log.';
    }

    private function label(string $field): string
    {
        return Str::of($field)->replace('_', ' ')->headline()->toString();
    }
}
