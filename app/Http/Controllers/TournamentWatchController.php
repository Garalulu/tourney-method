<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentWatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentWatchController extends Controller
{
    /**
     * Watch a tournament (POST /tournaments/{tournamentId}/watch)
     *
     * Per OpenAPI spec: watchTournament
     * - Creates or updates watch status
     * - Returns 200 on success
     * - Returns 401 if unauthenticated
     * - Returns 404 if tournament not found
     */
    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'watch_type' => 'required|in:watching,stream',
        ]);

        // Only allow watching approved tournaments
        if ($tournament->status !== 'approved') {
            abort(404);
        }

        $watch = TournamentWatch::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'tournament_id' => $tournament->id,
            ],
            [
                'watch_type' => $validated['watch_type'],
                'notify_registration_close' => $validated['watch_type'] === 'watching',
                'notify_stream_live' => $validated['watch_type'] === 'stream',
            ]
        );

        return response()->json([
            'message' => 'Watch created/updated',
            'watch_type' => $watch->watch_type,
        ]);
    }

    /**
     * Unwatch a tournament (DELETE /tournaments/{tournamentId}/watch)
     *
     * Per OpenAPI spec: unwatchTournament
     * - Removes watch record
     * - Returns 200 on success
     * - Returns 401 if unauthenticated
     */
    public function destroy(Tournament $tournament): JsonResponse
    {
        TournamentWatch::where('user_id', Auth::id())
            ->where('tournament_id', $tournament->id)
            ->delete();

        return response()->json([
            'message' => 'Watch removed',
        ]);
    }
}
