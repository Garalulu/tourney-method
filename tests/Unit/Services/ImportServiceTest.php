<?php

/**
 * ImportService Unit Tests
 *
 * Tests historical data import from tcomm.hivie.tn and o!TR APIs
 * per research.md#13-historical-import-strategy
 *
 * Key features:
 * - Tournament import from tcomm (badge tournaments)
 * - Tournament import from o!TR (match data)
 * - Cross-source linking by forum_post_id
 * - Match and score data import
 * - User match participation linking
 * - Dry-run mode support
 */

use App\Models\ImportJob;
use App\Models\MatchGame;
use App\Models\MatchScore;
use App\Models\OsuMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserMatchParticipation;
use App\Services\ImportService;
use App\Services\OtrApiService;
use App\Services\TcommApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Mock both API services
    Http::fake();

    // Clean database to avoid stale data from previous tests
    DB::table('matches')->delete();
    DB::table('match_games')->delete();
    DB::table('match_scores')->delete();
    DB::table('tournaments')->delete();

    // Use Mockery directly for more control
    $this->tcommApi = Mockery::mock(TcommApiService::class);
    $this->otrApi = Mockery::mock(OtrApiService::class);

    /** @var TcommApiService $tcommApi */
    $tcommApi = $this->tcommApi;
    /** @var OtrApiService $otrApi */
    $otrApi = $this->otrApi;

    $this->importService = new ImportService($tcommApi, $otrApi);
});

describe('ImportService::importTournamentFromTcomm', function () {
    it('creates new tournament from tcomm data', function () {
        $tcommData = [
            'id' => 'tcomm123',
            'name' => 'New Tournament',
            'abbreviation' => 'NT',
            'mode' => 'osu',
            'teamSize' => ['minSize' => 1, 'maxSize' => 2],
            'rankRange' => ['lower' => 1000, 'upper' => 10000],
            'hosts' => [12345],
            'forumPostId' => 54321,
            'registrationStartDate' => '2024-01-01T00:00:00Z',
            'registrationEndDate' => '2024-01-15T00:00:00Z',
            'tournamentStartDate' => '2024-02-01T00:00:00Z',
            'tournamentEndDate' => '2024-02-28T00:00:00Z',
            'links' => [],
        ];

        $this->tcommApi
            ->shouldReceive('formatTournamentData')
            ->with($tcommData)
            ->once()
            ->andReturn([
                'tcomm_id' => 'tcomm123',
                'title' => 'New Tournament',
                'status' => 'approved',
                'is_badge' => true,
                'modes' => ['osu'],
            ]);

        $result = $this->importService->importTournamentFromTcomm($tcommData);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('created');
        expect($result['tournament'])->toBeInstanceOf(Tournament::class);
        expect($result['tournament']->tcomm_id)->toBe('tcomm123');
    });

    it('updates existing tournament with same tcomm_id', function () {
        $existing = Tournament::factory()->create([
            'tcomm_id' => 'tcomm123',
            'title' => 'Old Title',
        ]);

        $tcommData = [
            'id' => 'tcomm123',
            'name' => 'Updated Tournament',
            'abbreviation' => 'UT',
            'mode' => 'osu',
            'teamSize' => ['minSize' => 1, 'maxSize' => 2],
            'rankRange' => ['lower' => 1, 'upper' => 50000],
            'hosts' => [12345],
            'links' => [],
        ];

        $this->tcommApi
            ->shouldReceive('formatTournamentData')
            ->andReturn(['tcomm_id' => 'tcomm123', 'title' => 'Updated Tournament', 'modes' => ['osu']]);

        $result = $this->importService->importTournamentFromTcomm($tcommData);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('updated');
        expect($result['tournament']->id)->toBe($existing->id);
        expect($result['tournament']->title)->toBe('Updated Tournament');
    });

    it('links to existing tournament by forum_post_id', function () {
        $existing = Tournament::factory()->create([
            'forum_topic_id' => 54321,
            'tcomm_id' => null,
        ]);

        $tcommData = [
            'id' => 'tcomm456',
            'name' => 'Linkable Tournament',
            'forumPostId' => 54321,
            'abbreviation' => 'LT',
            'mode' => 'taiko',
            'teamSize' => ['minSize' => 2, 'maxSize' => 4],
            'rankRange' => ['lower' => 1, 'upper' => 50000],
            'hosts' => [12345],
            'links' => [],
        ];

        $this->tcommApi
            ->shouldReceive('formatTournamentData')
            ->andReturn(['tcomm_id' => 'tcomm456', 'forum_topic_id' => 54321, 'title' => 'Linkable Tournament', 'modes' => ['taiko']]);

        $result = $this->importService->importTournamentFromTcomm($tcommData);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('linked');
        expect($result['tournament']->id)->toBe($existing->id);
        expect($result['tournament']->tcomm_id)->toBe('tcomm456');
    });

    it('respects dry-run mode', function () {
        $tcommData = [
            'id' => 'tcomm789',
            'name' => 'Dry Run Tournament',
            'abbreviation' => 'DR',
            'mode' => 'osu',
            'teamSize' => ['minSize' => 1, 'maxSize' => 2],
            'rankRange' => ['lower' => 1, 'upper' => 50000],
            'hosts' => [12345],
            'links' => [],
        ];

        $this->tcommApi
            ->shouldReceive('formatTournamentData')
            ->andReturn(['tcomm_id' => 'tcomm789', 'title' => 'Dry Run Tournament', 'modes' => ['osu']]);

        $result = $this->importService->importTournamentFromTcomm($tcommData, dryRun: true);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('created');
        expect($result['tournament'])->toBeNull();

        // Verify nothing was saved to database
        $tourney = Tournament::where('tcomm_id', 'tcomm789')->first();
        expect($tourney)->toBeNull();
    });
});

