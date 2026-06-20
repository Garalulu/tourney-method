<?php

namespace App\Services;

use App\Models\ImportJob;
use App\Models\MatchGame;
use App\Models\MatchScore;
use App\Models\OsuMatch;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Models\UserMatchParticipation;
use Illuminate\Support\Facades\Log;

/**
 * Service for importing historical tournament and match data
 * from tcomm.hivie.tn and o!TR APIs
 */
class ImportService
{
    public function __construct(
        private TcommApiService $tcommApi,
        private OtrApiService $otrApi,
    ) {}

    /**
     * Import tournament from tcomm data
     *
     * @param  array<string, mixed>  $tcommData  Raw tcomm tournament data
     * @param  bool  $dryRun  If true, don't save to database
     * @return array{success: bool, tournament: ?Tournament, action: string}
     */
    public function importTournamentFromTcomm(array $tcommData, bool $dryRun = false): array
    {
        $formatted = $this->tcommApi->formatTournamentData($tcommData);
        $tcommId = $formatted['tcomm_id'];

        // Check if tournament already exists by tcomm_id
        $existing = Tournament::where('tcomm_id', $tcommId)->first();

        if ($existing) {
            // Update existing tournament - only overwrite non-null TCOMM values
            if (! $dryRun) {
                // Filter out null values from TCOMM data to preserve existing DB values
                $updateData = array_filter($formatted, fn ($value) => $value !== null);
                $existing->update($updateData);

                // Add host to tournament_staff if available
                if (! empty($formatted['host_osu_id']) && ! empty($formatted['host_username'])) {
                    $this->addHostAsStaff($existing, $formatted['host_osu_id'], $formatted['host_username']);
                }
            }

            return [
                'success' => true,
                'tournament' => $dryRun ? null : $existing,
                'action' => 'updated',
            ];
        }

        // Check for duplicate by forum_post_id
        if (! empty($formatted['forum_topic_id'])) {
            $byForum = Tournament::where('forum_topic_id', $formatted['forum_topic_id'])->first();
            if ($byForum) {
                // Link tcomm_id to existing tournament
                if (! $dryRun) {
                    $byForum->update(['tcomm_id' => $tcommId, 'import_source' => 'tcomm']);
                }

                return [
                    'success' => true,
                    'tournament' => $dryRun ? null : $byForum,
                    'action' => 'linked',
                ];
            }
        }

        // Create new tournament
        if ($dryRun) {
            return [
                'success' => true,
                'tournament' => null,
                'action' => 'created',
            ];
        }

        $tournament = Tournament::create($formatted);

        // Add host to tournament_staff if available
        if (! empty($formatted['host_osu_id']) && ! empty($formatted['host_username'])) {
            $this->addHostAsStaff($tournament, $formatted['host_osu_id'], $formatted['host_username']);
        }

        return [
            'success' => true,
            'tournament' => $tournament,
            'action' => 'created',
        ];
    }

    /**
     * Import tournament from o!TR data
     *
     * @param  array<string, mixed>  $otrData  Raw o!TR tournament data
     * @param  bool  $dryRun  If true, don't save to database
     * @return array{success: bool, tournament: ?Tournament, action: string}
     */
    public function importTournamentFromOtr(array $otrData, bool $dryRun = false): array
    {
        $formatted = $this->otrApi->formatTournamentData($otrData);
        $otrId = $formatted['otr_id'];

        // Check if tournament already exists by otr_id
        $existing = Tournament::where('otr_id', $otrId)->first();

        if ($existing) {
            // Update existing tournament - only overwrite non-null OTR values
            if (! $dryRun) {
                // Filter out null values from OTR data to preserve existing DB values
                $updateData = array_filter($formatted, fn ($value) => $value !== null);
                $existing->update($updateData);
            }

            return [
                'success' => true,
                'tournament' => $dryRun ? null : $existing,
                'action' => 'updated',
            ];
        }

        // Try to link to existing tournament by forum_post_id (any source)
        // ONLY link if the tournament doesn't already have an otr_id
        if (! empty($formatted['forum_topic_id'])) {
            /** @var Tournament|null $existingTournament */
            $existingTournament = Tournament::where('forum_topic_id', $formatted['forum_topic_id'])
                ->whereNull('otr_id')  // Only link if no otr_id set yet
                ->first();

            if ($existingTournament) {
                // Link otr_id to existing tournament
                if (! $dryRun) {
                    $existingTournament->update(['otr_id' => $otrId]);
                }

                return [
                    'success' => true,
                    'tournament' => $dryRun ? null : $existingTournament,
                    'action' => 'linked',
                ];
            }
        }

        // Try fuzzy match by name and date
        $fuzzyMatch = $this->findTournamentByFuzzyMatch($otrData);
        if ($fuzzyMatch) {
            if (! $dryRun) {
                $fuzzyMatch->update(['otr_id' => $otrId]);
            }

            return [
                'success' => true,
                'tournament' => $dryRun ? null : $fuzzyMatch,
                'action' => 'linked_fuzzy',
            ];
        }

        // Create new tournament
        if ($dryRun) {
            return [
                'success' => true,
                'tournament' => null,
                'action' => 'created',
            ];
        }

        $tournament = Tournament::create($formatted);

        return [
            'success' => true,
            'tournament' => $tournament,
            'action' => 'created',
        ];
    }

