<?php

use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\User;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Http;

test('add tournament form uses the complete correction form with empty required creation fields', function () {
    $user = User::factory()->withSetup()->create();

    $this->actingAs($user)
        ->get(route('tournaments.add'))
        ->assertOk()
        ->assertSee('Add Tournament')
        ->assertSee('name="title"', false)
        ->assertSee('name="modes[]"', false)
        ->assertSee('name="forum_post_url"', false)
        ->assertSee('name="podium_groups', false)
        ->assertSee('data-stage-drag-handle', false)
        ->assertSee('@click="moveStage(index, -1)"', false)
        ->assertSee('@click="moveStage(index, 1)"', false)
        ->assertSee(':disabled="index === 0"', false)
        ->assertSee(':disabled="index === stages.length - 1"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('@pointerdown.stop', false)
        ->assertSee('@click.stop.prevent="show = true"', false)
        ->assertSee(':aria-expanded="show.toString()"', false)
        ->assertSee('Submit Tournament Request');
});

test('tournament index displays a standalone request success message', function () {
    $this->withSession([
        'tournament_request_success' => 'New tournament request #1 was submitted for admin review.',
    ])
        ->get(route('tournaments.index'))
        ->assertOk()
        ->assertSee('New tournament request #1 was submitted for admin review.');
});

test('new tournament requests require title modes and confirmation while forum url stays optional', function () {
    $user = User::factory()->withSetup()->create();

    $this->actingAs($user)
        ->post(route('tournaments.add.store'), [])
        ->assertSessionHasErrors(['title', 'modes']);

    $this->actingAs($user)
        ->post(route('tournaments.add.store'), [
            'title' => 'Community Cup',
            'modes' => ['osu'],
        ])
        ->assertSessionHasErrors('creation_confirmed');
});

test('standalone request without forum url skips external calls', function () {
    Http::fake();
    $user = User::factory()->withSetup()->create();

    $response = $this->actingAs($user)
        ->post(route('tournaments.add.store'), [
            'title' => 'Community Cup',
            'modes' => ['osu'],
            'creation_confirmed' => '1',
        ])
        ->assertRedirect(route('tournament-corrections.show', TournamentCorrection::query()->firstOrFail()))
        ->assertSessionHas('success')
        ->assertSessionHas('tournament_request_success');

    Http::assertNothingSent();

    $correction = TournamentCorrection::query()->firstOrFail();
    expect($correction->kind)->toBe(TournamentCorrection::KIND_NEW_TOURNAMENT)
        ->and($correction->tournament_id)->toBeNull()
        ->and(data_get($correction->payload, 'proposal.title'))->toBe('Community Cup')
        ->and(data_get($correction->payload, 'forum_topic_id'))->toBeNull();
});

test('duplicate preflight returns all approved matches with new tab urls', function () {
    $user = User::factory()->withSetup()->create();
    $matches = Tournament::factory()->count(2)->approved()->create(['forum_topic_id' => 123456]);

    $response = $this->actingAs($user)
        ->postJson(route('tournaments.add.check-duplicates'), [
            'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/123456',
        ])
        ->assertOk()
        ->assertJsonCount(2, 'approved')
        ->assertJsonPath('pending', null);

    expect($response->json('approved.0.url'))->toBe(route('tournaments.show', $matches[0]));
});

test('pending topic match takes precedence and becomes a correction', function () {
    $user = User::factory()->withSetup()->create();
    $pending = Tournament::factory()->pending()->create(['forum_topic_id' => 654321]);
    $approved = Tournament::factory()->approved()->create(['forum_topic_id' => 654321]);
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getForumTopic')->once()->with(654321)->andReturn(['posts' => []]);
    app()->instance(OsuApiService::class, $osuApi);

    $this->actingAs($user)
        ->post(route('tournaments.add.store'), [
            'title' => 'Updated pending title',
            'modes' => ['osu'],
            'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/654321',
            'selected_tournament_id' => $approved->id,
            'creation_confirmed' => '1',
        ])
        ->assertRedirect(route('tournaments.corrections.history', $pending));

    expect(TournamentCorrection::query()->firstOrFail()->tournament_id)->toBe($pending->id);
});

test('manipulated approved duplicate selection is rejected by the server', function () {
    $user = User::factory()->withSetup()->create();
    $unrelated = Tournament::factory()->approved()->create(['forum_topic_id' => 111111]);
    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldNotReceive('getForumTopic');
    app()->instance(OsuApiService::class, $osuApi);

    $this->actingAs($user)
        ->post(route('tournaments.add.store'), [
            'title' => 'Manipulated Cup',
            'modes' => ['osu'],
            'selected_tournament_id' => $unrelated->id,
            'creation_confirmed' => '1',
        ])
        ->assertSessionHasErrors('selected_tournament_id');

    expect(TournamentCorrection::query()->exists())->toBeFalse();
});

test('admin must accept title and modes together before a standalone request creates a pending tournament', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->withSetup()->create(['role' => 'admin']);

    $this->actingAs($submitter)->post(route('tournaments.add.store'), [
        'title' => 'Review Me Cup',
        'modes' => ['taiko'],
        'creation_confirmed' => '1',
    ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.title'],
        ])
        ->assertSessionHasErrors('accepted_keys');

    expect(Tournament::query()->where('title', 'Review Me Cup')->exists())->toBeFalse();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.title', 'metadata.modes'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect($correction->fresh()->tournament->status)->toBe(Tournament::STATUS_PENDING);
});

test('rejecting a standalone request creates no tournament', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->withSetup()->create(['role' => 'admin']);

    $this->actingAs($submitter)->post(route('tournaments.add.store'), [
        'title' => 'Rejected Cup',
        'modes' => ['catch'],
        'creation_confirmed' => '1',
    ]);

    $correction = TournamentCorrection::query()->firstOrFail();
    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'reject_all',
        ]);

    expect($correction->fresh()->status)->toBe(TournamentCorrection::STATUS_REJECTED)
        ->and($correction->tournament_id)->toBeNull()
        ->and(Tournament::query()->where('title', 'Rejected Cup')->exists())->toBeFalse();
});
