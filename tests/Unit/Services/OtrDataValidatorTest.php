<?php

use App\Services\OtrDataValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('validate passes valid tournament data', function () {
    $otrData = [
        'id' => 12345,
        'name' => 'Test Tournament',
        'forum_url' => 'https://osu.ppy.sh/community/forums/topics/123456',
        'rank_range_lower_bound' => 1000,
        'ruleset' => 0,
        'lobby_size' => 16,
        'start_time' => '2024-01-01T12:00:00Z',
        'end_time' => '2024-01-02T18:00:00Z',
    ];

    $service = new OtrDataValidator;
    $result = $service->validate($otrData);

    expect($result)->toBeEmpty(); // Should return empty array for valid data
});

test('validate fails with invalid forum URL', function () {
    $otrData = [
        'id' => 12345,
        'name' => 'Test Tournament',
        'forum_url' => 'clearly-not-a-url',
        'rank_range_lower_bound' => 1000,
        'ruleset' => 0,
        'lobby_size' => 16,
        'start_time' => '2024-01-01T12:00:00Z',
        'end_time' => '2024-01-02T18:00:00Z',
    ];

    $service = new OtrDataValidator;
    $result = $service->validate($otrData);

    expect($result)->not->toBeEmpty();
    expect($result)->toHaveKey('forum_url');
});

test('validate fails with invalid date range', function () {
    $otrData = [
        'id' => 12345,
        'name' => 'Test Tournament',
        'forum_url' => 'https://osu.ppy.sh/community/forums/topics/123456',
        'rank_range_lower_bound' => 1000,
        'ruleset' => 0,
        'lobby_size' => 16,
        'start_time' => '2024-01-02T12:00:00Z',
        'end_time' => '2024-01-01T18:00:00Z', // End before start
    ];

    $service = new OtrDataValidator;
    $result = $service->validate($otrData);

    expect($result)->not->toBeEmpty();
    expect($result)->toHaveKey('dates');
});

test('validate fails with invalid ruleset', function () {
    $otrData = [
        'id' => 12345,
        'name' => 'Test Tournament',
        'forum_url' => 'https://osu.ppy.sh/community/forums/topics/123456',
        'rank_range_lower_bound' => 1000,
        'ruleset' => 99, // Invalid ruleset
        'lobby_size' => 16,
        'start_time' => '2024-01-01T12:00:00Z',
        'end_time' => '2024-01-02T18:00:00Z',
    ];

    $service = new OtrDataValidator;
    $result = $service->validate($otrData);

    expect($result)->not->toBeEmpty();
    expect($result)->toHaveKey('ruleset');
});

test('validate passes with null forum URL', function () {
    $otrData = [
        'id' => 12345,
        'name' => 'Test Tournament',
        'forum_url' => null,
        'rank_range_lower_bound' => 1000,
        'ruleset' => 0,
        'lobby_size' => 16,
        'start_time' => '2024-01-01T12:00:00Z',
        'end_time' => '2024-01-02T18:00:00Z',
    ];

    $service = new OtrDataValidator;
    $result = $service->validate($otrData);

    expect($result)->toBeEmpty(); // Should accept null forum_url
});

test('extractForumTopicId correctly parses topic ID', function () {
    $service = new OtrDataValidator;

    // Valid URL with topic ID
    expect($service->extractForumTopicId('https://osu.ppy.sh/community/forums/topics/12345'))->toBe(12345);
    expect($service->extractForumTopicId('https://osu.ppy.sh/community/forums/topics/12345?n=1'))->toBe(12345);

    // Invalid URL
    expect($service->extractForumTopicId('invalid-url'))->toBeNull();
    expect($service->extractForumTopicId(null))->toBeNull();
});

test('validateRuleset correctly validates ruleset values', function () {
    $service = new OtrDataValidator;

    // Valid rulesets
    expect($service->validateRuleset(0))->toBeTrue();  // osu
    expect($service->validateRuleset(1))->toBeTrue();  // taiko
    expect($service->validateRuleset(2))->toBeTrue();  // catch
    expect($service->validateRuleset(3))->toBeTrue();  // mania
    expect($service->validateRuleset(4))->toBeTrue();  // mania 4k
    expect($service->validateRuleset(5))->toBeTrue();  // mania 7k

    // Invalid rulesets
    expect($service->validateRuleset(-1))->toBeFalse();
    expect($service->validateRuleset(6))->toBeFalse();
    expect($service->validateRuleset(99))->toBeFalse();
    expect($service->validateRuleset(null))->toBeFalse();
});
