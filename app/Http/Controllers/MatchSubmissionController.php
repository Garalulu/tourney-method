<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitMatchRequest;
use App\Jobs\ImportMatchJob;
use App\Models\OsuMatch;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MatchSubmissionController extends Controller
{
    /**
     * Show the match submission form.
     */
    public function create(): ViewContract
    {
        return view('profile.add-match');
    }

    /**
     * Store a new match submission.
     *
     * Per OpenAPI contract: Returns 201 Created with Match schema.
     * Match schema: id, osu_match_id, name, tournament, start_time, end_time, status
     */
    public function store(SubmitMatchRequest $request): RedirectResponse|JsonResponse
    {
        $osuMatchId = $request->getMatchId();
        $userId = Auth::id();
        $tournamentId = $request->input('tournament_id');

        // Check for duplicate match (409 Conflict per OpenAPI spec)
        if (OsuMatch::where('osu_match_id', $osuMatchId)->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This match has already been imported.',
                    'errors' => [
                        'mp_link' => ['This match has already been imported.'],
                    ],
                ], 409);
            }

            return redirect()->back()
                ->withErrors(['mp_link' => 'This match has already been imported.'])
                ->withInput();
        }

        // Create pending match record per OpenAPI spec
        $match = OsuMatch::create([
            'osu_match_id' => $osuMatchId,
            'name' => 'Importing...', // Will be updated by job
            'submitted_by' => $userId,
            'tournament_id' => $tournamentId,
            'status' => 'pending',
        ]);

        // Dispatch the import job to fetch full match data
        ImportMatchJob::dispatch($osuMatchId, $userId, $tournamentId);

        // Return appropriate response based on request type
        if ($request->expectsJson()) {
            // Per OpenAPI Match schema: id, osu_match_id, name, tournament, start_time, end_time, status
            return response()->json([
                'id' => $match->id,
                'osu_match_id' => $match->osu_match_id,
                'name' => $match->name,
                'tournament' => $match->tournament ? [
                    'id' => $match->tournament->id,
                    'title' => $match->tournament->title,
                ] : null,
                'start_time' => $match->start_time?->toIso8601String(),
                'end_time' => $match->end_time?->toIso8601String(),
                'status' => $match->status,
            ], 201);
        }

        return redirect()->back()->with('success', 'Match submitted for review');
    }
}
