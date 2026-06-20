<?php

use App\Jobs\SyncUserFromOsu;
use App\Models\AdminAuditLog;
use App\Models\ParticipationDeletionRequest;
use App\Models\ParticipationInputLock;
use App\Models\ParticipationInputLog;
use App\Models\ParticipationRecordMatch;
use App\Models\ParticipationRecordReport;
use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRankHistory;
use App\Services\ParticipationPlacementService;
use App\Services\ParticipationPodiumBackfillService;
use App\Services\ParticipationStageOptionsService;
use App\Services\ParticipationStatsService;
use App\Services\TournamentParticipantSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('profile player badge uses recent approved participation records', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'tournament_end' => now()->subMonths(2),
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('PLAYER');
});

test('profile player badge ignores old pending rejected and unapproved participation records', function () {
    $user = User::factory()->withSetup()->create();
    $oldTournament = Tournament::factory()->approved()->create([
        'tournament_end' => now()->subMonths(13),
    ]);
    $pendingTournament = Tournament::factory()->approved()->create([
        'tournament_end' => now()->subMonths(2),
    ]);
    $rejectedTournament = Tournament::factory()->approved()->create([
        'tournament_end' => now()->subMonths(3),
    ]);
    $unapprovedTournament = Tournament::factory()->pending()->create([
        'tournament_end' => now()->subMonths(2),
    ]);

    foreach ([
        [$oldTournament, TournamentParticipationRecord::REVIEW_APPROVED],
        [$pendingTournament, TournamentParticipationRecord::REVIEW_PENDING],
        [$rejectedTournament, TournamentParticipationRecord::REVIEW_REJECTED],
        [$unapprovedTournament, TournamentParticipationRecord::REVIEW_APPROVED],
    ] as [$tournament, $status]) {
        TournamentParticipationRecord::query()->create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'review_status' => $status,
        ]);
    }

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertDontSee('PLAYER');
});

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<int, array<string, mixed>>  $matches
 */
function createParticipationRecordWithCurrentMatches(array $attributes, array $matches): TournamentParticipationRecord
{
    $record = TournamentParticipationRecord::query()->create($attributes);
    $record->syncParticipationMatches($matches);

    return $record;
}

test('participation attack strings are stored as text flagged and escaped in profile and moderation views', function () {
    $owner = User::factory()->withSetup()->create();
    $reporter = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create(['username' => 'SecurityMate']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Security Text Cup']);
    $teamPayload = '<script>alert(1)</script><img src=x onerror=alert(2)>';
    $memoPayload = '<iframe srcdoc="<script>alert(3)</script>"></iframe>';
    $explanationPayload = '<object data="javascript:alert(4)"></object>';
    $deletionPayload = '<embed src="data:text/html,<script>alert(5)</script>">';

    $this->actingAs($owner)
        ->post(route('users.participation.store', $owner), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
            'team_name' => $teamPayload,
            'memo' => $memoPayload,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $owner->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $this->actingAs($reporter)
        ->postJson(route('users.participation.report', [$owner, $record]), [
            'category' => ParticipationRecordReport::CATEGORY_INAPPROPRIATE_MEMO,
            'explanation' => $explanationPayload,
        ])
        ->assertOk();

    $this->actingAs($owner)
        ->postJson(route('users.participation.deletion-request', [$owner, $record]), [
            'reason' => $deletionPayload,
        ])
        ->assertOk();

    $record->refresh();
    $report = ParticipationRecordReport::query()->firstOrFail();
    $deletionRequest = ParticipationDeletionRequest::query()->firstOrFail();
    $log = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'saved')
        ->firstOrFail();

    expect($record->team_name)->toBe($teamPayload)
        ->and($record->memo)->toBe($memoPayload)
        ->and($report->explanation)->toBe($explanationPayload)
        ->and($deletionRequest->reason)->toBe($deletionPayload)
        ->and($log->flagged)->toBeTrue()
        ->and($log->flag_reason)->toBe('flag_pattern:<script');

    $profileHtml = $this->actingAs($owner)
        ->get(route('users.show', ['user' => $owner, 'tab' => 'participation']))
        ->assertOk()
        ->getContent();

    expect($profileHtml)
        ->toContain(e($teamPayload))
        ->toContain(e($memoPayload))
        ->not()->toContain($teamPayload)
        ->not()->toContain($memoPayload);

    $moderationHtml = $this->actingAs($admin)
        ->get(route('admin.participation-moderation.index'))
        ->assertOk()
        ->getContent();

    expect($moderationHtml)
        ->toContain(e($teamPayload))
        ->toContain(e($memoPayload))
        ->toContain(e($explanationPayload))
        ->toContain(e($deletionPayload))
        ->not()->toContain($teamPayload)
        ->not()->toContain($memoPayload)
        ->not()->toContain($explanationPayload)
        ->not()->toContain($deletionPayload);
});

test('owner can create visible participation record with manual match summary and unknown teammate sync', function () {
    $user = User::factory()->withSetup()->create();
    $resolvedTeammate = User::factory()->withSetup()->create(['osu_id' => 987654]);
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 64, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'bracket:0:32:losers:ro32_lb:49:64',
            'team_name' => '<script>alert(1)</script>',
            'memo' => 'good run',
            'pending_teammate_osu_ids' => [987654],
            'matches' => [
                ['stage' => 'Ro64', 'score_for' => 5, 'score_against' => 2, 'mp_link' => '123456', 'is_forfeit' => false],
            ],
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($record->team_name)->toBe('<script>alert(1)</script>')
        ->and($record->memo)->toBe('good run')
        ->and($record->placement_min)->toBe(49)
        ->and($record->placement_max)->toBe(64)
        ->and($record->matches)->toHaveCount(1)
        ->and($record->matches[0]['result'])->toBe('5-2')
        ->and($record->matches[0]['mp_link'])->toBe('https://osu.ppy.sh/community/matches/123456')
        ->and($record->matches[0]['mp_id'])->toBe(123456)
        ->and(ParticipationRecordMatch::query()->where('tournament_participation_record_id', $record->id)->count())->toBe(1)
        ->and($record->teammates()->where('osu_id', 987654)->exists())->toBeTrue()
        ->and(ParticipationInputLog::query()->where('user_id', $user->id)->exists())->toBeTrue();

    expect(TournamentParticipationRecord::query()
        ->where('user_id', $resolvedTeammate->id)
        ->where('tournament_id', $tournament->id)
        ->exists())->toBeTrue();
});

test('non owner cannot edit another users participation records', function () {
    $owner = User::factory()->withSetup()->create();
    $other = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
    ]);

    $this->actingAs($other)
        ->patch(route('users.participation.update', [$owner, $record]), ['seed' => 1])
        ->assertForbidden();
});

test('non owner cannot search mutate delete or hide another users participation records', function () {
    $owner = User::factory()->withSetup()->create();
    $other = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => Tournament::factory()->approved()->create(['title' => 'Private Edit Cup'])->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    $this->actingAs($other)
        ->getJson(route('users.participation.tournaments.search', [$owner, 'q' => 'Private']))
        ->assertForbidden();

    $this->actingAs($other)
        ->getJson(route('users.participation.users.search', [$owner, 'q' => 'Priv']))
        ->assertForbidden();

    $this->actingAs($other)
        ->patchJson(route('users.participation.visibility', [$owner, $record]))
        ->assertForbidden();

    $this->actingAs($other)
        ->postJson(route('users.participation.deletion-request', [$owner, $record]))
        ->assertForbidden();

    $this->actingAs($other)
        ->deleteJson(route('users.participation.destroy', [$owner, $record]))
        ->assertForbidden();

    expect(TournamentParticipationRecord::query()->whereKey($record->id)->exists())->toBeTrue()
        ->and($record->fresh()->profile_hidden_at)->toBeNull();
});

test('adding an existing tournament participation is rejected without overwriting it', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
            'memo' => 'first save',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
            'memo' => 'second save',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', __('users.participation.flash.duplicate_record'));

    $this->actingAs($user)
        ->postJson(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
            'memo' => 'third save',
        ])
        ->assertStatus(409)
        ->assertJsonPath('type', 'error')
        ->assertJsonPath('message', __('users.participation.flash.duplicate_record'));

    expect(TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->count())->toBe(1);

    expect(TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail()
        ->memo)->toBe('first save');
});

test('participation match links reject non osu and javascript urls', function (string $mpLink) {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
            'matches' => [
                ['stage' => 'F', 'score_for' => 5, 'score_against' => 3, 'mp_link' => $mpLink],
            ],
        ])
        ->assertSessionHasErrors('matches.0.mp_link');

    expect(TournamentParticipationRecord::query()->where('user_id', $user->id)->exists())->toBeFalse();
})->with([
    'javascript url' => ['javascript:alert(1)'],
    'offsite url' => ['https://example.com/community/matches/123456'],
    'osu html payload' => ['https://osu.ppy.sh/community/matches/123456"><script>alert(1)</script>'],
]);

test('participation match scores reject values above nine', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->postJson(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
            'matches' => [
                ['stage' => 'F', 'score_for' => 10, 'score_against' => 9],
                ['stage' => 'SF', 'score_for' => 9, 'score_against' => 10],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'matches.0.score_for',
            'matches.1.score_against',
        ]);

    expect(TournamentParticipationRecord::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('participation match scores accept minus one zero and nine', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
            'matches' => [
                ['stage' => 'SF', 'score_for' => -1, 'score_against' => 0],
                ['stage' => 'F', 'score_for' => 9, 'score_against' => -1],
            ],
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($record->matches)->toHaveCount(2)
        ->and($record->matches[0]['score_for'])->toBe(-1)
        ->and($record->matches[0]['score_against'])->toBe(0)
        ->and($record->matches[0]['result'])->toBe('-1-0')
        ->and($record->matches[1]['score_for'])->toBe(9)
        ->and($record->matches[1]['score_against'])->toBe(-1)
        ->and($record->matches[1]['result'])->toBe('9--1');
});

test('stage options include dnq dnp and double elimination bracket paths', function () {
    $tournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'bracket', 'start_round_size' => 64, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    $values = app(ParticipationStageOptionsService::class)->optionsFor($tournament)->pluck('value');

    expect($values)
        ->toContain('dnq')
        ->toContain('dnp')
        ->toContain('bracket:1:32:losers:ro32_lb:49:64')
        ->toContain('bracket:1:16:losers:ro16_lb1:33:48')
        ->toContain('bracket:1:8:losers:qf_lb2:13:16')
        ->toContain('bracket:1:1:losers:gf_lb:3:3')
        ->toContain('bracket:1:1:grand_finals:gf');

    expect($values->contains('bracket:1:64:winners'))->toBeFalse();
    expect($values->contains('bracket:1:2:winners:f:2:2'))->toBeFalse();
    expect($values->contains('bracket:1:2:losers:f_lb:3:3'))->toBeFalse();

    expect($values->filter(fn (string $value): bool => $value === 'dnq' || str_starts_with($value, 'stage:0')))->toHaveCount(1);
    expect(app(ParticipationStageOptionsService::class)->optionsFor($tournament)->firstWhere('value', 'dnq')['editable_placement'])->toBeFalse();
});

test('qualifier stage ignores placement overrides', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'bracket', 'start_round_size' => 64, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'dnq',
            'placement_override' => 12,
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($record->placement)->toBeNull()
        ->and($record->placement_min)->toBeNull()
        ->and($record->placement_max)->toBeNull()
        ->and($record->placement_override)->toBeNull();
});

test('double elimination grand finals placement is constrained', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'bracket:0:1:grand_finals:gf',
            'placement_override' => 4,
        ])
        ->assertSessionHasErrors('placement_override');

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'bracket:0:1:grand_finals:gf',
            'placement_override' => 2,
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($record->placement)->toBe(2)
        ->and($record->round_label)->toBe('GF')
        ->and(data_get($record->metadata, 'stage_value'))->toBe('bracket:0:1:grand_finals:gf');
});

test('double elimination grand finals lower bracket stores third place', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'bracket:0:1:losers:gf_lb:3:3',
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($record->placement)->toBe(3)
        ->and($record->round_label)->toBe('GF LB')
        ->and($record->bracket_path)->toBe(TournamentParticipationRecord::BRACKET_LOSERS);
});

