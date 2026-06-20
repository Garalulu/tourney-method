<?php

use App\Jobs\ProcessTournamentCorrectionJob;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserRankHistory;
use App\Services\OsuApiService;
use App\Services\TournamentCorrectionService;
use App\Services\TournamentParticipantSyncService;
use Illuminate\Support\Facades\Queue;

test('guest cannot open tournament correction page', function () {
    $tournament = Tournament::factory()->approved()->create();

    $this->get(route('tournaments.corrections.create', $tournament))
        ->assertRedirect(route('login'));
});

test('authenticated user can submit correction for approved tournament', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Original title']);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Corrected title',
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $correction = TournamentCorrection::query()->firstOrFail();

    expect($correction->status)->toBe(TournamentCorrection::STATUS_PENDING)
        ->and($correction->payload['changes']['metadata.title']['new'])->toBe('Corrected title');
});

test('correction form renders schedule dates in utc without timezone labels', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'registration_start' => '2026-06-18 21:00:00',
        'registration_end' => '2026-06-18 22:00:00',
        'tournament_start' => '2026-06-19 21:00:00',
        'tournament_end' => '2026-06-19 22:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('tournaments.corrections.create', $tournament))
        ->assertOk()
        ->assertSee('name="registration_start" value="2026-06-18T12:00"', false)
        ->assertSee('name="tournament_start" value="2026-06-19T12:00"', false)
        ->assertDontSee('UTC')
        ->assertDontSee('+00:00');
});

test('correction schedule values are interpreted compared and stored as utc', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'registration_start' => '2026-06-18 21:00:00',
        'registration_end' => '2026-06-18 22:00:00',
        'tournament_start' => '2026-06-19 21:00:00',
        'tournament_end' => '2026-06-19 22:00:00',
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'registration_start' => '2026-06-18T12:00',
            'registration_end' => '2026-06-18T13:00',
            'tournament_start' => '2026-05-24T23:59',
            'tournament_end' => '2026-05-25T00:59',
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $correction = TournamentCorrection::query()->firstOrFail();

    $changes = $correction->payload['changes'];

    expect(array_keys($changes))
        ->not->toContain('metadata.registration_start', 'metadata.registration_end')
        ->and($changes['metadata.tournament_start']['new'])
        ->toBe('2026-05-24 23:59:00')
        ->and($changes['metadata.tournament_start']['apply'])
        ->toBe('2026-05-25 08:59:00');

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => [
                'metadata.tournament_start',
                'metadata.tournament_end',
            ],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect($tournament->fresh()->tournament_start->format('Y-m-d H:i:s'))
        ->toBe('2026-05-25 08:59:00')
        ->and($tournament->fresh()->tournament_end->format('Y-m-d H:i:s'))
        ->toBe('2026-05-25 09:59:00');

    $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $tournament))
        ->assertOk()
        ->assertSee('name="tournament_start" value="2026-05-25T08:59"', false);
});

test('correction schedule ordering validation preserves datetime local input', function () {
    $submitter = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($submitter)
        ->from(route('tournaments.corrections.create', $tournament))
        ->post(route('tournaments.corrections.store', $tournament), [
            'registration_start' => '2026-06-18T13:00',
            'registration_end' => '2026-06-18T12:00',
        ])
        ->assertRedirect(route('tournaments.corrections.create', $tournament))
        ->assertSessionHasErrors('registration_end')
        ->assertSessionHasInput('registration_start', '2026-06-18T13:00');
});

test('submitter note is optional, stored, and escaped on correction history and admin review', function () {
    $user = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Original title']);
    $note = '<script>alert(1)</script>Use forum post #12';

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Corrected title',
            'submitter_note' => $note,
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $correction = TournamentCorrection::query()->firstOrFail();

    expect($correction->submitter_note)->toBe($note);

    $historyHtml = $this->actingAs($user)
        ->get(route('tournaments.corrections.history', $tournament))
        ->assertOk()
        ->assertSee('User note')
        ->getContent();

    expect($historyHtml)
        ->not()->toContain($note)
        ->toContain(e($note));

    $adminHtml = $this->actingAs($admin)
        ->get(route('admin.tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSee('User note')
        ->getContent();

    expect($adminHtml)
        ->not()->toContain($note)
        ->toContain(e($note));
});

test('empty submitter note is omitted from correction history', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Original title']);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Corrected title',
            'submitter_note' => '',
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $this->actingAs($user)
        ->get(route('tournaments.corrections.history', $tournament))
        ->assertOk()
        ->assertDontSee('User note');
});

test('tournament correction url fields reject javascript and off format payloads', function (string $field, string $value) {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Url Guard Cup']);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            $field => $value,
        ])
        ->assertSessionHasErrors($field);

    expect(TournamentCorrection::query()->exists())->toBeFalse();
})->with([
    'forum javascript' => ['forum_post_url', 'javascript:alert(1)'],
    'spreadsheet html payload' => ['spreadsheet_url', 'https://docs.google.com/spreadsheets/d/example"><script>alert(1)</script>'],
    'banner data html' => ['banner_url', 'data:text/html,<script>alert(1)</script>'],
]);

test('admin correction review notes may store attack text but are not rendered as html', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Review Note Cup']);
    $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Review Note Cup Fixed',
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.title'],
            'review_note' => $payload,
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $correction->refresh();

    expect($correction->review_note)->toBe($payload);

    $html = $this->actingAs($admin)
        ->get(route('tournament-corrections.show', $correction))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not()->toContain($payload)
        ->toContain(e($payload));
});

test('admin review note is visible and escaped on public correction history', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Review Note Cup']);
    $note = '<script>alert(1)</script>Looks right from sheet.';

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Review Note Cup Fixed',
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.title'],
            'review_note' => $note,
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $html = $this->actingAs($submitter)
        ->get(route('tournaments.corrections.history', $tournament))
        ->assertOk()
        ->assertSee('Admin reply')
        ->getContent();

    expect($html)
        ->not()->toContain($note)
        ->toContain(e($note));
});

test('pending correction blocks all users for that tournament', function () {
    $firstUser = User::factory()->withSetup()->create();
    $secondUser = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Original title']);

    $this->actingAs($firstUser)
        ->post(route('tournaments.corrections.store', $tournament), ['title' => 'First correction'])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $this->actingAs($secondUser)
        ->post(route('tournaments.corrections.store', $tournament), ['title' => 'Second correction'])
        ->assertSessionHas('error');

    expect(TournamentCorrection::query()->count())->toBe(1);
});

