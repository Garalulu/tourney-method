<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\User;
use App\Support\AdminAuditLogPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $query = AdminAuditLog::with(['admin'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->integer('admin_id'));
        }

        if ($request->filled('action')) {
            $query->whereIn('action', AdminAuditLogPresenter::actionAliases((string) $request->query('action')));
        }

        if ($request->filled('entity_type')) {
            $query->whereIn('entity_type', AdminAuditLogPresenter::entityAliases((string) $request->query('entity_type')));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $tournamentIds = Tournament::query()
                ->where('title', 'ilike', "%{$search}%")
                ->orWhere('id', is_numeric($search) ? (int) $search : 0)
                ->pluck('id');

            $query->where(function ($query) use ($tournamentIds) {
                $query->whereIn('entity_type', AdminAuditLogPresenter::entityAliases('tournament'))
                    ->whereIn('entity_id', $tournamentIds);
            });
        }

        $logs = $query
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($logs, 200);
        }

        $presentedLogs = AdminAuditLogPresenter::presentMany($logs->getCollection());
        $actionOptions = collect(AdminAuditLogPresenter::actionOptions())
            ->merge(
                AdminAuditLog::query()
                    ->select('action')
                    ->distinct()
                    ->pluck('action')
                    ->mapWithKeys(fn (string $action): array => [$action => AdminAuditLogPresenter::actionLabel($action)])
            )
            ->sort()
            ->all();
        $entityOptions = AdminAuditLogPresenter::entityOptions();
        $admins = User::query()
            ->where('role', '!=', 'player')
            ->orderBy('username')
            ->get(['id', 'username']);

        return view('admin.audit-log.index', compact('logs', 'presentedLogs', 'actionOptions', 'entityOptions', 'admins'));
    }
}
