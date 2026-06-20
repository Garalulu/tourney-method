<?php

use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Services\OsuApiService;
use App\Services\SipService;
use App\Services\UserProfileSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('syncs winner profile data, current main mode rank, badges, and preserves oauth setup mode', function () {
    $user = User::factory()->create([
        'osu_id' => 12345,
        'username' => 'OldName',
        'main_mode' => 'osu',
        'main_mode_source' => 'oauth_setup',
    ]);

    $tournament = Tournament::factory()->create([
        'modes' => ['mania'],
        'tournament_end' => '2025-08-01',
    ]);

    TournamentWinner::factory()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'osu_id' => $user->osu_id,
        'username' => $user->username,
        'placement' => 1,
        'gamemode' => 'mania',
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUserForMode')->once()->with(12345, 'osu')->andReturn([
        'id' => 12345,
        'username' => 'NewName',
        'avatar_url' => 'https://a.ppy.sh/12345',
        'country_code' => 'KR',
        'previous_usernames' => ['OldName'],
        'badges' => [
            [
                'description' => 'Example Cup 2025 Winner',
                'url' => 'https://osu.ppy.sh/community/forums/topics/7654321',
                'image_url' => 'https://assets.ppy.sh/badges/example.png',
                'image@2x_url' => 'https://assets.ppy.sh/badges/example@2x.png',
                'awarded_at' => '2025-09-01T00:00:00Z',
            ],
        ],
        'statistics' => [
            'global_rank' => 1000,
            'country_rank' => 10,
            'pp' => 10000.0,
        ],
    ]);

    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserRankStats')->never();
    $osuApi->shouldReceive('getUserManiaRankings')->never();

    /** @var SipService&MockInterface $sipService */
    $sipService = Mockery::mock(SipService::class);
    $sipService->shouldReceive('queueUserForSipFetch')->once();

    $service = new UserProfileSyncService($osuApi, $sipService);
    $service->syncWinnerProfile($user, 2025, 2025);

    $user->refresh();

    expect($user->username)->toBe('NewName');
    expect($user->country_code)->toBe('KR');
    expect($user->previous_usernames)->toBe(['OldName']);
    expect($user->main_mode)->toBe('osu');
    expect($user->main_mode_source)->toBe('oauth_setup');
    expect($user->rank_mania_4k)->toBeNull();
    expect($user->rank_mania_7k)->toBeNull();

    expect($user->rankHistory()->pluck('mode')->sort()->values()->all())
        ->toBe(['osu']);

    $badge = $user->badges()->first();

    expect($badge->name)->toBe('Example Cup 2025 Winner');
    expect($badge->badge_url)->toBe('https://osu.ppy.sh/community/forums/topics/7654321');
    expect($badge->image_2x_url)->toBe('https://assets.ppy.sh/badges/example@2x.png');
    expect($badge->tournament_id)->toBeNull();
    expect($badge->is_bws_eligible)->toBeFalse();
});