test('authenticated user can submit missing metadata fields and description is ignored', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'description' => 'Forum sourced description',
        'modes' => ['osu'],
        'restricted_countries' => [],
        'is_bws' => false,
    ]);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'description' => 'Should not be suggested',
            'modes' => ['mania'],
            'mania_variants' => ['mania_4k', 'mania_7k'],
            'team_formation_style' => 'draft',
            'format_structure' => [
                'stages' => [
                    [
                        'type' => 'bracket',
                        'start_round_size' => 64,
                        'elimination_type' => 'single_elimination',
                    ],
                ],
            ],
            'restricted_countries' => 'kr, jp',
            'star_rating_first' => 4.5,
            'star_rating_last' => 7.2,
            'star_rating_qualifier' => 5.1,
            'is_bws' => true,
            'bws_base_exponent' => 0.9925,
            'bws_badge_power' => 2.5,
            'bws_divisor' => 1.25,
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $changes = TournamentCorrection::query()->firstOrFail()->payload['changes'];

    expect($changes)->toHaveKeys([
        'metadata.modes',
        'metadata.team_formation_style',
        'metadata.format_structure',
        'metadata.restricted_countries',
        'metadata.star_rating_first',
        'metadata.star_rating_last',
        'metadata.star_rating_qualifier',
        'metadata.is_bws',
        'metadata.bws_base_exponent',
        'metadata.bws_badge_power',
        'metadata.bws_divisor',
    ])
        ->and($changes)->not->toHaveKey('metadata.description')
        ->and($changes['metadata.modes']['apply'])->toBe([
            ['mode' => 'mania', 'key_count' => 4],
            ['mode' => 'mania', 'key_count' => 7],
        ])
        ->and($changes['metadata.restricted_countries']['apply'])->toBe(['KR', 'JP']);
});

test('correction form renders admin parity metadata controls', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'modes' => ['osu'],
        'is_bws' => false,
    ]);

    $response = $this->actingAs($user)
        ->get(route('tournaments.corrections.create', $tournament));

    $response->assertStatus(200);
    $response->assertSee('Game Modes');
    $response->assertSee('Select every osu! game mode supported by this tournament.');
    $response->assertSee('Lowest allowed rank number. Leave empty with Rank Max for Open Rank.');
    $response->assertSee('x-show="maniaChecked"', false);
    $response->assertSee('VS Size');
    $response->assertSee('Team Size Min');
    $response->assertSee('Team Size Max');
    $response->assertSee('Region Templates');
    $response->assertSee('Search country or code...');
    $response->assertSee('Add Stage');
    $response->assertSee('Delete');
    $response->assertSee('Single Elimination');
    $response->assertSee('Swiss rounds');
    $response->assertSee('x-show="bwsChecked"', false);
});

test('correction submission accepts admin style structured metadata', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'modes' => ['osu'],
        'restricted_countries' => [],
        'is_bws' => false,
        'format_structure' => ['stages' => []],
    ]);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'modes' => ['osu', 'mania'],
            'mania_variants' => ['mania_4k'],
            'team_formation_style' => 'auction',
            'format_structure' => [
                'stages' => [
                    [
                        'type' => 'qualifier',
                        'advance_count' => 64,
                    ],
                    [
                        'type' => 'swiss_round',
                        'round_count' => 5,
                        'advance_count' => 16,
                    ],
                    [
                        'type' => 'bracket',
                        'start_round_size' => 16,
                        'elimination_type' => 'double_elimination',
                        'entry_type' => 'winner_loser_hybrid',
                    ],
                ],
            ],
            'restricted_countries' => ['kr', 'jp'],
            'is_bws' => true,
            'bws_base_exponent' => 0.9925,
            'bws_badge_power' => 2.5,
            'bws_divisor' => 1.25,
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $changes = TournamentCorrection::query()->firstOrFail()->payload['changes'];

    expect($changes['metadata.modes']['apply'])->toContain(['mode' => 'mania', 'key_count' => 4])
        ->and($changes['metadata.format_structure']['apply']['stages'])->toHaveCount(3)
        ->and($changes['metadata.format_structure']['apply']['stages'][1]['type'])->toBe('swiss_round')
        ->and($changes['metadata.format_tags']['apply'])->toContain('auction')
        ->and($changes['metadata.restricted_countries']['apply'])->toBe(['KR', 'JP'])
        ->and($changes['metadata.is_bws']['apply'])->toBeTrue();
});

test('correction submission can remove all format stages without legacy rebuild', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format' => 'Double Elimination',
        'start_round_size' => 32,
        'format_structure' => [
            'stages' => [[
                'type' => 'bracket',
                'start_round_size' => 32,
                'entry_type' => 'winner_only',
                'elimination_type' => 'double_elimination',
            ]],
            'legacy_format' => 'Double Elimination',
        ],
    ]);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'format' => 'Double Elimination',
            'format_structure_present' => '1',
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $changes = TournamentCorrection::query()->firstOrFail()->payload['changes'];

    expect($changes)->toHaveKey('metadata.format_structure')
        ->and($changes['metadata.format_structure']['apply'])->toBe(['stages' => []])
        ->and($changes['metadata.format_structure']['new'])->toBeNull()
        ->and($changes['metadata.format_structure']['apply'])->not->toHaveKey('legacy_format')
        ->and($changes)->toHaveKey('metadata.start_round_size')
        ->and($changes['metadata.start_round_size']['apply'])->toBeNull()
        ->and($changes['metadata.start_round_size']['visible'])->toBeFalse()
        ->and($changes['metadata.start_round_size']['derived_from'])->toBe(['metadata.format_structure']);
});

test('correction submission preserves added and reordered format stages', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format' => 'Double Elimination',
        'start_round_size' => null,
        'format_structure' => ['stages' => []],
    ]);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'format' => 'Double Elimination',
            'format_structure_present' => '1',
            'format_structure' => [
                'stages' => [
                    [
                        'type' => 'swiss_round',
                        'round_count' => 5,
                        'advance_count' => 16,
                    ],
                    [
                        'type' => 'qualifier',
                        'advance_count' => 64,
                    ],
                ],
            ],
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $stages = TournamentCorrection::query()
        ->firstOrFail()
        ->payload['changes']['metadata.format_structure']['apply']['stages'];

    expect($stages)->toHaveCount(2)
        ->and($stages[0])->toEqual([
            'type' => 'swiss_round',
            'advance_count' => 16,
            'round_count' => 5,
        ])
        ->and($stages[1])->toEqual([
            'type' => 'qualifier',
            'advance_count' => 64,
        ]);
});

test('correction submission keeps non mania modes in enhanced mode structure', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'modes' => [['mode' => 'taiko', 'key_count' => null]],
    ]);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'modes' => ['osu'],
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $change = TournamentCorrection::query()->firstOrFail()->payload['changes']['metadata.modes'];

    expect($change['old'])->toBe([['mode' => 'taiko', 'key_count' => null]])
        ->and($change['new'])->toBe([['mode' => 'osu', 'key_count' => null]])
        ->and($change['apply'])->toBe([['mode' => 'osu', 'key_count' => null]]);
});

