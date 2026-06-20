<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OsuMatch;
use App\Models\Tournament;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MatchController extends Controller
{
    /**
     * Display a list of pending matches.
     *
     * Per OpenAPI: GET /admin/matches/pending
     * Returns PaginatedPendingMatches schema
     */
    public function pending(Request $request): JsonResponse|View
    {
        $matches = OsuMatch::where('status', 'pending')
            ->with(['submitter', 'tournament'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $matches->map(fn ($match) => [
                    'id' => $match->id,
                    'name' => $match->name,
                    'submitted_by' => $match->submitter ? [
                        'id' => $match->submitter->id,
                        'username' => $match->submitter->username,
                    ] : null,
                    'submitted_at' => $match->created_at->toIso8601String(),
                ]),
                'links' => [
                    'first' => $matches->url(1),
                    'last' => $matches->url($matches->lastPage()),
                    'prev' => $matches->previousPageUrl(),
                    'next' => $matches->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $matches->currentPage(),
                    'from' => $matches->firstItem(),
                    'last_page' => $matches->lastPage(),
                    'links' => collect($matches->linkCollection())->map(fn ($link) => [
                        'url' => $link['url'],
                        'label' => $link['label'],
                        'active' => $link['active'],
                    ])->all(),
                    'path' => $matches->path(),
                    'per_page' => $matches->perPage(),
                    'to' => $matches->lastItem(),
                    'total' => $matches->total(),
                ],
            ]);
        }

        return view('admin.matches.pending', compact('matches'));
    }

    /**
     * Display the specified match for review.
     */
    public function show(int $id): View
    {
        $match = OsuMatch::with([
            'submitter',
            'tournament',
            'games.scores.user',
        ])->findOrFail($id);

        $tournaments = Tournament::where('status', 'approved')
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('admin.matches.show', compact('match', 'tournaments'));
    }

    /**
     * Approve a match.
     */
    public function approve(int $id, AuditLogger $auditLogger): RedirectResponse
    {
        $match = OsuMatch::findOrFail($id);

        $match->status = 'approved';
        $match->reviewed_by = Auth::id();
        $match->reviewed_at = now();
        $match->save();

        // Log the action
        $auditLogger->log(
            action: 'match.approve',
            entityType: 'match',
            entityId: $match->id,
            details: [
                'match_name' => $match->name,
                'osu_match_id' => $match->osu_match_id,
                'submitted_by' => $match->submitted_by,
            ]
        );

        return redirect()->route('admin.matches.pending')
            ->with('success', 'Match approved successfully.');
    }

    /**
     * Reject a match.
     */
    public function reject(int $id, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $match = OsuMatch::findOrFail($id);

        $match->status = 'rejected';
        $match->reviewed_by = Auth::id();
        $match->reviewed_at = now();
        $match->save();

        // Log the action
        $auditLogger->log(
            action: 'match.reject',
            entityType: 'match',
            entityId: $match->id,
            details: [
                'match_name' => $match->name,
                'osu_match_id' => $match->osu_match_id,
                'submitted_by' => $match->submitted_by,
                'reason' => $request->input('reason'),
            ]
        );

        return redirect()->route('admin.matches.pending')
            ->with('success', 'Match rejected.');
    }

    /**
     * Update match details (e.g., tournament association).
     */
    public function update(int $id, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $request->validate([
            'tournament_id' => 'nullable|exists:tournaments,id',
        ]);

        $match = OsuMatch::findOrFail($id);

        $oldTournamentId = $match->tournament_id;
        $newTournamentId = $request->input('tournament_id');

        $match->tournament_id = $newTournamentId;
        $match->save();

        // Log the action
        $auditLogger->log(
            action: 'match.edit',
            entityType: 'match',
            entityId: $match->id,
            details: [
                'match_name' => $match->name,
                'osu_match_id' => $match->osu_match_id,
                'old_tournament_id' => $oldTournamentId,
                'new_tournament_id' => $newTournamentId,
            ]
        );

        return redirect()->back()
            ->with('success', 'Match updated successfully.');
    }
}
