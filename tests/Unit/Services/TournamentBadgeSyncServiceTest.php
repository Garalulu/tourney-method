<?php

use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\TournamentBadgeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('matches podium user badge by forum topic url', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'forum_topic_id' => 1977529,
        'title' => 'Forum Match Cup',
    ], [
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/1977529?n=1',
        'name' => 'Some Other Description',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['matched'])->toBe(1);
    expect($winner->fresh()->badge_url)->toBe($badge->badge_url);
    expect($badge->fresh()->tournament_id)->toBe($tournament->id);
    expect($badge->fresh()->is_bws_eligible)->toBeTrue();
});

it('does not match podium user badge by tournament title in badge description', function () {
    [, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Title Match Championship',
        'badge_urls' => null,
    ], [
        'badge_url' => null,
        'name' => 'Title Match Championship Winner',
        'image_url' => 'https://assets.ppy.sh/badges/title-only.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/title-only@2x.png',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($winner->tournament);

    expect($stats['matched'])->toBe(0);
    expect($stats['unmatched'])->toBe(1);
    expect($winner->fresh()->badge_description)->toBeNull();
    expect($badge->fresh()->tournament_id)->toBeNull();
});

it('matches podium user badge by tournament badge image url against user 2x image', function () {
    [$tournament, $winner] = createTournamentBadgeSyncFixture([
        'title' => 'Image Match Cup',
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/image-match@2x.png'],
        ],
    ], [
        'badge_url' => null,
        'name' => 'Unrelated Badge Text',
        'image_url' => 'https://assets.ppy.sh/badges/image-match.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/image-match@2x.png',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['matched'])->toBe(1);
    expect($winner->fresh()->badge_image_2x_url)->toBe('https://assets.ppy.sh/badges/image-match@2x.png');
});

it('only marks matched osu badges as bws eligible', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Taiko Badge Cup',
        'modes' => ['taiko'],
    ], [
        'name' => 'Taiko Badge Cup Winner',
    ], [
        'gamemode' => 'taiko',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['matched'])->toBe(1);
    expect($winner->fresh()->badge_description)->toBe('Taiko Badge Cup Winner');
    expect($badge->fresh()->tournament_id)->toBe($tournament->id);
    expect($badge->fresh()->is_bws_eligible)->toBeFalse();
});

it('uses tournament mode instead of stale winner gamemode when linking badges and updating main mode', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Catch Badge Cup',
        'modes' => ['catch'],
    ], [
        'name' => 'Catch Badge Cup Winner',
    ], [
        'gamemode' => 'osu',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    $user = $winner->user->fresh();

    expect($stats['matched'])->toBe(1);
    expect($stats['main_modes_updated'])->toBe(1);
    expect($winner->fresh()->gamemode)->toBe('catch');
    expect($badge->fresh()->is_bws_eligible)->toBeFalse();
    expect($user->main_mode)->toBe('catch');
    expect($user->main_mode_source)->toBe('auto_detected');
});

it('unlinks already linked badges that no longer match current rules', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Already Linked Catch Cup',
        'modes' => ['catch'],
        'badge_urls' => null,
    ], [
        'name' => 'Already Linked Catch Cup Winner',
        'badge_url' => null,
        'image_url' => 'https://assets.ppy.sh/badges/old-title-match.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/old-title-match@2x.png',
        'is_bws_eligible' => true,
        'tournament_id' => null,
    ], [
        'gamemode' => 'osu',
    ]);

    $badge->update([
        'tournament_id' => $tournament->id,
        'is_bws_eligible' => true,
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['matched'])->toBe(0);
    expect($stats['unmatched'])->toBe(1);
    expect($stats['stale_badges_reset'])->toBe(1);
    expect($winner->fresh()->gamemode)->toBe('osu');
    expect($badge->fresh())->not->toBeNull();
    expect($badge->fresh()->tournament_id)->toBeNull();
    expect($badge->fresh()->is_bws_eligible)->toBeFalse();
});