test('accepting format stage removal clears legacy bracket fallback', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format' => 'Double Elimination',
        'start_round_size' => 32,
        'format_structure' => [
            'stages' => [[
                'type' => 'bracket',
                'start_round_size' => 32,
                'entry_type' => 'winner_only',
                'elimination_type' => 'double_elimination',
            ]],
            'legacy_format' => 'Double Elimination',
        ],
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'format' => 'Double Elimination',
            'format_structure_present' => '1',
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.format_structure'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->format_structure)->toBe(['stages' => []])
        ->and($tournament->start_round_size)->toBeNull()
        ->and($tournament->progression_summary)->toBeNull()
        ->and($tournament->formatStages()->all())->toBe([])
        ->and($correction->status)->toBe(TournamentCorrection::STATUS_APPROVED)
        ->and(data_get($correction->admin_decisions, 'accepted'))->toContain('metadata.format_structure', 'metadata.start_round_size');
});

test('legacy format cleanup is hidden and applied with another accepted correction', function () {
    Queue::fake([ProcessTournamentCorrectionJob::class]);

    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Original title',
        'format_structure' => [
            'stages' => [[
                'type' => 'bracket',
                'legacy_format' => 'Double Elimination',
                'entry_type' => 'winner_only',
                'elimination_type' => 'double_elimination',
                'start_round_size' => 16,
            ]],
            'legacy_format' => 'Double Elimination',
        ],
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Corrected title',
            'format_structure_present' => '1',
            'format_structure' => [
                'stages' => [[
                    'type' => 'bracket',
                    'entry_type' => 'winner_only',
                    'elimination_type' => 'double_elimination',
                    'start_round_size' => 16,
                ]],
            ],
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();
    $visibleChanges = app(TournamentCorrectionService::class)->visibleChanges($correction);

    expect($visibleChanges)->toHaveKey('metadata.title');
    expect(array_key_exists('metadata.format_structure', $visibleChanges))->toBeFalse();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.title'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->title)->toBe('Corrected title')
        ->and($tournament->format_structure)->toBe([
            'stages' => [[
                'type' => 'bracket',
                'entry_type' => 'winner_only',
                'elimination_type' => 'double_elimination',
                'start_round_size' => 16,
            ]],
        ])
        ->and(data_get($correction->admin_decisions, 'accepted'))
        ->toContain('metadata.title', 'metadata.format_structure');
});

test('admin can accept only selected metadata fields', function () {
    $user = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Original title',
        'rank_range_min' => 1000,
    ]);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Corrected title',
            'rank_range_min' => 2000,
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.title'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->title)->toBe('Corrected title')
        ->and($tournament->rank_range_min)->toBe(1000)
        ->and($correction->status)->toBe(TournamentCorrection::STATUS_PARTIALLY_APPROVED);
});

test('admin review page submits explicit finalize action and shows structured summaries', function () {
    $user = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => ['stages' => []],
    ]);

    $this->actingAs($user)
        ->post(route('tournaments.corrections.store', $tournament), [
            'team_formation_style' => 'draft',
            'format_structure' => [
                'stages' => [
                    [
                        'type' => 'qualifier',
                        'advance_count' => 64,
                    ],
                    [
                        'type' => 'bracket',
                        'start_round_size' => 32,
                        'elimination_type' => 'single_elimination',
                    ],
                ],
            ],
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.tournament-corrections.show', $correction))
        ->assertOk()
        ->assertDontSee('type="hidden" name="review_action"', false)
        ->assertSee('type="button" name="review_action" value="reject_all"', false)
        ->assertSee('type="button" name="review_action" value="finalize_selected"', false)
        ->assertSee('data-review-submit', false)
        ->assertSee('data-review-confirm-modal', false)
        ->assertDontSee('data-confirm-reject-all', false)
        ->assertSee('name="accepted_keys[]" value="metadata.format_structure"', false)
        ->assertDontSee('name="accepted_keys[]" value="metadata.format_structure" disabled', false)
        ->assertSee('Qualifier (Top: 64) -&gt; Bracket (Ro32, Elim: Single Elimination)', false)
        ->assertSee('Raw JSON');

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.team_formation_style'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect($correction->refresh()->status)->toBe(TournamentCorrection::STATUS_PARTIALLY_APPROVED);
});

test('reject all ignores submitted selected keys and records all changes as rejected', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Original title',
        'rank_range_min' => 1000,
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Corrected title',
            'rank_range_min' => 2000,
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'reject_all',
            'accepted_keys' => ['metadata.title'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->title)->toBe('Original title')
        ->and($tournament->rank_range_min)->toBe(1000)
        ->and($correction->status)->toBe(TournamentCorrection::STATUS_REJECTED)
        ->and(data_get($correction->admin_decisions, 'accepted'))->toBe([])
        ->and(data_get($correction->admin_decisions, 'rejected'))->toContain('metadata.title', 'metadata.rank_range_min');
});

test('admin can accept selected staff change while rejecting podium change', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $staffUser = User::factory()->create(['username' => 'StaffPerson']);
    $podiumUser = User::factory()->create(['username' => 'PodiumPerson']);
    $tournament = Tournament::factory()->approved()->create([
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'staff_organizer' => 'StaffPerson',
            'podium_groups' => [
                1 => [
                    [
                        'group_key' => 'new-placement-1-0',
                        'team_name' => null,
                        'usernames' => 'PodiumPerson',
                    ],
                ],
            ],
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();
    $staffKey = collect(data_get($correction->payload, 'changes', []))
        ->keys()
        ->first(fn (string $key): bool => str_starts_with($key, 'staff.'));

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => [$staffKey],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect(TournamentStaff::query()
        ->where('tournament_id', $tournament->id)
        ->where('user_id', $staffUser->id)
        ->where('role', 'organizer')
        ->where('status', 'approved')
        ->exists())->toBeTrue()
        ->and(TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $podiumUser->id)
            ->exists())->toBeFalse();
});

test('contributors appear only after accepted correction and history count excludes rejected only corrections', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Original title']);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), ['title' => 'Corrected title']);

    $correction = TournamentCorrection::query()->firstOrFail();

    auth()->logout();

    $this->get(route('tournaments.show', $tournament))
        ->assertDontSee($submitter->username);

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.title'],
        ]);

    $this->get(route('tournaments.show', $tournament))
        ->assertSee($submitter->username)
        ->assertSee('History (1)');
});

