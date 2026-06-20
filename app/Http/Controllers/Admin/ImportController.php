<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualImportRequest;
use App\Jobs\ParseForumTopicJob;
use App\Models\AdminMaintenanceRun;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ImportController extends Controller
{
    /**
     * Display import job history.
     */
    public function index(): View
    {
        $runs = AdminMaintenanceRun::query()
            ->systemMaintenance()
            ->with([
                'items' => fn ($query) => $query
                    ->orderBy('action')
                    ->orderBy('created_at'),
            ])
            ->latest('started_at')
            ->paginate(20);

        $latestRuns = AdminMaintenanceRun::query()
            ->systemMaintenance()
            ->whereIn('id', AdminMaintenanceRun::query()
                ->systemMaintenance()
                ->selectRaw('max(id)')
                ->groupBy('command')
            )
            ->orderBy('command')
            ->get()
            ->keyBy('command');

        return view('admin.imports.index', [
            'runs' => $runs,
            'latestRuns' => $latestRuns,
        ]);
    }

    /**
     * Manually import tournament(s) from osu! forum.
     * Accepts either a single topic_id or an array of topic_ids.
     */
    public function manualImport(ManualImportRequest $request): RedirectResponse
    {
        // Collect topic IDs from either field
        $topicIds = [];

        if ($request->filled('topic_id')) {
            $topicIds[] = $request->integer('topic_id');
        }

        if ($request->filled('topic_ids')) {
            $topicIds = array_merge($topicIds, $request->input('topic_ids', []));
        }

        if (empty($topicIds)) {
            return back()
                ->with('error', 'No topic IDs provided.');
        }

        // Remove duplicates while preserving keys
        $topicIds = array_unique($topicIds);

        // Dispatch jobs for each topic
        $count = 0;
        foreach ($topicIds as $topicId) {
            ParseForumTopicJob::dispatch($topicId);
            $count++;
        }

        $message = $count === 1
            ? '1 tournament import queued successfully.'
            : "{$count} tournament imports queued successfully.";

        return redirect()
            ->route('admin.imports.index')
            ->with('success', $message);
    }
}