test('owner can search osu lobby finder lobbies with normalized response', function () {
    config(['app.timezone' => 'Asia/Seoul']);

    $user = User::factory()->withSetup()->create();

    Http::fake([
        'osulobbyfinder.dri3x.cz/api/lobbies*' => Http::response([
            'lobbies' => [
                [
                    'lobby_id' => 118997026,
                    'lobby_name' => '5DKC2025: (Civil oath) vs (DalChae)',
                    'created_at' => '2025-08-17 12:54:27.000000 +00:00',
                    'is_tournament' => 1,
                ],
                [
                    'lobby_id' => 118997027,
                    'lobby_name' => 'No Timezone Cup: Red vs Blue',
                    'created_at' => '2026-06-06 14:53:44',
                    'is_tournament' => 1,
                ],
            ],
            'total' => 284,
            'limit' => 10,
            'offset' => 20,
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('users.participation.lobbies.search', [
            $user,
            'q' => '5DKC',
            'offset' => 20,
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('total', 284)
        ->assertJsonPath('limit', 10)
        ->assertJsonPath('offset', 20)
        ->assertJsonPath('lobbies.0.lobby_id', 118997026)
        ->assertJsonPath('lobbies.0.mp_link', 'https://osu.ppy.sh/community/matches/118997026')
        ->assertJsonPath('lobbies.0.created_at_display', 'Aug 17')
        ->assertJsonPath('lobbies.1.created_at_display', 'Jun 06');

    expect($response->json())->not()->toHaveKey('all_lobbies');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://osulobbyfinder.dri3x.cz/api/lobbies?limit=10&offset=20&name=5DKC&is_tournament=true';
    });
    Http::assertSentCount(1);
});

test('osu lobby finder scans past first upstream page before applying tournament date filter', function () {
    config(['app.timezone' => 'Asia/Seoul']);

    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'tournament_start' => '2025-08-10 00:00:00',
        'tournament_end' => '2025-09-01 00:00:00',
    ]);

    Http::fake([
        'osulobbyfinder.dri3x.cz/api/lobbies*' => Http::sequence()
            ->push([
                'lobbies' => collect(range(1, 200))->map(fn (int $id): array => [
                    'lobby_id' => 1000 + $id,
                    'lobby_name' => "Too New {$id}",
                    'created_at' => '2025-10-05 00:00:00.000000 +00:00',
                ])->all(),
                'total' => 203,
                'limit' => 200,
                'offset' => 0,
            ], 200)
            ->push([
                'lobbies' => [
                    ['lobby_id' => 222, 'lobby_name' => 'Main Cup', 'created_at' => '2025-08-15 12:00:00.000000 +00:00'],
                    ['lobby_id' => 333, 'lobby_name' => 'Buffer Cup', 'created_at' => '2025-09-20 12:00:00.000000 +00:00'],
                    ['lobby_id' => 444, 'lobby_name' => 'Before Start Window', 'created_at' => '2025-07-01 00:00:00.000000 +00:00'],
                ],
                'total' => 203,
                'limit' => 200,
                'offset' => 200,
            ], 200),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('users.participation.lobbies.search', [
            $user,
            'q' => 'Cup',
            'tournament_id' => $tournament->id,
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('lobbies.0.lobby_id', 222)
        ->assertJsonPath('lobbies.1.lobby_id', 333)
        ->assertJsonCount(2, 'all_lobbies');

    expect(collect($response->json('lobbies'))->pluck('lobby_id')->all())->toBe([222, 333])
        ->and(collect($response->json('all_lobbies'))->pluck('lobby_id')->all())->toBe([222, 333]);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'limit=200&offset=0'));
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'limit=200&offset=200'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'offset=400'));
});

test('osu lobby finder paginates after filtering scanned upstream results', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'tournament_start' => '2025-08-10 00:00:00',
        'tournament_end' => '2025-09-01 00:00:00',
    ]);

    Http::fake([
        'osulobbyfinder.dri3x.cz/api/lobbies*' => Http::sequence()
            ->push([
                'lobbies' => collect(range(1, 10))->map(fn (int $id): array => [
                    'lobby_id' => 2000 + $id,
                    'lobby_name' => "Valid {$id}",
                    'created_at' => '2025-08-20 00:00:00.000000 +00:00',
                ])->merge([
                    ['lobby_id' => 2011, 'lobby_name' => 'Valid 11', 'created_at' => '2025-08-19 00:00:00.000000 +00:00'],
                    ['lobby_id' => 2012, 'lobby_name' => 'Valid 12', 'created_at' => '2025-08-18 00:00:00.000000 +00:00'],
                    ['lobby_id' => 2013, 'lobby_name' => 'Too Old', 'created_at' => '2025-07-01 00:00:00.000000 +00:00'],
                ])->all(),
                'total' => 13,
                'limit' => 200,
                'offset' => 0,
            ], 200),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('users.participation.lobbies.search', [
            $user,
            'q' => 'Valid',
            'offset' => 10,
            'tournament_id' => $tournament->id,
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('total', 12)
        ->assertJsonPath('offset', 10)
        ->assertJsonCount(12, 'all_lobbies');

    expect(collect($response->json('lobbies'))->pluck('lobby_id')->all())->toBe([2011, 2012])
        ->and(collect($response->json('all_lobbies'))->pluck('lobby_id')->all())->toBe(range(2001, 2012));

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://osulobbyfinder.dri3x.cz/api/lobbies?limit=200&offset=0&name=Valid&is_tournament=true';
    });
});

test('osu lobby finder uses tournament start minus one month and registration start fallback', function () {
    $user = User::factory()->withSetup()->create();
    $tournamentStartPreferred = Tournament::factory()->approved()->create([
        'registration_start' => '2025-01-01 00:00:00',
        'tournament_start' => '2025-08-10 00:00:00',
        'tournament_end' => null,
    ]);
    $registrationFallback = Tournament::factory()->approved()->create([
        'registration_start' => '2025-07-01 00:00:00',
        'tournament_start' => null,
        'tournament_end' => null,
    ]);

    Http::fake([
        'osulobbyfinder.dri3x.cz/api/lobbies*' => Http::sequence()
            ->push([
                'lobbies' => [
                    ['lobby_id' => 111, 'lobby_name' => 'Registration Old', 'created_at' => '2025-06-01 00:00:00.000000 +00:00'],
                    ['lobby_id' => 222, 'lobby_name' => 'Month Window', 'created_at' => '2025-07-15 00:00:00.000000 +00:00'],
                ],
                'total' => 2,
                'limit' => 200,
                'offset' => 0,
            ], 200)
            ->push([
                'lobbies' => [
                    ['lobby_id' => 333, 'lobby_name' => 'Before Registration', 'created_at' => '2025-06-01 00:00:00.000000 +00:00'],
                    ['lobby_id' => 444, 'lobby_name' => 'After Registration', 'created_at' => '2025-07-01 00:00:00.000000 +00:00'],
                ],
                'total' => 2,
                'limit' => 200,
                'offset' => 0,
            ], 200),
    ]);

    $tournamentStartResponse = $this->actingAs($user)
        ->getJson(route('users.participation.lobbies.search', [
            $user,
            'q' => 'Start',
            'tournament_id' => $tournamentStartPreferred->id,
        ]));

    $fallbackResponse = $this->actingAs($user)
        ->getJson(route('users.participation.lobbies.search', [
            $user,
            'q' => 'Fallback',
            'tournament_id' => $registrationFallback->id,
        ]));

    expect(collect($tournamentStartResponse->json('lobbies'))->pluck('lobby_id')->all())->toBe([222])
        ->and(collect($fallbackResponse->json('lobbies'))->pluck('lobby_id')->all())->toBe([444]);
});

test('non owner cannot search osu lobby finder lobbies for another user', function () {
    $owner = User::factory()->withSetup()->create();
    $other = User::factory()->withSetup()->create();

    Http::fake();

    $this->actingAs($other)
        ->getJson(route('users.participation.lobbies.search', [$owner, 'q' => 'Cup']))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('osu lobby finder search handles upstream failure safely', function () {
    $user = User::factory()->withSetup()->create();

    Http::fake([
        'osulobbyfinder.dri3x.cz/api/lobbies*' => Http::response(['message' => 'down'], 503),
    ]);

    $this->actingAs($user)
        ->getJson(route('users.participation.lobbies.search', [$user, 'q' => 'Cup']))
        ->assertOk()
        ->assertJsonPath('lobbies', [])
        ->assertJsonPath('total', 0)
        ->assertJsonPath('error', __('users.participation.lobby_search.error'));
});

test('owner can search tournaments with participation metadata', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create(['username' => 'ExistingMate']);
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Searchable Participation Cup',
        'team_size_max' => 4,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'bracket', 'start_round_size' => 32, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 2,
        'placement_min' => 2,
        'placement_max' => 2,
        'team_name' => 'Existing Team',
        'memo' => 'existing memo',
        'metadata' => [
            'stage_value' => 'bracket:0:2:winners:f:2:2',
            'autofilled_from' => 'tournament_winners',
        ],
    ]);
    $record->syncParticipationMatches([[
        'stage' => 'F',
        'result' => '7-5',
        'mp_link' => 'https://osu.ppy.sh/community/matches/118997026',
    ]]);
    $record->teammates()->attach($teammate);

    $response = $this->actingAs($user)
        ->getJson(route('users.participation.tournaments.search', [$user, 'q' => 'Searchable']));

    $response
        ->assertOk()
        ->assertJsonPath('tournaments.0.id', $tournament->id)
        ->assertJsonPath('tournaments.0.has_qualifier', true)
        ->assertJsonPath('tournaments.0.qualifier_cutoff', 64)
        ->assertJsonPath('tournaments.0.is_team_tournament', true)
        ->assertJsonPath('tournaments.0.existing_record.id', $record->id)
        ->assertJsonPath('tournaments.0.existing_record.podium_locked', true)
        ->assertJsonPath('tournaments.0.existing_record.stage_value', 'bracket:0:2:winners:f:2:2')
        ->assertJsonPath('tournaments.0.existing_record.team_name', 'Existing Team')
        ->assertJsonPath('tournaments.0.existing_record.memo', 'existing memo')
        ->assertJsonPath('tournaments.0.existing_record.matches.0.mp_id', 118997026)
        ->assertJsonPath('tournaments.0.existing_record.matches.0.mp_link', 'https://osu.ppy.sh/community/matches/118997026')
        ->assertJsonPath('tournaments.0.existing_record.teammates.0.username', 'ExistingMate');

    expect($response->json('tournaments.0.match_stage_options'))->toContain('Ro32', 'Ro16', 'QF', 'SF', 'F', 'GF');
});

test('participation dialog displays canonical mp links as mp ids for editing and lobby selection', function () {
    $user = User::factory()->withSetup()->create();

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('displayMpId(mpId, mpLink')
        ->toContain('mp_link: this.displayMpId(match.mp_id, match.mp_link)')
        ->toContain('match.mp_link = this.displayMpId(lobby.lobby_id, lobby.mp_link)')
        ->toContain("params.set('tournament_id', String(this.selectedTournament.id))")
        ->toContain('lobbySearchAllResults: []')
        ->toContain('lobbySearchLoadedComplete: false')
        ->toContain('resetLobbySearchSession()')
        ->toContain('showLobbySearchPage(offset = 0)')
        ->toContain('this.lobbySearchAllResults.slice(')
        ->toContain('if (this.lobbySearchLoadedComplete) {')
        ->toContain('this.resetLobbySearchSession();');

    $closeLobbySearchMethod = Str::between($html, 'closeLobbySearch() {', 'resetLobbySearchSession() {');
    $selectLobbyMethod = Str::between($html, 'selectLobby(match, lobby) {', 'lobbySearchHasPrevious() {');

    expect($closeLobbySearchMethod)
        ->toContain('this.lobbySearchOpenKey = null')
        ->and($closeLobbySearchMethod)->not->toContain("this.lobbySearchQuery = ''")
        ->and($closeLobbySearchMethod)->not->toContain('this.lobbySearchResults = []')
        ->and($closeLobbySearchMethod)->not->toContain('this.clearLobbySearchCache()')
        ->and($closeLobbySearchMethod)->not->toContain('this.lobbySearchTotal = 0');

    expect($selectLobbyMethod)
        ->toContain('match.mp_link = this.displayMpId(lobby.lobby_id, lobby.mp_link)')
        ->toContain('this.closeLobbySearch();');
});

test('participation records endpoint filters by text year mode badged and focused tournament', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create(['username' => 'FilterMate']);
    $matchingTournament = Tournament::factory()->approved()->create([
        'title' => 'Fast Filter Cup',
        'modes' => ['mania'],
        'is_badge' => true,
        'badge_status' => 'approved',
        'tournament_end' => now()->setDate(2025, 4, 10),
    ]);
    $otherTournament = Tournament::factory()->approved()->create([
        'title' => 'Other Filter Cup',
        'modes' => ['osu'],
        'is_badge' => false,
        'badge_status' => null,
        'tournament_end' => now()->setDate(2024, 4, 10),
    ]);
    $matchingRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $matchingTournament->id,
        'team_name' => 'Needle Team',
    ]);
    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $otherTournament->id,
        'team_name' => 'Other Team',
    ]);
    $matchingRecord->teammates()->attach($teammate);

    $response = $this->actingAs($user)
        ->getJson(route('users.participation.records', [
            'user' => $user,
            'q' => 'FilterMate',
            'year' => '2025',
            'mode' => 'mania',
            'badged' => '1',
            'participation_tournament' => $matchingTournament->id,
        ]))
        ->assertOk();

    expect($response->json('html'))
        ->toContain('Fast Filter Cup')
        ->toContain('Needle Team')
        ->not()->toContain('Other Filter Cup');
});

test('public profile renders only first participation page while filters summarize full history', function () {
    $user = User::factory()->withSetup()->create();
    $viewer = User::factory()->withSetup()->create();

    for ($index = 1; $index <= 12; $index++) {
        $mode = match ($index) {
            11 => 'mania',
            12 => 'taiko',
            default => 'osu',
        };
        $year = $index === 12 ? 2020 : 2025;
        $tournament = Tournament::factory()->approved()->create([
            'title' => sprintf('Profile Page Cup %02d', $index),
            'modes' => [$mode],
            'tournament_end' => now()->setDate($year, 6, 1)->subDays($index),
        ]);

        TournamentParticipationRecord::query()->create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        ]);
    }

    $html = $this->actingAs($viewer)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Profile Page Cup 01')
        ->toContain('Profile Page Cup 10')
        ->not()->toContain('Profile Page Cup 11')
        ->not()->toContain('Profile Page Cup 12')
        ->toContain('value="mania"')
        ->toContain('value="taiko"')
        ->toContain('<option value="2020">2020</option>');
});

test('participation records endpoint returns only requested page without full history html', function () {
    $user = User::factory()->withSetup()->create();

    for ($index = 1; $index <= 12; $index++) {
        $tournament = Tournament::factory()->approved()->create([
            'title' => sprintf('Load More Cup %02d', $index),
            'tournament_end' => now()->setDate(2025, 6, 1)->subDays($index),
        ]);

        TournamentParticipationRecord::query()->create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        ]);
    }

    $response = $this->actingAs($user)
        ->getJson(route('users.participation.records', [
            'user' => $user,
            'offset' => 10,
        ]))
        ->assertOk()
        ->assertJsonPath('next_offset', null)
        ->assertJsonPath('has_more', false);

    expect($response->json('html'))
        ->toContain('Load More Cup 11')
        ->toContain('Load More Cup 12')
        ->not()->toContain('Load More Cup 01')
        ->not()->toContain('Load More Cup 10');
});

test('profile read does not backfill podium participation records', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Read Only Podium Cup']);

    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'placement' => 1,
        'gamemode' => 'osu',
    ]);

    $this->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk();

    expect(TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->exists())->toBeFalse();
});

test('participation json refresh preserves active filters and loaded depth', function () {
    $user = User::factory()->withSetup()->create();
    $targetTournament = Tournament::factory()->approved()->create([
        'title' => 'Filtered 2024 Cup',
        'tournament_end' => now()->setDate(2024, 5, 1),
    ]);
    $otherTournament = Tournament::factory()->approved()->create([
        'title' => 'Visible 2026 Cup',
        'tournament_end' => now()->setDate(2026, 5, 1),
    ]);
    $targetRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $targetTournament->id,
    ]);
    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $otherTournament->id,
    ]);

    $response = $this->actingAs($user)
        ->patchJson(route('users.participation.update', [
            'user' => $user,
            'record' => $targetRecord,
            'year' => 2024,
            'refresh_limit' => 20,
        ]), [
            'memo' => 'still filtered',
        ])
        ->assertOk()
        ->assertJsonPath('next_offset', null);

    expect($response->json('html'))
        ->toContain('Filtered 2024 Cup')
        ->toContain('still filtered')
        ->not()->toContain('Visible 2026 Cup');
});

test('mode filters render in fixed osu order with participated modes only', function () {
    $user = User::factory()->withSetup()->create();

    foreach (['mania', 'taiko', 'osu', 'catch'] as $mode) {
        $tournament = Tournament::factory()->approved()->create(['modes' => [$mode]]);
        TournamentParticipationRecord::query()->create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
        ]);
    }

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'value="osu"'))->toBeLessThan(strpos($html, 'value="taiko"'))
        ->and(strpos($html, 'value="taiko"'))->toBeLessThan(strpos($html, 'value="catch"'))
        ->and(strpos($html, 'value="catch"'))->toBeLessThan(strpos($html, 'value="mania"'));
});

test('participation json save returns lightweight refresh fragment', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Light Refresh Cup']);

    $response = $this->actingAs($user)
        ->postJson(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    expect($response->json('html'))
        ->toContain('data-participation-refresh')
        ->toContain('Light Refresh Cup')
        ->not()->toContain('window.participationDialog');
});

test('participation teammate search finds previous usernames', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create([
        'username' => 'CurrentMateName',
        'previous_usernames' => ['FormerMateName'],
    ]);

    $this->actingAs($user)
        ->getJson(route('users.participation.users.search', [$user, 'q' => 'FormerMateName']))
        ->assertOk()
        ->assertJsonPath('users.0.id', $teammate->id)
        ->assertJsonPath('users.0.username', 'CurrentMateName');
});

test('participation dialog exposes stable manual osu id fallback and deferred edit stage hydration', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);
    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'metadata' => ['stage_value' => 'bracket:0:4:single'],
    ]);

    $html = $this->actingAs($user)
        ->get(route('users.show', $user))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('manualTeammateInputOpen')
        ->toContain('if (tournament.existing_record)')
        ->toContain('this.openEdit(tournament.existing_record, tournament)')
        ->toContain('openPendingTeammateInput')
        ->toContain('showPendingTeammateInput()')
        ->toContain('add manually by osu! ID')
        ->toContain('px-8 py-8 text-center')
        ->toContain('text-osu-pink hover:opacity-80')
        ->toContain('modeLabel(teammate.main_mode)')
        ->not()->toContain('`osu ${teammate.osu_id}`')
        ->not()->toContain('teammate.bws_rank ?')
        ->toContain('this.$nextTick')
        ->toContain(':placeholder="placementPlaceholder"')
        ->toContain('bracket:0:4:single');
});

test('single elimination final accepts manual placement from one to four only', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);
    $finalValue = app(ParticipationStageOptionsService::class)
        ->optionsFor($tournament)
        ->firstWhere('label', 'F')['value'];

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $finalValue,
            'placement_override' => 5,
        ])
        ->assertSessionHasErrors('placement_override');

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $finalValue,
            'placement_override' => 4,
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($finalValue)->toBe('bracket:0:2:single')
        ->and($record->placement)->toBe(4)
        ->and($record->placement_min)->toBe(4)
        ->and($record->placement_max)->toBe(4)
        ->and($record->placement_override)->toBe(4);
});

