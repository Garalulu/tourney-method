<?php

use App\Http\Requests\Admin\TournamentStoreRequest;
use App\Http\Requests\Admin\TournamentUpdateRequest;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('start_round_size_accepts_powers_of_2', function ($value) {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge(['start_round_size' => $value]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
})->with([2, 4, 8, 16, 32, 64, 128, 256, 512, 1024]);

test('start_round_size_rejects_non_powers_of_2', function ($value) {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge(['start_round_size' => $value]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('start_round_size'))->toBeTrue();
})->with([3, 5, 7, 15, 100, 1000, 2000]);

test('start_round_size_accepts_null', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge(['start_round_size' => null]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('restricted_countries_accepts_valid_iso_codes', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'restricted_countries' => ['KR', 'JP', 'US', 'GB'],
    ]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('restricted_countries_normalizes_lowercase_to_uppercase', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'restricted_countries' => ['kr', 'jp', 'us'],
    ]);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('restricted_countries'))->toBe(['KR', 'JP', 'US']);
});

test('restricted_countries_rejects_invalid_codes', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'restricted_countries' => ['USA', 'KOR', 'XYZ'], // 3+ characters
    ]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('restricted_countries.0'))->toBeTrue();
});

test('restricted_countries_rejects_unknown_two_letter_codes', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'restricted_countries' => ['KR', 'XX'],
    ]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('restricted_countries.1'))->toBeTrue();
});

test('restricted_countries_blank_hidden_value_normalizes_to_empty_array', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge(['restricted_countries' => '']);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('restricted_countries'))->toBe([]);
});

test('restricted_countries_null_normalizes_to_empty_array', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge(['restricted_countries' => null]);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('restricted_countries'))->toBe([]);
});

test('restricted_countries_accepts_empty_array', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge(['restricted_countries' => []]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('mania_variants_accepts_valid_values', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'mania_variants' => ['mania_4k', 'mania_7k', 'mania_other'],
        'modes' => ['osu'],
    ]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('mania_variants_rejects_invalid_values', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'mania_variants' => ['mania_5k', 'invalid'],
    ]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('mania_variants.0'))->toBeTrue();
});

test('mania_variants_accepts_null', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge(['mania_variants' => null]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('prepareForValidation_merges_mania_variants_into_modes', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'modes' => ['osu', 'mania'],
        'mania_variants' => ['mania_4k', 'mania_7k'],
    ]);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    $modes = $request->input('modes');

    // Should remove generic 'mania' and add enhanced variants
    expect($modes)->toContain(['mode' => 'osu', 'key_count' => null]);
    expect($modes)->toContain(['mode' => 'mania', 'key_count' => 4]);
    expect($modes)->toContain(['mode' => 'mania', 'key_count' => 7]);
    expect($modes)->not->toContain('mania');
});

test('prepareForValidation_converts_mania_4k_to_enhanced_format', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'modes' => [],
        'mania_variants' => ['mania_4k'],
    ]);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('modes'))->toBe([
        ['mode' => 'mania', 'key_count' => 4],
    ]);
});

test('prepareForValidation_converts_mania_7k_to_enhanced_format', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'modes' => [],
        'mania_variants' => ['mania_7k'],
    ]);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('modes'))->toBe([
        ['mode' => 'mania', 'key_count' => 7],
    ]);
});

test('prepareForValidation_converts_mania_other_to_enhanced_format', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'modes' => [],
        'mania_variants' => ['mania_other'],
    ]);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('modes'))->toBe([
        ['mode' => 'mania', 'key_count' => null],
    ]);
});

test('custom_error_messages_are_correct', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);

    $messages = $request->messages();

    expect($messages)->toHaveKey('start_round_size.regex');
    expect($messages)->toHaveKey('restricted_countries.*.regex');
    expect($messages)->toHaveKey('restricted_countries.*.size');
    expect($messages)->toHaveKey('mania_variants.*.in');

    expect($messages['start_round_size.regex'])->toContain('power of 2');
    expect($messages['restricted_countries.*.regex'])->toContain('CLDR territory codes');
    expect($messages['mania_variants.*.in'])->toContain('4K, 7K, or Other');
});

test('authorization_fails_for_non_admin_users', function () {
    $user = User::factory()->player()->create();

    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeFalse();
});

test('authorization_succeeds_for_admin_users', function () {
    $admin = User::factory()->admin()->create();

    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $admin);

    expect($request->authorize())->toBeTrue();
});

test('authorization_succeeds_for_master_users', function () {
    $master = User::factory()->master()->create();

    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $master);

    expect($request->authorize())->toBeTrue();
});

test('date_only_format_is_normalized_to_noon_for_tournament_dates', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'registration_start' => '2026-03-04',
        'tournament_end' => '2026-03-05',
        'bws_badge_age_cutoff' => '2026-03-06',
    ]);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('registration_start'))->toBe('2026-03-04 12:00:00');
    expect($request->input('tournament_end'))->toBe('2026-03-05 12:00:00');
    expect($request->input('bws_badge_age_cutoff'))->toBe('2026-03-06 00:00:00');
});