it('clears invalid winner badge metadata while preserving user badge data', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Hedge Hop Madness',
        'forum_topic_id' => 1841693,
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/profile-badges/hedgehopmadness2024@2x.png'],
        ],
    ], [
        'name' => 'Hedge Hop Madness: Beach Episode Winning Team',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/1935328',
        'image_url' => 'https://assets.ppy.sh/profile-badges/hedge-hop-beach-episode-winner.png',
        'image_2x_url' => 'https://assets.ppy.sh/profile-badges/hedge-hop-beach-episode-winner@2x.png',
        'tournament_id' => null,
        'is_bws_eligible' => true,
    ]);

    $winner->update([
        'badge_description' => $badge->name,
        'badge_url' => $badge->badge_url,
        'badge_image_url' => $badge->image_url,
        'badge_image_2x_url' => $badge->image_2x_url,
        'badge_awarded_at' => now(),
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    $winner->refresh();
    $badge->refresh();

    expect($stats['matched'])->toBe(0);
    expect($stats['unmatched'])->toBe(1);
    expect($stats['stale_badges_reset'])->toBe(1);
    expect($winner->badge_description)->toBeNull();
    expect($winner->badge_url)->toBeNull();
    expect($winner->badge_image_url)->toBeNull();
    expect($winner->badge_image_2x_url)->toBeNull();
    expect($winner->badge_awarded_at)->toBeNull();
    expect($badge->exists)->toBeTrue();
    expect($badge->tournament_id)->toBeNull();
    expect($badge->is_bws_eligible)->toBeTrue();
});

it('keeps already linked badges that still match by url and updates stale winner gamemode', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Already Linked Url Cup',
        'modes' => ['catch'],
        'forum_topic_id' => 333333,
    ], [
        'name' => 'Unrelated badge text',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/333333?n=1',
        'is_bws_eligible' => true,
        'tournament_id' => null,
    ], [
        'gamemode' => 'osu',
    ]);

    $badge->update([
        'tournament_id' => $tournament->id,
        'is_bws_eligible' => true,
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['matched'])->toBe(1);
    expect($stats['stale_badges_reset'])->toBe(0);
    expect($winner->fresh()->gamemode)->toBe('catch');
    expect($badge->fresh()->tournament_id)->toBe($tournament->id);
    expect($badge->fresh()->is_bws_eligible)->toBeFalse();
});

it('keeps already linked badges that still match by image', function () {
    [$tournament, , $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Already Linked Image Cup',
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/already-linked-image@2x.png'],
        ],
    ], [
        'name' => 'Unrelated badge text',
        'badge_url' => null,
        'image_url' => 'https://assets.ppy.sh/badges/already-linked-image.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/already-linked-image@2x.png',
        'tournament_id' => null,
    ]);

    $badge->update([
        'tournament_id' => $tournament->id,
        'is_bws_eligible' => true,
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['matched'])->toBe(1);
    expect($stats['stale_badges_reset'])->toBe(0);
    expect($badge->fresh()->tournament_id)->toBe($tournament->id);
    expect($badge->fresh()->is_bws_eligible)->toBeTrue();
});

it('requires matching badge image when multiple tournaments share a forum topic', function () {
    [$wrongTournament, $wrongWinner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Series Cup X-1',
        'forum_topic_id' => 777777,
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/series-x1-first.png'],
        ],
    ], [
        'name' => 'Series Cup X-2 Champion',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/777777',
        'image_url' => 'https://assets.ppy.sh/badges/series-x2-first.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/series-x2-first@2x.png',
        'is_bws_eligible' => true,
    ]);

    $badge->update([
        'tournament_id' => $wrongTournament->id,
    ]);

    $wrongWinner->update([
        'badge_description' => $badge->name,
        'badge_url' => $badge->badge_url,
        'badge_image_url' => $badge->image_url,
        'badge_image_2x_url' => $badge->image_2x_url,
        'badge_awarded_at' => now(),
    ]);

    $correctTournament = Tournament::factory()->create([
        'status' => 'approved',
        'is_badge' => true,
        'badge_status' => 'approved',
        'tournament_end' => '2025-06-15',
        'modes' => ['osu'],
        'title' => 'Series Cup X-2',
        'forum_topic_id' => 777777,
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/series-x2-first.png'],
        ],
    ]);

    $correctWinner = TournamentWinner::factory()->create([
        'tournament_id' => $correctTournament->id,
        'user_id' => $wrongWinner->user_id,
        'osu_id' => $wrongWinner->osu_id,
        'username' => $wrongWinner->username,
        'placement' => 1,
        'gamemode' => 'osu',
    ]);

    $wrongStats = app(TournamentBadgeSyncService::class)->syncTournament($wrongTournament);
    $correctStats = app(TournamentBadgeSyncService::class)->syncTournament($correctTournament);

    expect($wrongStats['matched'])->toBe(0);
    expect($wrongStats['stale_badges_reset'])->toBe(2);
    expect($wrongWinner->fresh()->badge_description)->toBeNull();
    expect($correctStats['matched'])->toBe(1);
    expect($correctWinner->fresh()->badge_image_url)->toBe('https://assets.ppy.sh/badges/series-x2-first.png');
    expect($badge->fresh()->tournament_id)->toBe($correctTournament->id);
});