test('participation edit dialog can hydrate saved final placement without override', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'placement' => 3,
        'placement_min' => 3,
        'placement_max' => 3,
        'metadata' => ['stage_value' => 'bracket:0:1:losers:gf_lb:3:3'],
    ]);

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('placementFromRecord(record)')
        ->toContain('placement_min')
        ->toContain('placement_max')
        ->toContain('bracket:0:1:losers:gf_lb:3:3');
});

test('manual teammate osu id uses existing user when available', function () {
    Queue::fake();

    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create(['osu_id' => 7654321]);
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'pending_teammate_osu_ids' => [$teammate->osu_id],
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($record->teammates()->pluck('users.id')->all())
        ->toBe([$teammate->id])
        ->and($record->pending_teammate_osu_ids)->toBe([]);

    Queue::assertNotPushed(SyncUserFromOsu::class);
});

test('manual teammate osu id failures are removed and notify requester', function () {
    $user = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    $this->mock(TournamentParticipantSyncService::class, function ($mock) {
        $mock->shouldReceive('resolvePodiumUserByOsuId')
            ->once()
            ->withAnyArgs()
            ->andThrow(new RuntimeException('osu! user not found'));
    });

    $this->actingAs($user)
        ->postJson(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'pending_teammate_osu_ids' => [999999],
        ])
        ->assertOk();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($record->pending_teammate_osu_ids)->toBe([])
        ->and($record->teammates()->exists())->toBeFalse()
        ->and(User::query()->where('username', 'osu_999999')->exists())->toBeFalse();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $user->id,
        'type' => UserNotification::TYPE_TEAMMATE_OSU_ID_FAILED,
        'tournament_id' => $tournament->id,
    ]);
});

test('teammate with existing tournament participation cannot be added to another team', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create(['username' => 'AlreadyRostered']);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('teammate_ids');

    expect(TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->exists())->toBeFalse();
});

test('group stage calculates eliminated placement range from group size and advancing teams', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'group_stage', 'group_count' => 8, 'teams_per_group' => 4, 'advance_count' => 16],
            ],
        ],
    ]);
    $stageValue = app(ParticipationStageOptionsService::class)
        ->optionsFor($tournament)
        ->firstWhere('stage_type', Tournament::STAGE_GROUP)['value'];

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $stageValue,
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($stageValue)->toBe('stage:0:17:32')
        ->and($record->placement)->toBe(17)
        ->and($record->placement_min)->toBe(17)
        ->and($record->placement_max)->toBe(32);
});

test('participation edit payload includes full non editable placement range', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Range Edit Payload Cup',
        'format_structure' => [
            'stages' => [
                ['type' => 'group_stage', 'group_count' => 8, 'teams_per_group' => 4, 'advance_count' => 16],
            ],
        ],
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'placement' => 17,
        'placement_min' => 17,
        'placement_max' => 32,
        'metadata' => ['stage_value' => 'stage:0:17:32'],
    ]);

    $this->actingAs($user)
        ->getJson(route('users.participation.tournaments.search', [$user, 'q' => 'Range Edit Payload']))
        ->assertOk()
        ->assertJsonPath('tournaments.0.existing_record.stage_value', 'stage:0:17:32')
        ->assertJsonPath('tournaments.0.existing_record.placement', 17)
        ->assertJsonPath('tournaments.0.existing_record.placement_min', 17)
        ->assertJsonPath('tournaments.0.existing_record.placement_max', 32)
        ->assertJsonPath('tournaments.0.existing_record.placement_range_editable', false);
});

test('group stage inherits field size from previous qualifier when group size is missing', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'group_stage', 'advance_count' => 32],
                ['type' => 'bracket', 'start_round_size' => 32, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);
    $stageOption = app(ParticipationStageOptionsService::class)
        ->optionsFor($tournament)
        ->firstWhere('stage_type', Tournament::STAGE_GROUP);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $stageOption['value'],
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($stageOption)
        ->toMatchArray(['value' => 'stage:1:33:64', 'placement_min' => 33, 'placement_max' => 64, 'editable_placement' => false])
        ->and($record->placement)->toBe(33)
        ->and($record->placement_min)->toBe(33)
        ->and($record->placement_max)->toBe(64);
});

test('swiss stage inherits field size from qualifier and saves fixed eliminated range', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 16],
                ['type' => 'swiss_round', 'round_count' => 4, 'advance_count' => 8],
                ['type' => 'bracket', 'start_round_size' => 8, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);
    $stageOption = app(ParticipationStageOptionsService::class)
        ->optionsFor($tournament)
        ->firstWhere('stage_type', Tournament::STAGE_SWISS);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $stageOption['value'],
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($stageOption)
        ->toMatchArray([
            'value' => 'swiss:1:9:16',
            'placement_min' => 9,
            'placement_max' => 16,
            'range_placement' => false,
            'editable_placement' => false,
        ])
        ->and($record->stage_type)->toBe(Tournament::STAGE_SWISS)
        ->and($record->placement)->toBe(9)
        ->and($record->placement_min)->toBe(9)
        ->and($record->placement_max)->toBe(16)
        ->and($record->placement_override)->toBeNull()
        ->and(data_get($record->metadata, 'stage_value'))->toBe('swiss:1:9:16');
});

test('legacy swiss record edit payload maps to current inferred range option', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Legacy Swiss Payload Cup',
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 16],
                ['type' => 'swiss_round', 'round_count' => 4, 'advance_count' => 8],
                ['type' => 'bracket', 'start_round_size' => 8, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);
    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'placement' => 9,
        'placement_min' => 9,
        'placement_max' => 16,
        'metadata' => ['stage_value' => 'swiss:1'],
    ]);

    $this->actingAs($user)
        ->getJson(route('users.participation.tournaments.search', [$user, 'q' => 'Legacy Swiss Payload']))
        ->assertOk()
        ->assertJsonPath('tournaments.0.existing_record.stage_value', 'swiss:1:9:16')
        ->assertJsonPath('tournaments.0.existing_record.placement_range_editable', false);
});

test('swiss stage accepts a manual placement range', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'swiss_round', 'round_count' => 5, 'advance_count' => 16],
            ],
        ],
    ]);
    $stageValue = app(ParticipationStageOptionsService::class)
        ->optionsFor($tournament)
        ->firstWhere('stage_type', Tournament::STAGE_SWISS)['value'];

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $stageValue,
            'placement_range_override' => '17-32',
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($stageValue)->toBe('swiss:0')
        ->and($record->stage_type)->toBe(Tournament::STAGE_SWISS)
        ->and($record->placement)->toBe(17)
        ->and($record->placement_min)->toBe(17)
        ->and($record->placement_max)->toBe(32)
        ->and($record->placement_override)->toBeNull();
});

test('bracket stage inherits start size from previous stage advance count', function () {
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'group_stage', 'advance_count' => 32],
                ['type' => 'bracket', 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    $values = app(ParticipationStageOptionsService::class)
        ->optionsFor($tournament)
        ->pluck('value');

    expect($values)
        ->toContain('stage:1:33:64')
        ->toContain('bracket:2:16:losers:ro16_lb:25:32')
        ->toContain('bracket:2:1:grand_finals:gf');
});

test('swiss stage rejects malformed placement ranges', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'swiss_round', 'round_count' => 5, 'advance_count' => 16],
            ],
        ],
    ]);

    $stageValue = app(ParticipationStageOptionsService::class)
        ->optionsFor($tournament)
        ->firstWhere('stage_type', Tournament::STAGE_SWISS)['value'];

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $stageValue,
            'placement_range_override' => '32-17',
        ])
        ->assertSessionHasErrors('placement_range_override');
});

test('battle royale exposes lobby-elimination rounds and grand finals placement depends on final lobby size', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'battle_royale', 'lobby_count' => 8, 'players_per_lobby' => 8, 'advance_per_lobby' => 4],
            ],
        ],
    ]);
    $options = app(ParticipationStageOptionsService::class)->optionsFor($tournament);
    $quarterfinal = $options->firstWhere('label', 'QF');
    $semifinal = $options->firstWhere('label', 'SF');
    $final = $options->firstWhere('label', 'F');
    $grandFinalValue = $options->firstWhere('label', 'GF')['value'];

    expect($options->pluck('label')->all())->toBe(['QF', 'SF', 'F', 'GF']);
    expect($quarterfinal)
        ->toMatchArray(['value' => 'battle_royale:0:8:single:qf:33:64', 'placement_min' => 33, 'placement_max' => 64, 'editable_placement' => false]);
    expect($semifinal)
        ->toMatchArray(['value' => 'battle_royale:0:4:single:sf:17:32', 'placement_min' => 17, 'placement_max' => 32, 'editable_placement' => false]);
    expect($final)
        ->toMatchArray(['value' => 'battle_royale:0:2:single:f:9:16', 'placement_min' => 9, 'placement_max' => 16, 'editable_placement' => false]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $grandFinalValue,
            'placement_override' => 9,
        ])
        ->assertSessionHasErrors('placement_override');

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => $grandFinalValue,
            'placement_override' => 8,
        ])
        ->assertRedirect(route('users.show', ['user' => $user, 'tab' => 'participation']));

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($grandFinalValue)->toBe('battle_royale:0:1:single:gf:1:8')
        ->and($record->stage_type)->toBe(Tournament::STAGE_BATTLE_ROYALE)
        ->and($record->round_label)->toBe('GF')
        ->and($record->placement)->toBe(8)
        ->and($record->placement_override)->toBe(8);
});

test('one lobby battle royale exposes only editable grand finals placement', function () {
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'battle_royale', 'lobby_count' => 1, 'players_per_lobby' => 16, 'advance_per_lobby' => 0],
            ],
        ],
    ]);

    $options = app(ParticipationStageOptionsService::class)->optionsFor($tournament);

    expect($options->pluck('label')->all())->toBe(['GF']);
    expect($options->first())
        ->toMatchArray([
            'value' => 'battle_royale:0:1:single:gf:1:16',
            'placement_min' => 1,
            'placement_max' => 16,
            'editable_placement' => true,
        ]);
});

test('profile displays saved matches while match edit inputs are enabled', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);

    createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ], [
        ['stage' => 'Round 9', 'result' => '5-4', 'score_for' => 5, 'score_against' => 4],
    ]);

    expect(app(ParticipationStageOptionsService::class)->matchStageLabelsFor($tournament))
        ->toBe([
            'Battle Royale',
            'Group Stage',
            'Swiss Round',
            'Qualifier',
            'Tryout',
            'Ro128',
            'Ro64',
            'Ro32',
            'Ro16',
            'QF',
            'SF',
            'F',
            'GF',
        ]);

    $html = $this->actingAs($user)
        ->get(route('users.show', $user))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Round 9')
        ->toContain('matches[${index}][stage]');
});

test('placement service calculates bracket ranges and allows non bracket override', function () {
    $tournament = Tournament::factory()->approved()->create();
    $service = app(ParticipationPlacementService::class);

    expect($service->calculate($tournament, 'bracket:0:8:losers:qf_lb:13:16'))
        ->toMatchArray(['placement_min' => 13, 'placement_max' => 16, 'editable' => false]);

    expect($service->calculate($tournament, 'stage:0', 12))
        ->toMatchArray(['placement' => 12, 'placement_min' => 12, 'placement_max' => 12, 'editable' => true]);
});

test('profile shows owner participation first and visitor participation last', function () {
    $owner = User::factory()->withSetup()->create();
    $visitor = User::factory()->withSetup()->create();

    $ownerHtml = $this->actingAs($owner)->get(route('users.show', $owner))->getContent();
    $visitorHtml = $this->actingAs($visitor)->get(route('users.show', $owner))->getContent();

    expect($ownerHtml)->toContain("profileTabs('participation')")
        ->and($visitorHtml)->toContain("profileTabs('history')");
});

test('podiums autofill missing participation records', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'registration_start' => now()->subMonths(4),
        'registration_end' => now()->subMonths(3),
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ]);

    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'placement' => 2,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'gamemode' => 'osu',
    ]);

    app(ParticipationPodiumBackfillService::class)->backfillFor($user);

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->source)->toBe(TournamentParticipationRecord::SOURCE_SYSTEM)
        ->and($record->placement)->toBe(2)
        ->and($record->round_label)->toBe('GF')
        ->and(data_get($record->metadata, 'stage_value'))->toBe('bracket:0:1:grand_finals:gf')
        ->and(data_get($record->metadata, 'hide_finished_bracket'))->toBeTrue();
});

test('third place podium autofill stores grand finals lower bracket', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'registration_start' => now()->subMonths(4),
        'registration_end' => now()->subMonths(3),
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'placement' => 3,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'gamemode' => 'osu',
    ]);

    app(ParticipationPodiumBackfillService::class)->backfillFor($user);

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->placement)->toBe(3)
        ->and($record->round_label)->toBe('GF LB')
        ->and($record->bracket_path)->toBe(TournamentParticipationRecord::BRACKET_LOSERS)
        ->and(data_get($record->metadata, 'stage_value'))->toBe('bracket:0:1:losers:gf_lb:3:3')
        ->and(data_get($record->metadata, 'hide_finished_bracket'))->toBeTrue();
});

test('admin podium update syncs participation record immediately', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);
    $winner = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'placement' => 1,
        'username' => 'placeholder',
        'osu_id' => 1234,
        'gamemode' => 'osu',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.tournaments.podium.update', [$tournament, $winner]), [
            'user_id' => $user->id,
            'username' => $user->username,
            'osu_id' => $user->osu_id,
        ])
        ->assertOk();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->placement)->toBe(1)
        ->and($record->round_label)->toBe('GF')
        ->and(data_get($record->metadata, 'autofilled_from'))->toBe('tournament_winners');
});

test('podium autofill links same placement teammates and keeps structured fields locked', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $newTeammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();
    $groupId = (string) Str::uuid();

    foreach ([$user, $teammate] as $winnerUser) {
        TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winnerUser->id,
            'placement' => 1,
            'username' => $winnerUser->username,
            'osu_id' => $winnerUser->osu_id,
            'gamemode' => 'osu',
            'metadata' => ['podium_group_id' => $groupId],
        ]);
    }

    app(ParticipationPodiumBackfillService::class)->backfillFor($user);

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->teammates()->pluck('users.id')->all())->toBe([$teammate->id]);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'stage_value' => 'stage:0',
            'placement_override' => 99,
            'seed' => 33,
            'team_name' => 'Official team name',
            'memo' => 'private podium memo',
            'teammate_ids' => [$newTeammate->id],
            'matches' => [['stage' => 'F', 'score_for' => 7, 'score_against' => 5]],
        ])
        ->assertRedirect();

    $record->refresh();

    expect($record->placement)->toBe(1)
        ->and($record->placement_override)->toBeNull()
        ->and($record->seed)->toBe(33)
        ->and($record->team_name)->toBe('Official team name')
        ->and($record->memo)->toBe('private podium memo')
        ->and($record->matches[0]['result'])->toBe('7-5')
        ->and($record->teammates()->pluck('users.id')->all())->toBe([$teammate->id]);
});

test('normal user podium match edit is not overwritten by teammate blank source record', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    foreach ([$user, $teammate] as $winnerUser) {
        TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winnerUser->id,
            'placement' => 1,
            'username' => $winnerUser->username,
            'osu_id' => $winnerUser->osu_id,
            'gamemode' => 'osu',
        ]);
    }

    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);
    $teammateRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'team_name' => 'Existing podium team',
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);
    $record->teammates()->attach($teammate);
    $teammateRecord->teammates()->attach($user);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Existing podium team',
            'matches' => [
                ['stage' => 'F', 'score_for' => 6, 'score_against' => 4, 'mp_link' => '123456'],
            ],
        ])
        ->assertRedirect();

    $log = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($record->fresh()->matches)->toHaveCount(1)
        ->and($record->fresh()->matches[0]['result'])->toBe('6-4')
        ->and($teammateRecord->fresh()->matches)->toHaveCount(1)
        ->and($teammateRecord->fresh()->matches[0]['result'])->toBe('6-4')
        ->and(data_get($log->changed_fields, 'changes.matches.new.0.result'))->toBe('6-4');
});

