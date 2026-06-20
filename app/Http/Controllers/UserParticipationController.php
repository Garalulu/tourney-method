<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParticipationRecordRequest;
use App\Models\ParticipationDeletionRequest;
use App\Models\ParticipationRecordReport;
use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Services\BwsCalculator;
use App\Services\InAppNotificationService;
use App\Services\OsuLobbyFinderService;
use App\Services\ParticipationInputAuditService;
use App\Services\ParticipationPlacementService;
use App\Services\ParticipationPodiumBackfillService;
use App\Services\ParticipationRecordQueryService;
use App\Services\ParticipationStageOptionsService;
use App\Services\ParticipationStatsService;
use App\Services\TournamentParticipantSyncService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserParticipationController extends Controller
{
    private const RECORDS_PER_PAGE = 10;

    public function __construct(
        private ParticipationStageOptionsService $stageOptions,
        private ParticipationPlacementService $placements,
        private ParticipationInputAuditService $audit,
        private ParticipationStatsService $stats,
        private ParticipationRecordQueryService $recordQueries,
        private InAppNotificationService $notifications
    ) {}

    public function searchTournaments(Request $request, User $user): JsonResponse
    {
        $this->authorizeParticipationManager($user);

        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 3) {
            return response()->json(['tournaments' => []]);
        }

        /** @var Collection<int, Tournament> $tournamentModels */
        $tournamentModels = Tournament::query()
            ->approved()
            ->where('title', 'ilike', "%{$query}%")
            ->orderByRaw('tournament_end DESC NULLS LAST')
            ->limit(8)
            ->get();

        $existingRecords = $user->tournamentParticipationRecords()
            ->with(['tournament', 'teammates.rankHistory'])
            ->whereIn('tournament_id', $tournamentModels->pluck('id'))
            ->get()
            ->keyBy('tournament_id');

        $tournaments = $tournamentModels->map(function (Tournament $tournament) use ($existingRecords, $user): array {
            $payload = $this->tournamentPayload($tournament, $user);
            $existingRecord = $existingRecords->get($tournament->id);

            return array_merge($payload, [
                'existing_record' => $existingRecord instanceof TournamentParticipationRecord
                    ? $this->dialogRecordPayload($existingRecord)
                    : null,
            ]);
        });

        return response()->json(['tournaments' => $tournaments]);
    }

    public function searchUsers(Request $request, User $user): JsonResponse
    {
        $this->authorizeParticipationManager($user);

        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['users' => []]);
        }

        $tournament = $request->integer('tournament_id')
            ? Tournament::query()->approved()->find($request->integer('tournament_id'))
            : null;
        $mode = $tournament instanceof Tournament ? $this->firstTournamentMode($tournament) : null;
        $bwsCalculator = app(BwsCalculator::class);

        /** @var Collection<int, User> $userModels */
        $userModels = User::query()
            ->with('rankHistory')
            ->select(['id', 'osu_id', 'username', 'country_code', 'main_mode'])
            ->where('id', '!=', $user->id)
            ->whereNull('deleted_at')
            ->where(function ($userQuery) use ($query): void {
                $userQuery->where('username', 'ilike', "%{$query}%")
                    ->orWhereRaw('previous_usernames::text ilike ?', ["%{$query}%"]);
            })
            ->orderBy('username')
            ->limit(8)
            ->get();

        $bwsRanks = [];

        if ($tournament instanceof Tournament && $mode !== null) {
            $bwsRanks = $userModels
                ->mapWithKeys(fn (User $match): array => [$match->id => $bwsCalculator->calculateForUser($match, $tournament, $mode)])
                ->all();
            $userModels = $userModels
                ->sortBy(fn (User $match): float => $bwsRanks[$match->id] ?? PHP_INT_MAX)
                ->values();
        }

        $users = $userModels->map(function (User $match) use ($bwsRanks): array {
            return [
                'id' => $match->id,
                'osu_id' => $match->osu_id,
                'username' => $match->username,
                'avatar_url' => $match->avatar_url,
                'country_code' => $match->country_code,
                'main_mode' => $match->main_mode,
                'profile_url' => route('users.show', $match),
                'bws_rank' => $bwsRanks[$match->id] ?? null,
            ];
        });

        return response()->json(['users' => $users]);
    }

    public function searchLobbies(Request $request, User $user, OsuLobbyFinderService $lobbies): JsonResponse
    {
        $this->authorizeParticipationManager($user);

        $query = trim((string) $request->query('q', ''));
        $offset = max(0, $request->integer('offset', 0));
        $tournament = $request->integer('tournament_id') > 0
            ? Tournament::query()->approved()->find($request->integer('tournament_id'))
            : null;
        $start = $tournament instanceof Tournament
            ? ($tournament->tournament_start?->copy()->subMonth() ?? $tournament->registration_start)
            : null;
        $end = $tournament instanceof Tournament && $tournament->tournament_end !== null
            ? $tournament->tournament_end->copy()->addMonth()
            : null;

        return response()->json($lobbies->search($query, $offset, 10, $start, $end));
    }

    public function records(Request $request, User $user): JsonResponse
    {
        $viewerCanSeeHiddenParticipation = $this->viewerCanSeeHiddenParticipation($user);
        $offset = max(0, $request->integer('offset', 0));
        $records = $this->recordQueries->pageFor($user, $viewerCanSeeHiddenParticipation, $request, self::RECORDS_PER_PAGE, $offset);
        $nextOffset = $offset + $records->count();
        $hasMore = $this->recordQueries->hasMoreFor($user, $viewerCanSeeHiddenParticipation, $request, $nextOffset);

        return response()->json([
            'html' => $this->renderParticipationRecordItems($user, $records),
            'next_offset' => $hasMore ? $nextOffset : null,
            'has_more' => $hasMore,
        ]);
    }

    public function store(ParticipationRecordRequest $request, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeParticipationManager($user);
        if ($redirect = $this->redirectIfParticipationLocked($user)) {
            return $redirect;
        }

        $validated = $this->validatedData($request, $user);
        $tournament = Tournament::query()->approved()->findOrFail($validated['tournament_id']);
        $this->validateStagePlacement($tournament, $validated);

        if (TournamentParticipationRecord::query()
            ->where('user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->exists()) {
            return $this->participationResponse($request, $user, 'error', __('users.participation.flash.duplicate_record'), 409);
        }

        $record = DB::transaction(function () use ($validated, $user, $tournament) {
            $record = TournamentParticipationRecord::query()->create(array_merge(
                [
                    'user_id' => $user->id,
                    'tournament_id' => $tournament->id,
                ],
                $this->recordPayload($validated, $user, $tournament)
            ));
            $this->syncRecordMatchesFromInput($record, $validated, $user);

            $teammateChanges = $this->syncTeammates($record, $validated);
            $this->syncSharedTeammateRecords($record, pruneStaleSharedRecords: true);
            $snapshot = $this->userInputSnapshot($record);
            $teammates = $snapshot['teammates'] ?? [];
            unset($snapshot['teammates']);

            $meaningfulFields = $this->meaningfulInputFields($snapshot);
            if ($meaningfulFields !== []) {
                $this->audit->log($record, $user, $this->actor(), 'saved', $meaningfulFields);
            }
            if ($teammates !== []) {
                $this->audit->log($record, $user, $this->actor(), 'teammates_saved', [
                    'changes' => [
                        'teammates' => [
                            'old' => [],
                            'new' => $teammates,
                        ],
                    ],
                ]);
            }
            if ($actor = $this->actor()) {
                $this->notifications->notifyTeammatesAdded($record, $actor, $teammateChanges['added']);
            }

            return $record;
        });

        return $this->participationResponse($request, $user, 'success', __('users.participation.flash.saved'));
    }

    public function update(ParticipationRecordRequest $request, User $user, TournamentParticipationRecord $record): RedirectResponse|JsonResponse
    {
        $this->authorizeParticipationManager($user);
        abort_unless($record->user_id === $user->id, 404);
        if ($redirect = $this->redirectIfParticipationLocked($user)) {
            return $redirect;
        }

        $before = $this->userInputSnapshot($record);
        $validated = $this->validatedData($request, $user, $record);
        $tournament = Tournament::query()->approved()->findOrFail($record->tournament_id);
        $this->validateStagePlacement($tournament, $validated);

        DB::transaction(function () use ($before, $validated, $user, $tournament, $record): void {
            $record->update($this->recordPayload($validated, $user, $tournament, $record));
            $this->syncRecordMatchesFromInput($record, $validated, $user);
            $teammateChanges = ['added' => [], 'removed' => []];
            $hasTeammateInput = $this->hasTeammateInput($validated);
            if ((! $this->isPodiumBacked($record) || $this->actorCanModerateParticipation()) && $hasTeammateInput) {
                $teammateChanges = $this->syncTeammates($record, $validated);
            }
            if ($this->isPodiumBacked($record) && $this->actorCanModerateParticipation()) {
                app(ParticipationPodiumBackfillService::class)->syncRosterFromRecord($record->fresh());
            } else {
                if ($this->isPodiumBacked($record)) {
                    app(ParticipationPodiumBackfillService::class)->normalizeGroupFromRecord($record->fresh());
                    $record->refresh();
                }

                $this->syncSharedTeammateRecords($record, pruneStaleSharedRecords: $hasTeammateInput);
            }
            $afterRecord = $record->fresh(['tournament', 'teammates', 'participationMatches']) ?? $record;
            $changes = $this->changedInputFields($before, $this->userInputSnapshot($afterRecord));
            $teammateChange = $changes['teammates'] ?? null;
            unset($changes['teammates']);

            if ($changes !== []) {
                $this->audit->log($afterRecord, $user, $this->actor(), 'updated', [
                    'changes' => $changes,
                ]);
            }
            if (is_array($teammateChange)) {
                $this->audit->log($afterRecord, $user, $this->actor(), 'teammates_updated', [
                    'changes' => [
                        'teammates' => $teammateChange,
                    ],
                ]);
            }
            if ($actor = $this->actor()) {
                $this->notifications->notifyTeammatesAdded($afterRecord, $actor, $teammateChanges['added']);
                $this->notifications->notifyTeammatesRemoved($afterRecord, $actor, $teammateChanges['removed']);
                if ($changes !== []) {
                    $this->notifications->notifyParticipationUpdated($afterRecord, $actor, $changes);
                }
            }
        });

        return $this->participationResponse($request, $user, 'success', __('users.participation.flash.saved'));
    }

    public function destroy(Request $request, User $user, TournamentParticipationRecord $record): RedirectResponse|JsonResponse
    {
        $this->authorizeParticipationManager($user);
        abort_unless($record->user_id === $user->id, 404);
        if ($redirect = $this->redirectIfParticipationLocked($user)) {
            return $redirect;
        }
        $record->loadMissing('teammates');

        if (! $this->actorCanModerateParticipation() && ($record->isPodiumBacked() || $record->isAdminApprovedPodium())) {
            return $this->participationResponse($request, $user, 'error', __('users.participation.flash.delete_blocked_podium'), 422);
        }

        if (! $this->actorCanModerateParticipation() && ! $record->canUserDeleteDirectly()) {
            return $this->participationResponse($request, $user, 'error', __('users.participation.flash.delete_requires_request'), 422);
        }

        DB::transaction(function () use ($record, $user): void {
            $snapshot = $this->userInputSnapshot($record);
            $this->audit->log($record, $user, $this->actor(), 'deleted', $snapshot);
            $this->deleteLinkedPodiumRows($record);
            $record->delete();
        });

        return $this->participationResponse($request, $user, 'success', __('users.participation.flash.deleted'));
    }

    public function requestDeletion(Request $request, User $user, TournamentParticipationRecord $record): RedirectResponse|JsonResponse
    {
        $this->authorizeParticipationManager($user);
        abort_unless($record->user_id === $user->id, 404);
        if ($redirect = $this->redirectIfParticipationLocked($user)) {
            return $redirect;
        }
        $record->loadMissing('teammates');

        if ($this->actorCanModerateParticipation()) {
            return $this->destroy($request, $user, $record);
        }

        if ($record->isPodiumBacked() || $record->isAdminApprovedPodium()) {
            return $this->participationResponse($request, $user, 'error', __('users.participation.flash.delete_blocked_podium'), 422);
        }

        if ($record->teammates->isEmpty()) {
            return $this->destroy($request, $user, $record);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $deletionRequest = DB::transaction(function () use ($record, $user, $validated): ParticipationDeletionRequest {
            $record->update([
                'profile_hidden_at' => null,
                'profile_hidden_by' => null,
            ]);

            return ParticipationDeletionRequest::query()->firstOrCreate(
                [
                    'tournament_participation_record_id' => $record->id,
                    'requested_by' => $user->id,
                    'status' => ParticipationDeletionRequest::STATUS_PENDING,
                ],
                [
                    'reason' => $this->audit->sanitizeText($validated['reason'] ?? null, 500),
                ]
            );
        });

        $this->audit->log($record, $user, $this->actor(), 'deletion_requested', [
            'deletion_request_id' => $deletionRequest->id,
            'reason' => $deletionRequest->reason,
        ]);

        return $this->participationResponse($request, $user, 'success', __('users.participation.flash.deletion_requested'));
    }

    public function cancelDeletionRequest(Request $request, User $user, TournamentParticipationRecord $record): RedirectResponse|JsonResponse
    {
        $this->authorizeParticipationManager($user);
        abort_unless($record->user_id === $user->id, 404);

        $pendingRequest = ParticipationDeletionRequest::query()
            ->where('tournament_participation_record_id', $record->id)
            ->where('requested_by', $user->id)
            ->where('status', ParticipationDeletionRequest::STATUS_PENDING)
            ->first();

        if (! $pendingRequest) {
            return $this->participationResponse($request, $user, 'error', __('users.participation.flash.deletion_cancel_missing'), 404);
        }

        $pendingRequest->delete();

        $this->audit->log($record, $user, $this->actor(), 'deletion_request_cancelled', [
            'deletion_request_id' => $pendingRequest->id,
        ]);

        return $this->participationResponse($request, $user, 'success', __('users.participation.flash.deletion_cancelled'));
    }

    public function report(Request $request, User $user, TournamentParticipationRecord $record): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::check(), 403);
        abort_unless($record->user_id === $user->id, 404);
        abort_unless(Auth::id() !== $user->id, 403);

        if (! $this->actorCanModerateParticipation() && Auth::user()?->hasParticipationInputLock()) {
            return $this->participationResponse($request, $user, 'error', __('users.participation.flash.input_locked'), 423);
        }

        $validated = $request->validate([
            'category' => [
                'required',
                Rule::in([
                    ParticipationRecordReport::CATEGORY_RECORD_CORRECTION,
                    ParticipationRecordReport::CATEGORY_REPORT_SPAM,
                    ParticipationRecordReport::CATEGORY_INAPPROPRIATE_MEMO,
                ]),
            ],
            'explanation' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = ParticipationRecordReport::query()->firstOrCreate(
            [
                'tournament_participation_record_id' => $record->id,
                'reported_by' => Auth::id(),
                'category' => $validated['category'],
                'status' => ParticipationRecordReport::STATUS_PENDING,
            ],
            [
                'explanation' => $this->audit->sanitizeText($validated['explanation'] ?? null, 1000),
            ]
        );

        if (! $report->wasRecentlyCreated && ($validated['explanation'] ?? null)) {
            $report->update([
                'explanation' => $this->audit->sanitizeText($validated['explanation'], 1000),
            ]);
        }

        return $this->participationResponse($request, $user, 'success', __('users.participation.flash.report_sent'));
    }

    public function toggleVisibility(Request $request, User $user, TournamentParticipationRecord $record): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::id() === $user->id, 403);
        abort_unless($record->user_id === $user->id, 404);

        if ($record->isPodiumBacked() || $record->isAdminApprovedPodium()) {
            return $this->participationResponse($request, $user, 'error', __('users.participation.flash.hide_blocked_podium'), 422);
        }

        $hidden = $record->profile_hidden_at === null;

        $record->update([
            'profile_hidden_at' => $hidden ? now() : null,
            'profile_hidden_by' => $hidden ? $user->id : null,
        ]);

        $this->audit->log($record, $user, $this->actor(), $hidden ? 'hidden' : 'shown', [
            'profile_hidden_at' => $record->profile_hidden_at?->toDateTimeString(),
        ]);

        return $this->participationResponse($request, $user, 'success', __($hidden ? 'users.participation.flash.hidden' : 'users.participation.flash.visible'));
    }

    private function participationResponse(Request $request, User $user, string $type, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($this->wantsParticipationJson($request)) {
            $viewData = $this->participationViewData($user, $request);
            $viewData['includeParticipationShell'] = false;

            return response()->json([
                'type' => $type,
                'message' => $message,
                'html' => view('users.partials.participation', $viewData)->render(),
                'next_offset' => $viewData['recordsNextOffset'],
                'has_more' => $viewData['recordsHasMore'],
            ], $status);
        }

        return redirect()
            ->route('users.show', ['user' => $user, 'tab' => 'participation'])
            ->with($type, $message);
    }

    private function wantsParticipationJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function participationViewData(User $user, ?Request $request = null): array
    {
        $user->loadMissing('participationInputLock');

        $displayLimit = max(self::RECORDS_PER_PAGE, $request?->integer('refresh_limit', self::RECORDS_PER_PAGE) ?? self::RECORDS_PER_PAGE);
        $viewerCanSeeHiddenParticipation = $this->viewerCanSeeHiddenParticipation($user);
        $participationRequest = $request instanceof Request ? $request : null;
        $displayRecords = $this->recordQueries->pageFor($user, $viewerCanSeeHiddenParticipation, $participationRequest, $displayLimit);
        $participationRecordCount = $this->recordQueries->countFor($user, $viewerCanSeeHiddenParticipation, $participationRequest);
        $participationIndices = $this->recordQueries->globalIndicesFor($displayRecords, $user, $viewerCanSeeHiddenParticipation);
        $displayTeammatesByRecord = $this->recordQueries->displayTeammatesForRecords($displayRecords);
        $displayTeammateBwsRanksByRecord = $this->recordQueries
            ->displayTeammateBwsRanksForRecords($displayRecords, $displayTeammatesByRecord);

        $participationStageOptions = $displayRecords
            ->mapWithKeys(fn (TournamentParticipationRecord $record) => [$record->tournament_id => $this->stageOptions->optionsFor($record->tournament)->all()]);

        $includeTournamentOptions = Auth::id() === $user->id
            && ! ($request?->expectsJson() ?? false);

        /** @var Collection<int, Tournament> $participationTournamentOptions */
        $participationTournamentOptions = $includeTournamentOptions
            ? Tournament::query()
                ->approved()
                ->orderByRaw('tournament_end DESC NULLS LAST')
                ->limit(100)
                ->get()
            : new Collection;

        $participationTournamentDetails = $participationTournamentOptions
            ->merge($displayRecords->pluck('tournament'))
            ->unique('id')
            ->mapWithKeys(fn (Tournament $tournament) => [$tournament->id => $this->tournamentPayload($tournament, $user)]);

        return [
            'user' => $user,
            'records' => $displayRecords,
            'stats' => $this->stats->summarizeForUser($user, $viewerCanSeeHiddenParticipation),
            'availableYears' => $this->recordQueries->availableYearsFor($user, $viewerCanSeeHiddenParticipation),
            'availableModes' => $this->recordQueries->availableModesFor($user, $viewerCanSeeHiddenParticipation),
            'stageOptions' => $participationStageOptions,
            'participationIndices' => $participationIndices,
            'displayTeammatesByRecord' => $displayTeammatesByRecord,
            'displayTeammateBwsRanksByRecord' => $displayTeammateBwsRanksByRecord,
            'tournamentOptions' => $participationTournamentOptions,
            'createStageOptions' => $participationTournamentOptions
                ->mapWithKeys(fn (Tournament $tournament) => [$tournament->id => $this->stageOptions->optionsFor($tournament)->all()]),
            'tournamentDetails' => $participationTournamentDetails,
            'isOwnProfile' => Auth::id() === $user->id,
            'canManageParticipation' => Auth::id() === $user->id || $this->actorCanModerateParticipation(),
            'recordsUrl' => route('users.participation.records', $user),
            'recordsNextOffset' => $participationRecordCount > $displayLimit ? $displayLimit : null,
            'recordsHasMore' => $participationRecordCount > $displayLimit,
        ];
    }

    private function authorizeParticipationManager(User $user): void
    {
        abort_unless(Auth::id() === $user->id || $this->actorCanModerateParticipation(), 403);
    }

    private function actorCanModerateParticipation(): bool
    {
        return $this->actor()?->isAdmin() ?? false;
    }

    private function actor(): ?User
    {
        $actor = Auth::user();

        return $actor instanceof User ? $actor : null;
    }

    private function redirectIfParticipationLocked(User $user): ?RedirectResponse
    {
        if ($this->actorCanModerateParticipation() || ! $user->hasParticipationInputLock()) {
            return null;
        }

        return redirect()
            ->route('users.show', ['user' => $user, 'tab' => 'participation'])
            ->with('error', __('users.participation.flash.input_locked'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(ParticipationRecordRequest $request, User $user, ?TournamentParticipationRecord $record = null): array
    {
        $validated = $request->validated();
        $validated['tournament_id'] = $record instanceof TournamentParticipationRecord
            ? $record->tournament_id
            : (int) $validated['tournament_id'];

        if ($record instanceof TournamentParticipationRecord && $this->isPodiumBacked($record)) {
            unset(
                $validated['stage_value'],
                $validated['placement_override'],
                $validated['placement_range_override']
            );

            if (! $this->actorCanModerateParticipation()) {
                unset(
                    $validated['teammate_ids'],
                    $validated['pending_teammate_osu_ids']
                );
            }
        }

        if ($this->isParticipationTextLocked($user)) {
            unset($validated['team_name'], $validated['memo']);
        }

        return $validated;
    }

    private function isParticipationTextLocked(User $user): bool
    {
        return ! $this->actorCanModerateParticipation() && $user->hasParticipationInputLock();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function recordPayload(array $validated, User $user, Tournament $tournament, ?TournamentParticipationRecord $existingRecord = null): array
    {
        $stageValue = (string) ($validated['stage_value'] ?? '');
        $stageOption = $this->stageOptions->optionsFor($tournament)->firstWhere('value', $stageValue);
        $rangeOverride = $this->placementRangeOverride($validated, $stageOption);
        $override = $rangeOverride === null && ($stageOption['editable_placement'] ?? true) && isset($validated['placement_override'])
            ? (int) $validated['placement_override']
            : null;
        $placement = $rangeOverride ?? $this->placements->calculate($tournament, $stageValue, $override);
        $isTextLocked = $this->isParticipationTextLocked($user);
        $isPodiumBacked = $existingRecord instanceof TournamentParticipationRecord && $this->isPodiumBacked($existingRecord);
        $reviewStatus = (! $this->actorCanModerateParticipation() && $this->needsPodiumApproval($user, $tournament, $placement))
            ? TournamentParticipationRecord::REVIEW_PENDING
            : TournamentParticipationRecord::REVIEW_APPROVED;
        $seed = array_key_exists('seed', $validated)
            ? $validated['seed']
            : $existingRecord?->seed;

        $payload = [
            'source' => $existingRecord instanceof TournamentParticipationRecord ? $existingRecord->source : TournamentParticipationRecord::SOURCE_MANUAL,
            'review_status' => $isPodiumBacked ? $existingRecord->review_status : $reviewStatus,
            'selection_outcome' => match ($stageValue) {
                'dnq' => 'dnq',
                'dnp' => 'dnp',
                'tryout' => TournamentParticipationRecord::SELECTION_TRYOUT_FAILED,
                default => TournamentParticipationRecord::SELECTION_REGISTERED,
            },
            'final_result' => $placement['placement'] === 1
                ? TournamentParticipationRecord::RESULT_WINNER
                : TournamentParticipationRecord::RESULT_COMPLETED,
            'stage_type' => $stageOption['stage_type'] ?? null,
            'stage_name' => $stageOption['stage_name'] ?? null,
            'round_label' => $stageOption['round_label'] ?? null,
            'bracket_path' => $stageOption['bracket_path'] ?? null,
            'placement' => $placement['placement'],
            'placement_min' => $placement['placement_min'],
            'placement_max' => $placement['placement_max'],
            'placement_override' => $override,
            'seed' => $seed,
            'team_name' => $isTextLocked ? $existingRecord?->team_name : $this->audit->sanitizeText($validated['team_name'] ?? null, 150),
            'memo' => $isTextLocked ? $existingRecord?->memo : $this->audit->sanitizeText($validated['memo'] ?? null, 1000),
            'pending_teammate_osu_ids' => $this->pendingTeammateOsuIds($validated),
            'metadata' => [
                'stage_value' => $stageValue,
                'placement_editable' => $placement['editable'],
                'shared_from_record_id' => $existingRecord?->metadata['shared_from_record_id'] ?? null,
            ],
        ];

        if ($isPodiumBacked) {
            foreach ([
                'source',
                'review_status',
                'selection_outcome',
                'final_result',
                'stage_type',
                'stage_name',
                'round_label',
                'bracket_path',
                'placement',
                'placement_min',
                'placement_max',
                'placement_override',
                'pending_teammate_osu_ids',
                'metadata',
            ] as $field) {
                $payload[$field] = $existingRecord->{$field};
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncRecordMatchesFromInput(TournamentParticipationRecord $record, array $validated, User $user): void
    {
        $submittedBy = Auth::id() ?? $user->id;

        if (array_key_exists('matches', $validated)) {
            $record->syncParticipationMatches(
                $this->normalizedMatches($validated['matches']),
                $submittedBy
            );

            return;
        }

        if ($record->participationMatches()->exists()) {
            return;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function validateStagePlacement(Tournament $tournament, array $validated): void
    {
        $stageValue = (string) ($validated['stage_value'] ?? '');
        if ($stageValue === '') {
            return;
        }

        $stageOption = $this->stageOptions->optionsFor($tournament)->firstWhere('value', $stageValue);
        if (! $stageOption) {
            return;
        }

        $placement = isset($validated['placement_override']) ? (int) $validated['placement_override'] : null;
        $allowedPlacements = collect($stageOption['allowed_placements'] ?? [])
            ->map(fn ($placement): int => (int) $placement)
            ->filter(fn (int $placement): bool => $placement > 0)
            ->values()
            ->all();

        if ($allowedPlacements !== [] && ($placement === null || ! in_array($placement, $allowedPlacements, true))) {
            throw ValidationException::withMessages([
                'placement_override' => 'The selected stage requires placement '.implode(' or ', $allowedPlacements).'.',
            ]);
        }

        $placementMin = (int) ($stageOption['placement_min'] ?? 0);
        $placementMax = (int) ($stageOption['placement_max'] ?? 0);

        if (($stageOption['range_placement'] ?? false)) {
            $this->validatePlacementRangeOverride($validated, $stageOption);

            return;
        }

        if (($stageOption['editable_placement'] ?? false) && $placementMin > 0 && $placementMax > 0) {
            $min = min($placementMin, $placementMax);
            $max = max($placementMin, $placementMax);

            if ($placement === null || $placement < $min || $placement > $max) {
                throw ValidationException::withMessages([
                    'placement_override' => "The selected stage requires placement between {$min} and {$max}.",
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>|null  $stageOption
     * @return array{placement: int|null, placement_min: int|null, placement_max: int|null, editable: bool}|null
     */
    private function placementRangeOverride(array $validated, ?array $stageOption): ?array
    {
        if (! ($stageOption['range_placement'] ?? false)) {
            return null;
        }

        $range = $this->parsePlacementRange($validated['placement_range_override'] ?? null);
        if ($range === null) {
            return null;
        }

        return [
            'placement' => $range['min'],
            'placement_min' => $range['min'],
            'placement_max' => $range['max'],
            'editable' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $stageOption
     *
     * @throws ValidationException
     */
    private function validatePlacementRangeOverride(array $validated, array $stageOption): void
    {
        $range = $this->parsePlacementRange($validated['placement_range_override'] ?? null);

        if ($range === null) {
            throw ValidationException::withMessages([
                'placement_range_override' => 'The selected Swiss stage requires a placement range like 17-32.',
            ]);
        }

        $minimum = (int) ($stageOption['placement_min'] ?? 0);
        if ($minimum > 0 && $range['min'] < $minimum) {
            throw ValidationException::withMessages([
                'placement_range_override' => "The selected Swiss stage requires a placement range starting at {$minimum} or later.",
            ]);
        }
    }

    /**
     * @return array{min: int, max: int}|null
     */
    private function parsePlacementRange(mixed $value): ?array
    {
        $range = is_string($value) ? trim($value) : '';
        if ($range === '' || ! preg_match('/^(\d+)\s*-\s*(\d+)$/', $range, $matches)) {
            return null;
        }

        $min = (int) $matches[1];
        $max = (int) $matches[2];

        if ($min < 1 || $max < 1 || $min > $max) {
            return null;
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * @param  array<int, mixed>  $matches
     * @return array<int, array<string, mixed>>
     */
    private function normalizedMatches(array $matches): array
    {
        return collect($matches)
            ->filter(fn ($match): bool => is_array($match))
            ->map(function (array $match): array {
                $stage = $this->canonicalMatchStage($this->audit->sanitizeText($match['stage'] ?? null, 80));
                $isNonVersus = $this->isNonVersusMatchStage($stage);
                $scoreFor = ! $isNonVersus && array_key_exists('score_for', $match) && $match['score_for'] !== null && $match['score_for'] !== ''
                    ? (int) $match['score_for']
                    : null;
                $scoreAgainst = ! $isNonVersus && array_key_exists('score_against', $match) && $match['score_against'] !== null && $match['score_against'] !== ''
                    ? (int) $match['score_against']
                    : null;

                return [
                    'stage' => $stage,
                    'result' => $isNonVersus
                        ? null
                        : ($scoreFor !== null && $scoreAgainst !== null
                        ? "{$scoreFor}-{$scoreAgainst}"
                        : $this->audit->sanitizeText($match['result'] ?? null, 40)),
                    'score_for' => $scoreFor,
                    'score_against' => $scoreAgainst,
                    'mp_link' => $this->normalizeMpLink($match['mp_link'] ?? null, $match['mp_id'] ?? null),
                    'mp_id' => $this->normalizedMpId($match['mp_id'] ?? null, $match['mp_link'] ?? null),
                    'is_forfeit' => ! $isNonVersus && ($scoreFor === -1 || $scoreAgainst === -1),
                    'is_individual_qualifier' => $stage === 'Qualifier'
                        && (bool) ($match['is_individual_qualifier'] ?? false),
                ];
            })
            ->filter(fn (array $match): bool => array_filter($match, fn ($value): bool => $value !== null && $value !== false) !== [])
            ->values()
            ->all();
    }

    private function isNonVersusMatchStage(?string $stage): bool
    {
        return in_array($stage, ['QL', 'Qualifier', 'Battle Royale', 'Tryout'], true);
    }

    private function normalizeMpLink(mixed $mpLink, mixed $mpId): ?string
    {
        $mpLink = is_string($mpLink) ? trim($mpLink) : '';

        if ($mpLink !== '') {
            if (ctype_digit($mpLink)) {
                return "https://osu.ppy.sh/community/matches/{$mpLink}";
            }

            if (preg_match('~^https?://osu\.ppy\.sh/(?:community/matches|mp)/(\d+)$~', $mpLink, $matches)) {
                return "https://osu.ppy.sh/community/matches/{$matches[1]}";
            }

            return $mpLink;
        }

        if ($mpId !== null && $mpId !== '') {
            return 'https://osu.ppy.sh/community/matches/'.(int) $mpId;
        }

        return null;
    }

    private function normalizedMpId(mixed $mpId, mixed $mpLink): ?int
    {
        if ($mpId !== null && $mpId !== '' && is_numeric($mpId)) {
            return (int) $mpId;
        }

        $mpLink = is_string($mpLink) ? trim($mpLink) : '';
        if (ctype_digit($mpLink)) {
            return (int) $mpLink;
        }

        if (preg_match('~^https?://osu\.ppy\.sh/(?:community/matches|mp)/(\d+)$~', $mpLink, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int>
     */
    private function pendingTeammateOsuIds(array $validated): array
    {
        $pendingOsuIds = array_values(array_unique(array_filter(
            array_map('intval', $validated['pending_teammate_osu_ids'] ?? []),
            fn (int $osuId): bool => $osuId > 0
        )));

        if ($pendingOsuIds === []) {
            return [];
        }

        $existingOsuIds = User::query()
            ->whereIn('osu_id', $pendingOsuIds)
            ->pluck('osu_id')
            ->map(fn ($osuId): int => (int) $osuId)
            ->all();

        return array_values(array_diff($pendingOsuIds, $existingOsuIds));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{added: list<int>, removed: list<int>}
     */
    private function syncTeammates(TournamentParticipationRecord $record, array $validated): array
    {
        $record->loadMissing(['tournament', 'teammates']);
        $beforeIds = $record->teammates->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $teammateIds = array_values(array_unique(array_map('intval', $validated['teammate_ids'] ?? [])));
        $pendingOsuIds = array_values(array_unique(array_filter(
            array_map('intval', $validated['pending_teammate_osu_ids'] ?? []),
            fn (int $osuId): bool => $osuId > 0
        )));

        $existingUsers = User::query()
            ->whereIn('osu_id', $pendingOsuIds)
            ->get();
        $teammateIds = array_merge($teammateIds, $existingUsers->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $existingOsuIds = $existingUsers->pluck('osu_id')->map(fn ($osuId): int => (int) $osuId)->all();

        $shouldUsePodiumResolver = $this->actorCanModerateParticipation() && $this->isPodiumBacked($record);
        $pendingOsuIdsToResolve = $shouldUsePodiumResolver
            ? $pendingOsuIds
            : array_diff($pendingOsuIds, $existingOsuIds);
        $failedOsuIds = [];

        foreach ($pendingOsuIdsToResolve as $osuId) {
            if ($shouldUsePodiumResolver) {
                $user = app(TournamentParticipantSyncService::class)->resolvePodiumUserByOsuId($record->tournament, $osuId);
            } else {
                try {
                    $user = app(TournamentParticipantSyncService::class)->resolvePodiumUserByOsuId($record->tournament, $osuId);
                } catch (\Throwable $exception) {
                    Log::warning('Unable to resolve manual participation teammate osu ID', [
                        'record_id' => $record->id,
                        'tournament_id' => $record->tournament_id,
                        'osu_id' => $osuId,
                        'error' => $exception->getMessage(),
                    ]);
                    $failedOsuIds[] = $osuId;

                    continue;
                }
            }

            $teammateIds[] = $user->id;
        }

        $syncedIds = array_values(array_unique($teammateIds));
        $this->ensureTeammatesCanJoinRecord($record, $syncedIds);
        $record->teammates()->sync($syncedIds);
        $record->forceFill(['pending_teammate_osu_ids' => []])->save();
        $record->unsetRelation('teammates');

        if ($failedOsuIds !== []) {
            $record->loadMissing('user');
            $this->notifications->notifyFailedTeammateOsuIds($record->user, $record, array_values(array_unique($failedOsuIds)));
        }

        return [
            'added' => array_values(array_diff($syncedIds, $beforeIds)),
            'removed' => array_values(array_diff($beforeIds, $syncedIds)),
        ];
    }

    /**
     * @param  list<int>  $teammateIds
     *
     * @throws ValidationException
     */
    private function ensureTeammatesCanJoinRecord(TournamentParticipationRecord $record, array $teammateIds): void
    {
        if ($teammateIds === []) {
            return;
        }

        $allowedRecordIds = collect([
            $record->id,
            (int) data_get($record->metadata, 'shared_from_record_id'),
        ])
            ->merge($this->sharedRecordComponent($record)->pluck('id'))
            ->filter()
            ->unique()
            ->values();
        $currentPodiumGroupId = $this->podiumGroupIdForRecord($record);

        /** @var Collection<int, TournamentParticipationRecord> $existingRecords */
        $existingRecords = TournamentParticipationRecord::query()
            ->with('user')
            ->where('tournament_id', $record->tournament_id)
            ->whereIn('user_id', $teammateIds)
            ->get();

        foreach ($existingRecords as $existingRecord) {
            $existingRootId = (int) data_get($existingRecord->metadata, 'shared_from_record_id');
            if ($allowedRecordIds->contains($existingRecord->id) || ($existingRootId > 0 && $allowedRecordIds->contains($existingRootId))) {
                continue;
            }

            $existingPodiumGroupId = $this->podiumGroupIdForRecord($existingRecord);
            if ($currentPodiumGroupId !== null && $currentPodiumGroupId === $existingPodiumGroupId) {
                continue;
            }

            if ($this->actorCanModerateParticipation()
                && $record->isPodiumBacked()
                && $existingRecord->isPodiumBacked()
                && $this->samePodiumPlacement($record, $existingRecord)) {
                continue;
            }

            throw ValidationException::withMessages([
                'teammate_ids' => __('users.participation.flash.duplicate_teammate_record', [
                    'username' => $existingRecord->user->username,
                ]),
            ]);
        }
    }

    private function samePodiumPlacement(TournamentParticipationRecord $record, TournamentParticipationRecord $existingRecord): bool
    {
        $placement = $record->placement_min ?? $record->placement;
        $existingPlacement = $existingRecord->placement_min ?? $existingRecord->placement;

        return $placement !== null
            && $placement <= 3
            && $placement === $existingPlacement;
    }

    private function podiumGroupIdForRecord(TournamentParticipationRecord $record): ?string
    {
        $groupId = data_get($record->metadata, 'podium_group_id');
        if (is_string($groupId) && $groupId !== '') {
            return $groupId;
        }

        $placement = $record->placement_min ?? $record->placement;
        if ($placement === null) {
            return null;
        }

        $winner = TournamentWinner::query()
            ->where('tournament_id', $record->tournament_id)
            ->where('user_id', $record->user_id)
            ->where('placement', $placement)
            ->first();

        $groupId = data_get($winner?->metadata, 'podium_group_id');

        return is_string($groupId) && $groupId !== '' ? $groupId : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function hasTeammateInput(array $validated): bool
    {
        return (bool) ($validated['teammates_submitted'] ?? false)
            || array_key_exists('teammate_ids', $validated)
            || array_key_exists('pending_teammate_osu_ids', $validated);
    }

    private function syncSharedTeammateRecords(TournamentParticipationRecord $record, bool $pruneStaleSharedRecords = false): void
    {
        $record->refresh()->loadMissing(['teammates', 'tournament', 'participationMatches']);
        $component = $this->sharedRecordComponent($record);
        $root = $this->canonicalSharedRoot($component, $record);
        $root->loadMissing(['teammates', 'tournament', 'participationMatches']);

        $directTeammateIds = $record->teammates()
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== $record->user_id)
            ->values();
        $componentUserIds = $component
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $existingSharedUserIds = $componentUserIds
            ->filter(fn (int $id): bool => $id !== $root->user_id)
            ->values();
        $shouldPreserveExistingSharedRecords = $pruneStaleSharedRecords
            && $directTeammateIds->isEmpty()
            && $existingSharedUserIds->isNotEmpty();

        $groupUserIds = $pruneStaleSharedRecords && ! $shouldPreserveExistingSharedRecords
            ? $directTeammateIds->push($record->user_id)->unique()->values()
            : $componentUserIds;
        $groupUserIds = $groupUserIds
            ->merge($directTeammateIds)
            ->push($root->user_id)
            ->push($record->user_id)
            ->unique()
            ->values();
        $sharedUserIds = $groupUserIds
            ->filter(fn (int $id): bool => $id !== $root->user_id)
            ->values();

        if ($pruneStaleSharedRecords && ! $shouldPreserveExistingSharedRecords) {
            TournamentParticipationRecord::query()
                ->where('tournament_id', $root->tournament_id)
                ->where('metadata->shared_from_record_id', $root->id)
                ->whereNotIn('user_id', $sharedUserIds)
                ->delete();
        }

        if ($record->id !== $root->id) {
            $root->update($this->rootPayloadFromSharedSource($record, $root));
            $root->copyParticipationMatchesFrom($record, $this->matchesForTargetSync($root, $record));
            $root->refresh()->loadMissing(['participationMatches']);
        }

        foreach ($groupUserIds as $userId) {
            if ((int) $userId === $root->user_id) {
                $root->teammates()->sync(
                    $groupUserIds
                        ->filter(fn (int $id): bool => $id !== $root->user_id)
                        ->values()
                        ->all()
                );

                continue;
            }

            $shared = TournamentParticipationRecord::query()
                ->with('participationMatches')
                ->where('user_id', $userId)
                ->where('tournament_id', $root->tournament_id)
                ->first();
            $sharedPayload = $this->sharedPayloadFromSource($record, $root);

            if (! $shared instanceof TournamentParticipationRecord) {
                $shared = TournamentParticipationRecord::query()->create(array_merge(
                    [
                        'user_id' => $userId,
                        'tournament_id' => $root->tournament_id,
                    ],
                    $sharedPayload
                ));
            } elseif ($shared->id !== $record->id) {
                $shared->update($sharedPayload);
            } else {
                $this->normalizeSharedMetadata($shared, $root);
            }

            if ($shared->id !== $record->id) {
                $shared->copyParticipationMatchesFrom($record, $this->matchesForTargetSync($shared, $record));
            }

            $shared->teammates()->sync(
                $groupUserIds
                    ->filter(fn (int $id): bool => $id !== (int) $userId)
                    ->values()
                    ->all()
            );
        }
    }

    /**
     * @return Collection<int, TournamentParticipationRecord>
     */
    private function sharedRecordComponent(TournamentParticipationRecord $record): Collection
    {
        $records = new Collection;
        $seenRecordIds = [];
        $seenUserIds = [];
        $queueRecordIds = [$record->id];
        $queueUserIds = [$record->user_id];

        while ($queueRecordIds !== [] || $queueUserIds !== []) {
            $recordIds = array_values(array_unique(array_filter($queueRecordIds)));
            $userIds = array_values(array_unique(array_filter($queueUserIds)));
            $queueRecordIds = [];
            $queueUserIds = [];

            /** @var Collection<int, TournamentParticipationRecord> $found */
            $found = TournamentParticipationRecord::query()
                ->with(['teammates', 'tournament', 'participationMatches'])
                ->where('tournament_id', $record->tournament_id)
                ->where(function ($query) use ($recordIds, $userIds): void {
                    $hasCondition = false;

                    if ($recordIds !== []) {
                        $query->whereIn('id', $recordIds)
                            ->orWhereIn('metadata->shared_from_record_id', $recordIds);
                        $hasCondition = true;
                    }

                    if ($userIds !== []) {
                        $hasCondition
                            ? $query->orWhereIn('user_id', $userIds)
                            : $query->whereIn('user_id', $userIds);
                        $query->orWhereHas('teammates', function ($teammateQuery) use ($userIds): void {
                            $teammateQuery->whereIn('users.id', $userIds);
                        });
                    }
                })
                ->get();

            foreach ($found as $foundRecord) {
                if (isset($seenRecordIds[$foundRecord->id])) {
                    continue;
                }

                $seenRecordIds[$foundRecord->id] = true;
                $seenUserIds[$foundRecord->user_id] = true;
                $records->push($foundRecord);

                $rootId = (int) data_get($foundRecord->metadata, 'shared_from_record_id');
                if ($rootId > 0 && ! isset($seenRecordIds[$rootId])) {
                    $queueRecordIds[] = $rootId;
                }

                $queueRecordIds[] = $foundRecord->id;
                foreach ($foundRecord->teammates as $teammate) {
                    if (! isset($seenUserIds[$teammate->id])) {
                        $queueUserIds[] = $teammate->id;
                    }
                }
            }
        }

        return $records->isEmpty()
            ? new Collection([$record])
            : $records->values();
    }

    /**
     * @param  Collection<int, TournamentParticipationRecord>  $component
     */
    private function canonicalSharedRoot(Collection $component, TournamentParticipationRecord $fallback): TournamentParticipationRecord
    {
        $referencedRootId = $component
            ->pluck('metadata')
            ->map(fn ($metadata): int => (int) data_get($metadata, 'shared_from_record_id'))
            ->filter()
            ->first();

        if ($referencedRootId) {
            $root = $component->firstWhere('id', $referencedRootId);
            if ($root instanceof TournamentParticipationRecord) {
                return $root;
            }
        }

        $manualRoot = $component
            ->filter(fn (TournamentParticipationRecord $record): bool => $record->source !== 'manual_shared')
            ->sortBy('id')
            ->first();

        return $manualRoot instanceof TournamentParticipationRecord
            ? $manualRoot
            : ($component->sortBy('id')->first() ?? $fallback);
    }

    private function normalizeSharedMetadata(TournamentParticipationRecord $record, TournamentParticipationRecord $root): void
    {
        if ($record->id === $root->id) {
            return;
        }

        $record->update([
            'source' => 'manual_shared',
            'metadata' => array_merge($record->metadata ?? [], [
                'shared_from_record_id' => $root->id,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rootPayloadFromSharedSource(TournamentParticipationRecord $source, TournamentParticipationRecord $root): array
    {
        $metadata = $source->metadata ?? [];
        unset($metadata['shared_from_record_id']);

        return [
            'review_status' => $source->review_status,
            'selection_outcome' => $source->selection_outcome,
            'final_result' => $source->final_result,
            'stage_type' => $source->stage_type,
            'stage_name' => $source->stage_name,
            'round_label' => $source->round_label,
            'bracket_path' => $source->bracket_path,
            'placement' => $source->placement,
            'placement_min' => $source->placement_min,
            'placement_max' => $source->placement_max,
            'placement_override' => $source->placement_override,
            'seed' => $this->sharedSeedFor($source),
            'team_name' => $source->team_name,
            'pending_teammate_osu_ids' => [],
            'metadata' => array_merge($root->metadata ?? [], $metadata),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedPayloadFromSource(TournamentParticipationRecord $source, TournamentParticipationRecord $root): array
    {
        return [
            'source' => 'manual_shared',
            'review_status' => $source->review_status,
            'selection_outcome' => $source->selection_outcome,
            'final_result' => $source->final_result,
            'stage_type' => $source->stage_type,
            'stage_name' => $source->stage_name,
            'round_label' => $source->round_label,
            'bracket_path' => $source->bracket_path,
            'placement' => $source->placement,
            'placement_min' => $source->placement_min,
            'placement_max' => $source->placement_max,
            'placement_override' => $source->placement_override,
            'seed' => $this->sharedSeedFor($source),
            'team_name' => $source->team_name,
            'memo' => null,
            'pending_teammate_osu_ids' => [],
            'metadata' => array_merge($source->metadata ?? [], [
                'shared_from_record_id' => $root->id,
            ]),
        ];
    }

    private function sharedSeedFor(TournamentParticipationRecord $record): ?int
    {
        return $this->stageOptions->qualifierCutoff($record->tournament) === null ? null : $record->seed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchesForTargetSync(TournamentParticipationRecord $target, TournamentParticipationRecord $source): array
    {
        $target->loadMissing('participationMatches');

        $targetIndividualMatches = collect($target->matches)
            ->filter(fn (array $match): bool => ! $this->shouldShareParticipationMatch($match))
            ->values();

        $sharedMatches = collect($source->matches)
            ->filter(fn (array $match): bool => $this->shouldShareParticipationMatch($match))
            ->values();

        return $targetIndividualMatches
            ->merge($sharedMatches)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function shouldShareParticipationMatch(array $match): bool
    {
        $stage = $this->canonicalMatchStage($match['stage'] ?? null);

        if ($stage === 'Tryout') {
            return false;
        }

        if ($stage === 'Qualifier' && (bool) ($match['is_individual_qualifier'] ?? false)) {
            return false;
        }

        return true;
    }

    private function canonicalMatchStage(mixed $stage): ?string
    {
        $stage = is_string($stage) ? trim($stage) : null;
        if ($stage === null || $stage === '') {
            return null;
        }

        return match (strtolower($stage)) {
            'ql', 'qualifier' => 'Qualifier',
            'tryout' => 'Tryout',
            'battle royale' => 'Battle Royale',
            default => $stage,
        };
    }

    private function deleteLinkedPodiumRows(TournamentParticipationRecord $record): void
    {
        if (! $this->actorCanModerateParticipation()) {
            return;
        }

        if (! $record->isPodiumBacked() && ! $record->isAdminApprovedPodium()) {
            return;
        }

        $query = TournamentWinner::query()
            ->where('tournament_id', $record->tournament_id)
            ->where('user_id', $record->user_id);

        $groupId = $this->podiumGroupIdForRecord($record);
        if ($groupId !== null) {
            $query->where('metadata->podium_group_id', $groupId);
        }

        $query->delete();

        if ($groupId !== null) {
            app(ParticipationPodiumBackfillService::class)->backfillTournamentGroup($record->tournament_id, $groupId);
        }
    }

    private function isPodiumBacked(TournamentParticipationRecord $record): bool
    {
        return $record->isPodiumBacked();
    }

    /**
     * @param  array{placement: int|null, placement_min: int|null, placement_max: int|null, editable: bool}  $placement
     */
    private function needsPodiumApproval(User $user, Tournament $tournament, array $placement): bool
    {
        $candidatePlacement = $placement['placement_min'] ?? $placement['placement'];

        if ($candidatePlacement === null || $candidatePlacement > 3) {
            return false;
        }

        return ! TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->where('placement', $candidatePlacement)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function userInputSnapshot(TournamentParticipationRecord $record): array
    {
        $record->loadMissing('teammates');

        return [
            'team_name' => $record->team_name,
            'stage_value' => data_get($record->metadata, 'stage_value') ?: null,
            'seed' => $record->seed,
            'memo' => $record->memo,
            'matches' => $record->matches,
            'teammates' => $record->teammates
                ->sortBy('id')
                ->map(fn (User $teammate): array => [
                    'id' => $teammate->id,
                    'osu_id' => $teammate->osu_id,
                    'username' => $teammate->username,
                ])
                ->values()
                ->all(),
            'pending_teammate_osu_ids' => array_values($record->pending_teammate_osu_ids ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changedInputFields(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function meaningfulInputFields(array $fields): array
    {
        return array_filter(
            $fields,
            fn (mixed $value): bool => $value !== null
                && $value !== false
                && $value !== ''
                && $value !== []
        );
    }

    private function viewerCanSeeHiddenParticipation(User $user): bool
    {
        return Auth::id() === $user->id || (Auth::user()?->isAdmin() ?? false);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TournamentParticipationRecord>  $records
     */
    private function renderParticipationRecordItems(User $user, \Illuminate\Support\Collection $records): string
    {
        $viewerCanSeeHiddenParticipation = $this->viewerCanSeeHiddenParticipation($user);
        $displayTeammatesByRecord = $this->recordQueries->displayTeammatesForRecords($records);
        $stageOptions = $records
            ->mapWithKeys(fn (TournamentParticipationRecord $record) => [$record->tournament_id => $this->stageOptions->optionsFor($record->tournament)->all()]);
        $tournamentDetails = $records
            ->pluck('tournament')
            ->unique('id')
            ->mapWithKeys(fn (Tournament $tournament) => [$tournament->id => $this->tournamentPayload($tournament, $user)]);

        return view('users.partials.participation-record-items', [
            'records' => $records,
            'user' => $user,
            'isOwnProfile' => Auth::id() === $user->id,
            'canManageParticipation' => Auth::id() === $user->id || $this->actorCanModerateParticipation(),
            'stageOptions' => $stageOptions,
            'tournamentDetails' => $tournamentDetails,
            'participationIndices' => $this->recordQueries->globalIndicesFor($records, $user, $viewerCanSeeHiddenParticipation),
            'displayTeammatesByRecord' => $displayTeammatesByRecord,
            'displayTeammateBwsRanksByRecord' => $this->recordQueries
                ->displayTeammateBwsRanksForRecords($records, $displayTeammatesByRecord),
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function tournamentPayload(Tournament $tournament, ?User $user = null): array
    {
        $modes = collect($tournament->modes)->map(fn ($mode): ?string => data_get($mode, 'mode', $mode))->filter()->values();
        $podiumPlacements = $user instanceof User
            ? TournamentWinner::query()
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->pluck('placement')
                ->map(fn ($placement): int => (int) $placement)
                ->values()
                ->all()
            : [];

        return [
            'id' => $tournament->id,
            'title' => $tournament->title,
            'year' => $tournament->tournament_end?->year,
            'modes' => $modes,
            'is_badge' => (bool) $tournament->is_badge,
            'profile_url' => route('tournaments.show', $tournament),
            'forum_post_url' => $tournament->forum_post_url,
            'spreadsheet_url' => $tournament->spreadsheet_url,
            'bracket_url' => $tournament->bracket_url,
            'stage_options' => $this->stageOptions->optionsFor($tournament)->all(),
            'match_stage_options' => $this->stageOptions->matchStageLabelsFor($tournament),
            'has_qualifier' => $this->stageOptions->hasQualifier($tournament),
            'qualifier_cutoff' => $this->stageOptions->qualifierCutoff($tournament),
            'is_team_tournament' => $this->stageOptions->isTeamTournament($tournament),
            'current_user_podium_placements' => $podiumPlacements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dialogRecordPayload(TournamentParticipationRecord $record): array
    {
        $record->loadMissing(['tournament', 'teammates.rankHistory', 'participationMatches']);
        $tournament = $record->tournament;
        $stageValue = (string) data_get($record->metadata, 'stage_value');
        $stageOption = $this->stageOptionForRecordValue($tournament, $stageValue);
        $dialogStageValue = (string) ($stageOption['value'] ?? $stageValue);
        $mode = $this->firstTournamentMode($tournament);
        $bwsCalculator = app(BwsCalculator::class);
        $teammates = $this->displayTeammatesForRecord($record)
            ->sortBy(fn (User $teammate): float|int => $mode !== null
                ? ($bwsCalculator->calculateForUser($teammate, $tournament, $mode) ?? PHP_INT_MAX)
                : PHP_INT_MAX)
            ->values();

        return [
            'id' => $record->id,
            'stage_value' => $dialogStageValue,
            'placement' => $record->placement,
            'placement_min' => $record->placement_min,
            'placement_max' => $record->placement_max,
            'placement_override' => $record->placement_override,
            'placement_range_editable' => (bool) ($stageOption['range_placement'] ?? false),
            'podium_locked' => $this->isPodiumBacked($record),
            'podium_member_editable' => $this->actorCanModerateParticipation() && $this->isPodiumBacked($record),
            'seed' => $record->seed,
            'team_name' => $record->team_name,
            'memo' => $record->memo,
            'matches' => $record->matches ?? [],
            'teammates' => $teammates->map(fn (User $teammate): array => [
                'id' => $teammate->id,
                'osu_id' => $teammate->osu_id,
                'username' => $teammate->username,
                'avatar_url' => $teammate->avatar_url,
                'country_code' => $teammate->country_code,
                'profile_url' => route('users.show', $teammate),
                'bws_rank' => $mode !== null
                    ? $bwsCalculator->calculateForUser($teammate, $tournament, $mode)
                    : null,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stageOptionForRecordValue(Tournament $tournament, string $stageValue): ?array
    {
        $stageOptions = $this->stageOptions->optionsFor($tournament);
        $stageOption = $stageOptions->firstWhere('value', $stageValue);

        if ($stageOption !== null || ! preg_match('/^swiss:(\d+)$/', $stageValue, $matches)) {
            return $stageOption;
        }

        return $stageOptions->first(fn (array $option): bool => str_starts_with((string) $option['value'], "swiss:{$matches[1]}:"));
    }

    private function firstTournamentMode(Tournament $tournament): ?string
    {
        return collect($tournament->modes)
            ->map(fn ($mode): ?string => data_get($mode, 'mode', $mode))
            ->filter()
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function displayTeammatesForRecord(TournamentParticipationRecord $record): \Illuminate\Support\Collection
    {
        $teammateIds = $record->relationLoaded('teammates')
            ? $record->teammates->pluck('id')
            : $record->teammates()->pluck('users.id');

        if ($teammateIds->isNotEmpty()) {
            /** @var \Illuminate\Support\Collection<int, User> $users */
            $users = User::query()
                ->with('rankHistory')
                ->whereIn('id', $teammateIds->all())
                ->get();

            return $users;
        }

        $sharedUserIds = $this->sharedRecordComponent($record)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== $record->user_id)
            ->unique()
            ->values();
        if ($sharedUserIds->isEmpty()) {
            /** @var \Illuminate\Support\Collection<int, User> $users */
            $users = collect();

            return $users;
        }

        /** @var \Illuminate\Support\Collection<int, User> $users */
        $users = User::query()
            ->with('rankHistory')
            ->whereIn('id', $sharedUserIds->all())
            ->get();

        return $users;
    }
}