test('admin can accept podium team name as grouped podium change', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $firstWinner = User::factory()->create(['username' => 'WinnerOne']);
    $secondWinner = User::factory()->create(['username' => 'WinnerTwo']);
    $tournament = Tournament::factory()->approved()->create([
        'modes' => ['osu'],
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    foreach ([$firstWinner, $secondWinner] as $winner) {
        UserRankHistory::query()->create([
            'user_id' => $winner->id,
            'mode' => 'osu',
            'rank' => 1000,
            'recorded_at' => now(),
        ]);
    }

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'podium_groups' => [
                1 => [
                    [
                        'group_key' => 'new-placement-1-0',
                        'team_name' => 'Team Alpha',
                        'usernames' => "WinnerOne\nWinnerTwo",
                    ],
                ],
            ],
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();
    $podiumKey = collect(data_get($correction->payload, 'changes', []))
        ->keys()
        ->first(fn (string $key): bool => str_starts_with($key, 'podium.group.1.'));

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => [$podiumKey],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $winners = TournamentWinner::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('user_id', [$firstWinner->id, $secondWinner->id])
        ->get();

    expect($winners)->toHaveCount(2)
        ->and($winners->pluck('metadata.podium_group_id')->filter()->unique())->toHaveCount(1);
    expect(TournamentParticipationRecord::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('user_id', [$firstWinner->id, $secondWinner->id])
        ->pluck('team_name')
        ->unique()
        ->values()
        ->all())->toBe(['Team Alpha']);
});

test('correction workflow supports multiple same placement podium teams', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $teamOneA = User::factory()->create(['username' => 'TeamOneA']);
    $teamOneB = User::factory()->create(['username' => 'TeamOneB']);
    $teamTwoA = User::factory()->create(['username' => 'TeamTwoA']);
    $teamTwoB = User::factory()->create(['username' => 'TeamTwoB']);
    $tournament = Tournament::factory()->approved()->create([
        'modes' => ['osu'],
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    foreach ([$teamOneA, $teamOneB, $teamTwoA, $teamTwoB] as $winner) {
        UserRankHistory::query()->create([
            'user_id' => $winner->id,
            'mode' => 'osu',
            'rank' => 1000,
            'recorded_at' => now(),
        ]);
    }

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'podium_groups' => [
                1 => [
                    [
                        'group_key' => 'new-placement-1-0',
                        'team_name' => 'Team One',
                        'usernames' => "TeamOneA\nTeamOneB",
                    ],
                    [
                        'group_key' => 'new-placement-1-1',
                        'team_name' => 'Team Two',
                        'usernames' => "TeamTwoA\nTeamTwoB",
                    ],
                ],
            ],
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => [
                'podium.group.1.new-placement-1-0',
                'podium.group.1.new-placement-1-1',
            ],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $winners = TournamentWinner::query()
        ->where('tournament_id', $tournament->id)
        ->where('placement', 1)
        ->get()
        ->keyBy('user_id');

    $firstGroupId = data_get($winners->get($teamOneA->id)?->metadata, 'podium_group_id');
    $secondGroupId = data_get($winners->get($teamTwoA->id)?->metadata, 'podium_group_id');

    expect($winners)->toHaveCount(4)
        ->and($firstGroupId)->not->toBeNull()
        ->and($secondGroupId)->not->toBeNull()
        ->and($firstGroupId)->not->toBe($secondGroupId)
        ->and(data_get($winners->get($teamOneB->id)?->metadata, 'podium_group_id'))->toBe($firstGroupId)
        ->and(data_get($winners->get($teamTwoB->id)?->metadata, 'podium_group_id'))->toBe($secondGroupId);

    expect(TournamentParticipationRecord::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('user_id', [$teamOneA->id, $teamOneB->id])
        ->pluck('team_name')
        ->unique()
        ->values()
        ->all())->toBe(['Team One']);

    expect(TournamentParticipationRecord::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('user_id', [$teamTwoA->id, $teamTwoB->id])
        ->pluck('team_name')
        ->unique()
        ->values()
        ->all())->toBe(['Team Two']);
});

test('badge url corrections preserve placement keyed arrays', function () {
    $submitter = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'badge_urls' => [
            '1' => ['https://assets.ppy.sh/profile-badges/first.png'],
        ],
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'badge_urls' => [
                1 => ["https://assets.ppy.sh/profile-badges/first.png\nhttps://assets.ppy.sh/profile-badges/first-extra.png"],
                2 => ['https://assets.ppy.sh/profile-badges/second.png'],
                3 => ['https://assets.ppy.sh/profile-badges/third.png'],
            ],
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $change = TournamentCorrection::query()->firstOrFail()->payload['changes']['metadata.badge_urls'];

    expect($change['apply'])->toBe([
        '1' => [
            'https://assets.ppy.sh/profile-badges/first.png',
            'https://assets.ppy.sh/profile-badges/first-extra.png',
        ],
        '2' => ['https://assets.ppy.sh/profile-badges/second.png'],
        '3' => ['https://assets.ppy.sh/profile-badges/third.png'],
    ]);
});

test('correction form renders placement badge inputs and prefilled member lists', function () {
    $submitter = User::factory()->withSetup()->create();
    $staff = User::factory()->create(['username' => 'ExistingStaff']);
    $winner = User::factory()->create(['username' => 'ExistingWinner']);
    $tournament = Tournament::factory()->approved()->create([
        'badge_urls' => [
            '2' => ['https://assets.ppy.sh/profile-badges/second.png'],
        ],
    ]);
    $tournament->staff()->attach($staff->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $winner->id,
        'placement' => 2,
        'username' => $winner->username,
        'osu_id' => $winner->osu_id,
        'gamemode' => 'osu',
    ]);

    $this->actingAs($submitter)
        ->get(route('tournaments.corrections.create', $tournament))
        ->assertOk()
        ->assertSee('name="badge_urls[1][]"', false)
        ->assertSee('name="badge_urls[2][]"', false)
        ->assertSee('name="badge_urls[3][]"', false)
        ->assertSee('https://assets.ppy.sh/profile-badges/second.png')
        ->assertSee('Submit this correction for admin review?', false)
        ->assertSee('name="podium_groups[2][0][group_key]"', false)
        ->assertDontSee('name="podium_2"', false)
        ->assertSee('ExistingStaff')
        ->assertSee('ExistingWinner');
});

test('staff final state can suggest removing existing members', function () {
    $submitter = User::factory()->withSetup()->create();
    $staff = User::factory()->create(['username' => 'RemoveMe']);
    $tournament = Tournament::factory()->approved()->create();
    $tournament->staff()->attach($staff->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'staff_organizer' => '',
        ])
        ->assertRedirect(route('tournaments.corrections.history', $tournament));

    $changes = TournamentCorrection::query()->firstOrFail()->payload['changes'];

    expect($changes)->toHaveKey('staff.remove.organizer.removeme')
        ->and($changes['staff.remove.organizer.removeme']['apply']['action'])->toBe('remove');
});