describe('ImportService::importTournamentFromOtr', function () {
    it('creates new tournament from o!TR data', function () {
        $otrData = [
            'id' => 123,
            'name' => 'Otr Tournament',
            'abbreviation' => 'OT',
            'forumPostId' => 99999,
            'mode' => 0,
            'startTime' => '2024-01-01T00:00:00Z',
            'endTime' => '2024-01-31T00:00:00Z',
        ];

        $this->otrApi
            ->shouldReceive('formatTournamentData')
            ->with($otrData)
            ->once()
            ->andReturn([
                'otr_id' => 123,
                'title' => 'Otr Tournament',
                'status' => 'approved',
                'modes' => ['osu'],
            ]);

        $result = $this->importService->importTournamentFromOtr($otrData);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('created');
        expect($result['tournament'])->toBeInstanceOf(Tournament::class);
        expect($result['tournament']->otr_id)->toBe(123);
    });

    it('links to existing tcomm tournament by forum_post_id', function () {
        $existing = Tournament::factory()->create([
            'tcomm_id' => 'tcomm123',
            'forum_topic_id' => 54321,
            'otr_id' => null,
        ]);

        $otrData = [
            'id' => 456,
            'name' => 'Linkable OTR',
            'forumPostId' => 54321,
            'mode' => 0,
            'startTime' => '2024-01-01T00:00:00Z',
            'endTime' => '2024-01-31T00:00:00Z',
        ];

        $this->otrApi
            ->shouldReceive('formatTournamentData')
            ->andReturn(['otr_id' => 456, 'forum_topic_id' => 54321, 'title' => 'Linkable OTR', 'modes' => ['osu']]);

        $result = $this->importService->importTournamentFromOtr($otrData);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('linked');
        expect($result['tournament']->id)->toBe($existing->id);
        expect($result['tournament']->otr_id)->toBe(456);
    });

    it('updates existing tournament with same otr_id', function () {
        $existing = Tournament::factory()->create([
            'otr_id' => 123,
            'title' => 'Old OTR Title',
        ]);

        $otrData = [
            'id' => 123,
            'name' => 'Updated OTR',
            'forumPostId' => 11111,
            'mode' => 0,
        ];

        $this->otrApi
            ->shouldReceive('formatTournamentData')
            ->andReturn(['otr_id' => 123, 'title' => 'Updated OTR', 'modes' => ['osu']]);

        $result = $this->importService->importTournamentFromOtr($otrData);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('updated');
        expect($result['tournament']->id)->toBe($existing->id);
    });

    it('performs fuzzy match by name and date', function () {
        $existing = Tournament::factory()->create([
            'title' => 'Similar Tournament Name 2024',
            'status' => 'approved',
            'tournament_start' => '2024-01-01T00:00:00Z',
            'tournament_end' => '2024-01-31T00:00:00Z',
            'otr_id' => null,
        ]);

        $otrData = [
            'id' => 789,
            'name' => 'Tournament Name 2024', // Similar name
            'startTime' => '2024-01-10T00:00:00Z', // Date within range
            'endTime' => '2024-01-20T00:00:00Z',
        ];

        $this->otrApi
            ->shouldReceive('formatTournamentData')
            ->andReturn(['otr_id' => 789, 'title' => 'Tournament Name 2024', 'modes' => ['osu']]);

        $result = $this->importService->importTournamentFromOtr($otrData);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('linked_fuzzy');
        expect($result['tournament']->id)->toBe($existing->id);
        expect($result['tournament']->otr_id)->toBe(789);
    });

    it('respects dry-run mode', function () {
        $otrData = [
            'id' => 999,
            'name' => 'Dry Run OTR',
            'mode' => 0,
        ];

        $this->otrApi
            ->shouldReceive('formatTournamentData')
            ->andReturn(['otr_id' => 999, 'title' => 'Dry Run OTR', 'modes' => ['osu']]);

        $result = $this->importService->importTournamentFromOtr($otrData, dryRun: true);

        expect($result['success'])->toBeTrue();
        expect($result['action'])->toBe('created');
        expect($result['tournament'])->toBeNull();

        $tourney = Tournament::where('otr_id', 999)->first();
        expect($tourney)->toBeNull();
    });
});

