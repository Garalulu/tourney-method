<?php

use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\User;
use App\Services\TournamentCorrectionService;

test('correction diff omits unchanged values', function () {
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Original title',
        'description' => 'Original description',
        'rank_range_min' => 1000,
    ]);

    $payload = app(TournamentCorrectionService::class)->buildPayload($tournament, [
        'title' => 'Original title',
        'description' => 'Changed description',
        'rank_range_min' => 2000,
    ]);

    expect(array_keys($payload['changes']))->toBe(['metadata.rank_range_min'])
        ->and($payload['changes'])->not->toHaveKey('metadata.description');
});

test('correction diff treats submitted false boolean values as unchanged', function (string $status) {
    $tournament = Tournament::factory()->create([
        'status' => $status,
        'title' => 'Original title',
        'is_badge' => false,
        'is_bws' => false,
    ]);

    $payload = app(TournamentCorrectionService::class)->buildPayload($tournament, [
        'title' => 'Corrected title',
        'is_badge' => '0',
        'is_bws' => '0',
    ]);

    expect($payload['changes'])->toHaveKey('metadata.title');
    expect(array_key_exists('metadata.is_badge', $payload['changes']))->toBeFalse();
    expect(array_key_exists('metadata.is_bws', $payload['changes']))->toBeFalse();
})->with([
    'approved tournament' => Tournament::STATUS_APPROVED,
    'pending tournament' => Tournament::STATUS_PENDING,
]);

test('correction diff treats model-cast numeric metadata as unchanged', function (string $field, mixed $stored, string $submitted) {
    $tournament = Tournament::factory()->approved()->create([
        $field => $stored,
    ]);

    $payload = app(TournamentCorrectionService::class)->buildPayload($tournament, [
        $field => $submitted,
    ]);

    expect($payload['changes'])->not->toHaveKey("metadata.{$field}");
})->with([
    'versus size' => ['vs_size', 4, '4'],
    'minimum team size' => ['team_size_min', 4, '4'],
    'maximum team size' => ['team_size_max', 10, '10'],
    'minimum rank' => ['rank_range_min', 1000, '1000'],
    'maximum rank' => ['rank_range_max', 99999, '99999'],
    'start round size' => ['start_round_size', 16, '16'],
    'first round star rating' => ['star_rating_first', 6.6, '6.60'],
    'last round star rating' => ['star_rating_last', 7.4, '7.400'],
    'qualifier star rating' => ['star_rating_qualifier', 6.9, '6.90'],
    'BWS base exponent' => ['bws_base_exponent', 0.9937, '0.99370'],
    'BWS badge power' => ['bws_badge_power', 2, '2.0'],
    'BWS divisor' => ['bws_divisor', 1, '1.0000'],
]);

test('correction diff keeps genuinely changed numeric metadata', function () {
    $tournament = Tournament::factory()->approved()->create([
        'vs_size' => 4,
        'star_rating_first' => 6.6,
    ]);

    $payload = app(TournamentCorrectionService::class)->buildPayload($tournament, [
        'vs_size' => '5',
        'star_rating_first' => '6.7',
    ]);

    expect($payload['changes'])
        ->toHaveKeys(['metadata.vs_size', 'metadata.star_rating_first'])
        ->and($payload['changes']['metadata.vs_size']['new'])->toBe(5)
        ->and($payload['changes']['metadata.star_rating_first']['new'])->toBe('6.7');
});

test('legacy format cleanup is hidden and automatically accepted with visible changes', function () {
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

    $payload = app(TournamentCorrectionService::class)->buildPayload($tournament, [
        'title' => 'Corrected title',
        'format_structure' => [
            'stages' => [[
                'type' => 'bracket',
                'entry_type' => 'winner_only',
                'elimination_type' => 'double_elimination',
                'start_round_size' => 16,
            ]],
        ],
    ]);

    expect($payload['changes']['metadata.format_structure'])
        ->toMatchArray([
            'visible' => false,
            'auto_accept' => true,
        ])
        ->and($payload['changes']['metadata.format_structure']['apply'])
        ->not->toHaveKey('legacy_format')
        ->and($payload['changes']['metadata.format_structure']['apply']['stages'][0])
        ->not->toHaveKey('legacy_format');
});