test('datetime_local_format_is_normalized_with_seconds', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'registration_start' => '2026-03-04T15:30',
        'tournament_end' => '2026-03-05T09:00',
    ]);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('registration_start'))->toBe('2026-03-04 15:30:00');
    expect($request->input('tournament_end'))->toBe('2026-03-05 09:00:00');
});

test('datetime_local_midnight_is_preserved_when_explicitly_submitted', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'registration_start' => '2026-03-04T00:00',
        'registration_end' => '2026-03-05T00:00',
        'tournament_start' => '2026-03-06T00:00',
        'tournament_end' => '2026-03-07T00:00',
        'bws_badge_age_cutoff' => '2026-03-08T00:00',
    ]);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('registration_start'))->toBe('2026-03-04 00:00:00');
    expect($request->input('registration_end'))->toBe('2026-03-05 00:00:00');
    expect($request->input('tournament_start'))->toBe('2026-03-06 00:00:00');
    expect($request->input('tournament_end'))->toBe('2026-03-07 00:00:00');
    expect($request->input('bws_badge_age_cutoff'))->toBe('2026-03-08 00:00:00');
});

test('date_fields_accept_null', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'registration_start' => null,
        'registration_end' => null,
        'tournament_start' => null,
        'tournament_end' => null,
    ]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('store_and_update_requests_accept_decimal_star_ratings', function (string $requestClass) {
    $request = $requestClass::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'title' => 'Decimal SR Tournament',
        'modes' => ['osu'],
        'star_rating_first' => 2.7,
        'star_rating_last' => 2.72,
        'star_rating_qualifier' => 3.14,
    ]);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
})->with([
    [TournamentUpdateRequest::class],
    [TournamentStoreRequest::class],
]);

test('store_request_uses_update_request_mania_validation_and_normalization', function () {
    $request = TournamentStoreRequest::create('/admin/tournaments', 'POST');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'title' => 'Create Mania Tournament',
        'modes' => ['osu', 'mania'],
        'mania_variants' => ['mania_4k', 'mania_7k'],
    ]);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
    expect($request->input('modes'))->toContain(['mode' => 'osu', 'key_count' => null]);
    expect($request->input('modes'))->toContain(['mode' => 'mania', 'key_count' => 4]);
    expect($request->input('modes'))->toContain(['mode' => 'mania', 'key_count' => 7]);
    expect($request->input('modes'))->not->toContain('mania');
});

test('prepareForValidation_normalizes_structured_format_fields_and_tags', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'team_formation_style' => 'world-cup',
        'format_structure' => [
            'stages' => [
                [
                    'type' => 'qualifier',
                    'advance_count' => '64',
                ],
                [
                    'type' => 'battle-royale',
                    'lobby_count' => '4',
                    'players_per_lobby' => '8',
                    'advance_per_lobby' => '4',
                ],
            ],
        ],
    ]);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('team_formation_style'))->toBe('world_cup');
    expect($request->input('format_tags'))->toBe(['world_cup', 'battle_royale']);
    expect($request->input('format_structure.stages.0.advance_count'))->toBe(64);
    expect($request->input('format_structure.stages.1.type'))->toBe('battle_royale');
    expect($request->input('format_structure.stages.1.lobby_count'))->toBe(4);
});

test('prepareForValidation_builds_bracket_stage_from_legacy_start_round_size', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'start_round_size' => 32,
    ]);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('format_structure.stages'))->toBe([[
        'type' => 'bracket',
        'start_round_size' => 32,
        'entry_type' => 'winner_only',
        'elimination_type' => 'double_elimination',
    ]]);
});

test('format_structure_accepts_hybrid_bracket_stage', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'format_structure' => [
            'stages' => [
                [
                    'type' => 'bracket',
                    'start_round_size' => 16,
                    'entry_type' => 'winner_loser_hybrid',
                    'elimination_type' => 'double_elimination',
                ],
            ],
        ],
    ]);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
    expect($request->input('start_round_size'))->toBe(16);
    expect($request->input('format_structure.stages.0.elimination_type'))->toBe('double_elimination');
    expect($request->input('format_structure.stages.0.entry_type'))->toBe('winner_loser_hybrid');
});

test('format_structure_accepts_swiss_round_stage', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'format_structure' => [
            'stages' => [
                [
                    'type' => 'swiss-round',
                    'round_count' => '5',
                    'advance_count' => '16',
                ],
            ],
        ],
    ]);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
    expect($request->input('format_structure.stages.0.type'))->toBe('swiss_round');
    expect($request->input('format_structure.stages.0.round_count'))->toBe(5);
    expect($request->input('format_structure.stages.0.advance_count'))->toBe(16);
});

test('format_structure_forces_single_elimination_brackets_to_winner_only', function () {
    $request = TournamentUpdateRequest::create('/admin/tournaments/1', 'PATCH');
    $request->setUserResolver(fn () => $this->admin);
    $request->merge([
        'format_structure' => [
            'stages' => [
                [
                    'type' => 'bracket',
                    'start_round_size' => 16,
                    'entry_type' => 'winner_loser_hybrid',
                    'elimination_type' => 'single_elimination',
                ],
            ],
        ],
    ]);

    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
    expect($request->input('format_structure.stages.0.elimination_type'))->toBe('single_elimination');
    expect($request->input('format_structure.stages.0.entry_type'))->toBe('winner_only');
});