it('preserves matched badge tournament data when the badge still exists on osu api', function () {
    $user = User::factory()->create([
        'osu_id' => 67890,
        'main_mode' => 'osu',
    ]);

    $tournament = Tournament::factory()->create([
        'modes' => ['osu'],
        'tournament_end' => '2025-08-01',
    ]);

    TournamentWinner::factory()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'osu_id' => $user->osu_id,
        'username' => $user->username,
        'placement' => 1,
        'gamemode' => 'osu',
    ]);

    $matchedBadge = $user->badges()->create([
        'name' => 'Still Existing Cup Winner',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/123',
        'image_url' => 'https://assets.ppy.sh/badges/old.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/old@2x.png',
        'awarded_at' => '2025-09-01 00:00:00',
        'tournament_id' => $tournament->id,
        'is_bws_eligible' => true,
    ]);

    $removedBadge = $user->badges()->create([
        'name' => 'Removed Badge',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/456',
        'image_url' => 'https://assets.ppy.sh/badges/removed.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/removed@2x.png',
        'awarded_at' => '2024-01-01 00:00:00',
        'tournament_id' => $tournament->id,
        'is_bws_eligible' => true,
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUserForMode')->once()->with(67890, 'osu')->andReturn([
        'id' => 67890,
        'username' => $user->username,
        'avatar_url' => $user->avatar_url,
        'country_code' => $user->country_code,
        'badges' => [
            [
                'description' => 'Still Existing Cup Winner',
                'url' => 'https://osu.ppy.sh/community/forums/topics/123',
                'image_url' => 'https://assets.ppy.sh/badges/new.png',
                'image@2x_url' => 'https://assets.ppy.sh/badges/new@2x.png',
                'awarded_at' => '2025-09-01T00:00:00Z',
            ],
        ],
        'statistics' => [
            'global_rank' => 1000,
            'country_rank' => 10,
            'pp' => 10000.0,
        ],
    ]);

    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserRankStats')->never();
    $osuApi->shouldReceive('getUserManiaRankings')->never();

    /** @var SipService&MockInterface $sipService */
    $sipService = Mockery::mock(SipService::class);
    $sipService->shouldReceive('queueUserForSipFetch')->once();

    $service = new UserProfileSyncService($osuApi, $sipService);
    $service->syncWinnerProfile($user, 2025, 2025);

    $matchedBadge->refresh();

    expect($matchedBadge->tournament_id)->toBe($tournament->id);
    expect($matchedBadge->is_bws_eligible)->toBeTrue();
    expect($matchedBadge->image_url)->toBe('https://assets.ppy.sh/badges/new.png');
    expect($user->badges()->whereKey($removedBadge->id)->exists())->toBeFalse();
});

it('uses plain user endpoint and playmode when winner has no main mode yet', function () {
    $user = User::factory()->create([
        'osu_id' => 24680,
        'main_mode' => null,
        'main_mode_source' => null,
    ]);

    $tournament = Tournament::factory()->create([
        'modes' => ['catch'],
        'tournament_end' => '2025-08-01',
    ]);

    TournamentWinner::factory()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'osu_id' => $user->osu_id,
        'username' => $user->username,
        'placement' => 1,
        'gamemode' => 'catch',
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUser')->once()->with(24680)->andReturn([
        'id' => 24680,
        'username' => 'CatchPlayer',
        'avatar_url' => 'https://a.ppy.sh/24680',
        'country_code' => 'JP',
        'playmode' => 'fruits',
        'badges' => [],
        'statistics' => [
            'global_rank' => 321,
            'country_rank' => 12,
            'pp' => 9876.5,
        ],
    ]);
    $osuApi->shouldReceive('getUserForMode')->never();
    $osuApi->shouldReceive('getUserRankStats')->never();
    $osuApi->shouldReceive('getUserManiaRankings')->never();

    /** @var SipService&MockInterface $sipService */
    $sipService = Mockery::mock(SipService::class);
    $sipService->shouldReceive('queueUserForSipFetch')->never();

    $service = new UserProfileSyncService($osuApi, $sipService);
    $service->syncWinnerProfile($user, 2025, 2025);

    $user->refresh();

    expect($user->username)->toBe('CatchPlayer');
    expect($user->main_mode)->toBe('catch');
    expect($user->main_mode_source)->toBe('auto_detected');

    $rankHistory = $user->rankHistory()->first();

    expect($rankHistory->mode)->toBe('catch');
    expect($rankHistory->rank)->toBe(321);
    expect((float) $rankHistory->pp)->toBe(9876.5);
});