describe('ImportService::linkOtrToTcomm', function () {
    it('links otr_id to existing tcomm tournament', function () {
        $tournament = Tournament::factory()->create([
            'tcomm_id' => 'tcomm123',
            'otr_id' => null,
        ]);

        $result = $this->importService->linkOtrToTcomm(456, 'tcomm123');

        expect($result['success'])->toBeTrue();
        expect($result['tournament']->id)->toBe($tournament->id);

        $tournament->refresh();
        expect($tournament->otr_id)->toBe(456);
    });

    it('returns false when tcomm tournament not found', function () {
        $result = $this->importService->linkOtrToTcomm(456, 'nonexistent');

        expect($result['success'])->toBeFalse();
        expect($result['tournament'])->toBeNull();
    });

    it('respects dry-run mode', function () {
        $tournament = Tournament::factory()->create([
            'tcomm_id' => 'tcomm456',
            'otr_id' => null,
        ]);

        $this->importService->linkOtrToTcomm(789, 'tcomm456', dryRun: true);

        $tournament->refresh();
        expect($tournament->otr_id)->toBeNull();
    });
});

describe('ImportService::importMatchesFromOtr', function () {
    it('imports matches with games and scores', function () {
        $tournament = Tournament::factory()->create();
        $submitter = User::factory()->create(); // Required for submitted_by constraint

        $otrData = [
            'matches' => [
                [
                    'matchId' => 1001,
                    'name' => 'Match 1',
                    'startTime' => '2024-01-15T14:00:00Z',
                    'endTime' => '2024-01-15T15:00:00Z',
                    'games' => [],
                ],
            ],
        ];

        $this->otrApi
            ->shouldReceive('formatMatchData')
            ->once()
            ->andReturn([
                'osu_match_id' => 1001,
                'name' => 'Match 1',
                'tournament_id' => $tournament->id,
                'start_time' => '2024-01-15T14:00:00Z',
                'end_time' => '2024-01-15T15:00:00Z',
                'status' => 'approved',
                'submitted_by' => $submitter->id, // Required for NOT NULL constraint
            ]);

        $result = $this->importService->importMatchesFromOtr($tournament->id, $otrData);

        expect($result['matches_imported'])->toBe(1);
        expect($result['matches_failed'])->toBe(0);

        $match = OsuMatch::where('osu_match_id', 1001)->first();
        expect($match)->not->toBeNull();
    });

    it('skips existing matches', function () {
        $tournament = Tournament::factory()->create();
        $existingMatch = OsuMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'osu_match_id' => 1001,
        ]);

        $otrData = [
            'matches' => [
                [
                    'matchId' => 1001,
                    'name' => 'Existing Match',
                    'games' => [],
                ],
            ],
        ];

        $this->otrApi
            ->shouldReceive('formatMatchData')
            ->andReturn([
                'osu_match_id' => 1001,
                'name' => 'Existing Match',
                'status' => 'approved',
            ]);

        $result = $this->importService->importMatchesFromOtr($tournament->id, $otrData);

        expect($result['matches_imported'])->toBe(0);
        expect(OsuMatch::where('osu_match_id', 1001)->count())->toBe(1);
    });

    it('respects dry-run mode', function () {
        $tournament = Tournament::factory()->create();

        $otrData = [
            'matches' => [
                [
                    'matchId' => 2001,
                    'name' => 'Dry Run Match',
                    'games' => [],
                ],
            ],
        ];

        $this->otrApi
            ->shouldReceive('formatMatchData')
            ->andReturn([
                'osu_match_id' => 2001,
                'name' => 'Dry Run Match',
                'status' => 'approved',
            ]);

        $result = $this->importService->importMatchesFromOtr($tournament->id, $otrData, dryRun: true);

        expect($result['matches_imported'])->toBe(1);
        expect(OsuMatch::where('osu_match_id', 2001)->first())->toBeNull();
    });
});