it('does not match duplicate forum topic tournaments without a matching badge image', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Shared Topic A',
        'forum_topic_id' => 888888,
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/shared-topic-a.png'],
        ],
    ], [
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/888888',
        'image_url' => 'https://assets.ppy.sh/badges/unknown-shared-topic.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/unknown-shared-topic@2x.png',
    ]);

    Tournament::factory()->create([
        'status' => 'approved',
        'is_badge' => true,
        'badge_status' => 'approved',
        'tournament_end' => '2025-06-15',
        'modes' => ['osu'],
        'title' => 'Shared Topic B',
        'forum_topic_id' => 888888,
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/shared-topic-b.png'],
        ],
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['matched'])->toBe(0);
    expect($stats['unmatched'])->toBe(1);
    expect($winner->fresh()->badge_description)->toBeNull();
    expect($badge->fresh()->tournament_id)->toBeNull();
});

it('approves missing badge status when a badge is matched', function () {
    [$tournament] = createTournamentBadgeSyncFixture([
        'title' => 'Missing Badge Status Cup',
        'badge_status' => null,
    ], [
        'name' => 'Missing Badge Status Cup Winner',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['matched'])->toBe(1);
    expect($tournament->fresh()->badge_status)->toBe('approved');
});

it('skips rejected badge status tournaments during direct sync', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Rejected Badge Status Cup',
        'badge_status' => 'rejected',
    ], [
        'name' => 'Rejected Badge Status Cup Winner',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['winners'])->toBe(0);
    expect($stats['matched'])->toBe(0);
    expect($tournament->fresh()->badge_status)->toBe('rejected');
    expect($winner->fresh()->badge_description)->toBeNull();
    expect($badge->fresh()->tournament_id)->toBeNull();
});

it('skips rejected tournaments during direct sync', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Rejected Tournament Cup',
        'status' => 'rejected',
        'badge_status' => 'pending',
    ], [
        'name' => 'Rejected Tournament Cup Winner',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    expect($stats['winners'])->toBe(0);
    expect($stats['matched'])->toBe(0);
    expect($tournament->fresh()->badge_status)->toBe('pending');
    expect($winner->fresh()->badge_description)->toBeNull();
    expect($badge->fresh()->tournament_id)->toBeNull();
});

it('preserves rejected tournament winner metadata during range cleanup', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Rejected Range Cleanup Cup',
        'status' => 'rejected',
        'badge_status' => 'pending',
        'tournament_end' => '2025-06-01',
        'forum_topic_id' => 123123,
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/rejected-range-cleanup.png'],
        ],
    ], [
        'name' => 'Other Cup Winner',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/987987',
        'image_url' => 'https://assets.ppy.sh/badges/other-range-cleanup.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/other-range-cleanup@2x.png',
    ]);

    $winner->update([
        'badge_description' => $badge->name,
        'badge_url' => $badge->badge_url,
        'badge_image_url' => $badge->image_url,
        'badge_image_2x_url' => $badge->image_2x_url,
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncRange(2025, 2025);

    expect($stats['tournaments'])->toBe(0);
    expect($winner->fresh()->badge_description)->toBe('Other Cup Winner');
    expect($tournament->fresh()->badge_status)->toBe('pending');
});

it('updates matched winner main mode from most frequent approved podium gamemode including unbadged tournaments', function () {
    [$tournament, $winner] = createTournamentBadgeSyncFixture([
        'title' => 'Main Mode Badge Cup',
        'modes' => ['osu'],
    ], [
        'name' => 'Main Mode Badge Cup Winner',
    ], [
        'gamemode' => 'osu',
    ]);

    $user = $winner->user;

    foreach (['2025-05-01', '2025-05-15'] as $tournamentEnd) {
        $unbadgedTaikoTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => false,
            'badge_status' => null,
            'tournament_end' => $tournamentEnd,
            'modes' => ['taiko'],
        ]);

        TournamentWinner::factory()->create([
            'tournament_id' => $unbadgedTaikoTournament->id,
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => $user->username,
            'placement' => 1,
            'gamemode' => 'osu',
        ]);
    }

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    $user->refresh();

    expect($stats['matched'])->toBe(1);
    expect($stats['main_modes_updated'])->toBe(1);
    expect($user->main_mode)->toBe('taiko');
    expect($user->main_mode_source)->toBe('auto_detected');
});