test('legacy ungrouped first place podium winners remain teammates in participation', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    foreach ([$user, $teammate] as $winnerUser) {
        TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winnerUser->id,
            'placement' => 1,
            'username' => $winnerUser->username,
            'osu_id' => $winnerUser->osu_id,
            'gamemode' => 'osu',
        ]);
    }

    app(ParticipationPodiumBackfillService::class)->backfillFor($user);

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->teammates()->pluck('users.id')->all())->toBe([$teammate->id])
        ->and(data_get($record->metadata, 'podium_group_id'))->toBeNull();
});

test('normal user edit promotes legacy podium teammates to explicit group metadata', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    foreach ([$user, $teammate] as $winnerUser) {
        TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winnerUser->id,
            'placement' => 1,
            'username' => $winnerUser->username,
            'osu_id' => $winnerUser->osu_id,
            'gamemode' => 'osu',
        ]);
    }

    app(ParticipationPodiumBackfillService::class)->backfillFor($user);

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'seed' => 7,
            'team_name' => 'Legacy Team Updated',
            'matches' => [['stage' => 'F', 'score_for' => 6, 'score_against' => 4]],
        ])
        ->assertRedirect();

    $winnerGroups = TournamentWinner::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('user_id', [$user->id, $teammate->id])
        ->pluck('metadata')
        ->map(fn (?array $metadata): ?string => data_get($metadata, 'podium_group_id'))
        ->all();

    expect($winnerGroups[0])->toBeString()
        ->and($winnerGroups[1])->toBe($winnerGroups[0])
        ->and(TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('user_id', [$user->id, $teammate->id])
            ->where('metadata->podium_group_manual', true)
            ->count())->toBe(2)
        ->and($record->fresh()->team_name)->toBe('Legacy Team Updated')
        ->and($record->fresh()->matches[0]['result'])->toBe('6-4');
});

test('legacy singleton auto groups fall back to same placement teammates', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    foreach ([$user, $teammate] as $winnerUser) {
        TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winnerUser->id,
            'placement' => 1,
            'username' => $winnerUser->username,
            'osu_id' => $winnerUser->osu_id,
            'gamemode' => 'osu',
            'metadata' => ['podium_group_id' => (string) Str::uuid()],
        ]);
    }

    app(ParticipationPodiumBackfillService::class)->backfillFor($user);

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->teammates()->pluck('users.id')->all())->toBe([$teammate->id])
        ->and(data_get($record->metadata, 'podium_group_id'))->toBeNull();
});

test('podium autofill keeps same placement winners separate without a podium group', function () {
    $user = User::factory()->withSetup()->create();
    $otherThird = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_size_min' => 1,
        'team_size_max' => 1,
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 8, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);

    foreach ([$user, $otherThird] as $winnerUser) {
        TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winnerUser->id,
            'placement' => 3,
            'username' => $winnerUser->username,
            'osu_id' => $winnerUser->osu_id,
            'gamemode' => 'osu',
        ]);
    }

    app(ParticipationPodiumBackfillService::class)->backfillFor($user);

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->teammates()->pluck('users.id')->all())->toBe([])
        ->and(TournamentParticipationRecord::query()
            ->where('user_id', $otherThird->id)
            ->where('tournament_id', $tournament->id)
            ->exists())->toBeFalse();
});

test('admin podium grouping makes same placement winners teammates', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    $winners = collect([$user, $teammate])->map(fn (User $winnerUser) => TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $winnerUser->id,
        'placement' => 3,
        'username' => $winnerUser->username,
        'osu_id' => $winnerUser->osu_id,
        'gamemode' => 'osu',
    ]));

    $this->actingAs($admin)
        ->postJson(route('admin.tournaments.podium.groups.store', $tournament), [
            'winner_ids' => $winners->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->teammates()->pluck('users.id')->all())->toBe([$teammate->id]);
});

test('admin podium grouping copies selected source team data to merged members', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $sourceUser = User::factory()->withSetup()->create();
    $mergedUser = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 16],
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);
    $sourceWinner = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $sourceUser->id,
        'placement' => 1,
        'username' => $sourceUser->username,
        'osu_id' => $sourceUser->osu_id,
        'gamemode' => 'osu',
    ]);
    $mergedWinner = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $mergedUser->id,
        'placement' => 1,
        'username' => $mergedUser->username,
        'osu_id' => $mergedUser->osu_id,
        'gamemode' => 'osu',
    ]);

    createParticipationRecordWithCurrentMatches([
        'user_id' => $sourceUser->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'seed' => 4,
        'team_name' => 'Source Team',
        'metadata' => ['autofilled_from' => 'tournament_winners', 'winner_id' => $sourceWinner->id],
    ], [['stage' => 'GF', 'score_for' => 7, 'score_against' => 5, 'result' => '7-5']]);
    TournamentParticipationRecord::query()->create([
        'user_id' => $mergedUser->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'team_name' => 'Old Team',
        'metadata' => ['autofilled_from' => 'tournament_winners', 'winner_id' => $mergedWinner->id],
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.tournaments.podium.groups.store', $tournament), [
            'winner_ids' => [$sourceWinner->id, $mergedWinner->id],
            'source_winner_id' => $sourceWinner->id,
            'team_name' => 'Admin Team',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $mergedRecord = TournamentParticipationRecord::query()
        ->where('user_id', $mergedUser->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($mergedRecord->team_name)->toBe('Admin Team')
        ->and($mergedRecord->seed)->toBe(4)
        ->and($mergedRecord->matches[0]['result'])->toBe('7-5')
        ->and($mergedRecord->teammates()->pluck('users.id')->all())->toBe([$sourceUser->id]);
});

test('manual podium claims without winner row require admin approval', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'stage:0',
            'placement_override' => 2,
        ])
        ->assertRedirect();

    expect(TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail()->review_status)
        ->toBe(TournamentParticipationRecord::REVIEW_PENDING);
});

test('shared teammate records do not copy private memo and qualifier matches omit scores', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 16],
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'dnq',
            'seed' => 17,
            'team_name' => 'Qualifier Team',
            'memo' => 'only mine',
            'teammate_ids' => [$teammate->id],
            'matches' => [['stage' => 'QL', 'score_for' => 1, 'score_against' => 0, 'is_forfeit' => true, 'mp_link' => '123']],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();
    $shared = TournamentParticipationRecord::query()->where('user_id', $teammate->id)->firstOrFail();

    expect($record->matches[0]['stage'])->toBe('Qualifier')
        ->and($record->matches[0]['result'])->toBeNull()
        ->and($record->matches[0]['score_for'])->toBeNull()
        ->and($record->matches[0]['is_forfeit'])->toBeFalse()
        ->and($record->seed)->toBe(17)
        ->and($shared->team_name)->toBe('Qualifier Team')
        ->and($shared->seed)->toBe(17)
        ->and($shared->matches[0]['mp_link'])->toBe('https://osu.ppy.sh/community/matches/123')
        ->and($shared->memo)->toBeNull();
});

test('shared teammate records omit qualifier seed when cutoff is not set', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier'],
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'dnq',
            'seed' => 17,
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'Qualifier', 'score_for' => 2, 'score_against' => 5, 'is_forfeit' => true],
                ['stage' => 'Battle Royale', 'score_for' => 1, 'score_against' => 3, 'is_forfeit' => true],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();
    $shared = TournamentParticipationRecord::query()->where('user_id', $teammate->id)->firstOrFail();

    expect($record->seed)->toBe(17)
        ->and($record->matches[0]['result'])->toBeNull()
        ->and($record->matches[0]['score_for'])->toBeNull()
        ->and($record->matches[0]['is_forfeit'])->toBeFalse()
        ->and($record->matches[1]['result'])->toBeNull()
        ->and($record->matches[1]['score_against'])->toBeNull()
        ->and($record->matches[1]['is_forfeit'])->toBeFalse()
        ->and($shared->seed)->toBeNull();
});

test('shared teammate records receive team name changes from the source record', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Original Team',
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();
    $shared = TournamentParticipationRecord::query()
        ->where('user_id', $teammate->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($shared->team_name)->toBe('Original Team');

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Updated Team',
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    expect($shared->fresh()->team_name)->toBe('Updated Team');
});

test('shared teammate records receive match changes and mp links normalize', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Match Team',
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();
    $shared = TournamentParticipationRecord::query()
        ->where('user_id', $teammate->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Match Team',
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'SF', 'score_for' => 6, 'score_against' => 3, 'mp_link' => 'https://osu.ppy.sh/mp/456789'],
            ],
        ])
        ->assertRedirect();

    expect($record->fresh()->matches[0]['mp_link'])->toBe('https://osu.ppy.sh/community/matches/456789')
        ->and($record->fresh()->matches[0])->not->toHaveKey('match_id')
        ->and($shared->fresh()->matches[0]['result'])->toBe('6-3')
        ->and($shared->fresh()->matches[0]['mp_link'])->toBe('https://osu.ppy.sh/community/matches/456789')
        ->and($shared->fresh()->matches[0])->not->toHaveKey('match_id');
});

test('shared teammate records replace old matches when source matches are edited', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Replace Team',
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'R1', 'score_for' => 5, 'score_against' => 1, 'mp_link' => '111111'],
                ['stage' => 'R2', 'score_for' => 5, 'score_against' => 2, 'mp_link' => '222222'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();
    $shared = TournamentParticipationRecord::query()
        ->where('user_id', $teammate->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($shared->fresh()->matches)->toHaveCount(2);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Replace Team',
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 5, 'mp_link' => 'https://osu.ppy.sh/mp/333333'],
            ],
        ])
        ->assertRedirect();

    $ownerMatches = $record->fresh()->matches;
    $sharedMatches = $shared->fresh()->matches;

    expect($ownerMatches)->toHaveCount(1)
        ->and($sharedMatches)->toHaveCount(1)
        ->and($ownerMatches[0]['result'])->toBe('7-5')
        ->and($sharedMatches[0]['result'])->toBe('7-5')
        ->and($sharedMatches[0]['mp_id'])->toBe(333333)
        ->and($sharedMatches[0]['mp_link'])->toBe('https://osu.ppy.sh/community/matches/333333')
        ->and($sharedMatches[0])->not->toHaveKey('match_id')
        ->and(ParticipationRecordMatch::query()
            ->where('tournament_participation_record_id', $shared->id)
            ->whereIn('mp_id', [111111, 222222])
            ->exists())->toBeFalse();
});

test('editing a shared teammate record updates the crossed team match results', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Original Team',
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'Qualifier', 'mp_link' => '111111', 'is_individual_qualifier' => true],
                ['stage' => 'SF', 'score_for' => 5, 'score_against' => 2, 'mp_link' => '222222'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();
    $shared = TournamentParticipationRecord::query()
        ->where('user_id', $teammate->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $this->actingAs($teammate)
        ->patch(route('users.participation.update', [$teammate, $shared]), [
            'team_name' => 'Edited By Teammate',
            'teammates_submitted' => 1,
            'teammate_ids' => [$user->id],
            'matches' => [
                ['stage' => 'Qualifier', 'mp_link' => '333333', 'is_individual_qualifier' => true],
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 6, 'mp_link' => '444444'],
            ],
        ])
        ->assertRedirect();

    $rootMatches = $record->fresh()->matches;
    $sharedMatches = $shared->fresh()->matches;

    expect($record->fresh()->team_name)->toBe('Edited By Teammate')
        ->and(collect($rootMatches)->pluck('mp_id')->all())->toBe([111111, 444444])
        ->and(collect($sharedMatches)->pluck('mp_id')->all())->toBe([333333, 444444])
        ->and($rootMatches[0]['is_individual_qualifier'])->toBeTrue()
        ->and($sharedMatches[0]['is_individual_qualifier'])->toBeTrue()
        ->and(ParticipationRecordMatch::query()
            ->whereIn('tournament_participation_record_id', [$record->id, $shared->id])
            ->where('mp_id', 222222)
            ->exists())->toBeFalse();
});

test('match result ordering is persisted by submitted row order', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'matches' => [
                ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '111111'],
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 4, 'mp_link' => '222222'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'matches' => [
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 4, 'mp_link' => '222222'],
                ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '111111'],
            ],
        ])
        ->assertRedirect();

    expect(collect($record->fresh()->matches)->pluck('stage')->all())->toBe(['F', 'SF']);
});

test('owner can edit match scores remove rows and match payload omits note', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'matches' => [
                ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '111111', 'note' => 'ignored'],
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 4, 'mp_link' => '222222'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->fresh()->matches[0])->not->toHaveKey('note');

    $response = $this->actingAs($user)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'matches' => [
                ['stage' => 'F', 'score_for' => 6, 'score_against' => 5, 'mp_link' => '222222'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    $matches = $record->fresh()->matches;
    $log = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['stage'])->toBe('F')
        ->and($matches[0]['result'])->toBe('6-5')
        ->and($matches[0])->not->toHaveKey('note')
        ->and(ParticipationRecordMatch::query()
            ->where('tournament_participation_record_id', $record->id)
            ->where('mp_id', 111111)
            ->exists())->toBeFalse()
        ->and(data_get($log->changed_fields, 'changes.matches.old'))->toHaveCount(2)
        ->and(data_get($log->changed_fields, 'changes.matches.new'))->toHaveCount(1)
        ->and(data_get($log->changed_fields, 'changes.matches.new.0.result'))->toBe('6-5')
        ->and($response->json('html'))->toContain('F 6 - 5')
        ->and($response->json('html'))->not->toContain('SF 5 - 3');
});

test('owner match reorder writes a non empty audit diff', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $record = createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ], [
        ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '111111'],
        ['stage' => 'F', 'score_for' => 7, 'score_against' => 4, 'mp_link' => '222222'],
    ]);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'matches' => [
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 4, 'mp_link' => '222222'],
                ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '111111'],
            ],
        ])
        ->assertRedirect();

    $log = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect(collect($record->fresh()->matches)->pluck('stage')->all())->toBe(['F', 'SF'])
        ->and(collect(data_get($log->changed_fields, 'changes.matches.old'))->pluck('stage')->all())->toBe(['SF', 'F'])
        ->and(collect(data_get($log->changed_fields, 'changes.matches.new'))->pluck('stage')->all())->toBe(['F', 'SF']);
});

test('owner clearing match rows writes a non empty audit diff', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $record = createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ], [
        ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '111111'],
    ]);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'matches' => [],
        ])
        ->assertRedirect();

    $log = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($record->fresh()->matches)->toBe([])
        ->and(data_get($log->changed_fields, 'changes.matches.old'))->toHaveCount(1)
        ->and(data_get($log->changed_fields, 'changes.matches.new'))->toBe([]);
});

test('checked individual qualifier matches stay private with a cutoff while bracket matches share', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'team_size_max' => 2,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'Qualifier', 'mp_link' => '111111', 'is_individual_qualifier' => true],
                ['stage' => 'F', 'score_for' => 6, 'score_against' => 4, 'mp_link' => '222222'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();
    $shared = TournamentParticipationRecord::query()->where('user_id', $teammate->id)->firstOrFail();

    expect(collect($record->matches)->pluck('stage')->all())->toBe(['Qualifier', 'F'])
        ->and($record->matches[0]['is_individual_qualifier'])->toBeTrue()
        ->and(collect($shared->matches)->pluck('stage')->all())->toBe(['F'])
        ->and($shared->matches[0]['mp_id'])->toBe(222222);
});