describe('ImportService::linkUsersToMatches', function () {
    it('creates participation records for users in match', function () {
        $user1 = User::factory()->create(['osu_id' => 111]);
        $user2 = User::factory()->create(['osu_id' => 222]);
        // Note: No user with osu_id = 999, so that score won't link to anyone

        $match = OsuMatch::factory()->create();

        $game = MatchGame::factory()->create(['match_id' => $match->id]);

        MatchScore::factory()->create([
            'match_game_id' => $game->id,
            'osu_user_id' => 111,
            'score' => 800000,
            'accuracy' => 0.95,
        ]);

        MatchScore::factory()->create([
            'match_game_id' => $game->id,
            'osu_user_id' => 222,
            'score' => 850000,
            'accuracy' => 0.97,
        ]);

        MatchScore::factory()->create([
            'match_game_id' => $game->id,
            'osu_user_id' => 999, // This score won't match user1's osu_id
            'score' => 900000,
            'accuracy' => 0.98,
        ]);

        $result = $this->importService->linkUsersToMatches($match->id);

        expect($result['participants_linked'])->toBe(2); // Only 111 and 222

        $participations = UserMatchParticipation::where('match_id', $match->id)->get();
        expect($participations)->toHaveCount(2);
        expect($participations->pluck('user_id'))->toContain($user1->id);
        expect($participations->pluck('user_id'))->toContain($user2->id);
    });

    it('skips existing participation records', function () {
        $user = User::factory()->create(['osu_id' => 111]);
        $match = OsuMatch::factory()->create();
        $game = MatchGame::factory()->create(['match_id' => $match->id]);

        MatchScore::factory()->create([
            'match_game_id' => $game->id,
            'osu_user_id' => 111,
        ]);

        UserMatchParticipation::factory()->create([
            'user_id' => $user->id,
            'match_id' => $match->id,
        ]);

        $result = $this->importService->linkUsersToMatches($match->id);

        expect($result['participants_linked'])->toBe(0);
        expect(UserMatchParticipation::where('match_id', $match->id)->count())->toBe(1);
    });

    it('respects dry-run mode', function () {
        $user = User::factory()->create(['osu_id' => 111]);
        $match = OsuMatch::factory()->create();
        $game = MatchGame::factory()->create(['match_id' => $match->id]);

        MatchScore::factory()->create([
            'match_game_id' => $game->id,
            'osu_user_id' => 111,
        ]);

        $result = $this->importService->linkUsersToMatches($match->id, dryRun: true);

        expect($result['participants_linked'])->toBe(1);
        expect(UserMatchParticipation::where('match_id', $match->id)->count())->toBe(0);
    });
});

