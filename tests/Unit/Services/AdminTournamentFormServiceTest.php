<?php

use App\Services\AdminTournamentFormService;

test('normalizes forum topic url into forum topic id', function () {
    $service = app(AdminTournamentFormService::class);

    $normalized = $service->normalizeForumLink([
        'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/1977529?n=1',
    ]);

    expect($normalized['forum_topic_id'])->toBe(1977529);
    expect($normalized['forum_post_url'])->toBeNull();
});

test('preserves wiki url while clearing forum topic id', function () {
    $service = app(AdminTournamentFormService::class);
    $wikiUrl = 'https://osu.ppy.sh/wiki/en/Tournaments/OWC/2024';

    $normalized = $service->normalizeForumLink([
        'forum_post_url' => $wikiUrl,
    ]);

    expect($normalized['forum_topic_id'])->toBeNull();
    expect($normalized['forum_post_url'])->toBe($wikiUrl);
});

test('manual field sources exclude virtual request fields', function () {
    $service = app(AdminTournamentFormService::class);

    $fieldSources = $service->manualFieldSources([
        'title' => 'Create Cup',
        'modes' => ['osu'],
        'mania_variants' => ['mania_4k'],
    ]);

    expect($fieldSources['title'])->toBe('manual');
    expect($fieldSources['modes'])->toBe('manual');
    expect(isset($fieldSources['mania_variants']))->toBeFalse();
});

test('create field sources ignore blank optional values and unchecked booleans', function () {
    $service = app(AdminTournamentFormService::class);

    $fieldSources = $service->manualFieldSources([
        'title' => 'pls enjoy tournament 2019',
        'modes' => ['osu'],
        'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/904607',
        'banner_url' => null,
        'discord_url' => '',
        'spreadsheet_url' => '',
        'rank_range_min' => null,
        'rank_range_max' => null,
        'registration_start' => null,
        'is_badge' => false,
        'is_bws' => '0',
    ]);

    expect($fieldSources['title'])->toBe('manual');
    expect($fieldSources['modes'])->toBe('manual');
    expect($fieldSources['forum_post_url'])->toBe('manual');
    expect($fieldSources)->not->toHaveKey('banner_url');
    expect($fieldSources)->not->toHaveKey('discord_url');
    expect($fieldSources)->not->toHaveKey('spreadsheet_url');
    expect($fieldSources)->not->toHaveKey('rank_range_min');
    expect($fieldSources)->not->toHaveKey('rank_range_max');
    expect($fieldSources)->not->toHaveKey('registration_start');
    expect($fieldSources)->not->toHaveKey('is_badge');
    expect($fieldSources)->not->toHaveKey('is_bws');
});

test('create field sources protect explicitly filled optional values', function () {
    $service = app(AdminTournamentFormService::class);

    $fieldSources = $service->manualFieldSources([
        'title' => 'Manual Cup',
        'modes' => ['osu'],
        'rank_range_min' => 1000,
        'discord_url' => 'https://discord.gg/manual',
        'is_badge' => true,
        'is_bws' => '1',
    ]);

    expect($fieldSources['rank_range_min'])->toBe('manual');
    expect($fieldSources['discord_url'])->toBe('manual');
    expect($fieldSources['is_badge'])->toBe('manual');
    expect($fieldSources['is_bws'])->toBe('manual');
});

test('update field sources still protect submitted blank values', function () {
    $service = app(AdminTournamentFormService::class);

    $fieldSources = $service->mergeManualFieldSources([], [
        'discord_url' => '',
        'is_badge' => false,
    ]);

    expect($fieldSources['discord_url'])->toBe('manual');
    expect($fieldSources['is_badge'])->toBe('manual');
});