test('unchecked qualifier matches share even when the tournament has no qualifier cutoff', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'team_size_max' => 2,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier'],
                ['type' => 'bracket', 'start_round_size' => 16],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'Qualifier', 'mp_link' => '111111'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();
    $shared = TournamentParticipationRecord::query()->where('user_id', $teammate->id)->firstOrFail();

    expect($record->matches[0]['is_individual_qualifier'])->toBeFalse()
        ->and($shared->matches[0]['mp_id'])->toBe(111111)
        ->and($shared->matches[0]['is_individual_qualifier'])->toBeFalse();
});

test('individual qualifier migration preserves the previous no cutoff sharing behavior', function () {
    $user = User::factory()->withSetup()->create();
    $legacyIndividualTournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'format_structure' => ['stages' => [['type' => 'qualifier']]],
    ]);
    $cutoffTournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'format_structure' => ['stages' => [['type' => 'qualifier', 'advance_count' => 64]]],
    ]);
    $standardTournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_STANDARD,
        'format_structure' => ['stages' => [['type' => 'qualifier']]],
    ]);

    $matches = collect([
        $legacyIndividualTournament,
        $cutoffTournament,
        $standardTournament,
    ])->map(function (Tournament $tournament) use ($user): ParticipationRecordMatch {
        $record = createParticipationRecordWithCurrentMatches([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
        ], [
            ['stage' => 'Qualifier', 'mp_link' => (string) (100000 + $tournament->id)],
        ]);

        return $record->participationMatches()->firstOrFail();
    });

    $migration = include database_path('migrations/2026_06_18_000001_add_individual_qualifier_to_participation_record_matches.php');
    $migration->down();
    $migration->up();

    $flags = ParticipationRecordMatch::query()
        ->whereIn('id', $matches->pluck('id'))
        ->orderBy('id')
        ->pluck('is_individual_qualifier')
        ->all();

    expect($flags)->toBe([true, false, false]);
});

test('tryout match results always stay individual', function (string $teamFormationStyle) {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_formation_style' => $teamFormationStyle,
        'team_size_max' => 4,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'tryout',
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'Tryout', 'score_for' => 3, 'score_against' => 2, 'is_forfeit' => true, 'mp_link' => '111111'],
                ['stage' => 'F', 'score_for' => 6, 'score_against' => 4, 'mp_link' => '222222'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();
    $shared = TournamentParticipationRecord::query()->where('user_id', $teammate->id)->firstOrFail();

    expect($record->selection_outcome)->toBe(TournamentParticipationRecord::SELECTION_TRYOUT_FAILED)
        ->and($record->matches[0]['stage'])->toBe('Tryout')
        ->and($record->matches[0]['score_for'])->toBeNull()
        ->and($record->matches[0]['is_forfeit'])->toBeFalse()
        ->and(collect($shared->matches)->pluck('stage')->all())->toBe(['F']);
})->with([
    Tournament::TEAM_FORMATION_STANDARD,
    Tournament::TEAM_FORMATION_DRAFT,
    Tournament::TEAM_FORMATION_WORLD_CUP,
]);

test('individual qualifier flag is cleared when the match stage is not qualifier', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'matches' => [
                [
                    'stage' => 'SF',
                    'score_for' => 5,
                    'score_against' => 3,
                    'mp_link' => '111111',
                    'is_individual_qualifier' => true,
                ],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect($record->matches[0]['is_individual_qualifier'])->toBeFalse();
});

test('match only edits preserve existing shared records when teammate inputs are empty', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Existing Shared Team',
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();
    $shared = TournamentParticipationRecord::query()
        ->where('user_id', $teammate->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    $record->teammates()->detach();

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Existing Shared Team',
            'teammates_submitted' => 1,
            'matches' => [
                ['stage' => 'F', 'score_for' => 6, 'score_against' => 4, 'mp_link' => 'https://osu.ppy.sh/mp/444444'],
            ],
        ])
        ->assertRedirect();

    $sharedMatches = $shared->fresh()->matches;

    expect($sharedMatches)->toHaveCount(1)
        ->and($sharedMatches[0]['result'])->toBe('6-4')
        ->and($sharedMatches[0]['mp_id'])->toBe(444444)
        ->and($sharedMatches[0])->not->toHaveKey('match_id')
        ->and(TournamentParticipationRecord::query()->whereKey($shared->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('users.show', ['user' => $teammate, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee('444444');
});

test('legacy json matches column is removed from participation records', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Legacy Json Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);

    expect(Schema::hasColumn('tournament_participation_records', 'matches'))->toBeFalse()
        ->and($record->fresh()->matches)->toBe([]);

    $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertDontSee('LegacyOnly');
});

test('submitting an empty match list clears current rows after legacy json removal', function () {
    $user = User::factory()->withSetup()->create();
    $record = createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
    ], [
        ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '111111'],
    ]);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'matches' => [],
        ])
        ->assertRedirect();

    $record->refresh();

    expect($record->matches)->toBe([])
        ->and($record->participationMatches()->count())->toBe(0);
});

test('missing source match rows are not copied back into teammate records after save', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    $shared = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => 'manual_shared',
        'metadata' => ['shared_from_record_id' => $record->id],
    ]);
    $record->teammates()->attach($teammate);
    $shared->teammates()->attach($user);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
            'matches' => [],
        ])
        ->assertRedirect();

    expect($record->fresh()->matches)->toBe([])
        ->and($shared->fresh()->matches)->toBe([]);
});

test('legacy teammate pivot components sync matches and normalize shared root metadata', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    $record = createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'team_name' => 'Legacy Pivot Team',
    ], [
        ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '111111'],
    ]);
    $teammateRecord = createParticipationRecordWithCurrentMatches([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'team_name' => 'Legacy Pivot Team',
    ], [
        ['stage' => 'QF', 'score_for' => 4, 'score_against' => 2, 'mp_link' => '222222'],
    ]);
    $record->teammates()->attach($teammate);
    $teammateRecord->teammates()->attach($user);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Normalized Team',
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 5, 'mp_link' => '333333'],
            ],
        ])
        ->assertRedirect();

    $record->refresh();
    $teammateRecord->refresh();

    expect($record->matches[0]['result'])->toBe('7-5')
        ->and($teammateRecord->matches[0]['result'])->toBe('7-5')
        ->and(data_get($record->metadata, 'shared_from_record_id'))->toBeNull()
        ->and(data_get($teammateRecord->metadata, 'shared_from_record_id'))->toBe($record->id)
        ->and($record->teammates()->pluck('users.id')->all())->toBe([$teammate->id])
        ->and($teammateRecord->teammates()->pluck('users.id')->all())->toBe([$user->id]);
});

test('legacy reverse-only teammate pivot still syncs match edits and hydrates teammates', function () {
    $user = User::factory()->withSetup()->create(['username' => 'ReverseOwner']);
    $teammate = User::factory()->withSetup()->create(['username' => 'ReverseMate']);
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Reverse Legacy Cup',
        'team_size_max' => 2,
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    $teammateRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
    ]);
    $teammateRecord->teammates()->attach($user);

    $this->actingAs($user)
        ->getJson(route('users.participation.tournaments.search', ['user' => $user, 'q' => 'Reverse Legacy']))
        ->assertOk()
        ->assertJsonPath('tournaments.0.existing_record.teammates.0.id', $teammate->id);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'teammates_submitted' => 1,
            'matches' => [
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 4, 'mp_link' => '555555'],
            ],
        ])
        ->assertRedirect();

    $record->refresh();
    $teammateRecord->refresh();

    expect($record->matches[0]['mp_id'])->toBe(555555)
        ->and($teammateRecord->matches[0]['mp_id'])->toBe(555555)
        ->and(data_get($teammateRecord->metadata, 'shared_from_record_id'))->toBe($record->id)
        ->and($record->teammates()->pluck('users.id')->all())->toBe([$teammate->id])
        ->and($teammateRecord->teammates()->pluck('users.id')->all())->toBe([$user->id]);
});

test('legacy teammate normalization migration converts old pivot teams to shared groups', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
    ]);
    $teammateRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
    ]);
    $teammateRecord->teammates()->attach($user);

    $migration = include database_path('migrations/2026_06_02_000003_normalize_legacy_participation_teammate_groups.php');
    $migration->up();

    $record->refresh();
    $teammateRecord->refresh();

    expect(data_get($record->metadata, 'shared_from_record_id'))->toBeNull()
        ->and($teammateRecord->source)->toBe('manual_shared')
        ->and(data_get($teammateRecord->metadata, 'shared_from_record_id'))->toBe($record->id)
        ->and($record->teammates()->pluck('users.id')->all())->toBe([$teammate->id])
        ->and($teammateRecord->teammates()->pluck('users.id')->all())->toBe([$user->id]);
});

test('empty submitted match list removes shareable team rows from every teammate record', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 5, 'mp_link' => '444444'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();
    $shared = TournamentParticipationRecord::query()->where('user_id', $teammate->id)->firstOrFail();

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
            'matches' => [],
        ])
        ->assertRedirect();

    expect($record->fresh()->matches)->toBe([])
        ->and($shared->fresh()->matches)->toBe([])
        ->and(ParticipationRecordMatch::query()
            ->whereIn('tournament_participation_record_id', [$record->id, $shared->id])
            ->exists())->toBeFalse();
});

test('target individual qualifier rows stay private while edited shareable rows propagate', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'team_size_max' => 2,
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier'],
                ['type' => 'bracket', 'start_round_size' => 16],
            ],
        ],
    ]);
    $record = createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ], [
        ['stage' => 'Qualifier', 'mp_link' => '111111', 'is_individual_qualifier' => true],
        ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '222222'],
    ]);
    $shared = createParticipationRecordWithCurrentMatches([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => 'manual_shared',
        'metadata' => ['shared_from_record_id' => $record->id],
    ], [
        ['stage' => 'Qualifier', 'mp_link' => '999999', 'is_individual_qualifier' => true],
        ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '222222'],
    ]);
    $record->teammates()->attach($teammate);
    $shared->teammates()->attach($user);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
            'matches' => [
                ['stage' => 'Qualifier', 'mp_link' => '111111', 'is_individual_qualifier' => true],
                ['stage' => 'F', 'score_for' => 7, 'score_against' => 6, 'mp_link' => '333333'],
            ],
        ])
        ->assertRedirect();

    expect(collect($record->fresh()->matches)->pluck('mp_id')->all())->toBe([111111, 333333])
        ->and(collect($shared->fresh()->matches)->pluck('mp_id')->all())->toBe([999999, 333333]);
});

test('match reorder dragging starts only from the handle button', function () {
    $blade = file_get_contents(resource_path('views/users/partials/participation.blade.php'));

    expect($blade)->toContain('<button type="button" draggable="true" @dragstart="startMatchDrag(index)"')
        ->and($blade)->not()->toContain('<div class="mb-2 rounded-lg border border-slate-700/60 bg-slate-900/50 p-3 transition"'."\r\n".'                                                 draggable="true"')
        ->and($blade)->not()->toContain('<div class="mb-2 rounded-lg border border-slate-700/60 bg-slate-900/50 p-3 transition"'."\n".'                                                 draggable="true"');
});

test('participation dialog exposes the individual qualifier checkbox only for qualifier rows', function () {
    $blade = file_get_contents(resource_path('views/users/partials/participation.blade.php'));

    expect($blade)
        ->toContain('x-show="isQualifierMatch(match)"')
        ->toContain('matches[${index}][is_individual_qualifier]')
        ->toContain('match.is_individual_qualifier = false');
});

test('opposing teams can save the same canonical multiplayer link without creating match rows', function () {
    $firstUser = User::factory()->withSetup()->create();
    $secondUser = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    foreach ([$firstUser, $secondUser] as $user) {
        $this->actingAs($user)
            ->post(route('users.participation.store', $user), [
                'tournament_id' => $tournament->id,
                'team_name' => 'Team '.$user->id,
                'matches' => [
                    ['stage' => 'F', 'score_for' => 5, 'score_against' => 4, 'mp_link' => '777777'],
                ],
            ])
            ->assertRedirect();
    }

    $linkedParticipationRecordIds = ParticipationRecordMatch::query()
        ->where('mp_id', 777777)
        ->pluck('tournament_participation_record_id')
        ->unique();

    expect(DB::table('matches')->where('osu_match_id', 777777)->count())->toBe(0)
        ->and($linkedParticipationRecordIds)->toHaveCount(2);
});

test('participation match links preserve canonical MP data without linking imported multiplayer matches', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();
    DB::table('matches')->insert([
        'osu_match_id' => 888888,
        'tournament_id' => $tournament->id,
        'status' => 'approved',
        'name' => 'Imported Finals',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'matches' => [
                ['stage' => 'F', 'score_for' => 6, 'score_against' => 4, 'mp_link' => 'https://osu.ppy.sh/mp/888888'],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()->where('user_id', $user->id)->firstOrFail();

    expect(DB::table('matches')->where('osu_match_id', 888888)->count())->toBe(1)
        ->and($record->matches[0])->not->toHaveKey('match_id')
        ->and($record->matches[0]['mp_link'])->toBe('https://osu.ppy.sh/community/matches/888888');
});

test('sharing teammates blocks users who already have a tournament participation record', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    $existingTeammateRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'team_name' => 'Already Mine',
        'memo' => 'keep this',
        'metadata' => ['stage_value' => 'completed'],
    ]);

    $this->actingAs($user)
        ->postJson(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'dnq',
            'team_name' => 'New Shared Team',
            'teammate_ids' => [$teammate->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('teammate_ids');

    $existingTeammateRecord->refresh();

    expect(TournamentParticipationRecord::query()
        ->where('user_id', $teammate->id)
        ->where('tournament_id', $tournament->id)
        ->count())->toBe(1)
        ->and($existingTeammateRecord->team_name)->toBe('Already Mine')
        ->and($existingTeammateRecord->memo)->toBe('keep this')
        ->and(data_get($existingTeammateRecord->metadata, 'shared_from_record_id'))->toBeNull();
});

test('match rows with data require a stage before saving', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'matches' => [
                ['stage' => '', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '123456'],
            ],
        ])
        ->assertSessionHasErrors('matches.0.stage');
});

test('updating participation without match input preserves existing matches', function () {
    $user = User::factory()->withSetup()->create();
    $record = createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
    ], [['stage' => 'SF', 'result' => '5-3', 'score_for' => 5, 'score_against' => 3]]);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'memo' => 'updated memo',
        ])
        ->assertRedirect();

    $record->refresh();

    expect($record->memo)->toBe('updated memo')
        ->and($record->matches[0]['stage'])->toBe('SF')
        ->and($record->matches[0]['result'])->toBe('5-3')
        ->and($record->participationMatches()->count())->toBe(1);

    $log = ParticipationInputLog::query()->where('action', 'updated')->firstOrFail();

    expect(array_keys($log->changed_fields['changes']))->toBe(['memo'])
        ->and($log->changed_fields['changes']['memo'])->toEqual([
            'old' => null,
            'new' => 'updated memo',
        ]);
});

test('updating participation without teammate input preserves existing teammates', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create(['team_size_max' => 2])->id,
        'team_name' => 'Keep Mate',
    ]);
    $record->teammates()->attach($teammate);

    $this->actingAs($user)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Still Keep Mate',
        ])
        ->assertOk();

    expect($record->fresh()->teammates()->pluck('users.id')->all())->toBe([$teammate->id]);
});

test('updating normal match scores clears a stale forfeit flag', function () {
    $user = User::factory()->withSetup()->create();
    $record = createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
    ], [['stage' => 'F', 'score_for' => -1, 'score_against' => 0, 'is_forfeit' => true]]);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'matches' => [
                ['stage' => 'F', 'score_for' => 6, 'score_against' => 4, 'is_forfeit' => true],
            ],
        ])
        ->assertRedirect();

    expect($record->fresh()->matches[0]['is_forfeit'])->toBeFalse();
});

