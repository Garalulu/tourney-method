<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParticipationDeletionRequest;
use App\Models\ParticipationInputLock;
use App\Models\ParticipationInputLog;
use App\Models\ParticipationRecordReport;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AuditLogger;
use App\Services\InAppNotificationService;
use App\Services\ParticipationPodiumBackfillService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ParticipationModerationController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ParticipationInputLog::query()
            ->with([
                'user' => fn ($query) => $query->withTrashed(),
                'actor' => fn ($query) => $query->withTrashed(),
                'record.tournament',
            ])
            ->when($request->boolean('flagged'), fn ($query) => $query->where('flagged', true))
            ->when($request->filled('action'), fn ($query) => $query->where('action', (string) $request->query('action')))
            ->when($request->query('entity_type') === 'record', fn ($query) => $query->whereNotNull('tournament_participation_record_id'))
            ->when($request->query('entity_type') === 'user', fn ($query) => $query->whereNull('tournament_participation_record_id'))
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('q'));

                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('username', 'ilike', "%{$search}%"))
                        ->orWhereHas('record.tournament', function (Builder $tournamentQuery) use ($search): void {
                            $tournamentQuery->where('title', 'ilike', "%{$search}%");

                            if (is_numeric($search)) {
                                $tournamentQuery->orWhere('id', (int) $search);
                            }
                        });
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $locks = ParticipationInputLock::query()
            ->with([
                'user' => fn ($query) => $query->withTrashed(),
                'locker' => fn ($query) => $query->withTrashed(),
            ])
            ->latest()
            ->get();

        $deletionRequests = ParticipationDeletionRequest::query()
            ->with([
                'requester' => fn ($query) => $query->withTrashed(),
                'record.user' => fn ($query) => $query->withTrashed(),
                'record.tournament',
                'record.teammates' => fn ($query) => $query->withTrashed(),
            ])
            ->where('status', ParticipationDeletionRequest::STATUS_PENDING)
            ->when($request->filled('deletion_user'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('deletion_user'));

                $query->whereHas('requester', fn (Builder $userQuery) => $userQuery->where('username', 'ilike', "%{$search}%"));
            })
            ->latest()
            ->get();

        $addRequests = TournamentParticipationRecord::query()
            ->with([
                'user' => fn ($query) => $query->withTrashed(),
                'tournament',
                'teammates' => fn ($query) => $query->withTrashed(),
            ])
            ->where('review_status', TournamentParticipationRecord::REVIEW_PENDING)
            ->whereNull('metadata->shared_from_record_id')
            ->where(function (Builder $query): void {
                $query->where('placement', '<=', 3)
                    ->orWhere('placement_min', '<=', 3);
            })
            ->latest()
            ->get();

        $reports = ParticipationRecordReport::query()
            ->with([
                'reporter' => fn ($query) => $query->withTrashed(),
                'record.user' => fn ($query) => $query->withTrashed(),
                'record.tournament',
                'record.teammates' => fn ($query) => $query->withTrashed(),
            ])
            ->where('status', ParticipationRecordReport::STATUS_PENDING)
            ->latest()
            ->get();

        $actionOptions = ParticipationInputLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->mapWithKeys(fn (string $action): array => [$action => str($action)->replace('_', ' ')->headline()->toString()])
            ->all();
        $entityOptions = [
            'record' => __('admin.participation.filters.entities.record'),
            'user' => __('admin.participation.filters.entities.user'),
        ];

        return view('admin.participation-moderation.index', compact('logs', 'locks', 'deletionRequests', 'addRequests', 'reports', 'actionOptions', 'entityOptions'));
    }

    public function approveAddRequest(
        Request $request,
        TournamentParticipationRecord $record,
        AuditLogger $audit,
        InAppNotificationService $notifications,
        ParticipationPodiumBackfillService $podiumBackfill
    ): RedirectResponse {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $record->loadMissing(['user', 'tournament', 'teammates']);

        DB::transaction(function () use ($record, $validated, $audit, $notifications, $podiumBackfill): void {
            $recordIds = $this->relatedTeamRecordIds($record);

            TournamentParticipationRecord::query()
                ->whereIn('id', $recordIds)
                ->update(['review_status' => TournamentParticipationRecord::REVIEW_APPROVED]);

            $freshRecord = $record->fresh(['user', 'tournament', 'teammates']);
            if ($freshRecord instanceof TournamentParticipationRecord) {
                $podiumBackfill->syncRosterFromRecord($freshRecord);

                $notifications->notifyParticipationAddResolved($freshRecord, UserNotification::TYPE_PARTICIPATION_ADD_APPROVED);
            }

            $audit->log('participation_add_request_approved', 'participation_record', $record->id, [
                'record_ids' => $recordIds,
                'user_id' => $record->user_id,
                'tournament_id' => $record->tournament_id,
                'review_note' => $validated['review_note'] ?? null,
            ]);
        });

        return back()->with('success', __('admin.participation.messages.add_request_approved'));
    }

    public function rejectAddRequest(
        Request $request,
        TournamentParticipationRecord $record,
        AuditLogger $audit,
        InAppNotificationService $notifications
    ): RedirectResponse {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $record->loadMissing(['user', 'tournament', 'teammates']);

        DB::transaction(function () use ($record, $validated, $audit, $notifications): void {
            $recordIds = $this->relatedTeamRecordIds($record);

            $notifications->notifyParticipationAddResolved($record, UserNotification::TYPE_PARTICIPATION_ADD_DENIED);

            $audit->log('participation_add_request_rejected', 'participation_record', $record->id, [
                'record_ids' => $recordIds,
                'user_id' => $record->user_id,
                'tournament_id' => $record->tournament_id,
                'review_note' => $validated['review_note'] ?? null,
            ]);

            TournamentParticipationRecord::query()
                ->whereIn('id', $recordIds)
                ->delete();
        });

        return back()->with('success', __('admin.participation.messages.add_request_rejected'));
    }

    public function deleteInput(Request $request, TournamentParticipationRecord $record, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $record->update([
            'team_name' => null,
            'memo' => null,
            'user_input_deleted_at' => now(),
            'user_input_deleted_by' => Auth::id(),
            'user_input_deleted_reason' => $validated['reason'] ?? null,
        ]);

        $audit->log('participation_input_deleted', 'participation_record', $record->id, [
            'user_id' => $record->user_id,
            'reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('success', __('admin.participation.messages.input_deleted'));
    }

    public function approveDeletionRequest(
        Request $request,
        ParticipationDeletionRequest $deletionRequest,
        AuditLogger $audit,
        InAppNotificationService $notifications
    ): RedirectResponse {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $deletionRequest->loadMissing(['record.user', 'record.tournament', 'record.teammates']);

        DB::transaction(function () use ($deletionRequest, $validated, $audit, $notifications): void {
            $record = $deletionRequest->record;
            $relatedRecordIds = $this->relatedTeamRecordIds($record);
            $relatedRequestIds = ParticipationDeletionRequest::query()
                ->whereIn('tournament_participation_record_id', $relatedRecordIds)
                ->where('status', ParticipationDeletionRequest::STATUS_PENDING)
                ->pluck('id')
                ->all();

            ParticipationDeletionRequest::query()
                ->whereIn('id', $relatedRequestIds)
                ->update([
                    'status' => ParticipationDeletionRequest::STATUS_APPROVED,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    'review_note' => $validated['review_note'] ?? null,
                ]);

            $audit->log('participation_deletion_request_approved', 'participation_deletion_request', $deletionRequest->id, [
                'record_id' => $record->id,
                'record_ids' => $relatedRecordIds,
                'deletion_request_ids' => $relatedRequestIds,
                'user_id' => $record->user_id,
                'tournament_id' => $record->tournament_id,
                'review_note' => $validated['review_note'] ?? null,
            ]);

            $deletionRequest->refresh();
            $notifications->notifyDeletionResolved($deletionRequest, UserNotification::TYPE_DELETION_APPROVED);

            TournamentParticipationRecord::query()
                ->whereIn('id', $relatedRecordIds)
                ->delete();
        });

        return back()->with('success', __('admin.participation.messages.deletion_request_approved'));
    }

    public function rejectDeletionRequest(
        Request $request,
        ParticipationDeletionRequest $deletionRequest,
        AuditLogger $audit,
        InAppNotificationService $notifications
    ): RedirectResponse {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $deletionRequest->update([
            'status' => ParticipationDeletionRequest::STATUS_REJECTED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        $audit->log('participation_deletion_request_rejected', 'participation_deletion_request', $deletionRequest->id, [
            'record_id' => $deletionRequest->tournament_participation_record_id,
            'review_note' => $validated['review_note'] ?? null,
        ]);

        $deletionRequest->refresh();
        $notifications->notifyDeletionResolved($deletionRequest, UserNotification::TYPE_DELETION_DENIED);

        return back()->with('success', __('admin.participation.messages.deletion_request_rejected'));
    }

    public function lock(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        ParticipationInputLock::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'locked_by' => Auth::id(),
                'reason' => $validated['reason'] ?? null,
                'locked_at' => now(),
            ]
        );

        $audit->log('participation_input_locked', 'user', $user->id, [
            'reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('success', __('admin.participation.messages.locked'));
    }

    public function unlock(User $user, AuditLogger $audit): RedirectResponse
    {
        ParticipationInputLock::query()->where('user_id', $user->id)->delete();

        $audit->log('participation_input_unlocked', 'user', $user->id);

        return back()->with('success', __('admin.participation.messages.unlocked'));
    }

    public function rollbackTeammates(ParticipationInputLog $log, AuditLogger $audit): RedirectResponse
    {
        $log->loadMissing('record.teammates');
        $record = $log->record;
        abort_unless($record instanceof TournamentParticipationRecord, 404);

        $change = data_get($log->changed_fields, 'changes.teammates');
        abort_unless(is_array($change), 404);

        $oldIds = $this->teammateIdsFromAuditValue($change['old'] ?? []);
        $newIds = $this->teammateIdsFromAuditValue($change['new'] ?? []);
        $idsToReAdd = array_values(array_diff($oldIds, $newIds));
        $idsToRemove = array_values(array_diff($newIds, $oldIds));

        abort_if($idsToReAdd === [] && $idsToRemove === [], 404);

        DB::transaction(function () use ($record, $log, $idsToReAdd, $idsToRemove, $audit): void {
            $currentIds = $record->teammates()
                ->pluck('users.id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $existingReAddIds = User::query()
                ->whereIn('id', $idsToReAdd)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $syncedIds = collect($currentIds)
                ->diff($idsToRemove)
                ->merge($existingReAddIds)
                ->unique()
                ->values()
                ->all();

            $record->teammates()->sync($syncedIds);

            $audit->log('participation_teammates_rollback', 'participation_record', $record->id, [
                'source_log_id' => $log->id,
                'record_id' => $record->id,
                'user_id' => $record->user_id,
                'tournament_id' => $record->tournament_id,
                'added_ids' => array_values(array_diff($syncedIds, $currentIds)),
                'removed_ids' => array_values(array_intersect($currentIds, $idsToRemove)),
            ]);
        });

        return back()->with('success', 'Teammate change rolled back.');
    }

    public function resolveReport(Request $request, ParticipationRecordReport $report, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:500'],
            'lock_user' => ['nullable', 'boolean'],
        ]);

        $report->loadMissing(['record.user', 'record.tournament', 'reporter']);

        DB::transaction(function () use ($report, $validated, $audit): void {
            $report->update([
                'status' => ParticipationRecordReport::STATUS_RESOLVED,
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
                'resolution_note' => $validated['resolution_note'] ?? null,
            ]);

            if (($validated['lock_user'] ?? false) && $report->record->user) {
                ParticipationInputLock::query()->updateOrCreate(
                    ['user_id' => $report->record->user_id],
                    [
                        'locked_by' => Auth::id(),
                        'reason' => $validated['resolution_note'] ?? __('admin.participation.labels.report_lock_reason'),
                        'locked_at' => now(),
                    ]
                );
            }

            $audit->log('participation_report_resolved', 'participation_record_report', $report->id, [
                'record_id' => $report->tournament_participation_record_id,
                'reported_user_id' => $report->record->user_id,
                'reported_by' => $report->reported_by,
                'category' => $report->category,
                'lock_user' => (bool) ($validated['lock_user'] ?? false),
                'resolution_note' => $validated['resolution_note'] ?? null,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('admin.participation.messages.report_resolved'),
                'report_id' => $report->id,
                'remaining_reports' => ParticipationRecordReport::query()
                    ->where('status', ParticipationRecordReport::STATUS_PENDING)
                    ->count(),
            ]);
        }

        return back()->with('success', __('admin.participation.messages.report_resolved'));
    }

    /**
     * @return list<int>
     */
    private function teammateIdsFromAuditValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function (mixed $item): ?int {
                if (is_array($item) && is_numeric($item['id'] ?? null)) {
                    return (int) $item['id'];
                }

                return is_numeric($item) ? (int) $item : null;
            })
            ->filter(fn (?int $id): bool => $id !== null && $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function relatedTeamRecordIds(TournamentParticipationRecord $record): array
    {
        $record->loadMissing('teammates');

        $teamUserIds = $record->teammates
            ->pluck('id')
            ->push($record->user_id)
            ->unique()
            ->values()
            ->all();
        $sharedRootId = (int) (data_get($record->metadata, 'shared_from_record_id') ?: $record->id);

        return TournamentParticipationRecord::query()
            ->where('tournament_id', $record->tournament_id)
            ->where(function (Builder $query) use ($record, $teamUserIds, $sharedRootId): void {
                $query->whereKey($record->id)
                    ->orWhereIn('user_id', $teamUserIds)
                    ->orWhere('id', $sharedRootId)
                    ->orWhere('metadata->shared_from_record_id', $record->id)
                    ->orWhere('metadata->shared_from_record_id', $sharedRootId);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
