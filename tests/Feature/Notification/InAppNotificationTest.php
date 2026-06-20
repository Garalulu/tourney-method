<?php

use App\Models\ParticipationDeletionRequest;
use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use App\Models\UserNotification;

test('navigation renders notification bell for authenticated users only', function () {
    $user = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);

    $this->get('/')->assertDontSee('/notifications', false);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('/notifications', false);
});

test('settings persist in app notification event preferences', function () {
    $user = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);

    $this->actingAs($user)
        ->patchJson(route('settings.update'), [
            'in_app_notification_preferences' => [
                UserNotification::PREF_TEAMMATE_CHANGES => false,
                UserNotification::PREF_PARTICIPATION_UPDATES => true,
                UserNotification::PREF_DELETION_REQUESTS => true,
                UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN => false,
                UserNotification::TYPE_ELIGIBLE_REGISTRATION_CLOSING => true,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('in_app_notification_preferences.'.UserNotification::PREF_TEAMMATE_CHANGES, false)
        ->assertJsonPath('in_app_notification_preferences.'.UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN, false);

    expect($user->fresh()->in_app_notification_preferences[UserNotification::PREF_TEAMMATE_CHANGES])->toBeFalse();
});

test('adding an oauth setup teammate creates a notification', function () {
    $actor = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $teammate = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $placeholder = User::factory()->withSetup()->create(['main_mode_source' => 'auto_detected']);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 4]);

    $this->actingAs($actor)
        ->post(route('users.participation.store', $actor), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Pink Team',
            'teammate_ids' => [$teammate->id, $placeholder->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $teammate->id,
        'actor_id' => $actor->id,
        'type' => UserNotification::TYPE_TEAMMATE_ADDED,
        'tournament_id' => $tournament->id,
    ]);
    $this->assertDatabaseMissing('user_notifications', [
        'user_id' => $placeholder->id,
        'type' => UserNotification::TYPE_TEAMMATE_ADDED,
    ]);
});