it('does not update matched winner main mode selected during oauth setup', function () {
    [$tournament, $winner] = createTournamentBadgeSyncFixture([
        'title' => 'OAuth Protected Cup',
    ], [
        'name' => 'OAuth Protected Cup Winner',
    ], [
        'gamemode' => 'mania',
    ]);

    $winner->user->update([
        'main_mode' => 'osu',
        'main_mode_source' => 'oauth_setup',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncTournament($tournament);

    $user = $winner->user->fresh();

    expect($stats['matched'])->toBe(1);
    expect($stats['main_modes_updated'])->toBe(0);
    expect($user->main_mode)->toBe('osu');
    expect($user->main_mode_source)->toBe('oauth_setup');
});

it('filters sync range by tournament end year', function () {
    createTournamentBadgeSyncFixture([
        'title' => 'In Range Cup',
        'tournament_end' => '2025-06-01',
    ], [
        'name' => 'In Range Cup Winner',
    ]);

    createTournamentBadgeSyncFixture([
        'title' => 'Out Range Cup',
        'tournament_end' => '2024-06-01',
    ], [
        'name' => 'Out Range Cup Winner',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncRange(2025, 2025);

    expect($stats['tournaments'])->toBe(1);
    expect($stats['matched'])->toBe(1);
});

it('essential sync only checks tournaments with missing first place badge metadata', function () {
    [, $syncedWinner, $syncedBadge] = createTournamentBadgeSyncFixture([
        'title' => 'Already Synced Cup',
        'tournament_end' => '2025-06-01',
        'forum_topic_id' => 111111,
    ], [
        'name' => 'Already Synced Cup Winner',
    ], [
        'badge_description' => 'Already Synced Cup Winner',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/111111',
    ]);

    createTournamentBadgeSyncFixture([
        'title' => 'Needs Sync Cup',
        'tournament_end' => '2025-06-01',
    ], [
        'name' => 'Needs Sync Cup Winner',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncRange(2025, 2025, false, true);

    expect($stats['tournaments'])->toBe(1);
    expect($stats['matched'])->toBe(1);
    expect($syncedWinner->fresh()->badge_url)->toBe('https://osu.ppy.sh/community/forums/topics/111111');
    expect($syncedBadge->fresh()->tournament_id)->toBeNull();
});

it('essential sync ignores tournaments where only non-first podium badge metadata is missing', function () {
    [$tournament, $firstPlaceWinner, $firstPlaceBadge] = createTournamentBadgeSyncFixture([
        'title' => 'Second Place Missing Cup',
        'tournament_end' => '2025-06-01',
        'forum_topic_id' => 222222,
    ], [
        'name' => 'Second Place Missing Cup Winner',
    ], [
        'badge_description' => 'Second Place Missing Cup Winner',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/222222',
    ]);

    $secondPlaceUser = User::factory()->create();

    TournamentWinner::factory()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $secondPlaceUser->id,
        'osu_id' => $secondPlaceUser->osu_id,
        'username' => $secondPlaceUser->username,
        'placement' => 2,
        'badge_description' => null,
        'badge_url' => null,
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncRange(2025, 2025, false, true);

    expect($stats['tournaments'])->toBe(0);
    expect($stats['matched'])->toBe(0);
    expect($firstPlaceWinner->fresh()->badge_url)->toBe('https://osu.ppy.sh/community/forums/topics/222222');
    expect($firstPlaceBadge->fresh()->tournament_id)->toBeNull();
});

it('auto-approves pending review tournaments when a podium user has a matching badge', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'status' => 'pending_review',
        'reviewed_at' => null,
        'reviewed_by' => null,
        'title' => 'Pending Badge Cup',
        'tournament_end' => '2025-06-01',
    ], [
        'name' => 'Pending Badge Cup Winner',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncRange(2025, 2025);

    $tournament->refresh();

    expect($stats['tournaments'])->toBe(1);
    expect($stats['matched'])->toBe(1);
    expect($stats['auto_approved'])->toBe(1);
    expect($tournament->status)->toBe('approved');
    expect($tournament->reviewed_at)->not->toBeNull();
    expect($winner->fresh()->badge_url)->toBe($badge->badge_url);
    expect($badge->fresh()->tournament_id)->toBe($tournament->id);
});

it('essential sync includes pending badge status tournaments and auto-approves when matched', function () {
    [$tournament, $winner, $badge] = createTournamentBadgeSyncFixture([
        'status' => 'pending_review',
        'badge_status' => 'pending',
        'reviewed_at' => null,
        'reviewed_by' => null,
        'title' => 'Pending Badge Status Cup',
        'tournament_end' => '2025-06-01',
    ], [
        'name' => 'Pending Badge Status Cup Winner',
    ]);

    $stats = app(TournamentBadgeSyncService::class)->syncRange(2025, 2025, false, true);

    $tournament->refresh();

    expect($stats['tournaments'])->toBe(1);
    expect($stats['matched'])->toBe(1);
    expect($stats['auto_approved'])->toBe(1);
    expect($tournament->status)->toBe('approved');
    expect($tournament->badge_status)->toBe('approved');
    expect($winner->fresh()->badge_url)->toBe($badge->badge_url);
    expect($badge->fresh()->tournament_id)->toBe($tournament->id);
});

it('resets user badge links for soft deleted tournaments', function () {
    [$tournament, , $badge] = createTournamentBadgeSyncFixture();

    $badge->update([
        'tournament_id' => $tournament->id,
        'is_bws_eligible' => true,
    ]);

    $tournament->delete();

    $count = app(TournamentBadgeSyncService::class)->resetStaleTournamentBadgeLinks();

    $badge->refresh();

    expect($count)->toBe(1);
    expect($badge->tournament_id)->toBeNull();
    expect($badge->is_bws_eligible)->toBeFalse();
});

it('resets bws eligibility for hard deleted tournament links already nulled by database', function () {
    [, , $badge] = createTournamentBadgeSyncFixture();

    $badge->update([
        'tournament_id' => null,
        'is_bws_eligible' => true,
    ]);

    $count = app(TournamentBadgeSyncService::class)->resetStaleTournamentBadgeLinks();

    $badge->refresh();

    expect($count)->toBe(1);
    expect($badge->tournament_id)->toBeNull();
    expect($badge->is_bws_eligible)->toBeFalse();
});

it('resets invalid tournament winner badge metadata globally', function () {
    [, $winner, $badge] = createTournamentBadgeSyncFixture([
        'title' => 'Global Metadata Cleanup Cup',
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/global-metadata-cleanup.png'],
        ],
    ], [
        'name' => 'Other Series Cup Winner',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/987654',
        'image_url' => 'https://assets.ppy.sh/badges/other-series.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/other-series@2x.png',
        'tournament_id' => null,
    ]);

    $winner->update([
        'badge_description' => $badge->name,
        'badge_url' => $badge->badge_url,
        'badge_image_url' => $badge->image_url,
        'badge_image_2x_url' => $badge->image_2x_url,
    ]);

    $count = app(TournamentBadgeSyncService::class)->resetStaleTournamentBadgeLinks();

    $winner->refresh();
    $badge->refresh();

    expect($count)->toBe(1);
    expect($winner->badge_description)->toBeNull();
    expect($winner->badge_url)->toBeNull();
    expect($winner->badge_image_url)->toBeNull();
    expect($winner->badge_image_2x_url)->toBeNull();
    expect($badge->exists)->toBeTrue();
    expect($badge->tournament_id)->toBeNull();
});

/**
 * @param  array<string, mixed>  $tournamentOverrides
 * @param  array<string, mixed>  $badgeOverrides
 * @param  array<string, mixed>  $winnerOverrides
 * @return array{0: Tournament, 1: TournamentWinner, 2: UserBadge}
 */
function createTournamentBadgeSyncFixture(array $tournamentOverrides = [], array $badgeOverrides = [], array $winnerOverrides = []): array
{
    $user = User::factory()->create();

    /** @var Tournament $tournament */
    $tournament = Tournament::factory()->create(array_merge([
        'status' => 'approved',
        'is_badge' => true,
        'badge_status' => 'approved',
        'tournament_end' => '2025-06-01',
        'modes' => ['osu'],
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/badges/source.png'],
        ],
    ], $tournamentOverrides));

    /** @var TournamentWinner $winner */
    $winner = TournamentWinner::factory()->create(array_merge([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'osu_id' => $user->osu_id,
        'username' => $user->username,
        'placement' => 1,
        'gamemode' => 'osu',
    ], $winnerOverrides));

    /** @var UserBadge $badge */
    $badge = UserBadge::factory()->create(array_merge([
        'user_id' => $user->id,
        'name' => $tournament->title.' Winner',
        'badge_url' => 'https://osu.ppy.sh/community/forums/topics/'.($tournament->forum_topic_id ?? 999999),
        'image_url' => 'https://assets.ppy.sh/badges/source.png',
        'image_2x_url' => 'https://assets.ppy.sh/badges/source@2x.png',
        'is_bws_eligible' => false,
        'tournament_id' => null,
    ], $badgeOverrides));

    return [$tournament, $winner, $badge];
}
