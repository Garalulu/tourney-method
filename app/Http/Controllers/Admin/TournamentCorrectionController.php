<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TournamentCorrection;
use App\Services\TournamentCorrectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TournamentCorrectionController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', TournamentCorrection::STATUS_PENDING);
        $allowedStatuses = [
            TournamentCorrection::STATUS_PENDING,
            TournamentCorrection::STATUS_PROCESSING,
            TournamentCorrection::STATUS_APPROVED,
            TournamentCorrection::STATUS_PARTIALLY_APPROVED,
            TournamentCorrection::STATUS_REJECTED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = TournamentCorrection::STATUS_PENDING;
        }

        $corrections = TournamentCorrection::query()
            ->with(['tournament', 'submitter', 'reviewer'])
            ->where('status', $status)
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim((string) $request->query('q'));
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('tournament', fn ($tournamentQuery) => $tournamentQuery->where('title', 'ilike', "%{$search}%"))
                        ->orWhereHas('submitter', fn ($userQuery) => $userQuery->where('username', 'ilike', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $counts = collect($allowedStatuses)
            ->mapWithKeys(fn (string $item): array => [$item => TournamentCorrection::query()->where('status', $item)->count()])
            ->all();

        return view('admin.tournament-corrections.index', compact('corrections', 'counts', 'status'));
    }

    public function show(TournamentCorrection $correction, TournamentCorrectionService $service): View
    {
        $correction->load(['tournament', 'submitter', 'reviewer']);
        $groupedChanges = $service->groupedOrderedChanges($correction);

        return view('admin.tournament-corrections.show', compact('correction', 'groupedChanges'));
    }

    public function review(Request $request, TournamentCorrection $correction, TournamentCorrectionService $service): RedirectResponse
    {
        if ($correction->status !== TournamentCorrection::STATUS_PENDING) {
            return back()->with('error', 'This correction has already been reviewed.');
        }

        $validated = $request->validate([
            'review_action' => ['required', 'string', 'in:finalize_selected,reject_all'],
            'accepted_keys' => ['exclude_if:review_action,reject_all', 'required', 'array', 'min:1'],
            'accepted_keys.*' => ['string'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $acceptedKeys = [];
        if ($validated['review_action'] === 'finalize_selected') {
            $changes = $service->visibleChanges($correction);
            $acceptedKeys = array_values(array_intersect($validated['accepted_keys'] ?? [], array_keys($changes)));

            if ($acceptedKeys === []) {
                throw ValidationException::withMessages([
                    'accepted_keys' => ['Select at least one valid correction change to accept.'],
                ]);
            }
        }

        try {
            $reviewedCorrection = $service->review(
                $correction,
                $request->user(),
                $acceptedKeys,
                $validated['review_note'] ?? null
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['accepted_keys' => [$exception->getMessage()]]);
        }

        return redirect()
            ->route('tournament-corrections.show', $reviewedCorrection)
            ->with(
                'success',
                $reviewedCorrection->status === TournamentCorrection::STATUS_PROCESSING
                    ? 'Correction review saved. Member changes are now processing in the background.'
                    : 'Correction review saved.'
            );
    }
}