test('accepted missing staff user is resolved through osu sync service', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create();
    $syncService = Mockery::mock(TournamentParticipantSyncService::class);

    $syncService->shouldReceive('addStaffByUsername')
        ->once()
        ->with(Mockery::type(Tournament::class), 'MissingStaff', 'organizer', $admin->id)
        ->andReturnUsing(function (Tournament $tournament): TournamentStaff {
            $user = User::factory()->create(['username' => 'MissingStaff']);

            return TournamentStaff::query()->create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'role' => 'organizer',
                'status' => 'approved',
                'source' => 'manual',
                'submitted_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);
        });
    app()->instance(TournamentParticipantSyncService::class, $syncService);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'staff_organizer' => 'MissingStaff',
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['staff.add.organizer.missingstaff'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect(TournamentStaff::query()
        ->where('tournament_id', $tournament->id)
        ->where('role', 'organizer')
        ->whereHas('user', fn ($query) => $query->where('username', 'MissingStaff'))
        ->exists())->toBeTrue();
});

test('correction staff duplicate checks resolve previous usernames with shared precedence', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $staffUser = User::factory()->create([
        'username' => 'CurrentStaffName',
        'previous_usernames' => ['FormerStaffName'],
    ]);
    $tournament = Tournament::factory()->approved()->create();
    TournamentStaff::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $staffUser->id,
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    $syncService = Mockery::mock(TournamentParticipantSyncService::class);
    $syncService->shouldNotReceive('addStaffByUsername');
    app()->instance(TournamentParticipantSyncService::class, $syncService);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'staff_organizer' => 'FormerStaffName',
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['staff.add.organizer.formerstaffname'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect(TournamentStaff::query()
        ->where('tournament_id', $tournament->id)
        ->where('user_id', $staffUser->id)
        ->where('role', 'organizer')
        ->count())->toBe(1);
});

test('podium final state can remove existing winner and participation record', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $winnerUser = User::factory()->create(['username' => 'OldWinner']);
    $tournament = Tournament::factory()->approved()->create([
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    $winner = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $winnerUser->id,
        'placement' => 1,
        'username' => $winnerUser->username,
        'osu_id' => $winnerUser->osu_id,
        'gamemode' => 'osu',
        'metadata' => ['podium_group_id' => 'remove-group', 'podium_group_manual' => true],
    ]);
    TournamentParticipationRecord::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $winnerUser->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'final_result' => TournamentParticipationRecord::RESULT_WINNER,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners', 'podium_group_id' => 'remove-group'],
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'podium_groups' => [
                1 => [],
            ],
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['podium.group.remove.1.remove-group'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect(TournamentWinner::query()->whereKey($winner->id)->exists())->toBeFalse()
        ->and(TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $winnerUser->id)
            ->exists())->toBeFalse();
});

test('accepted missing podium user is resolved through osu sync service', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    $syncService = Mockery::mock(TournamentParticipantSyncService::class);

    $syncService->shouldReceive('addPodiumByUsername')
        ->once()
        ->with(Mockery::type(Tournament::class), 'MissingWinner', 2, Mockery::type('string'), false)
        ->andReturnUsing(function (Tournament $tournament): TournamentWinner {
            $user = User::factory()->create(['username' => 'MissingWinner']);

            return TournamentWinner::query()->create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'placement' => 2,
                'username' => $user->username,
                'osu_id' => $user->osu_id,
                'gamemode' => 'osu',
            ]);
        });
    app()->instance(TournamentParticipantSyncService::class, $syncService);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'podium_groups' => [
                2 => [
                    [
                        'group_key' => 'new-placement-2-0',
                        'team_name' => 'Deferred Team',
                        'usernames' => 'MissingWinner',
                    ],
                ],
            ],
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['podium.group.2.new-placement-2-0'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect(TournamentWinner::query()
        ->where('tournament_id', $tournament->id)
        ->where('placement', 2)
        ->where('username', 'MissingWinner')
        ->exists())->toBeTrue()
        ->and(TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->value('team_name'))->toBe('Deferred Team');
});

test('accepted blank podium team name clears existing participation team names', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $winnerUser = User::factory()->create(['username' => 'ClearTeamWinner']);
    $tournament = Tournament::factory()->approved()->create([
        'modes' => ['osu'],
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    $winner = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $winnerUser->id,
        'placement' => 1,
        'username' => $winnerUser->username,
        'osu_id' => $winnerUser->osu_id,
        'gamemode' => 'osu',
        'metadata' => [
            'podium_group_id' => 'clear-team-group',
            'podium_group_manual' => true,
        ],
    ]);
    TournamentParticipationRecord::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $winnerUser->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'team_name' => 'Old Team',
        'placement' => 1,
        'metadata' => [
            'autofilled_from' => 'tournament_winners',
            'winner_id' => $winner->id,
            'podium_group_id' => 'clear-team-group',
        ],
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'podium_groups' => [
                1 => [[
                    'group_key' => 'clear-team-group',
                    'group_id' => 'clear-team-group',
                    'team_name' => '',
                    'usernames' => 'ClearTeamWinner',
                ]],
            ],
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['podium.group.1.clear-team-group'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect(TournamentParticipationRecord::query()
        ->where('tournament_id', $tournament->id)
        ->where('user_id', $winnerUser->id)
        ->value('team_name'))->toBeNull();
});

test('unchanged prefilled staff and podium lists produce no correction', function () {
    $submitter = User::factory()->withSetup()->create();
    $staff = User::factory()->create(['username' => 'StableStaff']);
    $winnerUser = User::factory()->create(['username' => 'StableWinner']);
    $tournament = Tournament::factory()->approved()->create();
    $tournament->staff()->attach($staff->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $winnerUser->id,
        'placement' => 1,
        'username' => $winnerUser->username,
        'osu_id' => $winnerUser->osu_id,
        'gamemode' => 'osu',
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'staff_organizer' => 'StableStaff',
            'podium_groups' => [
                1 => [
                    [
                        'group_key' => 'legacy-placement-1',
                        'team_name' => null,
                        'usernames' => 'StableWinner',
                    ],
                ],
            ],
        ])
        ->assertSessionHas('error', 'No changes were suggested.');

    expect(TournamentCorrection::query()->exists())->toBeFalse();
});