it('syncs mania winner variant ranks from osu api statistics variants', function () {
    $user = User::factory()->create([
        'osu_id' => 112358,
        'username' => 'OldManiaName',
        'main_mode' => 'mania',
        'rank_mania_4k' => 999999,
        'rank_mania_7k' => 888888,
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'approved',
        'modes' => [['mode' => 'mania', 'key_count' => 4]],
        'tournament_end' => '2025-08-01',
    ]);

    TournamentWinner::factory()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'osu_id' => $user->osu_id,
        'username' => $user->username,
        'placement' => 1,
        'gamemode' => 'mania',
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUserForMode')->once()->with(112358, 'mania')->andReturn([
        'id' => 112358,
        'username' => 'NewManiaName',
        'avatar_url' => 'https://a.ppy.sh/112358',
        'country_code' => 'KR',
        'badges' => [],
        'statistics' => [
            'global_rank' => 12345,
            'country_rank' => 321,
            'pp' => 6543.21,
            'variants' => [
                [
                    'variant' => '4k',
                    'global_rank' => 4567,
                    'pp' => 5432.1,
                ],
                [
                    'variant' => '7k',
                    'global_rank' => 7654,
                    'pp' => 4321.5,
                ],
            ],
        ],
    ]);

    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserRankStats')->never();
    $osuApi->shouldReceive('getUserManiaRankings')->never();

    /** @var SipService&MockInterface $sipService */
    $sipService = Mockery::mock(SipService::class);
    $sipService->shouldReceive('queueUserForSipFetch')->never();

    $service = new UserProfileSyncService($osuApi, $sipService);
    $service->syncWinnerProfile($user, 2025, 2025);

    $user->refresh();

    expect($user->username)->toBe('NewManiaName');
    expect($user->rank_mania_4k)->toBe(4567);
    expect($user->rank_mania_7k)->toBe(7654);

    $maniaRank = $user->rankHistory()->where('mode', 'mania')->first();
    $fourKeyRank = $user->rankHistory()->where('mode', '4k')->first();
    $sevenKeyRank = $user->rankHistory()->where('mode', '7k')->first();

    expect($maniaRank->rank)->toBe(12345);
    expect($maniaRank->country_rank)->toBe(321);
    expect((float) $maniaRank->pp)->toBe(6543.21);
    expect($fourKeyRank->rank)->toBe(4567);
    expect($fourKeyRank->country_rank)->toBeNull();
    expect((float) $fourKeyRank->pp)->toBe(5432.1);
    expect($sevenKeyRank->rank)->toBe(7654);
    expect($sevenKeyRank->country_rank)->toBeNull();
    expect((float) $sevenKeyRank->pp)->toBe(4321.5);
});

it('sets staff main mode from most common approved staff tournament modes across all years', function () {
    $user = User::factory()->create([
        'osu_id' => 13579,
        'main_mode' => null,
        'main_mode_source' => null,
    ]);

    $osuTournament = Tournament::factory()->create([
        'modes' => ['osu'],
        'tournament_end' => '2019-01-01',
    ]);
    $taikoTournament = Tournament::factory()->create([
        'modes' => ['taiko'],
        'tournament_end' => '2020-01-01',
    ]);
    $anotherTaikoTournament = Tournament::factory()->create([
        'modes' => ['taiko'],
        'tournament_end' => '2026-01-01',
    ]);
    $pendingCatchTournament = Tournament::factory()->create([
        'modes' => ['catch'],
        'tournament_end' => '2026-01-01',
    ]);

    createStaffRow($user, $osuTournament, 'organizer', 'approved');
    createStaffRow($user, $taikoTournament, 'organizer', 'approved');
    createStaffRow($user, $anotherTaikoTournament, 'referee', 'approved');
    createStaffRow($user, $pendingCatchTournament, 'organizer', 'pending');

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUsers')->once()->with(['13579'])->andReturn([
        13579 => [
            'id' => 13579,
            'username' => 'StaffUser',
            'avatar_url' => 'https://a.ppy.sh/13579',
            'country_code' => 'KR',
            'previous_usernames' => [],
        ],
    ]);

    /** @var SipService&MockInterface $sipService */
    $sipService = Mockery::mock(SipService::class);

    $service = new UserProfileSyncService($osuApi, $sipService);
    $result = $service->syncBasicProfiles(collect([$user]));

    $user->refresh();

    expect($result)->toBe(['synced' => 1, 'failed' => 0]);
    expect($user->main_mode)->toBe('taiko');
    expect($user->main_mode_source)->toBe('auto_detected');
});

