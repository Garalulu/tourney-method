<?php

use App\Jobs\ParseForumTopicJob;
use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\AdminMaintenanceRunRecorder;
use App\Services\BatchTransactionService;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use App\Services\SipService;
use App\Services\TournamentBadgeSyncService;
use App\Services\UserProfileSyncService;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

test('parser records created tournament items', function () {
    $topicId = 54321;
    $run = app(AdminMaintenanceRunRecorder::class)
        ->start(AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE);
    $transactionId = app(BatchTransactionService::class)->generateTransactionId($run->id);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $topicId,
            'title' => 'Daily Parse Cup',
            'forum_id' => 55,
            'user_id' => 98765,
            'created_at' => now()->subDays(2)->toIso8601String(),
            'posts' => [
                ['body' => ['raw' => 'Daily Parse Cup. Modes: osu!']],
            ],
        ], 200),
        'osu.ppy.sh/api/v2/users/*' => Http::response([
            'id' => 98765,
            'username' => 'host',
        ], 200),
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
    ]);

    $job = new ParseForumTopicJob($topicId, $transactionId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::query()->where('forum_topic_id', $topicId)->firstOrFail();

    $this->assertDatabaseHas('admin_maintenance_run_items', [
        'admin_maintenance_run_id' => $run->id,
        'item_type' => AdminMaintenanceRunItem::TYPE_TOURNAMENT,
        'action' => AdminMaintenanceRunItem::ACTION_CREATED,
        'tournament_id' => $tournament->id,
        'tournament_title' => 'Daily Parse Cup',
    ]);
});

test('sync tournament records synced tournament and user details', function () {
    $run = app(AdminMaintenanceRunRecorder::class)
        ->start(AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT);
    $tournament = Tournament::factory()->badge()->approved()->create([
        'title' => 'Badge Link Cup',
        'forum_topic_id' => 111222,
        'badge_status' => 'approved',
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);
    $user = User::factory()->create(['username' => 'WinnerUser', 'osu_id' => 555]);
    $badge = UserBadge::factory()->for($user)->create([
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/111222',
        'is_bws_eligible' => false,
        'tournament_id' => null,
    ]);

    TournamentWinner::factory()->for($tournament)->for($user)->create([
        'placement' => 1,
        'osu_id' => $user->osu_id,
        'username' => $user->username,
        'badge_url' => null,
        'badge_description' => null,
    ]);

    app(TournamentBadgeSyncService::class)->syncRange(
        (int) $tournament->tournament_end->format('Y'),
        (int) $tournament->tournament_end->format('Y'),
        maintenanceRun: $run,
    );

    $this->assertDatabaseHas('admin_maintenance_run_items', [
        'admin_maintenance_run_id' => $run->id,
        'action' => AdminMaintenanceRunItem::ACTION_SYNCED,
        'tournament_id' => $tournament->id,
        'tournament_title' => 'Badge Link Cup',
        'user_id' => $user->id,
        'osu_id' => $user->osu_id,
        'username' => 'WinnerUser',
    ]);

    expect($badge->fresh()->tournament_id)->toBe($tournament->id);
});

test('user profile sync records only users with newly added badges', function () {
    $run = app(AdminMaintenanceRunRecorder::class)
        ->start(AdminMaintenanceRun::COMMAND_SYNC_USER_PROFILES);
    $user = User::factory()->create([
        'username' => 'BadgeGetter',
        'osu_id' => 999,
        'main_mode' => 'osu',
    ]);

    /** @var OsuApiService&MockInterface $osuApi */
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUserForMode')->twice()->andReturn([
        'id' => 999,
        'username' => 'BadgeGetter',
        'country_code' => 'KR',
        'previous_usernames' => [],
        'badges' => [
            [
                'description' => 'Brand New Badge',
                'awarded_at' => '2026-01-01T00:00:00+00:00',
                'url' => 'https://osu.ppy.sh/community/forums/topics/123',
                'image_url' => 'https://assets.ppy.sh/badges/new.png',
                'image@2x_url' => 'https://assets.ppy.sh/badges/new@2x.png',
            ],
        ],
        'statistics' => [
            'global_rank' => 100,
            'country_rank' => 10,
            'pp' => 1234.56,
        ],
    ]);

    /** @var SipService&MockInterface $sip */
    $sip = Mockery::mock(SipService::class);
    $sip->shouldReceive('queueUserForSipFetch')->once();

    $result = (new UserProfileSyncService($osuApi, $sip))
        ->syncWinnerProfile($user, 2025, 2026, maintenanceRun: $run);

    expect($result['new_badges'])->toBe(1);

    $this->assertDatabaseHas('admin_maintenance_run_items', [
        'admin_maintenance_run_id' => $run->id,
        'item_type' => AdminMaintenanceRunItem::TYPE_USER,
        'action' => AdminMaintenanceRunItem::ACTION_NEW_BADGES,
        'user_id' => $user->id,
        'osu_id' => $user->osu_id,
        'username' => 'BadgeGetter',
    ]);

    $result = (new UserProfileSyncService($osuApi, $sip))
        ->syncWinnerProfile($user->fresh(), 2025, 2026, skipSip: true, maintenanceRun: $run);

    expect($result['new_badges'])->toBe(0)
        ->and($run->items()->where('action', AdminMaintenanceRunItem::ACTION_NEW_BADGES)->count())->toBe(1);
});

test('maintenance run failures persist errors', function () {
    $run = app(AdminMaintenanceRunRecorder::class)
        ->start(AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT);

    app(AdminMaintenanceRunRecorder::class)->fail($run, 'Sync exploded', ['stage' => 'sync']);

    $run->refresh();

    expect($run->status)->toBe(AdminMaintenanceRun::STATUS_FAILED)
        ->and($run->errors[0]['message'])->toBe('Sync exploded')
        ->and($run->errors[0]['stage'])->toBe('sync');
});