test('match score sentinel keeps the match marked as forfeit', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'matches' => [
                ['stage' => 'F', 'score_for' => 0, 'score_against' => -1, 'is_forfeit' => false],
            ],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->matches[0]['is_forfeit'])->toBeTrue();
});

test('unchanged participation update does not create an audit log', function () {
    $user = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
        'team_name' => 'No Change',
        'metadata' => ['stage_value' => 'completed'],
    ]);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'team_name' => 'No Change',
            'stage_value' => 'completed',
        ])
        ->assertRedirect();

    expect(ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->exists())->toBeFalse();
});

test('saved participation audit contains only meaningful non teammate values', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
            'team_name' => '',
            'memo' => '',
            'matches' => [],
        ])
        ->assertRedirect();

    $log = ParticipationInputLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'saved')
        ->firstOrFail();

    expect($log->changed_fields)->toBe(['stage_value' => 'completed']);
});

test('admin can lock text input and delete user input sections', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $record = createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
        'team_name' => 'bad team',
        'memo' => 'bad memo',
    ], [['stage' => 'SF', 'result' => '0-5 lose']]);

    $this->actingAs($admin)
        ->post(route('admin.participation-moderation.lock', $user), ['reason' => 'bad words'])
        ->assertRedirect();

    expect(ParticipationInputLock::query()->where('user_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.participation-moderation.delete-input', $record), ['reason' => 'cleanup'])
        ->assertRedirect();

    $record->refresh();

    expect($record->team_name)->toBeNull()
        ->and($record->memo)->toBeNull()
        ->and($record->matches[0])->not->toHaveKey('note')
        ->and($record->user_input_deleted_by)->toBe($admin->id);
});

test('locked users cannot mutate participation records', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'team_name' => 'original',
        'memo' => 'original memo',
    ]);
    $record->teammates()->attach($teammate);
    ParticipationInputLock::query()->create([
        'user_id' => $user->id,
        'locked_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'completed',
        ])
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'seed' => 7,
            'team_name' => 'changed',
            'memo' => 'changed',
        ])
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->post(route('users.participation.deletion-request', [$user, $record]), ['reason' => 'remove'])
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->delete(route('users.participation.destroy', [$user, $record]))
        ->assertSessionHas('error');

    $record->refresh();

    expect($record->seed)->toBeNull()
        ->and($record->team_name)->toBe('original')
        ->and($record->memo)->toBe('original memo')
        ->and(TournamentParticipationRecord::query()->whereKey($record->id)->exists())->toBeTrue()
        ->and(ParticipationDeletionRequest::query()->count())->toBe(0);
});

test('participation locale keys exist in configured visible locales', function () {
    foreach (['en', 'es', 'ko', 'ru', 'zh-Hans', 'zh-Hant'] as $locale) {
        expect(trans('users.tabs.participation', [], $locale))->not()->toBe('users.tabs.participation')
            ->and(trans('admin.participation.title', [], $locale))->not()->toBe('admin.participation.title');
    }
});

test('participation row omits duplicate dnq text and colors full match links', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'DNQ Display Cup',
        'registration_start' => now()->subMonths(5),
        'registration_end' => now()->subMonths(4),
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 32],
                ['type' => 'bracket', 'start_round_size' => 32],
            ],
        ],
    ]);

    createParticipationRecordWithCurrentMatches([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'selection_outcome' => 'dnq',
        'seed' => 53,
        'metadata' => ['stage_value' => 'dnq'],
    ], [
        ['stage' => 'Ro16', 'score_for' => 1, 'score_against' => 5, 'mp_link' => 'https://osu.ppy.sh/community/matches/123'],
        ['stage' => 'Ro32', 'score_for' => -1, 'score_against' => 0, 'mp_link' => 'https://osu.ppy.sh/community/matches/124', 'is_forfeit' => true],
    ]);

    $html = $this->actingAs($user)->get(route('users.show', $user))->assertOk()->getContent();

    expect($html)->not()->toContain('<span class="text-slate-300">DNQ</span>')
        ->and($html)->toContain('Qualifier Seed 53/32')
        ->and($html)->toContain('Ro16 1 - 5')
        ->and($html)->toContain('Ro32 FF')
        ->and($html)->toContain('text-red-300');
});

test('dnp display omits selection wording and uses purple placement styling', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'title' => 'DNP Display Cup',
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'selection_outcome' => 'dnp',
        'metadata' => ['stage_value' => 'dnp'],
    ]);

    $html = $this->actingAs($user)->get(route('users.show', $user))->assertOk()->getContent();

    expect($html)->toContain('DNP')
        ->and($html)->not()->toContain('Selection - DNP')
        ->and($html)->toContain('bg-purple-500/15 text-purple-200');
});

test('participation history exposes search and filter metadata with chronological index', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create(['username' => 'FilterMate']);
    $oldTournament = Tournament::factory()->approved()->create([
        'title' => 'First Played Cup',
        'registration_start' => now()->subYears(3),
        'registration_end' => now()->subYears(3)->addMonth(),
        'tournament_start' => now()->subYears(2)->subMonths(10),
        'tournament_end' => now()->subYears(2),
    ]);
    $newTournament = Tournament::factory()->approved()->create([
        'title' => 'Newest Badged Cup',
        'registration_start' => now()->subYears(2),
        'registration_end' => now()->subYears(2)->addMonth(),
        'tournament_start' => now()->subYear()->subMonths(10),
        'tournament_end' => now()->subYear(),
        'is_badge' => true,
        'badge_status' => 'approved',
    ]);

    $oldRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $oldTournament->id,
    ]);
    $newRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $newTournament->id,
        'team_name' => 'Searchable Team',
    ]);
    $newRecord->teammates()->attach($teammate);

    $html = $this->actingAs($user)->get(route('users.show', $user))->assertOk()->getContent();

    expect($html)->toContain('x-model="historySearch"')
        ->and($html)->toContain('data-search="Newest Badged Cup Searchable Team FilterMate"')
        ->and($html)->toContain('Most Teamed User')
        ->and($html)->toContain("https://a.ppy.sh/{$teammate->osu_id}")
        ->and($html)->toContain(route('users.show', $teammate))
        ->and($html)->toContain('data-badged="1"')
        ->and($html)->toContain('Badged')
        ->and($html)->toContain('#1')
        ->and($html)->toContain('#2');
});

test('profile participation stats tie break by profile country then best bws rank', function () {
    $owner = User::factory()->withSetup()->create(['country_code' => 'KR']);
    $krTeammate = User::factory()->withSetup()->create(['username' => 'KoreaMate', 'country_code' => 'KR']);
    $usTeammate = User::factory()->withSetup()->create(['username' => 'UsMate', 'country_code' => 'US']);
    $tournament = Tournament::factory()->approved()->create(['modes' => [['mode' => 'osu']]]);
    UserRankHistory::query()->create(['user_id' => $krTeammate->id, 'mode' => 'osu', 'rank' => 500, 'recorded_at' => now()]);
    UserRankHistory::query()->create(['user_id' => $usTeammate->id, 'mode' => 'osu', 'rank' => 100, 'recorded_at' => now()]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => $tournament->id,
    ]);
    $record->teammates()->attach([$krTeammate->id, $usTeammate->id]);

    $stats = app(ParticipationStatsService::class)->summarizeForUser($owner, true);

    expect($stats['most_common_country'])->toBe('KR')
        ->and($stats['most_teamed_user']['username'])->toBe('KoreaMate');

    $polishOwner = User::factory()->withSetup()->create(['country_code' => 'PL']);
    $maliszewski = User::factory()->withSetup()->create(['username' => 'MALISZEWSKI', 'country_code' => 'PL']);
    $apteka = User::factory()->withSetup()->create(['username' => 'Apteka', 'country_code' => 'PL']);
    UserRankHistory::query()->create(['user_id' => $maliszewski->id, 'mode' => 'osu', 'rank' => 61, 'recorded_at' => now()]);
    UserRankHistory::query()->create(['user_id' => $apteka->id, 'mode' => 'osu', 'rank' => 6973, 'recorded_at' => now()]);
    $bwsRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $polishOwner->id,
        'tournament_id' => $tournament->id,
    ]);
    $bwsRecord->teammates()->attach([$maliszewski->id, $apteka->id]);

    $bwsStats = app(ParticipationStatsService::class)->summarizeForUser($polishOwner, true);

    expect($bwsStats['most_common_country'])->toBe('PL')
        ->and($bwsStats['most_teamed_user']['username'])->toBe('MALISZEWSKI');
});

test('visibility toggle hides participation from visitors but not owner or admin', function () {
    $owner = User::factory()->withSetup()->create();
    $visitor = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Hidden Profile Cup',
        'registration_start' => now()->subMonths(5),
        'registration_end' => now()->subMonths(4),
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => $tournament->id,
    ]);

    $this->actingAs($owner)
        ->patch(route('users.participation.visibility', [$owner, $record]))
        ->assertRedirect();

    $this->actingAs($visitor)->get(route('users.show', $owner))->assertOk()->assertDontSee('Hidden Profile Cup');
    $this->actingAs($owner)
        ->get(route('users.show', $owner))
        ->assertOk()
        ->assertSee('Hidden Profile Cup')
        ->assertSee('Hidden from public profile')
        ->assertSee('Only you and admins can see this record');
    $this->actingAs($admin)->get(route('users.show', $owner))->assertOk()->assertSee('Hidden Profile Cup');
});

test('participation mutations can refresh fragments without redirecting', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $createTournament = Tournament::factory()->approved()->create(['title' => 'Async Create Cup']);
    $deleteTournament = Tournament::factory()->approved()->create(['title' => 'Async Delete Cup']);
    $requestTournament = Tournament::factory()->approved()->create(['title' => 'Async Request Cup']);
    $deleteRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $deleteTournament->id,
    ]);
    $requestRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $requestTournament->id,
    ]);
    $requestRecord->teammates()->attach($teammate);

    $createResponse = $this->actingAs($user)
        ->postJson(route('users.participation.store', $user), [
            'tournament_id' => $createTournament->id,
            'stage_value' => 'completed',
            'memo' => 'created async',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success')
        ->assertJsonStructure(['html']);

    expect($createResponse->json('html'))->toContain('data-participation-refresh')
        ->toContain('Async Create Cup');

    $createdRecord = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $createTournament->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->patchJson(route('users.participation.update', [$user, $createdRecord]), [
            'memo' => 'updated async',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success')
        ->assertJsonPath('message', __('users.participation.flash.saved'));

    $visibilityResponse = $this->actingAs($user)
        ->patchJson(route('users.participation.visibility', [$user, $createdRecord]))
        ->assertOk()
        ->assertJsonPath('type', 'success');

    expect($visibilityResponse->json('html'))->toContain(__('users.participation.labels.hidden_public'));

    $this->actingAs($user)
        ->deleteJson(route('users.participation.destroy', [$user, $deleteRecord]))
        ->assertOk()
        ->assertJsonPath('type', 'success');

    expect(TournamentParticipationRecord::query()->whereKey($deleteRecord->id)->exists())->toBeFalse();

    $requestResponse = $this->actingAs($user)
        ->postJson(route('users.participation.deletion-request', [$user, $requestRecord]), ['reason' => 'wrong team'])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    expect($requestResponse->json('html'))->toContain(__('users.participation.labels.deletion_pending'));
});

test('participation page initially renders ten records and loads the next page from endpoint', function () {
    $user = User::factory()->withSetup()->create();

    foreach (range(1, 12) as $index) {
        $tournament = Tournament::factory()->approved()->create([
            'title' => sprintf('Paged Cup %02d', $index),
            'registration_start' => now()->subMonths(6),
            'registration_end' => now()->subMonths(5),
            'tournament_start' => now()->subMonths(4),
            'tournament_end' => now()->subDays($index),
        ]);

        TournamentParticipationRecord::query()->create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
        ]);
    }

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->getContent();

    expect(preg_match_all('/data-participation-record(?:\s|>)/', $html))->toBe(10)
        ->and($html)->toContain('Load more');

    $response = $this->actingAs($user)
        ->getJson(route('users.participation.records', [$user, 'offset' => 10]))
        ->assertOk()
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('next_offset', null);

    expect(preg_match_all('/data-participation-record(?:\s|>)/', $response->json('html')))->toBe(2)
        ->and($response->json('html'))->toContain('Paged Cup 11')
        ->and($response->json('html'))->toContain('Paged Cup 12');
});

test('participation profile only hydrates match rows for initially visible records', function () {
    $user = User::factory()->withSetup()->create();

    foreach (range(1, 12) as $index) {
        $tournament = Tournament::factory()->approved()->create([
            'title' => sprintf('Hydrated Cup %02d', $index),
            'registration_start' => now()->subMonths(6),
            'registration_end' => now()->subMonths(5),
            'tournament_start' => now()->subMonths(4),
            'tournament_end' => now()->subDays($index),
        ]);

        $record = TournamentParticipationRecord::query()->create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
        ]);
        $record->syncParticipationMatches([[
            'stage' => 'F',
            'result' => "{$index}-0",
            'score_for' => $index,
            'score_against' => 0,
            'mp_link' => "https://osu.ppy.sh/community/matches/900{$index}",
            'mp_id' => (int) "900{$index}",
            'is_forfeit' => false,
        ]], $user->id);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->getContent();

    DB::disableQueryLog();

    $matchQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'participation_record_matches'))
        ->values();

    expect($html)->toContain('Hydrated Cup 01')
        ->and($html)->toContain('9001')
        ->and($html)->not->toContain('90011')
        ->and($matchQueries->count())->toBeLessThanOrEqual(2);
});

test('admin can manage another users participation records from the profile page', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Admin Managed Cup',
        'team_size_max' => 2,
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'team_name' => 'Old Team',
    ]);
    $record->teammates()->attach($teammate);

    $html = $this->actingAs($admin)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee('Admin Managed Cup')
        ->assertSee(__('users.participation.actions.edit'))
        ->assertSee(__('users.participation.actions.delete'))
        ->assertSee(__('users.participation.delete_confirm_title'))
        ->getContent();

    expect($html)->not()->toContain(__('users.participation.actions.request_delete'));
    expect($html)->not()->toContain(__('users.participation.actions.hide'));

    $this->actingAs($admin)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Admin Team',
            'memo' => 'admin updated',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    $record->refresh();

    expect($record->team_name)->toBe('Admin Team')
        ->and($record->memo)->toBe('admin updated');
});

test('participation action menu is positioned inside mobile viewport', function () {
    $user = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
    ]);

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee(__('users.participation.actions.delete'))
        ->getContent();

    expect($record->exists)->toBeTrue()
        ->and($html)->toContain('absolute left-0 z-20 mt-2 w-56')
        ->toContain('sm:left-auto sm:right-0');
});

test('admin deletion removes podium participation and linked tournament podium row', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Admin Podium Delete Cup']);
    $winner = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'placement' => 1,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'gamemode' => 'osu',
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('users.participation.destroy', [$user, $record]))
        ->assertOk()
        ->assertJsonPath('type', 'success');

    expect(TournamentParticipationRecord::query()->whereKey($record->id)->exists())->toBeFalse()
        ->and(TournamentWinner::query()->whereKey($winner->id)->exists())->toBeFalse();
});

