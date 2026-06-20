<?php

use App\Jobs\ProcessTournamentCorrectionJob;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\TournamentCorrectionComment;
use App\Models\User;
use App\Services\OsuApiService;
use App\Services\TournamentCorrectionService;
use Illuminate\Support\Facades\Queue;

test('correction review redirects immediately and queues member processing without calling osu api', function () {
    Queue::fake();

    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Original Cup']);
    $correction = app(TournamentCorrectionService::class)->createCorrection($tournament, $submitter, [
        'title' => 'Corrected Cup',
        'staff_organizer' => 'SlowApiUser',
    ]);

    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldNotReceive('getUserByUsername');
    app()->instance(OsuApiService::class, $osuApi);

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => array_keys($correction->payload['changes']),
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction))
        ->assertSessionHas('success');

    expect($correction->fresh()->status)->toBe(TournamentCorrection::STATUS_PROCESSING)
        ->and($tournament->fresh()->title)->toBe('Corrected Cup');

    Queue::assertPushed(ProcessTournamentCorrectionJob::class);
});

test('queued correction processing records missing usernames for authenticated viewers', function () {
    Queue::fake();

    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $viewer = User::factory()->create();
    $tournament = Tournament::factory()->approved()->create();
    $correction = app(TournamentCorrectionService::class)->createCorrection($tournament, $submitter, [
        'staff_organizer' => 'MissingStaffUser',
    ]);

    app(TournamentCorrectionService::class)->review(
        $correction,
        $admin,
        array_keys($correction->payload['changes'])
    );

    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUserByUsername')
        ->once()
        ->with('MissingStaffUser')
        ->andReturn(null);
    app()->instance(OsuApiService::class, $osuApi);

    (new ProcessTournamentCorrectionJob($correction->id))
        ->handle(app(TournamentCorrectionService::class));

    $correction->refresh();

    expect($correction->status)->toBe(TournamentCorrection::STATUS_PARTIALLY_APPROVED)
        ->and(data_get($correction->admin_decisions, 'apply_failures.0.username'))->toBe('MissingStaffUser');

    $this->actingAs($viewer)
        ->get(route('tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSee('MissingStaffUser')
        ->assertSee('User not found on osu!');
});

test('only correction submitter and admins can post follow up messages after review', function () {
    $submitter = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->create();
    $correction = TournamentCorrection::factory()->create([
        'submitted_by' => $submitter->id,
        'status' => TournamentCorrection::STATUS_APPROVED,
    ]);

    $this->actingAs($submitter)
        ->post(route('tournament-corrections.comments.store', $correction), ['body' => 'Submitter follow-up'])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $this->actingAs($admin)
        ->post(route('tournament-corrections.comments.store', $correction), ['body' => 'Admin reply'])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $this->actingAs($otherUser)
        ->post(route('tournament-corrections.comments.store', $correction), ['body' => 'Not allowed'])
        ->assertForbidden();

    expect(TournamentCorrectionComment::query()->pluck('body')->all())
        ->toBe(['Submitter follow-up', 'Admin reply']);
});

test('correction detail escapes discussion messages', function () {
    $submitter = User::factory()->create();
    $correction = TournamentCorrection::factory()->create(['submitted_by' => $submitter->id]);
    $payload = '<script>alert(1)</script>';

    $correction->comments()->create([
        'user_id' => $submitter->id,
        'body' => $payload,
    ]);

    $html = $this->actingAs($submitter)
        ->get(route('tournament-corrections.show', $correction))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain($payload)
        ->toContain(e($payload));
});

test('correction detail groups repeated member errors and marks failed staff individually', function () {
    $submitter = User::factory()->create();
    $correction = TournamentCorrection::factory()->create([
        'submitted_by' => $submitter->id,
        'status' => TournamentCorrection::STATUS_PARTIALLY_APPROVED,
        'payload' => [
            'changes' => [
                'staff.add.organizer.first' => [
                    'domain' => 'staff',
                    'field' => 'organizer',
                    'label' => 'Add Staff: Organizer',
                    'old' => null,
                    'new' => 'FirstMissing',
                    'apply' => ['action' => 'add', 'role' => 'organizer', 'username' => 'FirstMissing'],
                ],
                'staff.add.organizer.second' => [
                    'domain' => 'staff',
                    'field' => 'organizer',
                    'label' => 'Add Staff: Organizer',
                    'old' => null,
                    'new' => 'SecondMissing',
                    'apply' => ['action' => 'add', 'role' => 'organizer', 'username' => 'SecondMissing'],
                ],
            ],
        ],
        'admin_decisions' => [
            'accepted' => ['staff.add.organizer.first', 'staff.add.organizer.second'],
            'rejected' => [],
            'apply_failures' => [
                [
                    'key' => 'staff.add.organizer.first',
                    'domain' => 'staff',
                    'username' => 'FirstMissing',
                    'message' => "User 'FirstMissing' not found on osu!",
                ],
                [
                    'key' => 'staff.add.organizer.second',
                    'domain' => 'staff',
                    'username' => 'SecondMissing',
                    'message' => "User 'SecondMissing' not found on osu!",
                ],
            ],
        ],
    ]);

    $html = $this->actingAs($submitter)
        ->get(route('tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSee('Organizer')
        ->assertSee('FirstMissing')
        ->assertSee('SecondMissing')
        ->assertSee('2 affected')
        ->assertSee('Failed')
        ->getContent();

    expect(substr_count($html, 'User not found on osu!'))->toBe(1);
});

test('correction detail groups role conflicts by role instead of username', function () {
    $submitter = User::factory()->create();
    $correction = TournamentCorrection::factory()->create([
        'submitted_by' => $submitter->id,
        'status' => TournamentCorrection::STATUS_PARTIALLY_APPROVED,
        'admin_decisions' => [
            'accepted' => [],
            'rejected' => [],
            'apply_failures' => [
                [
                    'key' => 'staff.add.other.first',
                    'domain' => 'staff',
                    'username' => 'FirstUser',
                    'message' => "FirstUser already has role 'other'",
                ],
                [
                    'key' => 'staff.add.other.second',
                    'domain' => 'staff',
                    'username' => 'SecondUser',
                    'message' => "SecondUser already has role 'other'",
                ],
            ],
        ],
    ]);

    $html = $this->actingAs($submitter)
        ->get(route('tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSee("Already has role 'other'")
        ->assertSee('2 affected')
        ->assertSee('FirstUser')
        ->assertSee('SecondUser')
        ->getContent();

    expect(substr_count($html, e("Already has role 'other'")))->toBe(1);
});

test('correction detail renders podium members without raw json', function () {
    $submitter = User::factory()->create();
    $correction = TournamentCorrection::factory()->create([
        'submitted_by' => $submitter->id,
        'status' => TournamentCorrection::STATUS_APPROVED,
        'payload' => [
            'changes' => [
                'podium.group.1.champions' => [
                    'domain' => 'podium',
                    'field' => '1',
                    'label' => 'Podium Team: 1',
                    'old' => null,
                    'new' => [
                        'team_name' => 'Champions',
                        'usernames' => ['WinnerOne', 'WinnerTwo'],
                    ],
                    'apply' => [
                        'action' => 'group',
                        'placement' => 1,
                        'team_name' => 'Champions',
                        'usernames' => ['WinnerOne', 'WinnerTwo'],
                    ],
                ],
            ],
        ],
        'admin_decisions' => [
            'accepted' => ['podium.group.1.champions'],
            'rejected' => [],
            'apply_failures' => [],
        ],
    ]);

    $this->actingAs($submitter)
        ->get(route('tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSee('1st Place')
        ->assertSee('Team name')
        ->assertSee('None')
        ->assertSee('Champions')
        ->assertSee('WinnerOne')
        ->assertSee('WinnerTwo')
        ->assertDontSee('Podium team')
        ->assertDontSee('Raw JSON');
});