test('genuine format changes remain visible without legacy format metadata', function () {
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [[
                'type' => 'qualifier',
                'advance_count' => 16,
                'legacy_format' => 'Qualifier',
            ]],
            'legacy_format' => 'Double Elimination',
        ],
    ]);

    $payload = app(TournamentCorrectionService::class)->buildPayload($tournament, [
        'format_structure' => [
            'stages' => [[
                'type' => 'qualifier',
                'advance_count' => 128,
            ]],
        ],
    ]);

    $change = $payload['changes']['metadata.format_structure'];

    expect($change)->not->toHaveKey('visible')
        ->and($change['old'])->toBe(['stages' => [['advance_count' => 16, 'type' => 'qualifier']]])
        ->and($change['new'])->toBe(['stages' => [['advance_count' => 128, 'type' => 'qualifier']]])
        ->and($change['apply'])->toBe(['stages' => [['type' => 'qualifier', 'advance_count' => 128]]]);
});

test('historical correction changes hide equivalent cast metadata and sanitize format structure', function () {
    $correction = TournamentCorrection::factory()->create([
        'payload' => [
            'changes' => [
                'metadata.vs_size' => [
                    'domain' => 'metadata',
                    'field' => 'vs_size',
                    'old' => 4,
                    'new' => '4',
                    'apply' => 4,
                ],
                'metadata.rank_range_min' => [
                    'domain' => 'metadata',
                    'field' => 'rank_range_min',
                    'old' => 1000,
                    'new' => '1000',
                    'apply' => 1000,
                ],
                'metadata.format_structure' => [
                    'domain' => 'metadata',
                    'field' => 'format_structure',
                    'old' => [
                        'stages' => [[
                            'type' => 'qualifier',
                            'advance_count' => 16,
                            'legacy_format' => 'Qualifier',
                        ]],
                        'legacy_format' => 'Double Elimination',
                    ],
                    'new' => [
                        'stages' => [[
                            'type' => 'qualifier',
                            'advance_count' => 128,
                        ]],
                    ],
                    'apply' => [
                        'stages' => [[
                            'type' => 'qualifier',
                            'advance_count' => 128,
                        ]],
                    ],
                ],
            ],
        ],
    ]);

    $changes = app(TournamentCorrectionService::class)->changes($correction);

    expect($changes)
        ->not->toHaveKeys(['metadata.vs_size', 'metadata.rank_range_min'])
        ->toHaveKey('metadata.format_structure')
        ->and($changes['metadata.format_structure']['old'])
        ->toBe(['stages' => [['advance_count' => 16, 'type' => 'qualifier']]])
        ->and($changes['metadata.format_structure']['new'])
        ->toBe(['stages' => [['advance_count' => 128, 'type' => 'qualifier']]]);
});

test('correction diff treats nested associative array key order as unchanged', function () {
    $tournament = Tournament::factory()->approved()->create([
        'format_structure' => [
            'stages' => [
                [
                    'type' => 'qualifier',
                    'advance_count' => 32,
                ],
                [
                    'type' => 'swiss_round',
                    'round_count' => 5,
                    'advance_count' => 8,
                ],
                [
                    'type' => 'bracket',
                    'entry_type' => 'winner_only',
                    'elimination_type' => 'double_elimination',
                    'start_round_size' => 8,
                ],
            ],
        ],
    ]);

    $payload = app(TournamentCorrectionService::class)->buildPayload($tournament, [
        'format_structure' => [
            'stages' => [
                [
                    'advance_count' => 32,
                    'type' => 'qualifier',
                ],
                [
                    'advance_count' => 8,
                    'type' => 'swiss_round',
                    'round_count' => 5,
                ],
                [
                    'start_round_size' => 8,
                    'elimination_type' => 'double_elimination',
                    'entry_type' => 'winner_only',
                    'type' => 'bracket',
                ],
            ],
        ],
    ]);

    expect($payload['changes'])->not->toHaveKey('metadata.format_structure');
});

test('correction changes are ordered by correction form category order', function () {
    $correction = TournamentCorrection::factory()->create([
        'payload' => [
            'changes' => [
                'metadata.discord_url' => ['domain' => 'metadata', 'field' => 'discord_url', 'label' => 'Discord URL'],
                'metadata.is_bws' => ['domain' => 'metadata', 'field' => 'is_bws', 'label' => 'BWS Ranking'],
                'podium.add.1.winner' => ['domain' => 'podium', 'field' => '1', 'label' => 'Add Podium'],
                'staff.add.organizer.host' => ['domain' => 'staff', 'field' => 'organizer', 'label' => 'Add Staff'],
                'metadata.rank_range_min' => ['domain' => 'metadata', 'field' => 'rank_range_min', 'label' => 'Rank Min'],
                'metadata.format_structure' => ['domain' => 'metadata', 'field' => 'format_structure', 'label' => 'Format Structure'],
                'metadata.banner_url' => ['domain' => 'metadata', 'field' => 'banner_url', 'label' => 'Banner URL'],
            ],
        ],
    ]);

    expect(array_keys(app(TournamentCorrectionService::class)->orderedChanges($correction)))->toBe([
        'metadata.banner_url',
        'metadata.format_structure',
        'metadata.rank_range_min',
        'staff.add.organizer.host',
        'podium.add.1.winner',
        'metadata.is_bws',
        'metadata.discord_url',
    ]);
});