describe('ImportService::linkAllUsersToMatches', function () {
    it('processes all approved matches', function () {
        $user1 = User::factory()->create(['osu_id' => 111]);
        $user2 = User::factory()->create(['osu_id' => 222]);

        $match1 = OsuMatch::factory()->create(['status' => 'approved']);
        $match2 = OsuMatch::factory()->create(['status' => 'approved']);
        OsuMatch::factory()->create(['status' => 'pending']); // Should be skipped

        foreach ([$match1, $match2] as $match) {
            $game = MatchGame::factory()->create(['match_id' => $match->id]);
            MatchScore::factory()->create([
                'match_game_id' => $game->id,
                'osu_user_id' => $match === $match1 ? 111 : 222,
            ]);
        }

        $result = $this->importService->linkAllUsersToMatches();

        expect($result['processed'])->toBe(2);
        expect($result['linked'])->toBe(2);
    });
});

describe('ImportService::runTcommImport', function () {
    it('imports tournaments from tcomm API', function () {
        $job = ImportJob::factory()->create(['source' => 'tcomm']);

        $tcommData = [
            ['id' => 't1', 'name' => 'Tournament 1', 'forumPostId' => 1, 'mode' => 'osu', 'teamSize' => ['minSize' => 1, 'maxSize' => 2], 'rankRange' => ['lower' => 1, 'upper' => 50000], 'hosts' => [1], 'links' => []],
            ['id' => 't2', 'name' => 'Tournament 2', 'forumPostId' => 2, 'mode' => 'taiko', 'teamSize' => ['minSize' => 1, 'maxSize' => 2], 'rankRange' => ['lower' => 1, 'upper' => 50000], 'hosts' => [1], 'links' => []],
        ];

        $this->tcommApi
            ->shouldReceive('getAllTournaments')
            ->with(['state' => 'archived'], Mockery::type('callable'))
            ->once()
            ->andReturn($tcommData);

        $this->tcommApi
            ->shouldReceive('formatTournamentData')
            ->twice()
            ->andReturnValues([
                ['tcomm_id' => 't1', 'title' => 'Tournament 1', 'status' => 'approved', 'modes' => ['osu']],
                ['tcomm_id' => 't2', 'title' => 'Tournament 2', 'status' => 'approved', 'modes' => ['taiko']],
            ]);

        $result = $this->importService->runTcommImport($job);

        expect($result['tournaments_imported'])->toBe(2);
        expect($result['tournaments_updated'])->toBe(0);
        expect($result['tournaments_failed'])->toBe(0);
    });
});

describe('ImportService::runOtrImport', function () {
    it('imports tournaments and matches from o!TR API', function () {
        $job = ImportJob::factory()->create(['source' => 'otr']);

        $otrTournaments = [
            ['id' => 123, 'name' => 'OTR Tournament'],
        ];

        $this->otrApi
            ->shouldReceive('setImportJob')
            ->once()
            ->with($job);

        $this->otrApi
            ->shouldReceive('getTournaments')
            ->once()
            ->andReturn($otrTournaments);

        $this->otrApi
            ->shouldReceive('getTournamentWithMatches')
            ->once()
            ->andReturn([
                'id' => 123,
                'name' => 'OTR Tournament',
                'mode' => 0,
                'matches' => [],
            ]);

        $this->otrApi
            ->shouldReceive('formatTournamentData')
            ->once()
            ->andReturn(['otr_id' => 123, 'title' => 'OTR Tournament', 'status' => 'approved', 'modes' => ['osu']]);

        $result = $this->importService->runOtrImport($job);

        expect($result['tournaments_imported'])->toBe(1);
        expect($result['matches_imported'])->toBe(0);
        expect($result['tournaments_failed'])->toBe(0);
    });
});