test('finalize selected requires at least one selected change unless rejecting all', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Original title']);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), ['title' => 'Corrected title']);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->from(route('tournament-corrections.show', $correction))
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction))
        ->assertSessionHasErrors('accepted_keys');

    $this->actingAs($admin)
        ->from(route('tournament-corrections.show', $correction))
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.stale_key'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction))
        ->assertSessionHasErrors('accepted_keys');

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'reject_all',
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    expect($correction->refresh()->status)->toBe(TournamentCorrection::STATUS_REJECTED);
});

test('correction history renders structured summaries with raw json', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => ['stages' => []],
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'format_structure' => [
                'stages' => [
                    [
                        'type' => 'swiss_round',
                        'round_count' => 5,
                        'advance_count' => 16,
                    ],
                ],
            ],
        ]);

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.format_structure'],
        ]);

    $this->actingAs($submitter)
        ->get(route('tournaments.corrections.history', $tournament))
        ->assertOk()
        ->assertSee('Swiss Round (Top: 16, Rounds: 5)')
        ->assertSee('Raw JSON');
});

test('correction history groups staff changes by role', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $existingOrganizer = User::factory()->create(['username' => 'OldOrganizer']);
    $newOrganizer = User::factory()->create(['username' => 'NewOrganizer']);
    $newMapper = User::factory()->create(['username' => 'NewMapper']);
    $tournament = Tournament::factory()->approved()->create();
    $tournament->staff()->attach($existingOrganizer->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'manual',
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'staff_organizer' => 'NewOrganizer',
            'staff_mapper' => 'NewMapper',
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();
    $changes = array_keys(data_get($correction->payload, 'changes', []));

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => [$changes[0]],
        ])
        ->assertRedirect();

    $html = $this->actingAs($submitter)
        ->get(route('tournaments.corrections.history', $tournament))
        ->assertOk()
        ->assertSee('Organizer')
        ->assertSee('Mapper')
        ->assertSee('Added')
        ->assertSee('Removed')
        ->assertSee('NewOrganizer')
        ->assertSee('OldOrganizer')
        ->assertSee('NewMapper')
        ->getContent();

    expect($html)->not->toContain('Add Staff: Organizer')
        ->and($html)->not->toContain('Remove Staff: Organizer');
});

test('derived format fields are hidden from review and history while still applying with accepted source', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_STANDARD,
        'format_tags' => [],
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    expect($correction->payload['changes']['metadata.format_tags']['visible'] ?? null)->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSee('Team Formation Style')
        ->assertDontSee('Format Tags')
        ->assertDontSee('name="accepted_keys[]" value="metadata.format_tags"', false)
        ->assertSee('Finalize Selected (<span data-selected-count>1</span>)', false);

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.team_formation_style'],
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction));

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->team_formation_style)->toBe(Tournament::TEAM_FORMATION_DRAFT)
        ->and($tournament->format_tags)->toContain(Tournament::FORMAT_TAG_DRAFT)
        ->and(data_get($correction->admin_decisions, 'accepted'))->toContain('metadata.team_formation_style', 'metadata.format_tags');

    $this->actingAs($submitter)
        ->get(route('tournaments.corrections.history', $tournament))
        ->assertOk()
        ->assertSee('1 approved / 1 suggested')
        ->assertSee('Team Formation Style')
        ->assertDontSee('Format Tags');
});

test('correction history uses compact header, single flash surface, and five item pagination', function () {
    $submitter = User::factory()->withSetup()->create(['username' => 'HistorySubmitter']);
    $reviewer = User::factory()->admin()->create(['username' => 'HistoryReviewer']);
    $tournament = Tournament::factory()->approved()->create();

    for ($index = 1; $index <= 6; $index++) {
        TournamentCorrection::factory()->create([
            'tournament_id' => $tournament->id,
            'submitted_by' => $submitter->id,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now()->subMinutes($index),
            'status' => TournamentCorrection::STATUS_APPROVED,
            'created_at' => now()->subMinutes($index),
            'payload' => [
                'changes' => [
                    "metadata.title.{$index}" => [
                        'domain' => 'metadata',
                        'field' => 'title',
                        'label' => "History Title {$index}",
                        'old' => "Old {$index}",
                        'new' => "New {$index}",
                    ],
                ],
            ],
            'admin_decisions' => [
                'accepted' => ["metadata.title.{$index}"],
                'rejected' => [],
            ],
        ]);
    }

    $response = $this
        ->actingAs($submitter)
        ->withSession(['success' => 'Correction saved once'])
        ->get(route('tournaments.corrections.history', $tournament));

    $firstCorrection = TournamentCorrection::query()->latest()->firstOrFail();
    $content = $response->getContent();

    $response->assertOk()
        ->assertViewHas('corrections', fn ($corrections): bool => $corrections->count() === 5 && $corrections->perPage() === 5)
        ->assertSee("#{$firstCorrection->id}")
        ->assertSee('Submitted at')
        ->assertSee('by <a href="'.route('users.show', $submitter).'" class="font-semibold text-pink-400 hover:underline">HistorySubmitter</a>', false)
        ->assertSee('Reviewed at')
        ->assertSee('by <a href="'.route('users.show', $reviewer).'" class="font-semibold text-pink-400 hover:underline">HistoryReviewer</a>', false);

    expect(substr_count($content, 'Correction saved once'))->toBe(1);
});

test('admin and public correction pages render changes in correction form order', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create();
    $payload = [
        'changes' => [
            'metadata.discord_url' => [
                'domain' => 'metadata',
                'field' => 'discord_url',
                'label' => 'Discord URL',
                'old' => null,
                'new' => 'https://discord.gg/order',
            ],
            'metadata.is_bws' => [
                'domain' => 'metadata',
                'field' => 'is_bws',
                'label' => 'BWS Ranking',
                'old' => false,
                'new' => true,
            ],
            'podium.add.1.winner' => [
                'domain' => 'podium',
                'field' => '1',
                'label' => 'Add Podium',
                'old' => null,
                'new' => 'Winner',
            ],
            'staff.add.organizer.host' => [
                'domain' => 'staff',
                'field' => 'organizer',
                'label' => 'Add Staff',
                'old' => null,
                'new' => 'Host',
            ],
            'metadata.rank_range_min' => [
                'domain' => 'metadata',
                'field' => 'rank_range_min',
                'label' => 'Rank Min',
                'old' => 1000,
                'new' => 2000,
            ],
            'metadata.format_structure' => [
                'domain' => 'metadata',
                'field' => 'format_structure',
                'label' => 'Format Structure',
                'old' => null,
                'new' => ['stages' => [['type' => 'qualifier', 'advance_count' => 32]]],
            ],
            'metadata.banner_url' => [
                'domain' => 'metadata',
                'field' => 'banner_url',
                'label' => 'Banner URL',
                'old' => null,
                'new' => 'https://example.com/banner.jpg',
            ],
        ],
    ];

    $correction = TournamentCorrection::factory()->create([
        'tournament_id' => $tournament->id,
        'submitted_by' => $submitter->id,
        'payload' => $payload,
    ]);

    $expectedAdminOrder = [
        'Banner URL',
        'Format Structure',
        'Rank Min',
        'Add Staff',
        'Add Podium',
        'BWS Ranking',
        'Discord URL',
    ];

    $this->actingAs($admin)
        ->get(route('admin.tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSeeInOrder($expectedAdminOrder);

    $expectedPublicOrder = [
        'Banner URL',
        'Format Structure',
        'Rank Min',
        'Organizer',
        '1st Place',
        'BWS Ranking',
        'Discord URL',
    ];

    $this->actingAs($submitter)
        ->get(route('tournaments.corrections.history', $tournament))
        ->assertOk()
        ->assertSeeInOrder($expectedPublicOrder);
});

test('admin correction review page uses confirmation modal before final submit', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $submitter = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Original Review Modal Cup',
        'rank_range_min' => 1000,
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Review Modal Cup',
            'rank_range_min' => 2000,
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSee('data-review-confirm-modal', false)
        ->assertSee('data-review-action="finalize_selected"', false)
        ->assertSee('data-review-action="reject_all"', false)
        ->assertSee('Confirm correction review')
        ->assertDontSee('data-confirm-reject-all', false)
        ->assertDontSee('type="submit" name="review_action"', false);
});