test('admin can edit podium participation members and sync the tournament podium roster', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create(['username' => 'PodiumOwner']);
    $oldTeammate = User::factory()->withSetup()->create(['username' => 'OldPodiumMate']);
    $newTeammate = User::factory()->withSetup()->create(['username' => 'NewPodiumMate']);
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Roster Sync Podium Cup',
        'team_size_max' => 2,
        'modes' => [['mode' => 'mania', 'key_count' => null]],
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'placement' => 1,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'gamemode' => 'mania',
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $oldTeammate->id,
        'placement' => 1,
        'username' => $oldTeammate->username,
        'osu_id' => $oldTeammate->osu_id,
        'gamemode' => 'mania',
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);
    $oldTeammateRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $oldTeammate->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);
    $record->teammates()->attach($oldTeammate);
    $oldTeammateRecord->teammates()->attach($user);

    $response = $this->actingAs($admin)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'teammate_ids' => [$newTeammate->id],
            'team_name' => 'Synced Roster',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    $record->refresh();

    expect($record->team_name)->toBe('Synced Roster')
        ->and($record->teammates()->pluck('users.id')->all())->toBe([$newTeammate->id])
        ->and(TournamentParticipationRecord::query()
            ->where('user_id', $newTeammate->id)
            ->where('tournament_id', $tournament->id)
            ->value('team_name'))->toBe('Synced Roster')
        ->and(TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('placement', 1)
            ->pluck('user_id')
            ->sort()
            ->values()
            ->all())->toBe(collect([$user->id, $newTeammate->id])->sort()->values()->all())
        ->and(TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $newTeammate->id)
            ->value('gamemode'))->toBe('mania')
        ->and(TournamentParticipationRecord::query()->whereKey($oldTeammateRecord->id)->exists())->toBeFalse()
        ->and($response->json('html'))->toContain('NewPodiumMate')
        ->and($response->json('html'))->not()->toContain('OldPodiumMate');
});

test('admin can edit podium team name when existing teammates have stale separate groups', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create(['username' => 'GroupedOwner']);
    $teammate = User::factory()->withSetup()->create(['username' => 'GroupedMate']);
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
    ]);

    foreach ([$user, $teammate] as $winnerUser) {
        TournamentWinner::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winnerUser->id,
            'placement' => 1,
            'username' => $winnerUser->username,
            'osu_id' => $winnerUser->osu_id,
            'gamemode' => 'osu',
            'metadata' => [
                'podium_group_id' => (string) Str::uuid(),
            ],
        ]);
    }

    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);
    $teammateRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);
    $record->teammates()->attach($teammate);
    $teammateRecord->teammates()->attach($user);

    $this->actingAs($admin)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'teammate_ids' => [$teammate->id],
            'team_name' => 'Renamed Podium Team',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    expect($record->fresh()->team_name)->toBe('Renamed Podium Team')
        ->and($record->fresh()->teammates()->pluck('users.id')->all())->toBe([$teammate->id]);
});

test('admin podium member edit resolves pending osu ids through podium sync logic', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $newTeammate = User::factory()->withSetup()->create([
        'osu_id' => 445566,
        'username' => 'ResolvedPodiumMate',
        'main_mode' => 'mania',
    ]);
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
        'modes' => [['mode' => 'mania', 'key_count' => null]],
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'placement' => 2,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'gamemode' => 'mania',
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 2,
        'placement_min' => 2,
        'placement_max' => 2,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);

    $syncService = Mockery::mock(TournamentParticipantSyncService::class);
    $syncService->shouldReceive('resolvePodiumUserByOsuId')
        ->once()
        ->with(Mockery::on(fn (Tournament $candidate): bool => $candidate->id === $tournament->id), 445566)
        ->andReturn($newTeammate);
    $this->app->instance(TournamentParticipantSyncService::class, $syncService);

    $this->actingAs($admin)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'pending_teammate_osu_ids' => [445566],
        ])
        ->assertOk();

    expect($record->fresh()->teammates()->pluck('users.id')->all())->toBe([$newTeammate->id])
        ->and(TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $newTeammate->id)
            ->where('placement', 2)
            ->value('gamemode'))->toBe('mania');
});

test('admin can remove all podium teammates from profile edit workflow', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create(['username' => 'PodiumCaptain']);
    $teammate = User::factory()->withSetup()->create(['username' => 'RemovedMate']);
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Remove Podium Mate Cup',
        'team_size_max' => 2,
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'placement' => 1,
        'username' => $user->username,
        'osu_id' => $user->osu_id,
        'gamemode' => 'osu',
    ]);
    TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $teammate->id,
        'placement' => 1,
        'username' => $teammate->username,
        'osu_id' => $teammate->osu_id,
        'gamemode' => 'osu',
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);
    $teammateRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);
    $record->teammates()->attach($teammate);
    $teammateRecord->teammates()->attach($user);

    $this->actingAs($admin)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'teammates_submitted' => 1,
            'team_name' => 'Solo Podium',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    expect($record->fresh()->teammates()->pluck('users.id')->all())->toBe([])
        ->and(TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('placement', 1)
            ->pluck('user_id')
            ->all())->toBe([$user->id])
        ->and(TournamentParticipationRecord::query()->whereKey($teammateRecord->id)->exists())->toBeFalse();
});

test('admin sees direct delete for own podium records', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Own Admin Podium Cup']);
    $winner = TournamentWinner::query()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $admin->id,
        'placement' => 1,
        'username' => $admin->username,
        'osu_id' => $admin->osu_id,
        'gamemode' => 'osu',
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $admin->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);

    $html = $this->actingAs($admin)
        ->get(route('users.show', ['user' => $admin, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee(__('users.participation.actions.delete'))
        ->getContent();

    expect($html)
        ->toContain('openDeleteConfirmDialog')
        ->not()->toContain(__('users.participation.actions.request_delete'))
        ->not()->toContain(__('users.participation.labels.delete_locked'));

    $this->actingAs($admin)
        ->deleteJson(route('users.participation.destroy', [$admin, $record]))
        ->assertOk();

    expect(TournamentParticipationRecord::query()->whereKey($record->id)->exists())->toBeFalse()
        ->and(TournamentWinner::query()->whereKey($winner->id)->exists())->toBeFalse();
});

test('admin sees direct delete confirmation for hidden or requested team records', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Hidden Admin Delete Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'profile_hidden_at' => now(),
        'profile_hidden_by' => $user->id,
    ]);
    $record->teammates()->attach($teammate);
    ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $record->id,
        'requested_by' => $user->id,
        'status' => ParticipationDeletionRequest::STATUS_PENDING,
    ]);

    $html = $this->actingAs($admin)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee(__('users.participation.actions.delete'))
        ->assertSee(__('users.participation.delete_confirm_title'))
        ->getContent();

    expect($html)
        ->toContain('openDeleteConfirmDialog')
        ->not()->toContain(__('users.participation.actions.request_delete'))
        ->not()->toContain(__('users.participation.actions.cancel_delete_request'));
});

test('podium participation cannot be hidden and does not show hide action', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'No Hide Podium Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => ['autofilled_from' => 'tournament_winners'],
    ]);

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee('No Hide Podium Cup')
        ->getContent();

    expect($html)->not()->toContain(__('users.participation.actions.hide'));

    $this->actingAs($user)
        ->patchJson(route('users.participation.visibility', [$user, $record]))
        ->assertStatus(422)
        ->assertJsonPath('message', __('users.participation.flash.hide_blocked_podium'));
});

test('team participation deletion requires admin approval and deletes related team records', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Team Delete Cup',
        'registration_start' => now()->subMonths(5),
        'registration_end' => now()->subMonths(4),
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    $shared = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => 'manual_shared',
        'metadata' => ['shared_from_record_id' => $record->id],
    ]);
    $record->teammates()->attach($teammate);
    $shared->teammates()->attach($user);

    $this->actingAs($user)
        ->delete(route('users.participation.destroy', [$user, $record]))
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->post(route('users.participation.deletion-request', [$user, $record]), ['reason' => 'remove mine'])
        ->assertRedirect();
    $this->actingAs($teammate)
        ->post(route('users.participation.deletion-request', [$teammate, $shared]), ['reason' => 'same team'])
        ->assertRedirect();

    $deletionRequest = ParticipationDeletionRequest::query()->where('requested_by', $user->id)->firstOrFail();
    expect($deletionRequest->status)->toBe(ParticipationDeletionRequest::STATUS_PENDING);

    $this->actingAs($user)
        ->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('Team Delete Cup')
        ->assertSee('Deletion requested')
        ->assertSee('Awaiting moderation review');

    $moderationUrl = route('admin.participation-moderation.index');

    $this->actingAs($admin)
        ->get($moderationUrl)
        ->assertOk();

    $this->getJson(route('notifications.index'))
        ->assertOk();

    $this->post(route('admin.participation-moderation.deletion-requests.approve', $deletionRequest))
        ->assertRedirect($moderationUrl);

    expect(TournamentParticipationRecord::query()->whereKey($record->id)->exists())->toBeFalse()
        ->and(TournamentParticipationRecord::query()->whereKey($shared->id)->exists())->toBeFalse();
});

test('pending podium add is private until admin approval creates tournament podium roster', function () {
    $user = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $teammate = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $viewer = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Approval Podium Cup',
        'team_size_max' => 2,
        'modes' => ['osu'],
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'stage_value' => 'bracket:0:1:grand_finals:gf',
            'placement_override' => 1,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();
    $shared = TournamentParticipationRecord::query()
        ->where('user_id', $teammate->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();

    expect($record->review_status)->toBe(TournamentParticipationRecord::REVIEW_PENDING)
        ->and($shared->review_status)->toBe(TournamentParticipationRecord::REVIEW_PENDING);

    $this->actingAs($admin)
        ->get(route('admin.participation-moderation.index'))
        ->assertOk()
        ->assertSee('Approval Podium Cup')
        ->assertSee($user->username)
        ->assertDontSee($teammate->username.' requested');

    $this->actingAs($viewer)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertDontSee('Approval Podium Cup');

    $this->actingAs($teammate)
        ->get(route('users.show', ['user' => $teammate, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee('Approval Podium Cup');

    $moderationUrl = route('admin.participation-moderation.index');

    $this->actingAs($admin)
        ->get($moderationUrl)
        ->assertOk();

    $this->getJson(route('settings.index'))
        ->assertOk();

    $this->post(route('admin.participation-moderation.add-requests.approve', $record))
        ->assertRedirect($moderationUrl);

    $this->assertDatabaseHas('tournament_winners', [
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'placement' => 1,
    ]);
    $this->assertDatabaseHas('tournament_winners', [
        'tournament_id' => $tournament->id,
        'user_id' => $teammate->id,
        'placement' => 1,
    ]);
    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $user->id,
        'type' => UserNotification::TYPE_PARTICIPATION_ADD_APPROVED,
        'tournament_id' => $tournament->id,
    ]);
});

test('admin can reject pending podium add and remove linked records', function () {
    $user = User::factory()->withSetup()->create(['main_mode_source' => 'oauth_setup']);
    $teammate = User::factory()->withSetup()->create();
    $admin = User::factory()->admin()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Rejected Podium Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_PENDING,
        'placement' => 2,
        'placement_min' => 2,
        'placement_max' => 2,
    ]);
    $shared = TournamentParticipationRecord::query()->create([
        'user_id' => $teammate->id,
        'tournament_id' => $tournament->id,
        'source' => 'manual_shared',
        'review_status' => TournamentParticipationRecord::REVIEW_PENDING,
        'placement' => 2,
        'placement_min' => 2,
        'placement_max' => 2,
        'metadata' => ['shared_from_record_id' => $record->id],
    ]);
    $record->teammates()->attach($teammate);
    $shared->teammates()->attach($user);

    $this->actingAs($admin)
        ->get(route('admin.participation-moderation.index'))
        ->assertOk()
        ->assertSee('Podium Add Requests')
        ->assertSee('Rejected Podium Cup');

    $this->actingAs($admin)
        ->post(route('admin.participation-moderation.add-requests.reject', $record), [
            'review_note' => 'Not on podium list.',
        ])
        ->assertRedirect();

    expect(TournamentParticipationRecord::query()->whereKey($record->id)->exists())->toBeFalse()
        ->and(TournamentParticipationRecord::query()->whereKey($shared->id)->exists())->toBeFalse();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $user->id,
        'type' => UserNotification::TYPE_PARTICIPATION_ADD_DENIED,
        'tournament_id' => $tournament->id,
    ]);
});

test('participation moderation filters logs and deletion requests', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create(['username' => 'NeedleUser']);
    $other = User::factory()->withSetup()->create(['username' => 'OtherUser']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Needle Cup']);
    $otherTournament = Tournament::factory()->approved()->create(['title' => 'Other Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    $otherRecord = TournamentParticipationRecord::query()->create([
        'user_id' => $other->id,
        'tournament_id' => $otherTournament->id,
    ]);

    ParticipationInputLog::query()->create([
        'tournament_participation_record_id' => $record->id,
        'user_id' => $user->id,
        'actor_id' => $user->id,
        'action' => 'updated',
        'changed_fields' => ['team_name' => 'Needle Team'],
        'flagged' => true,
        'flag_reason' => 'flag_pattern:Needle',
    ]);
    ParticipationInputLog::query()->create([
        'tournament_participation_record_id' => $otherRecord->id,
        'user_id' => $other->id,
        'actor_id' => $other->id,
        'action' => 'saved',
        'changed_fields' => ['team_name' => 'Other Team'],
    ]);

    ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $record->id,
        'requested_by' => $user->id,
        'reason' => 'Needle reason',
    ]);
    ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $otherRecord->id,
        'requested_by' => $other->id,
        'reason' => 'Other reason',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.participation-moderation.index', [
            'q' => (string) $tournament->id,
            'action' => 'updated',
            'entity_type' => 'record',
            'flagged' => 1,
            'deletion_user' => 'Needle',
        ]))
        ->assertOk()
        ->assertSee('NeedleUser')
        ->assertSee('Needle Cup')
        ->assertSee('Needle reason')
        ->assertSee('flag_pattern:Needle')
        ->assertSee($user->avatar_url, false)
        ->assertSee('Updated value')
        ->assertSee('Delete user input')
        ->assertSee('Lock participation editing')
        ->assertDontSee('OtherUser')
        ->assertDontSee('Other Cup')
        ->assertDontSee('Other reason');
});

test('participation moderation log shows admin actor for edits on another user record', function () {
    $admin = User::factory()->admin()->withSetup()->create(['username' => 'EditingAdmin']);
    $user = User::factory()->withSetup()->create(['username' => 'EditedPlayer']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Admin Actor Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    $this->actingAs($admin)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'team_name' => 'Admin Edited',
        ])
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.participation-moderation.index'))
        ->assertOk()
        ->assertSeeInOrder(['EditingAdmin', 'performed', 'Updated', 'for', 'EditedPlayer']);
});

test('deletion request uses dialog instead of expanding the action menu', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Reason Form Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    $record->teammates()->attach($teammate);

    $html = $this->actingAs($user)
        ->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('Request Participation Deletion')
        ->assertSee('name="reason"', false)
        ->assertSee('Deletion reason')
        ->assertSee('Send Request')
        ->assertDontSee('requestDeleteOpen')
        ->getContent();

    expect($html)->toContain('grid grid-cols-2 gap-2 sm:flex sm:flex-wrap')
        ->toContain('min-w-0 truncate font-medium');
});

test('owner can cancel pending deletion request and hidden action is removed while pending', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Cancel Delete Request Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    $record->teammates()->attach($teammate);
    $deletionRequest = ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $record->id,
        'requested_by' => $user->id,
        'status' => ParticipationDeletionRequest::STATUS_PENDING,
        'reason' => 'wrong entry',
    ]);

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee(__('users.participation.actions.cancel_delete_request'))
        ->getContent();

    expect($html)->not()->toContain(__('users.participation.actions.hide'));

    $response = $this->actingAs($user)
        ->deleteJson(route('users.participation.deletion-request.cancel', [$user, $record]))
        ->assertOk()
        ->assertJsonPath('type', 'success')
        ->assertJsonPath('message', __('users.participation.flash.deletion_cancelled'));

    expect(ParticipationDeletionRequest::query()->whereKey($deletionRequest->id)->exists())->toBeFalse()
        ->and($response->json('html'))->toContain(__('users.participation.actions.hide'))
        ->and($response->json('html'))->not()->toContain(__('users.participation.labels.deletion_pending'));
});