test('muted teammate added event does not create notification', function () {
    $actor = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $teammate = User::factory()->withSetup()->create([
        'main_mode_source' => 'oauth_setup',
        'in_app_notification_preferences' => array_merge(UserNotification::DEFAULT_PREFERENCES, [
            UserNotification::PREF_TEAMMATE_CHANGES => false,
        ]),
    ]);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($actor)
        ->post(route('users.participation.store', $actor), [
            'tournament_id' => $tournament->id,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('user_notifications', [
        'user_id' => $teammate->id,
        'type' => UserNotification::TYPE_TEAMMATE_ADDED,
    ]);
});

test('teammate removal notifies removed oauth setup user', function () {
    $actor = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $teammate = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($actor)
        ->post(route('users.participation.store', $actor), [
            'tournament_id' => $tournament->id,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $actor->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $this->actingAs($actor)
        ->patch(route('users.participation.update', [$actor, $record]), [
            'teammates_submitted' => 1,
            'teammate_ids' => [],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $teammate->id,
        'type' => UserNotification::TYPE_TEAMMATE_REMOVED,
        'tournament_id' => $tournament->id,
    ]);
});

test('participation detail changes notify participating oauth setup users except actor', function () {
    $actor = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $teammate = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($actor)
        ->post(route('users.participation.store', $actor), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Old Team',
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $actor->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $this->actingAs($actor)
        ->patch(route('users.participation.update', [$actor, $record]), [
            'team_name' => 'New Team',
            'stage_value' => 'stage:0',
            'seed' => 12,
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $teammate->id,
        'type' => UserNotification::TYPE_PARTICIPATION_UPDATED,
        'tournament_id' => $tournament->id,
    ]);
    $this->assertDatabaseMissing('user_notifications', [
        'user_id' => $actor->id,
        'type' => UserNotification::TYPE_PARTICIPATION_UPDATED,
    ]);
});

test('muted participation updates suppress team stage and seed notifications', function () {
    $actor = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $teammate = User::factory()->withSetup()->create([
        'main_mode_source' => 'oauth_setup',
        'in_app_notification_preferences' => array_merge(UserNotification::DEFAULT_PREFERENCES, [
            UserNotification::PREF_PARTICIPATION_UPDATES => false,
        ]),
    ]);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($actor)
        ->post(route('users.participation.store', $actor), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Old Team',
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $actor->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $this->actingAs($actor)
        ->patch(route('users.participation.update', [$actor, $record]), [
            'team_name' => 'New Team',
            'stage_value' => 'stage:0',
            'seed' => 3,
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('user_notifications', [
        'user_id' => $teammate->id,
        'type' => UserNotification::TYPE_PARTICIPATION_UPDATED,
    ]);
});

test('deletion request resolution notifies requester', function () {
    $admin = User::factory()->admin()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $requester = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $tournament = Tournament::factory()->approved()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $requester->id,
        'tournament_id' => $tournament->id,
        'metadata' => ['stage_value' => 'completed'],
    ]);
    $deletionRequest = ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $record->id,
        'requested_by' => $requester->id,
        'status' => ParticipationDeletionRequest::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.participation-moderation.deletion-requests.reject', $deletionRequest), [
            'review_note' => 'Keep it for now.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $requester->id,
        'type' => UserNotification::TYPE_DELETION_DENIED,
        'tournament_id' => $tournament->id,
    ]);
});

test('eligible tournament command creates deduped notifications for oauth setup users in matching main mode', function () {
    $eligible = User::factory()->withSetup('osu')->create(['main_mode_source' => 'oauth_setup']);
    $eligible->rankHistory()->create([
        'mode' => 'osu',
        'rank' => 5000,
        'recorded_at' => now(),
    ]);
    $eligible->forceFill(['rank_mania_4k' => 5000])->save();
    $maniaEligible = User::factory()->withSetup('mania')->create(['main_mode_source' => 'oauth_setup']);
    $maniaEligible->rankHistory()->create([
        'mode' => 'mania',
        'rank' => 5000,
        'recorded_at' => now(),
    ]);
    $maniaEligible->forceFill(['rank_mania_4k' => 5000])->save();
    $notOauth = User::factory()->withSetup('osu')->create(['main_mode_source' => 'auto_detected']);
    $notOauth->rankHistory()->create([
        'mode' => 'osu',
        'rank' => 5000,
        'recorded_at' => now(),
    ]);
    $osuTournament = Tournament::factory()->approved()->create([
        'modes' => ['osu'],
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
        'registration_start' => now()->subHour(),
        'registration_end' => now()->addHours(12),
        'restricted_countries' => null,
    ]);
    $maniaTournament = Tournament::factory()->approved()->create([
        'modes' => [['mode' => 'mania', 'key_count' => 4]],
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
        'registration_start' => now()->subHour(),
        'registration_end' => now()->addHours(12),
        'restricted_countries' => null,
    ]);

    $this->artisan('notifications:eligible-tournaments')->assertSuccessful();
    $this->artisan('notifications:eligible-tournaments')->assertSuccessful();

    expect(UserNotification::query()
        ->where('user_id', $eligible->id)
        ->where('tournament_id', $osuTournament->id)
        ->where('type', UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN)
        ->count())->toBe(1)
        ->and(UserNotification::query()
            ->where('user_id', $eligible->id)
            ->where('tournament_id', $osuTournament->id)
            ->where('type', UserNotification::TYPE_ELIGIBLE_REGISTRATION_CLOSING)
            ->count())->toBe(1)
        ->and(UserNotification::query()
            ->where('user_id', $eligible->id)
            ->where('tournament_id', $maniaTournament->id)
            ->count())->toBe(0)
        ->and(UserNotification::query()
            ->where('user_id', $maniaEligible->id)
            ->where('tournament_id', $maniaTournament->id)
            ->where('type', UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN)
            ->count())->toBe(1)
        ->and(UserNotification::query()
            ->where('user_id', $maniaEligible->id)
            ->where('tournament_id', $maniaTournament->id)
            ->where('type', UserNotification::TYPE_ELIGIBLE_REGISTRATION_CLOSING)
            ->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $notOauth->id)->count())->toBe(0);
});

test('eligible tournament notifications are created in the recipient locale', function () {
    $user = User::factory()->withSetup('osu')->create([
        'locale' => 'ko',
        'main_mode_source' => 'oauth_setup',
    ]);
    $user->rankHistory()->create([
        'mode' => 'osu',
        'rank' => 5000,
        'recorded_at' => now(),
    ]);
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Locale Cup',
        'modes' => ['osu'],
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
        'registration_start' => now()->subHour(),
        'registration_end' => now()->addDays(2),
        'restricted_countries' => null,
    ]);

    $this->artisan('notifications:eligible-tournaments')->assertSuccessful();

    $notification = UserNotification::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->where('type', UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN)
        ->firstOrFail();

    expect($notification->title)->toBe(trans(
        'common.notifications.messages.'.UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN.'.title',
        [],
        'ko'
    ))
        ->and($notification->body)->toBe(trans(
            'common.notifications.messages.'.UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN.'.body',
            ['tournament' => 'Locale Cup'],
            'ko'
        ));
});

test('notification routes enforce ownership and can read and dismiss', function () {
    $user = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $other = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $notification = UserNotification::query()->create([
        'user_id' => $user->id,
        'category' => UserNotification::CATEGORY_TOURNAMENT,
        'type' => UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN,
        'title' => 'Registration is open',
        'body' => 'A tournament is open.',
        'action_url' => route('tournaments.index'),
    ]);

    $this->actingAs($other)
        ->patchJson(route('notifications.read', $notification))
        ->assertNotFound();

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1);

    $this->actingAs($user)
        ->patchJson(route('notifications.read', $notification))
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->deleteJson(route('notifications.destroy', $notification))
        ->assertOk();

    expect($notification->fresh()->dismissed_at)->not->toBeNull();
});

test('user can dismiss all read notifications while preserving unread notifications', function () {
    $user = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $other = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);

    $read = UserNotification::query()->create([
        'user_id' => $user->id,
        'category' => UserNotification::CATEGORY_TOURNAMENT,
        'type' => UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN,
        'title' => 'Read notification',
        'action_url' => route('home'),
        'read_at' => now(),
    ]);
    $unread = UserNotification::query()->create([
        'user_id' => $user->id,
        'category' => UserNotification::CATEGORY_TOURNAMENT,
        'type' => UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN,
        'title' => 'Unread notification',
        'action_url' => route('home'),
    ]);
    $otherRead = UserNotification::query()->create([
        'user_id' => $other->id,
        'category' => UserNotification::CATEGORY_TOURNAMENT,
        'type' => UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN,
        'title' => 'Other user notification',
        'action_url' => route('home'),
        'read_at' => now(),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('notifications.destroy-read'))
        ->assertOk()
        ->assertJsonPath('dismissed_count', 1);

    expect($read->fresh()->dismissed_at)->not->toBeNull()
        ->and($unread->fresh()->dismissed_at)->toBeNull()
        ->and($otherRead->fresh()->dismissed_at)->toBeNull();
});

test('notification menus render remove all read controls', function () {
    $user = User::factory()->withSetup()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee(__('common.notifications.remove_read'));
});