it('does not change oauth setup main mode during staff basic sync', function () {
    $user = User::factory()->create([
        'osu_id' => 97531,
        'main_mode' => 'osu',
        'main_mode_source' => 'oauth_setup',
    ]);

    $taikoTournament = Tournament::factory()->create([
        'modes' => ['taiko'],
        'tournament_end' => '2026-01-01',
    ]);

    createStaffRow($user, $taikoTournament, 'organizer', 'approved');

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUsers')->once()->with(['97531'])->andReturn([
        97531 => [
            'id' => 97531,
            'username' => 'OauthStaffUser',
            'avatar_url' => 'https://a.ppy.sh/97531',
            'country_code' => 'JP',
            'previous_usernames' => [],
        ],
    ]);

    /** @var SipService&MockInterface $sipService */
    $sipService = Mockery::mock(SipService::class);

    $service = new UserProfileSyncService($osuApi, $sipService);
    $service->syncBasicProfiles(collect([$user]));

    $user->refresh();

    expect($user->main_mode)->toBe('osu');
    expect($user->main_mode_source)->toBe('oauth_setup');
});

function createStaffRow(User $user, Tournament $tournament, string $role, string $status): void
{
    DB::table('tournament_staff')->insert([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => $role,
        'status' => $status,
        'source' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('auto detects main mode from all approved podium tournament modes before fetching mode profile', function () {
    $user = User::factory()->create([
        'osu_id' => 13579,
        'username' => 'ModeUser',
        'main_mode' => 'osu',
        'main_mode_source' => 'auto_detected',
    ]);

    $currentYearOsuTournament = Tournament::factory()->create([
        'status' => 'approved',
        'modes' => ['osu'],
        'tournament_end' => '2026-05-01',
    ]);

    TournamentWinner::factory()->create([
        'tournament_id' => $currentYearOsuTournament->id,
        'user_id' => $user->id,
        'osu_id' => $user->osu_id,
        'username' => $user->username,
        'placement' => 1,
        'gamemode' => 'osu',
    ]);

    foreach (['2024-03-01', '2025-03-01'] as $tournamentEnd) {
        $oldCatchTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['catch'],
            'tournament_end' => $tournamentEnd,
        ]);

        TournamentWinner::factory()->create([
            'tournament_id' => $oldCatchTournament->id,
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'placement' => 1,
            'gamemode' => 'osu',
        ]);
    }

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUserForMode')->once()->with(13579, 'catch')->andReturn([
        'id' => 13579,
        'username' => 'ModeUserNew',
        'avatar_url' => 'https://a.ppy.sh/13579',
        'country_code' => 'KR',
        'badges' => [],
        'statistics' => [
            'global_rank' => 2222,
            'country_rank' => 22,
            'pp' => 8765.0,
        ],
    ]);

    $osuApi->shouldReceive('getUser')->never();
    $osuApi->shouldReceive('getUserRankStats')->never();
    $osuApi->shouldReceive('getUserManiaRankings')->never();

    /** @var SipService&MockInterface $sipService */
    $sipService = Mockery::mock(SipService::class);
    $sipService->shouldReceive('queueUserForSipFetch')->never();

    $service = new UserProfileSyncService($osuApi, $sipService);
    $service->syncWinnerProfile($user, 2026, 2026);

    $user->refresh();

    expect($user->main_mode)->toBe('catch');
    expect($user->main_mode_source)->toBe('auto_detected');
    expect($user->rankHistory()->where('mode', 'catch')->first()->rank)->toBe(2222);
});