test('correction review audit does not attribute automated application as admin contribution', function () {
    $admin = User::factory()->admin()->withSetup()->create(['username' => 'ReviewOnlyAdmin']);
    $submitter = User::factory()->withSetup()->create(['username' => 'ActualCorrectionUser']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Quiet Correction Cup']);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Quiet Correction Cup Updated',
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.title'],
        ])
        ->assertRedirect();

    expect(AdminAuditLog::query()->where('action', 'tournament.correction_reviewed')->exists())->toBeTrue();

    auth()->logout();

    $this->get(route('tournaments.show', $tournament))
        ->assertOk()
        ->assertSee('ActualCorrectionUser')
        ->assertDontSee('ReviewOnlyAdmin');

    $this->get(route('users.show', $admin).'?tab=contributions')
        ->assertOk()
        ->assertDontSee('Quiet Correction Cup');
});

test('admin direct edits appear as tournament and user profile contributions', function () {
    $admin = User::factory()->admin()->create(['username' => 'AdminContributor']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Edited Tournament']);

    AdminAuditLog::factory()->create([
        'admin_id' => $admin->id,
        'action' => 'tournament.updated',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
        'details' => ['changes' => ['title' => ['old' => 'Old', 'new' => 'New']]],
    ]);

    $this->get(route('tournaments.show', $tournament))
        ->assertSee('AdminContributor');

    $this->get(route('users.show', $admin).'?tab=contributions')
        ->assertSee('Edited Tournament')
        ->assertSeeText('1 tournament')
        ->assertDontSee('Admin edit');
});

test('user profile contribution list merges corrections and admin edits per tournament', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Grouped Contribution Cup',
        'rank_range_min' => 1000,
    ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'rank_range_min' => 2000,
        ]);

    $firstCorrection = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $firstCorrection), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.rank_range_min'],
        ]);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'discord_url' => 'https://discord.gg/grouped',
        ]);

    $secondCorrection = TournamentCorrection::query()->latest('id')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $secondCorrection), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => ['metadata.discord_url'],
        ]);

    AdminAuditLog::factory()->create([
        'admin_id' => $submitter->id,
        'action' => 'tournament.updated',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
        'details' => ['changes' => ['title' => ['old' => 'Old', 'new' => 'New']]],
    ]);

    $response = $this->get(route('users.show', $submitter).'?tab=contributions');

    $response->assertOk()
        ->assertSeeText('1 tournament')
        ->assertDontSee('>Tournament</a>', false);

    expect(substr_count($response->getContent(), 'Grouped Contribution Cup'))->toBe(1);
});

test('user profile contribution list initially renders first ten tournaments only', function () {
    $submitter = User::factory()->withSetup()->create();

    for ($index = 1; $index <= 12; $index++) {
        $tournament = Tournament::factory()->approved()->create([
            'title' => sprintf('Contribution Cup %02d', $index),
        ]);

        TournamentCorrection::factory()->create([
            'tournament_id' => $tournament->id,
            'submitted_by' => $submitter->id,
            'status' => TournamentCorrection::STATUS_APPROVED,
            'reviewed_at' => now()->subMinutes($index),
        ]);
    }

    $response = $this->get(route('users.show', $submitter).'?tab=contributions');

    $response->assertOk()
        ->assertSeeText('12 tournaments')
        ->assertSee('Load more')
        ->assertSee('Contribution Cup 01')
        ->assertSee('Contribution Cup 10')
        ->assertDontSee('Contribution Cup 11')
        ->assertDontSee('Contribution Cup 12')
        ->assertDontSee('Admin edit')
        ->assertDontSee('changed field');
});

test('user profile contribution endpoint returns the next contribution page only', function () {
    $submitter = User::factory()->withSetup()->create();

    for ($index = 1; $index <= 12; $index++) {
        $tournament = Tournament::factory()->approved()->create([
            'title' => sprintf('Paged Contribution Cup %02d', $index),
        ]);

        TournamentCorrection::factory()->create([
            'tournament_id' => $tournament->id,
            'submitted_by' => $submitter->id,
            'status' => TournamentCorrection::STATUS_APPROVED,
            'reviewed_at' => now()->subMinutes($index),
        ]);
    }

    $response = $this->getJson(route('users.contributions', [$submitter, 'offset' => 10]));

    $response->assertOk()
        ->assertJsonPath('next_offset', null)
        ->assertJsonPath('has_more', false);

    $html = $response->json('html');

    expect($html)
        ->toContain('Paged Contribution Cup 11')
        ->toContain('Paged Contribution Cup 12');
    expect($html)->not->toContain('Paged Contribution Cup 01');
});

test('user profile contribution list counts duplicate tournament contributions once', function () {
    $submitter = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Duplicate Contribution Cup']);

    TournamentCorrection::factory()->count(2)->create([
        'tournament_id' => $tournament->id,
        'submitted_by' => $submitter->id,
        'status' => TournamentCorrection::STATUS_APPROVED,
        'reviewed_at' => now(),
    ]);
    AdminAuditLog::factory()->create([
        'admin_id' => $submitter->id,
        'action' => 'tournament.updated',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
        'created_at' => now(),
    ]);

    $response = $this->get(route('users.show', $submitter).'?tab=contributions');

    $response->assertOk()
        ->assertSeeText('1 tournament')
        ->assertDontSee('Load more');

    expect(substr_count($response->getContent(), 'Duplicate Contribution Cup'))->toBe(1);
});

