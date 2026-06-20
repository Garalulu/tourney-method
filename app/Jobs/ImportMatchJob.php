<?php

namespace App\Jobs;

use App\Domain\Participation\MatchWinCalculator;
use App\Models\MatchGame;
use App\Models\MatchScore;
use App\Models\OsuMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserMatchParticipation;
use App\Services\OsuApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ImportMatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [60, 120, 240];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $matchId,
        public int $userId,
        public ?int $tournamentId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OsuApiService $osuApiService): void
    {
        try {
            Log::info("Starting match import for match {$this->matchId} by user {$this->userId}");

            // Step 1: Fetch match data from osu! API first
            $matchData = $osuApiService->getMatch($this->matchId);

            if (! $matchData) {
                throw new \Exception('Failed to fetch match data from osu! API');
            }

            // Step 2: Validate user participated in match (T097)
            $this->validateUserParticipation($matchData);

            // Step 3: Check for existing match (deduplication - T101)
            $existingMatch = OsuMatch::where('osu_match_id', $this->matchId)->first();

            if ($existingMatch) {
                // If match was just created by controller (has 'Importing...' name), update it
                if ($existingMatch->name === 'Importing...') {
                    Log::info("Updating pending match {$this->matchId} with API data");
                    DB::beginTransaction();
                    try {
                        $this->updateMatchFromApi($existingMatch, $matchData);
                        $this->createMatchGames($existingMatch, $matchData);
                        $this->createUserMatchParticipation($existingMatch, $matchData);
                        if (! $this->tournamentId) {
                            $this->autoSuggestTournament($existingMatch, $matchData);
                        }
                        DB::commit();
                        Log::info("Successfully updated match {$this->matchId}");

                        return;
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }

                Log::info("Match {$this->matchId} already exists, linking to user {$this->userId}");
                $this->linkUserToExistingMatch($existingMatch, $matchData);

                return;
            }

            // Step 4: Begin transaction for data integrity
            DB::beginTransaction();

            try {
                // Step 5: Create Match record
                $match = $this->createMatch($matchData);

                // Step 6: Create MatchGame records
                $this->createMatchGames($match, $matchData);

                // Step 7: Create UserMatchParticipation for submitter
                $this->createUserMatchParticipation($match, $matchData);

                // Step 8: Auto-suggest tournament (T098)
                if (! $this->tournamentId) {
                    $this->autoSuggestTournament($match, $matchData);
                }

                DB::commit();

                Log::info("Successfully imported match {$this->matchId}");
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            Log::warning("Validation failed for match {$this->matchId}: {$e->getMessage()}");
            $this->fail($e);
            throw $e;
        } catch (\Exception $e) {
            Log::error("Failed to import match {$this->matchId}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Link user to existing match.
     *
     * @param  array<string, mixed>  $matchData
     */
    protected function linkUserToExistingMatch(OsuMatch $match, array $matchData): void
    {
        // Check if user already linked
        $existing = UserMatchParticipation::where('user_id', $this->userId)
            ->where('match_id', $match->id)
            ->exists();

        if ($existing) {
            Log::info("User {$this->userId} already linked to match {$match->id}");

            return;
        }

        // Check if match has games/scores - if not, create them
        $hasGames = $match->games()->exists();

        if (! $hasGames) {
            Log::info("Match {$match->id} has no games, creating from API data");
            DB::beginTransaction();

            try {
                $this->createMatchGames($match, $matchData);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        // Get match data to calculate stats
        $matchScores = MatchScore::whereHas('game', function ($query) use ($match) {
            $query->where('match_id', $match->id);
        })->where('user_id', $this->userId)->get();

        if ($matchScores->isEmpty()) {
            Log::warning("User {$this->userId} has no scores in match {$match->id}");

            return;
        }

        // Calculate stats
        $gamesPlayed = $matchScores->count();
        $gamesWon = $this->calculateGamesWon($matchScores);
        $totalScore = $matchScores->sum('score');
        $avgAccuracy = $matchScores->avg('accuracy');
        $team = $matchScores->first()->team;

        UserMatchParticipation::create([
            'user_id' => $this->userId,
            'match_id' => $match->id,
            'team' => $team,
            'games_played' => $gamesPlayed,
            'games_won' => $gamesWon,
            'total_score' => $totalScore,
            'avg_accuracy' => $avgAccuracy,
        ]);

        Log::info("Linked user {$this->userId} to existing match {$match->id}");
    }

    /**
     * Validate that the user participated in the match.
     *
     * @param  array<string, mixed>  $matchData
     *
     * @throws ValidationException
     */
    protected function validateUserParticipation(array $matchData): void
    {
        // Get user's osu_id
        $user = User::find($this->userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => 'User not found',
            ]);
        }

        // Check if user is in the users array
        $userIds = array_column($matchData['users'] ?? [], 'id');

        if (! in_array($user->osu_id, $userIds)) {
            throw ValidationException::withMessages([
                'participation' => 'You did not participate in this match',
            ]);
        }
    }

    /**
     * Create Match record.
     *
     * @param  array<string, mixed>  $matchData
     */
    protected function createMatch(array $matchData): OsuMatch
    {
        $match = $matchData['match'] ?? [];

        return OsuMatch::create([
            'osu_match_id' => $this->matchId,
            'name' => $match['name'] ?? 'Unknown Match',
            'tournament_id' => $this->tournamentId,
            'start_time' => isset($match['start_time']) ? Carbon::parse($match['start_time']) : null,
            'end_time' => isset($match['end_time']) ? Carbon::parse($match['end_time']) : null,
            'status' => 'pending',
            'submitted_by' => $this->userId,
            'raw_data' => $matchData,
        ]);
    }

    /**
     * Update existing match record with API data.
     *
     * @param  array<string, mixed>  $matchData
     */
    protected function updateMatchFromApi(OsuMatch $match, array $matchData): void
    {
        $apiMatch = $matchData['match'] ?? [];

        $match->update([
            'name' => $apiMatch['name'] ?? 'Unknown Match',
            'start_time' => isset($apiMatch['start_time']) ? Carbon::parse($apiMatch['start_time']) : null,
            'end_time' => isset($apiMatch['end_time']) ? Carbon::parse($apiMatch['end_time']) : null,
            'raw_data' => $matchData,
        ]);
    }

    /**
     * Create MatchGame and MatchScore records.
     *
     * @param  array<string, mixed>  $matchData
     */
    protected function createMatchGames(OsuMatch $match, array $matchData): void
    {
        $events = $matchData['events'] ?? [];

        foreach ($events as $event) {
            // Skip non-game events
            if (! isset($event['game'])) {
                continue;
            }

            $gameData = $event['game'];

            // Create MatchGame
            $matchGame = MatchGame::create([
                'match_id' => $match->id,
                'game_id' => $gameData['id'],
                'beatmap_id' => $gameData['beatmap_id'] ?? null,
                'beatmap_title' => $gameData['beatmap']['title'] ?? null,
                'beatmap_version' => $gameData['beatmap']['version'] ?? null,
                'mods' => $gameData['mods'] ?? [],
                'mode' => $gameData['mode'] ?? 'osu',
                'scoring_type' => $gameData['scoring_type'] ?? null,
                'team_type' => $gameData['team_type'] ?? null,
                'start_time' => isset($gameData['start_time']) ? Carbon::parse($gameData['start_time']) : null,
                'end_time' => isset($gameData['end_time']) ? Carbon::parse($gameData['end_time']) : null,
            ]);

            // Create MatchScore records for each player
            $scores = $gameData['scores'] ?? [];

            foreach ($scores as $scoreData) {
                // Find user by osu_id
                $userId = User::where('osu_id', $scoreData['user_id'])->value('id');

                MatchScore::create([
                    'match_game_id' => $matchGame->id,
                    'user_id' => $userId,
                    'osu_user_id' => $scoreData['user_id'],
                    'username' => $scoreData['user']['username'] ?? 'Unknown',
                    'team' => $scoreData['match']['team'] ?? null,
                    'score' => $scoreData['score'] ?? 0,
                    'accuracy' => $scoreData['accuracy'] ?? null,
                    'max_combo' => $scoreData['max_combo'] ?? null,
                    'count_300' => $scoreData['statistics']['count_300'] ?? null,
                    'count_100' => $scoreData['statistics']['count_100'] ?? null,
                    'count_50' => $scoreData['statistics']['count_50'] ?? null,
                    'count_miss' => $scoreData['statistics']['count_miss'] ?? null,
                    'count_geki' => $scoreData['statistics']['count_geki'] ?? null,
                    'count_katu' => $scoreData['statistics']['count_katu'] ?? null,
                    'perfect' => $scoreData['perfect'] ?? false,
                    'passed' => $scoreData['passed'] ?? true,
                    'mods' => $scoreData['mods'] ?? [],
                ]);
            }
        }
    }

    /**
     * Create UserMatchParticipation record.
     *
     * @param  array<string, mixed>  $matchData
     */
    protected function createUserMatchParticipation(OsuMatch $match, array $matchData): void
    {
        // Get all scores for this user
        $matchScores = MatchScore::whereHas('game', function ($query) use ($match) {
            $query->where('match_id', $match->id);
        })->where('user_id', $this->userId)->get();

        if ($matchScores->isEmpty()) {
            Log::warning("No scores found for user {$this->userId} in match {$match->id}");

            return;
        }

        // Calculate stats
        $gamesPlayed = $matchScores->count();
        $gamesWon = $this->calculateGamesWon($matchScores);
        $totalScore = $matchScores->sum('score');
        $avgAccuracy = $matchScores->avg('accuracy');
        $team = $matchScores->first()->team;

        UserMatchParticipation::create([
            'user_id' => $this->userId,
            'match_id' => $match->id,
            'team' => $team,
            'games_played' => $gamesPlayed,
            'games_won' => $gamesWon,
            'total_score' => $totalScore,
            'avg_accuracy' => $avgAccuracy,
        ]);
    }

    /**
     * Count games won by the imported user.
     *
     * Team games compare aggregate team scores. Head-to-head games compare the
     * user's score with the highest score in that game. Ties are not wins.
     *
     * @param  Collection<int, MatchScore>  $matchScores
     */
    protected function calculateGamesWon(Collection $matchScores): int
    {
        return app(MatchWinCalculator::class)->count($matchScores);
    }

    /**
     * Auto-suggest tournament based on match name (T098).
     *
     * @param  array<string, mixed>  $matchData
     */
    protected function autoSuggestTournament(OsuMatch $match, array $matchData): void
    {
        $matchName = $match->name;

        // Extract potential acronyms (2+ uppercase letters)
        preg_match_all('/\b[A-Z]{2,}\b/', $matchName, $acronymMatches);
        $acronyms = $acronymMatches[0];

        // Extract year if present
        preg_match('/\b(20\d{2})\b/', $matchName, $yearMatches);
        $year = isset($yearMatches[1]) ? (int) $yearMatches[1] : null;

        // Extract keywords (filter common words)
        $commonWords = ['vs', 'the', 'and', 'or', 'of', 'in', 'at', 'to', 'for'];
        $words = array_filter(
            explode(' ', strtolower($matchName)),
            fn ($word) => strlen($word) > 2 && ! in_array($word, $commonWords)
        );

        // Get approved tournaments (date filtering is done in scoring)
        $tournaments = Tournament::where('status', 'approved')->get();

        if ($tournaments->isEmpty()) {
            Log::info("No tournaments found for auto-suggestion for match {$match->id}");

            return;
        }

        // Score each tournament
        $scores = [];

        foreach ($tournaments as $tournament) {
            $score = 0;

            // Generate acronym from tournament title (e.g., "Taiko World Cup" -> "TWC")
            $titleWords = preg_split('/\s+/', $tournament->title);
            $titleAcronym = '';
            foreach ($titleWords as $word) {
                if (preg_match('/^[A-Z]/', $word)) {
                    $titleAcronym .= strtoupper($word[0]);
                }
            }

            // Exact acronym match in title or acronym generation
            foreach ($acronyms as $acronym) {
                // Direct match in title
                if (stripos($tournament->title, $acronym) !== false) {
                    $score += 100;
                }
                // Acronym matches generated title acronym
                elseif ($titleAcronym && strtoupper($acronym) === $titleAcronym) {
                    $score += 100;
                }
            }

            // Partial keyword match
            foreach ($words as $word) {
                if (stripos($tournament->title, $word) !== false) {
                    $score += 50;
                }
            }

            // Year match
            if ($year && stripos($tournament->title, (string) $year) !== false) {
                $score += 25;
            }

            // Date proximity (if within range)
            if ($match->start_time && $tournament->registration_start && $tournament->tournament_end) {
                if ($match->start_time >= $tournament->registration_start &&
                    $match->start_time <= $tournament->tournament_end) {
                    $score += 25;
                }
            }

            $scores[$tournament->id] = $score;
        }

        // Sort by score descending
        arsort($scores);

        // Get top tournament
        $topTournamentId = array_key_first($scores);
        $topScore = $scores[$topTournamentId] ?? 0;

        Log::info("Auto-suggestion for match {$match->id}: Tournament {$topTournamentId} with score {$topScore}");

        // High confidence: set tournament_id
        if ($topScore >= 100) {
            $match->tournament_id = $topTournamentId;
            $match->save();
            Log::info("Auto-assigned tournament {$topTournamentId} to match {$match->id} (high confidence)");
        } elseif ($topScore >= 50) {
            $match->tournament_id = $topTournamentId;
            $match->save();
            Log::info("Auto-assigned tournament {$topTournamentId} to match {$match->id} (medium confidence, flagged for review)");
        } else {
            Log::info("Low confidence ({$topScore}), leaving tournament_id null for match {$match->id}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ImportMatchJob failed for match {$this->matchId}: {$exception->getMessage()}");
    }
}
