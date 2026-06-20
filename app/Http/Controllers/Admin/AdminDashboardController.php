<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use App\Models\ParticipationDeletionRequest;
use App\Models\ParticipationRecordReport;
use App\Models\TournamentCorrection;
use App\Models\TournamentParticipationRecord;
use App\Support\AdminAuditLogPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(): View
    {
        $recentParsedTournaments = AdminMaintenanceRunItem::query()
            ->with('run')
            ->where('item_type', AdminMaintenanceRunItem::TYPE_TOURNAMENT)
            ->whereIn('action', [
                AdminMaintenanceRunItem::ACTION_CREATED,
                AdminMaintenanceRunItem::ACTION_UPDATED,
            ])
            ->whereHas('run', fn ($query) => $query
                ->where('command', AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE))
            ->latest()
            ->take(6)
            ->get();

        $recentAuditLogs = AdminAuditLog::query()
            ->with('admin')
            ->latest()
            ->take(6)
            ->get();

        /** @var Collection<int, AdminAuditLog> $recentAuditLogs */
        $recentAuditEntries = AdminAuditLogPresenter::presentMany($recentAuditLogs);

        /** @var EloquentCollection<int, ParticipationRecordReport> $reports */
        $reports = ParticipationRecordReport::query()
            ->with([
                'reporter' => fn ($query) => $query->withTrashed(),
                'record.user' => fn ($query) => $query->withTrashed(),
                'record.tournament' => fn ($query) => $query->withTrashed(),
            ])
            ->where('status', ParticipationRecordReport::STATUS_PENDING)
            ->latest('updated_at')
            ->take(6)
            ->get();
        /** @var EloquentCollection<int, TournamentParticipationRecord> $podiumAdds */
        $podiumAdds = TournamentParticipationRecord::query()
            ->with([
                'user' => fn ($query) => $query->withTrashed(),
                'tournament' => fn ($query) => $query->withTrashed(),
            ])
            ->where('review_status', TournamentParticipationRecord::REVIEW_PENDING)
            ->whereNull('metadata->shared_from_record_id')
            ->where(function (Builder $query): void {
                $query->where('placement', '<=', 3)
                    ->orWhere('placement_min', '<=', 3);
            })
            ->latest('updated_at')
            ->take(6)
            ->get();
        /** @var EloquentCollection<int, ParticipationDeletionRequest> $deletionRequests */
        $deletionRequests = ParticipationDeletionRequest::query()
            ->with([
                'requester' => fn ($query) => $query->withTrashed(),
                'record.user' => fn ($query) => $query->withTrashed(),
                'record.tournament' => fn ($query) => $query->withTrashed(),
            ])
            ->where('status', ParticipationDeletionRequest::STATUS_PENDING)
            ->latest('updated_at')
            ->take(6)
            ->get();

        $recentParticipationModeration = collect()
            ->merge($reports->map(fn (ParticipationRecordReport $report): array => [
                'type' => 'Report',
                'status' => $report->status,
                'user' => $report->record->user->username,
                'tournament' => $report->record->tournament->title,
                'description' => ParticipationRecordReport::categoryLabels()[$report->category]
                    ?? str($report->category)->headline()->toString(),
                'updated_at' => Carbon::parse((string) $report->getRawOriginal('updated_at')),
            ]))
            ->merge($podiumAdds->map(fn (TournamentParticipationRecord $record): array => [
                'type' => 'Podium add',
                'status' => $record->review_status,
                'user' => $record->user->username,
                'tournament' => $record->tournament->title,
                'description' => $record->placementRangeLabel() ?? 'Podium placement',
                'updated_at' => Carbon::parse((string) $record->getRawOriginal('updated_at')),
            ]))
            ->merge($deletionRequests->map(fn (ParticipationDeletionRequest $request): array => [
                'type' => 'Removal request',
                'status' => $request->status,
                'user' => $request->record->user->username,
                'tournament' => $request->record->tournament->title,
                'description' => "Requested by {$request->requester->username}",
                'updated_at' => Carbon::parse((string) $request->getRawOriginal('updated_at')),
            ]))
            ->sortByDesc('updated_at')
            ->take(6)
            ->values();

        $recentCorrections = TournamentCorrection::query()
            ->with(['tournament', 'submitter'])
            ->where('status', TournamentCorrection::STATUS_PENDING)
            ->latest('updated_at')
            ->take(6)
            ->get();

        return view('admin.dashboard', [
            'recentParsedTournaments' => $recentParsedTournaments,
            'recentAuditEntries' => $recentAuditEntries,
            'recentParticipationModeration' => $recentParticipationModeration,
            'recentCorrections' => $recentCorrections,
        ]);
    }
}