test('user profile contribution list only includes approved tournaments', function () {
    $submitter = User::factory()->withSetup()->create();
    $approvedTournament = Tournament::factory()->approved()->create(['title' => 'Visible Contribution Cup']);
    $pendingTournament = Tournament::factory()->pending()->create(['title' => 'Pending Contribution Cup']);
    $rejectedTournament = Tournament::factory()->rejected()->create(['title' => 'Rejected Contribution Cup']);

    foreach ([$approvedTournament, $pendingTournament, $rejectedTournament] as $tournament) {
        TournamentCorrection::factory()->create([
            'tournament_id' => $tournament->id,
            'submitted_by' => $submitter->id,
            'status' => TournamentCorrection::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    AdminAuditLog::factory()->create([
        'admin_id' => $submitter->id,
        'action' => 'tournament.updated',
        'entity_type' => Tournament::class,
        'entity_id' => $pendingTournament->id,
        'created_at' => now(),
    ]);

    $response = $this->get(route('users.show', $submitter).'?tab=contributions');

    $response->assertOk()
        ->assertSeeText('1 tournament')
        ->assertSee('Visible Contribution Cup')
        ->assertDontSee('Pending Contribution Cup')
        ->assertDontSee('Rejected Contribution Cup');
});

test('user profile contribution endpoint only includes approved tournaments', function () {
    $submitter = User::factory()->withSetup()->create();
    $approvedTournament = Tournament::factory()->approved()->create(['title' => 'Ajax Visible Contribution Cup']);
    $pendingTournament = Tournament::factory()->pending()->create(['title' => 'Ajax Pending Contribution Cup']);

    foreach ([$approvedTournament, $pendingTournament] as $tournament) {
        TournamentCorrection::factory()->create([
            'tournament_id' => $tournament->id,
            'submitted_by' => $submitter->id,
            'status' => TournamentCorrection::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    $response = $this->getJson(route('users.contributions', $submitter));

    $response->assertOk()
        ->assertJsonPath('next_offset', null)
        ->assertJsonPath('has_more', false);

    $html = $response->json('html');

    expect($html)
        ->toContain('Ajax Visible Contribution Cup')
        ->not()->toContain('Ajax Pending Contribution Cup');
});

test('user profile contribution list renders empty state', function () {
    $user = User::factory()->withSetup()->create();

    $this->get(route('users.show', $user).'?tab=contributions')
        ->assertOk()
        ->assertSeeText('0 tournaments')
        ->assertSee('No contributions yet.')
        ->assertDontSee('Load more');
});

test('correction approval saves metadata and records failed podium username without 500', function () {
    Queue::fake();

    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Original Failure Cup',
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);

    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUserByUsername')
        ->once()
        ->with('disctable')
        ->andReturn(null);
    app()->instance(OsuApiService::class, $osuApi);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'title' => 'Corrected Failure Cup',
            'podium_groups' => [
                1 => [
                    [
                        'group_key' => 'new-placement-1-0',
                        'team_name' => null,
                        'usernames' => 'disctable',
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => array_keys($correction->payload['changes']),
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction))
        ->assertSessionHas('success');

    (new ProcessTournamentCorrectionJob($correction->id))
        ->handle(app(TournamentCorrectionService::class));

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->title)->toBe('Corrected Failure Cup')
        ->and($correction->status)->toBe(TournamentCorrection::STATUS_PARTIALLY_APPROVED)
        ->and(data_get($correction->admin_decisions, 'apply_failures.0.username'))->toBe('disctable')
        ->and(data_get($correction->admin_decisions, 'apply_failures.0.message'))->toBe("User 'disctable' not found on osu!")
        ->and(TournamentWinner::query()->where('tournament_id', $tournament->id)->exists())->toBeFalse();
});

test('correction approval adds valid podium users and skips invalid usernames in same group', function () {
    Queue::fake();

    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $validUser = User::factory()->create(['username' => 'ValidWinner']);
    $tournament = Tournament::factory()->approved()->create([
        'modes' => ['osu'],
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    UserRankHistory::query()->create([
        'user_id' => $validUser->id,
        'mode' => 'osu',
        'rank' => 1000,
        'recorded_at' => now(),
    ]);

    $osuApi = Mockery::mock(OsuApiService::class);
    $osuApi->shouldReceive('getUserByUsername')
        ->once()
        ->with('anon_uwu')
        ->andReturn(null);
    app()->instance(OsuApiService::class, $osuApi);

    $this->actingAs($submitter)
        ->post(route('tournaments.corrections.store', $tournament), [
            'podium_groups' => [
                1 => [
                    [
                        'group_key' => 'new-placement-1-0',
                        'team_name' => 'Winners',
                        'usernames' => "ValidWinner\nanon_uwu",
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $correction = TournamentCorrection::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.tournament-corrections.review', $correction), [
            'review_action' => 'finalize_selected',
            'accepted_keys' => array_keys($correction->payload['changes']),
        ])
        ->assertRedirect(route('tournament-corrections.show', $correction))
        ->assertSessionHas('success');

    (new ProcessTournamentCorrectionJob($correction->id))
        ->handle(app(TournamentCorrectionService::class));

    $correction->refresh();

    expect(TournamentWinner::query()
        ->where('tournament_id', $tournament->id)
        ->where('user_id', $validUser->id)
        ->where('placement', 1)
        ->exists())->toBeTrue()
        ->and(TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->whereRaw('lower(username) = ?', ['anon_uwu'])
            ->exists())->toBeFalse()
        ->and($correction->status)->toBe(TournamentCorrection::STATUS_PARTIALLY_APPROVED)
        ->and(data_get($correction->admin_decisions, 'apply_failures.0.username'))->toBe('anon_uwu');
});

test('correction apply failure panel escapes usernames and messages', function () {
    $submitter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create();
    $payload = '<script>alert(1)</script>';

    $correction = TournamentCorrection::factory()->create([
        'tournament_id' => $tournament->id,
        'submitted_by' => $submitter->id,
        'status' => TournamentCorrection::STATUS_PARTIALLY_APPROVED,
        'admin_decisions' => [
            'accepted' => [],
            'rejected' => [],
            'apply_failures' => [
                [
                    'key' => 'podium.add.1.bad',
                    'domain' => 'podium',
                    'username' => $payload,
                    'message' => "User '{$payload}' not found on osu!",
                ],
            ],
        ],
    ]);

    $html = $this->actingAs($admin)
        ->get(route('tournament-corrections.show', $correction))
        ->assertOk()
        ->assertSee('Some accepted changes could not be applied')
        ->getContent();

    expect($html)
        ->not()->toContain($payload)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});