test('derived correction changes are hidden from visible ordered changes', function () {
    $correction = TournamentCorrection::factory()->create([
        'payload' => [
            'changes' => [
                'metadata.team_formation_style' => [
                    'domain' => 'metadata',
                    'field' => 'team_formation_style',
                    'label' => 'Team Formation Style',
                    'old' => 'standard',
                    'new' => 'draft',
                ],
                'metadata.format_tags' => [
                    'domain' => 'metadata',
                    'field' => 'format_tags',
                    'label' => 'Format Tags',
                    'old' => [],
                    'new' => ['draft'],
                    'visible' => false,
                ],
            ],
        ],
    ]);

    $service = app(TournamentCorrectionService::class);

    expect($service->visibleChanges($correction))->toHaveKey('metadata.team_formation_style')
        ->and($service->visibleChanges($correction))->not->toHaveKey('metadata.format_tags')
        ->and($service->orderedChanges($correction))->not->toHaveKey('metadata.format_tags')
        ->and($service->groupedOrderedChanges($correction)->get('format_progression')?->has('metadata.format_tags'))->not->toBeTrue();
});

test('accepted visible source applies hidden derived correction changes', function () {
    $admin = User::factory()->admin()->create();
    $submitter = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'team_formation_style' => Tournament::TEAM_FORMATION_STANDARD,
        'format_tags' => [],
    ]);
    $service = app(TournamentCorrectionService::class);

    $correction = $service->createCorrection($tournament, $submitter, [
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'format_tags' => [Tournament::FORMAT_TAG_DRAFT],
    ]);

    $service->review($correction, $admin, ['metadata.team_formation_style']);

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->team_formation_style)->toBe(Tournament::TEAM_FORMATION_DRAFT)
        ->and($tournament->format_tags)->toBe([Tournament::FORMAT_TAG_DRAFT])
        ->and(data_get($correction->admin_decisions, 'accepted'))->toContain('metadata.team_formation_style', 'metadata.format_tags')
        ->and($correction->status)->toBe(TournamentCorrection::STATUS_APPROVED);
});

test('rejected visible source does not apply hidden derived correction changes', function () {
    $admin = User::factory()->admin()->create();
    $submitter = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create([
        'title' => 'Original title',
        'team_formation_style' => Tournament::TEAM_FORMATION_STANDARD,
        'format_tags' => [],
    ]);
    $service = app(TournamentCorrectionService::class);

    $correction = $service->createCorrection($tournament, $submitter, [
        'title' => 'Corrected title',
        'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
        'format_tags' => [Tournament::FORMAT_TAG_DRAFT],
    ]);

    $service->review($correction, $admin, ['metadata.title']);

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->title)->toBe('Corrected title')
        ->and($tournament->team_formation_style)->toBe(Tournament::TEAM_FORMATION_STANDARD)
        ->and($tournament->format_tags)->toBe([])
        ->and(data_get($correction->admin_decisions, 'accepted'))->toContain('metadata.title')
        ->and(data_get($correction->admin_decisions, 'accepted'))->not->toContain('metadata.format_tags')
        ->and($correction->status)->toBe(TournamentCorrection::STATUS_PARTIALLY_APPROVED);
});

test('correction apply runs atomically and records audit details', function () {
    $admin = User::factory()->admin()->create();
    $submitter = User::factory()->withSetup()->create();
    $tournament = Tournament::factory()->approved()->create(['title' => 'Original title']);
    $service = app(TournamentCorrectionService::class);

    $correction = $service->createCorrection($tournament, $submitter, [
        'title' => 'Corrected title',
    ]);

    $service->review($correction, $admin, ['metadata.title'], 'Looks right');

    $tournament->refresh();
    $correction->refresh();

    expect($tournament->title)->toBe('Corrected title')
        ->and($correction->status)->toBe(TournamentCorrection::STATUS_APPROVED)
        ->and($correction->review_note)->toBe('Looks right')
        ->and(AdminAuditLog::query()->where('action', 'tournament.correction_reviewed')->exists())->toBeTrue();
});
