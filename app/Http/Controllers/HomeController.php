<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Show the application homepage.
     */
    public function index(): View
    {
        // Start with active tournaments (approved, not ended)
        $query = Tournament::active();

        // For authenticated users, apply personalized filters
        if (Auth::check()) {
            $user = Auth::user();

            // Filter by user's main game mode
            // Use raw JSON query to handle both legacy ["osu"] and enhanced [{mode: "osu", key_count: null}] formats
            $query->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements(modes::jsonb) AS mode_elem
                WHERE mode_elem->>'mode' = ?
            )", [$user->main_mode]);

            // Include region-restricted tournaments only if user's country is eligible
            // (no restriction OR user's country is in restricted list)
            $query->where(function ($restrictionQuery) use ($user) {
                $restrictionQuery
                    ->whereNull('restricted_countries')
                    ->orWhereJsonLength('restricted_countries', 0)
                    ->orWhereJsonContains('restricted_countries', strtoupper($user->country_code ?? ''));
            });
        } else {
            // For guests, exclude all region-restricted tournaments
            $query->where(function ($restrictionQuery) {
                $restrictionQuery
                    ->whereNull('restricted_countries')
                    ->orWhereJsonLength('restricted_countries', 0);
            });
        }

        // Sort by latest registration end date first (tournaments closing soonest appear first)
        $query->orderBy('registration_end', 'desc');

        // Limit to 6 tournaments
        $featuredTournaments = $query->limit(6)->get();

        $activeSince = now()->subYear();

        $activeParticipationUsers = DB::table('tournament_participation_records')
            ->join('tournaments', 'tournaments.id', '=', 'tournament_participation_records.tournament_id')
            ->where('tournament_participation_records.review_status', TournamentParticipationRecord::REVIEW_APPROVED)
            ->where('tournaments.status', Tournament::STATUS_APPROVED)
            ->where('tournaments.tournament_end', '>=', $activeSince)
            ->whereNotNull('tournament_participation_records.user_id')
            ->select('tournament_participation_records.user_id');

        $activePodiumUsers = DB::table('tournament_winners')
            ->join('tournaments', 'tournaments.id', '=', 'tournament_winners.tournament_id')
            ->where('tournament_winners.placement', '<=', 3)
            ->where('tournaments.status', Tournament::STATUS_APPROVED)
            ->where('tournaments.tournament_end', '>=', $activeSince)
            ->whereNotNull('tournament_winners.user_id')
            ->select('tournament_winners.user_id');

        $playerCount = DB::query()
            ->fromSub($activeParticipationUsers->union($activePodiumUsers), 'active_players')
            ->distinct()
            ->count('user_id');

        $staffCount = DB::table('tournament_staff')
            ->join('tournaments', 'tournaments.id', '=', 'tournament_staff.tournament_id')
            ->where('tournament_staff.status', 'approved')
            ->where('tournaments.status', Tournament::STATUS_APPROVED)
            ->where('tournaments.tournament_end', '>=', $activeSince)
            ->whereNotNull('tournament_staff.user_id')
            ->distinct()
            ->count('tournament_staff.user_id');

        // Count approved tournaments
        $tournamentCount = Tournament::approved()->count();

        return view('welcome', [
            'featuredTournaments' => $featuredTournaments,
            'playerCount' => $playerCount,
            'staffCount' => $staffCount,
            'tournamentCount' => $tournamentCount,
        ]);
    }
}