    /**
     * Link o!TR tournament to tcomm tournament
     *
     * @param  int  $otrId  o!TR tournament ID
     * @param  string  $tcommId  tcomm tournament ID
     * @param  bool  $dryRun  If true, don't save to database
     * @return array{success: bool, tournament: ?Tournament}
     */
    public function linkOtrToTcomm(int $otrId, string $tcommId, bool $dryRun = false): array
    {
        $tournament = Tournament::where('tcomm_id', $tcommId)->first();

        if (! $tournament) {
            return [
                'success' => false,
                'tournament' => null,
            ];
        }

        if (! $dryRun) {
            $tournament->update(['otr_id' => $otrId]);
        }

        return [
            'success' => true,
            'tournament' => $tournament,
        ];
    }

    /**
     * Import matches from o!TR tournament data
     *
     * @param  int  $tournamentId  Local tournament ID
     * @param  array<string, mixed>  $otrData  o!TR tournament data with matches
     * @param  bool  $dryRun  If true, don't save to database
     * @return array{matches_imported: int, matches_failed: int, errors: array<int, string>}
     */
    public function importMatchesFromOtr(int $tournamentId, array $otrData, bool $dryRun = false): array
    {
        $matches = $otrData['matches'] ?? [];
        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($matches as $matchData) {
            try {
                $formatted = $this->otrApi->formatMatchData($matchData, $tournamentId);
                $osuMatchId = $formatted['osu_match_id'];

                // Check if match already exists
                $existing = OsuMatch::where('osu_match_id', $osuMatchId)->first();
                if ($existing) {
                    continue; // Skip existing matches
                }

                if ($dryRun) {
                    $imported++;

                    continue;
                }

                // Create match
                $match = OsuMatch::create($formatted);

                // Import games and scores
                $this->importMatchGames($match->id, $matchData['games'] ?? [], $dryRun);

                $imported++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Match {$matchData['osuId']}: {$e->getMessage()}";
                Log::error('Failed to import match', [
                    'match_id' => $matchData['osuId'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'matches_imported' => $imported,
            'matches_failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Import games and scores for a match
     *
     * @param  int  $matchId  Local match ID
     * @param  array<int, array<string, mixed>>  $games  Array of game data from o!TR
     * @param  bool  $dryRun  If true, don't save to database
     */
    private function importMatchGames(int $matchId, array $games, bool $dryRun = false): void
    {
        foreach ($games as $gameData) {
            try {
                $formattedGame = $this->otrApi->formatGameData($gameData, $matchId);

                if ($dryRun) {
                    continue;
                }

                $game = MatchGame::create($formattedGame);

                // Import scores
                $scores = $gameData['scores'] ?? [];
                foreach ($scores as $scoreData) {
                    $formattedScore = $this->otrApi->formatScoreData($scoreData, $game->id);
                    MatchScore::create($formattedScore);
                }
            } catch (\Exception $e) {
                Log::error('Failed to import match game', [
                    'match_id' => $matchId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Link users to matches they participated in
     * This creates UserMatchParticipation records for statistics
     *
     * @param  int  $matchId  Local match ID
     * @param  bool  $dryRun  If true, don't save to database
     * @return array{participants_linked: int}
     */
    public function linkUsersToMatches(int $matchId, bool $dryRun = false): array
    {
        $match = OsuMatch::with(['games.scores'])->findOrFail($matchId);

        // Get all unique osu_user_ids from scores
        $osuUserIds = $match->games
            ->flatMap->scores
            ->pluck('osu_user_id')
            ->unique()
            ->filter()
            ->values();

        $linked = 0;

        foreach ($osuUserIds as $osuUserId) {
            // Find local user by osu_id
            $user = User::where('osu_id', $osuUserId)->first();
            if (! $user) {
                continue; // Skip unregistered users
            }

            // Check if participation already exists
            $existing = UserMatchParticipation::where('user_id', $user->id)
                ->where('match_id', $matchId)
                ->first();

            if ($existing) {
                continue;
            }

            if ($dryRun) {
                $linked++;

                continue;
            }

            // Calculate participation stats
            $userScores = $match->games->flatMap->scores
                ->where('osu_user_id', $osuUserId);

            $gamesPlayed = $userScores->count();
            $gamesWon = $userScores->where('passed', true)->count(); // Simplified
            $totalScore = $userScores->sum('score');
            $avgAccuracy = $userScores->avg('accuracy');

            UserMatchParticipation::create([
                'user_id' => $user->id,
                'match_id' => $matchId,
                'games_played' => $gamesPlayed,
                'games_won' => $gamesWon,
                'total_score' => $totalScore,
                'avg_accuracy' => $avgAccuracy,
            ]);

            $linked++;
        }

        return [
            'participants_linked' => $linked,
        ];
    }

    /**
     * Find tournament by fuzzy matching name and date range
     *
     * @param  array<string, mixed>  $otrData  o!TR tournament data
     */
    private function findTournamentByFuzzyMatch(array $otrData): ?Tournament
    {
        $name = $otrData['name'] ?? '';
        $startTime = $otrData['startTime'] ?? null;

        if (! $name) {
            return null;
        }

        $query = Tournament::where('status', 'approved')
            ->where('title', 'ILIKE', '%'.$name.'%');

        // If we have date info, filter by overlapping date range
        if ($startTime) {
            $startDate = date('Y-m-d', strtotime($startTime));
            $query->where(function ($q) use ($startDate) {
                $q->whereDate('tournament_start', '<=', $startDate)
                    ->where(function ($q2) use ($startDate) {
                        $q2->whereNull('tournament_end')
                            ->orWhereDate('tournament_end', '>=', $startDate);
                    });
            });
        }

        return $query->first();
    }

    /**
     * Run full import from tcomm
     *
     * @param  ImportJob  $job  The import job to track progress
     * @param  bool  $dryRun  If true, don't save to database
     * @return array{tournaments_imported: int, tournaments_updated: int, tournaments_failed: int}
     */
    public function runTcommImport(ImportJob $job, bool $dryRun = false): array
    {
        $imported = 0;
        $updated = 0;
        $failed = 0;

        $tournaments = $this->tcommApi->getAllTournaments(['state' => 'archived'], function ($page, $lastPage, $totalCount) use ($job) {
            $job->updateProgress(['last_cursor' => "page:{$page}/{$lastPage},total:{$totalCount}"]);
        });

        foreach ($tournaments as $tcommData) {
            try {
                $result = $this->importTournamentFromTcomm($tcommData, $dryRun);

                if ($result['action'] === 'created') {
                    $imported++;
                } elseif (in_array($result['action'], ['updated', 'linked'])) {
                    $updated++;
                }
            } catch (\Exception $e) {
                $failed++;
                $job->addError('tournament', $tcommData['_id'] ?? $tcommData['id'] ?? 'unknown', $e->getMessage());
            }
        }

        return [
            'tournaments_imported' => $imported,
            'tournaments_updated' => $updated,
            'tournaments_failed' => $failed,
        ];
    }

    /**
     * Run full import from o!TR
     *
     * @param  ImportJob  $job  The import job to track progress
     * @param  bool  $dryRun  If true, don't save to database
     * @return array{tournaments_imported: int, tournaments_updated: int, tournaments_failed: int, matches_imported: int, matches_failed: int}
     */
    public function runOtrImport(ImportJob $job, bool $dryRun = false, ?int $limit = null): array
    {
        // Set up persistent rate limit state
        $this->otrApi->setImportJob($job);

        $tournamentsImported = 0;
        $tournamentsUpdated = 0;
        $tournamentsFailed = 0;
        $matchesImported = 0;
        $matchesFailed = 0;

        $tournaments = $this->otrApi->getTournaments();

        // Check for resume: find last successfully processed OTR tournament ID in FULL list
        $startingIndex = 0;
        $lastCursor = $job->last_cursor;

        if ($lastCursor && preg_match('/^last_otr_id:(\d+)$/', $lastCursor, $matches)) {
            $lastOtrId = (int) $matches[1];

            // Find the index of this tournament in the FULL list
            foreach ($tournaments as $index => $tournament) {
                if ($tournament['id'] === $lastOtrId) {
                    $startingIndex = $index + 1; // Start after the last processed one
                    break;
                }
            }

            if ($startingIndex > 0) {
                $job->updateProgress([
                    'tournaments_imported' => $job->tournaments_imported,
                    'tournaments_updated' => $job->tournaments_updated,
                    'tournaments_failed' => $job->tournaments_failed,
                    'matches_imported' => $job->matches_imported,
                    'matches_failed' => $job->matches_failed,
                ]);
            }
        }

        // Apply limit AFTER finding startingIndex (for resume support)
        if ($limit !== null) {
            // Slice starting from startingIndex, take up to $limit items
            $tournaments = array_slice($tournaments, $startingIndex, $limit);
            // Reset startingIndex to 0 since we've already sliced to the right position
            $startingIndex = 0;
        }

        $totalTournaments = count($tournaments);

        // Process tournaments starting from resume point
        for ($index = $startingIndex; $index < $totalTournaments; $index++) {
            $otrTournament = $tournaments[$index];

            try {
                // Get full tournament data with matches
                $otrData = $this->otrApi->getTournamentWithMatches($otrTournament['id']);

                // Import tournament
                $result = $this->importTournamentFromOtr($otrData, $dryRun);
                $tournament = $result['tournament'];

                if ($result['action'] === 'created') {
                    $tournamentsImported++;
                } elseif (in_array($result['action'], ['updated', 'linked', 'linked_fuzzy'])) {
                    $tournamentsUpdated++;
                }

                // Import matches if we have a tournament
                // DISABLED: Only importing tournaments for now
                // if ($tournament && isset($otrData['matches'])) {
                //     $matchResult = $this->importMatchesFromOtr(
                //         $tournament->id,
                //         $otrData,
                //         $dryRun
                //     );
                //
                //     $matchesImported += $matchResult['matches_imported'];
                //     $matchesFailed += $matchResult['matches_failed'];
                // }

                // Update cursor with last successfully processed OTR tournament ID
                $job->updateProgress([
                    'tournaments_imported' => $job->tournaments_imported + $tournamentsImported,
                    'tournaments_updated' => $job->tournaments_updated + $tournamentsUpdated,
                    'tournaments_failed' => $job->tournaments_failed + $tournamentsFailed,
                    'matches_imported' => $job->matches_imported + $matchesImported,
                    'matches_failed' => $job->matches_failed + $matchesFailed,
                    'last_cursor' => "last_otr_id:{$otrTournament['id']}",
                ]);
            } catch (\Exception $e) {
                $tournamentsFailed++;
                $job->addError('tournament', $otrTournament['id'] ?? 'unknown', $e->getMessage());
            }
        }

        return [
            'tournaments_imported' => $tournamentsImported,
            'tournaments_updated' => $tournamentsUpdated,
            'tournaments_failed' => $tournamentsFailed,
            'matches_imported' => $matchesImported,
            'matches_failed' => $matchesFailed,
        ];
    }

    /**
     * Link all existing match scores to users (bulk operation)
     * Use this after importing matches to populate participation records
     *
     * @param  bool  $dryRun  If true, don't save to database
     * @return array{processed: int, linked: int}
     */
    public function linkAllUsersToMatches(bool $dryRun = false): array
    {
        $processed = 0;
        $linked = 0;

        OsuMatch::approved()->chunk(100, function ($matches) use (&$processed, &$linked, $dryRun) {
            foreach ($matches as $match) {
                $processed++;
                $result = $this->linkUsersToMatches($match->id, $dryRun);
                $linked += $result['participants_linked'];
            }
        });

        return [
            'processed' => $processed,
            'linked' => $linked,
        ];
    }

    /**
     * Add host as tournament staff (organizer role)
     * Creates user if doesn't exist, then adds to tournament_staff
     *
     * @param  Tournament  $tournament  The tournament to add staff to
     * @param  int  $osuId  Host's osu! ID
     * @param  string  $username  Host's username
     */
    private function addHostAsStaff(Tournament $tournament, int $osuId, string $username): void
    {
        // Find or create user
        $user = User::where('osu_id', $osuId)->first();

        if (! $user) {
            $user = User::create([
                'osu_id' => $osuId,
                'username' => $username,
                // Other fields will be filled by osu! API sync if needed
            ]);
        }

        // Check if already in staff
        $existingStaff = TournamentStaff::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $existingStaff) {
            TournamentStaff::create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'role' => 'organizer',
                'status' => 'approved',
                'source' => 'parsed', // From tcomm API import
            ]);
        }
    }
}