test('requesting deletion for hidden team record makes it visible while pending', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Hidden Delete Request Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'profile_hidden_at' => now(),
        'profile_hidden_by' => $user->id,
    ]);
    $record->teammates()->attach($teammate);

    $response = $this->actingAs($user)
        ->postJson(route('users.participation.deletion-request', [$user, $record]), ['reason' => 'remove visible'])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    $record->refresh();

    expect($record->profile_hidden_at)->toBeNull()
        ->and($record->profile_hidden_by)->toBeNull()
        ->and($response->json('html'))->toContain(__('users.participation.labels.deletion_pending'))
        ->and($response->json('html'))->not()->toContain(__('users.participation.labels.hidden_public'));
});

test('participation dialog teammate profile links open in a new tab', function () {
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create(['username' => 'DialogLinkMate']);
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    $record->teammates()->attach($teammate);

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee('DialogLinkMate')
        ->getContent();

    expect($html)->toContain('target="_blank" rel="noopener" class="inline-flex items-center gap-2 transition hover:text-osu-pink"');
});

test('participation dialog exposes tournament detail forum sheet and bracket links', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Linked Dialog Cup',
        'forum_topic_id' => 123456,
        'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/example',
        'bracket_url' => 'https://challonge.com/example',
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    $this->actingAs($user)
        ->getJson(route('users.participation.tournaments.search', [$user, 'q' => 'Linked Dialog']))
        ->assertOk()
        ->assertJsonPath('tournaments.0.profile_url', route('tournaments.show', $tournament))
        ->assertJsonPath('tournaments.0.forum_post_url', 'https://osu.ppy.sh/community/forums/topics/123456')
        ->assertJsonPath('tournaments.0.spreadsheet_url', 'https://docs.google.com/spreadsheets/d/example')
        ->assertJsonPath('tournaments.0.bracket_url', 'https://challonge.com/example');

    $html = $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain(route('tournaments.show', $tournament))
        ->toContain(str_replace('/', '\/', route('users.participation.lobbies.search', $user)))
        ->toContain(__('users.participation.lobby_search.open'))
        ->toContain('number-input-no-spinner')
        ->toContain('participation-score-control')
        ->toContain('data-score-against')
        ->toContain('@pointerdown.window')
        ->toContain(__('users.participation.links.details'))
        ->toContain(__('users.participation.links.forum'))
        ->toContain(__('users.participation.links.sheet'))
        ->toContain(__('users.participation.links.bracket'));
});

test('participation page renders add tutorial and result help', function () {
    $user = User::factory()->withSetup()->create();
    Tournament::factory()->approved()->create(['title' => 'Guided Participation Cup']);

    $this->actingAs($user)
        ->get(route('users.show', ['user' => $user, 'tab' => 'participation']))
        ->assertOk()
        ->assertSee('Add your participation history')
        ->assertSee('tourney-method-participation-add-tutorial:v1')
        ->assertSee('Finished Stage')
        ->assertSee('Placement')
        ->assertSee('DNQ: Did not qualify from qualifiers.')
        ->assertSee('Tryout: Tried out for a World Cup/country/team roster but was not selected.');
});

test('logged in user can report another users participation record and duplicate pending report is reused', function () {
    $owner = User::factory()->withSetup()->create();
    $reporter = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    $this->actingAs($reporter)
        ->postJson(route('users.participation.report', [$owner, $record]), [
            'category' => ParticipationRecordReport::CATEGORY_REPORT_SPAM,
            'explanation' => 'wrong teammate on purpose',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    $this->actingAs($reporter)
        ->postJson(route('users.participation.report', [$owner, $record]), [
            'category' => ParticipationRecordReport::CATEGORY_REPORT_SPAM,
            'explanation' => 'updated explanation',
        ])
        ->assertOk();

    expect(ParticipationRecordReport::query()->count())->toBe(1)
        ->and(ParticipationRecordReport::query()->firstOrFail()->explanation)->toBe('updated explanation');
});

test('user cannot report their own participation record', function () {
    $user = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    $this->actingAs($user)
        ->postJson(route('users.participation.report', [$user, $record]), [
            'category' => ParticipationRecordReport::CATEGORY_RECORD_CORRECTION,
        ])
        ->assertForbidden();
});

test('participation input locked user cannot report another users record', function () {
    $owner = User::factory()->withSetup()->create();
    $reporter = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    ParticipationInputLock::query()->create([
        'user_id' => $reporter->id,
        'locked_by' => User::factory()->admin()->create()->id,
        'locked_at' => now(),
        'reason' => 'spam reports',
    ]);

    $this->actingAs($reporter)
        ->postJson(route('users.participation.report', [$owner, $record]), [
            'category' => ParticipationRecordReport::CATEGORY_REPORT_SPAM,
        ])
        ->assertStatus(423)
        ->assertJsonPath('type', 'error')
        ->assertJsonPath('message', __('users.participation.flash.input_locked'));

    expect(ParticipationRecordReport::query()->exists())->toBeFalse();

    $this->actingAs($reporter)
        ->get(route('users.show', ['user' => $owner, 'tab' => 'participation']))
        ->assertOk()
        ->assertDontSee(route('users.participation.report', [$owner, $record]), false);
});

test('admin can view resolve and lock from participation reports', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $owner = User::factory()->withSetup()->create(['username' => 'ReportedPlayer']);
    $reporter = User::factory()->withSetup()->create(['username' => 'CarefulReporter']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Reported Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => $tournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'memo' => 'bad wording',
    ]);
    $report = ParticipationRecordReport::query()->create([
        'tournament_participation_record_id' => $record->id,
        'reported_by' => $reporter->id,
        'category' => ParticipationRecordReport::CATEGORY_INAPPROPRIATE_MEMO,
        'explanation' => 'memo is not okay',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.participation-moderation.index'))
        ->assertOk()
        ->assertSee('CarefulReporter')
        ->assertSee('ReportedPlayer')
        ->assertSee('Reported Cup')
        ->assertSee('memo is not okay')
        ->assertSee(__('admin.participation.actions.resolve_report'));

    $this->actingAs($admin)
        ->post(route('admin.participation-moderation.reports.resolve', $report), [
            'lock_user' => 1,
            'resolution_note' => 'locked after review',
        ])
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe(ParticipationRecordReport::STATUS_RESOLVED)
        ->and($report->resolved_by)->toBe($admin->id)
        ->and(ParticipationInputLock::query()->where('user_id', $owner->id)->exists())->toBeTrue();
});

test('admin can resolve participation reports with json response', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $owner = User::factory()->withSetup()->create();
    $reporter = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);
    $report = ParticipationRecordReport::query()->create([
        'tournament_participation_record_id' => $record->id,
        'reported_by' => $reporter->id,
        'category' => ParticipationRecordReport::CATEGORY_RECORD_CORRECTION,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.participation-moderation.reports.resolve', $report), [
            'resolution_note' => 'handled inline',
        ])
        ->assertOk()
        ->assertJsonPath('report_id', $report->id)
        ->assertJsonPath('remaining_reports', 0);

    expect($report->fresh()->status)->toBe(ParticipationRecordReport::STATUS_RESOLVED);
});

test('teammate only participation updates are logged with teammate names and ids', function () {
    $user = User::factory()->withSetup()->create();
    $oldTeammate = User::factory()->withSetup()->create(['username' => 'OldMate']);
    $newTeammate = User::factory()->withSetup()->create(['username' => 'NewMate']);
    $tournament = Tournament::factory()->approved()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'metadata' => ['stage_value' => 'completed'],
    ]);
    $record->teammates()->attach($oldTeammate);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'stage_value' => 'completed',
            'teammates_submitted' => 1,
            'teammate_ids' => [$newTeammate->id],
        ])
        ->assertRedirect();

    $log = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'teammates_updated')
        ->latest('id')
        ->firstOrFail();

    expect(data_get($log->changed_fields, 'changes.teammates.old.0.id'))->toBe($oldTeammate->id)
        ->and(data_get($log->changed_fields, 'changes.teammates.old.0.username'))->toBe('OldMate')
        ->and(data_get($log->changed_fields, 'changes.teammates.new.0.id'))->toBe($newTeammate->id)
        ->and(data_get($log->changed_fields, 'changes.teammates.new.0.username'))->toBe('NewMate')
        ->and(ParticipationInputLog::query()
            ->where('tournament_participation_record_id', $record->id)
            ->where('action', 'updated')
            ->exists())->toBeFalse();
});

test('initial teammates are logged separately and can be rolled back without changing the record', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['team_size_max' => 2]);

    $this->actingAs($user)
        ->post(route('users.participation.store', $user), [
            'tournament_id' => $tournament->id,
            'team_name' => 'Rollback Team',
            'teammates_submitted' => 1,
            'teammate_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $record = TournamentParticipationRecord::query()
        ->where('user_id', $user->id)
        ->where('tournament_id', $tournament->id)
        ->firstOrFail();
    $teammateLog = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'teammates_saved')
        ->firstOrFail();
    $savedLog = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'saved')
        ->firstOrFail();

    expect($savedLog->changed_fields)->not->toHaveKey('teammates')
        ->and(data_get($teammateLog->changed_fields, 'changes.teammates.old'))->toBe([])
        ->and(data_get($teammateLog->changed_fields, 'changes.teammates.new.0.id'))->toBe($teammate->id);

    $this->actingAs($admin)
        ->post(route('admin.participation-moderation.logs.rollback-teammates', $teammateLog))
        ->assertRedirect();

    expect($record->fresh()->team_name)->toBe('Rollback Team')
        ->and($record->teammates()->count())->toBe(0);
});

test('admin teammate rollback re-adds removed teammates and preserves later additions', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $removedTeammate = User::factory()->withSetup()->create();
    $laterTeammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'metadata' => ['stage_value' => 'completed'],
    ]);
    $record->teammates()->attach($removedTeammate);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'stage_value' => 'completed',
            'teammates_submitted' => 1,
            'teammate_ids' => [],
        ])
        ->assertRedirect();

    $log = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'teammates_updated')
        ->latest('id')
        ->firstOrFail();

    $record->teammates()->sync([$laterTeammate->id]);

    $this->actingAs($admin)
        ->post(route('admin.participation-moderation.logs.rollback-teammates', $log))
        ->assertRedirect();

    expect($record->teammates()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$removedTeammate->id, $laterTeammate->id])->sort()->values()->all())
        ->and(AdminAuditLog::query()->where('action', 'participation_teammates_rollback')->exists())->toBeTrue();
});

test('admin teammate rollback removes teammates added by the selected log', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create();
    $addedTeammate = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'metadata' => ['stage_value' => 'completed'],
    ]);

    $this->actingAs($user)
        ->patch(route('users.participation.update', [$user, $record]), [
            'stage_value' => 'completed',
            'teammates_submitted' => 1,
            'teammate_ids' => [$addedTeammate->id],
        ])
        ->assertRedirect();

    $log = ParticipationInputLog::query()
        ->where('tournament_participation_record_id', $record->id)
        ->where('action', 'teammates_updated')
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.participation-moderation.logs.rollback-teammates', $log))
        ->assertRedirect();

    expect($record->teammates()->pluck('users.id')->all())->toBe([]);
});

test('non admin cannot resolve participation reports or review deletion requests', function () {
    $player = User::factory()->withSetup()->create();
    $owner = User::factory()->withSetup()->create();
    $reporter = User::factory()->withSetup()->create();
    $teammate = User::factory()->withSetup()->create();
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $owner->id,
        'tournament_id' => Tournament::factory()->approved()->create()->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);
    $record->teammates()->attach($teammate);
    $report = ParticipationRecordReport::query()->create([
        'tournament_participation_record_id' => $record->id,
        'reported_by' => $reporter->id,
        'category' => ParticipationRecordReport::CATEGORY_REPORT_SPAM,
    ]);
    $deletionRequest = ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $record->id,
        'requested_by' => $owner->id,
        'status' => ParticipationDeletionRequest::STATUS_PENDING,
        'reason' => 'wrong team',
    ]);

    $this->actingAs($player)
        ->post(route('admin.participation-moderation.reports.resolve', $report), [
            'resolution_note' => 'not an admin',
        ])
        ->assertForbidden();

    $this->actingAs($player)
        ->post(route('admin.participation-moderation.deletion-requests.approve', $deletionRequest), [
            'review_note' => 'not an admin',
        ])
        ->assertForbidden();

    expect($report->fresh()->status)->toBe(ParticipationRecordReport::STATUS_PENDING)
        ->and($deletionRequest->fresh()->status)->toBe(ParticipationDeletionRequest::STATUS_PENDING);
});

test('admin deletion request cards show team details and reason', function () {
    $admin = User::factory()->admin()->withSetup()->create();
    $user = User::factory()->withSetup()->create(['username' => 'DeleteRequester']);
    $teammate = User::factory()->withSetup()->create(['username' => 'DeleteMate']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Detailed Delete Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'team_name' => 'Detail Team',
        'placement_min' => 5,
        'placement_max' => 6,
        'round_label' => 'Ro8',
    ]);
    $record->teammates()->attach($teammate);
    ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $record->id,
        'requested_by' => $user->id,
        'reason' => 'Wrong team entry',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.participation-moderation.index'))
        ->assertOk()
        ->assertSee('DeleteRequester')
        ->assertSee('Detailed Delete Cup')
        ->assertSee('#'.$tournament->id)
        ->assertSee('#5-6')
        ->assertSee('Detail Team')
        ->assertSee('Ro8')
        ->assertSee('DeleteMate')
        ->assertSee('Wrong team entry')
        ->assertSee($user->avatar_url, false)
        ->assertSee('Approve deletion request')
        ->assertSee('Reject deletion request')
        ->assertSee('Review note');
});

test('podium backed participation edit only updates editable fields', function () {
    $user = User::factory()->withSetup()->create();
    $originalTeammate = User::factory()->withSetup()->create(['username' => 'OriginalMate']);
    $newTeammate = User::factory()->withSetup()->create(['username' => 'NewMate']);
    $tournament = Tournament::factory()->approved()->create([
        'team_size_max' => 2,
        'format_structure' => [
            'stages' => [
                ['type' => 'bracket', 'start_round_size' => 16, 'elimination_type' => 'single_elimination'],
            ],
        ],
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'selection_outcome' => TournamentParticipationRecord::SELECTION_REGISTERED,
        'final_result' => TournamentParticipationRecord::RESULT_WINNER,
        'round_label' => 'GF',
        'placement' => 1,
        'placement_min' => 1,
        'placement_max' => 1,
        'metadata' => [
            'stage_value' => 'bracket:0:1:single',
            'autofilled_from' => 'tournament_winners',
        ],
    ]);
    $record->teammates()->attach($originalTeammate);

    $this->actingAs($user)
        ->patchJson(route('users.participation.update', [$user, $record]), [
            'stage_value' => 'bracket:0:4:single',
            'placement_override' => 8,
            'team_name' => 'Editable Team',
            'memo' => 'Editable memo',
            'teammate_ids' => [$newTeammate->id],
        ])
        ->assertOk()
        ->assertJsonPath('type', 'success');

    $record->refresh();

    expect(data_get($record->metadata, 'stage_value'))->toBe('bracket:0:1:single')
        ->and($record->placement)->toBe(1)
        ->and($record->placement_min)->toBe(1)
        ->and($record->placement_max)->toBe(1)
        ->and($record->placement_override)->toBeNull()
        ->and($record->team_name)->toBe('Editable Team')
        ->and($record->memo)->toBe('Editable memo')
        ->and($record->teammates()->pluck('users.id')->all())->toBe([$originalTeammate->id]);
});

test('podium backed participation cannot be deleted by user', function () {
    $user = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'registration_start' => now()->subMonths(5),
        'registration_end' => now()->subMonths(4),
        'tournament_start' => now()->subMonths(3),
        'tournament_end' => now()->subMonths(2),
    ]);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
    ]);

    $this->actingAs($user)
        ->delete(route('users.participation.destroy', [$user, $record]))
        ->assertSessionHas('error');

    expect(TournamentParticipationRecord::query()->whereKey($record->id)->exists())->toBeTrue();
});
