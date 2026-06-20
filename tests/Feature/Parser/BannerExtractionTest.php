<?php

use App\Services\ForumParser;

beforeEach(function () {
    // Reset state before each test
});

test('parseBanner extracts from [imagemap] tag', function () {
    $parser = app(ForumParser::class);

    $bbcode = '[imagemap]
https://content.5teven.xyz/gfx/shs2/mainmenu.webp
77.8689 20.7353 20.2869 15.1471 https://docs.google.com/forms/d/e/1FAIpQLSdJ4dfX66Zzrql1lf9UXAAGPuWWE53oOuqoclzKWT6SO6-8xQ/viewform JOIN TOURNAMENT
[/imagemap]';

    $result = $parser->parseBanner($bbcode);
    expect($result)->toBe('https://content.5teven.xyz/gfx/shs2/mainmenu.webp');
});

test('parseBanner extracts from [img] tag', function () {
    $parser = app(ForumParser::class);

    $bbcode = '[img]https://github.com/calrsg/osu-tournament-assets/blob/main/the-oceanic-cup-2026/assets/the_oceanic_cup.png?raw=true[/img]';

    $result = $parser->parseBanner($bbcode);
    expect($result)->toBe('https://github.com/calrsg/osu-tournament-assets/blob/main/the-oceanic-cup-2026/assets/the_oceanic_cup.png?raw=true');
});

test('parseBanner returns null when no images found', function () {
    $parser = app(ForumParser::class);

    $bbcode = 'Tournament with no images here';

    $result = $parser->parseBanner($bbcode);
    expect($result)->toBeNull();
});

test('parseBanner extracts first image only', function () {
    $parser = app(ForumParser::class);

    $bbcode = '[img]https://first.com/banner1.png[/img]
Some content here
[img]https://second.com/banner2.png[/img]';

    $result = $parser->parseBanner($bbcode);
    expect($result)->toBe('https://first.com/banner1.png');
});

test('parseBanner handles malformed URLs gracefully', function () {
    $parser = app(ForumParser::class);

    $bbcode = '[img]not-a-valid-url[/img]';

    $result = $parser->parseBanner($bbcode);
    expect($result)->toBeNull();
});

test('parseBanner handles [image] tag alternative format', function () {
    $parser = app(ForumParser::class);

    $bbcode = '[image]https://example.com/banner.jpg[/image]';

    $result = $parser->parseBanner($bbcode);
    expect($result)->toBe('https://example.com/banner.jpg');
});

test('parseBanner prioritizes [imagemap] over other tags', function () {
    $parser = app(ForumParser::class);

    $bbcode = '[img]https://first.com/banner.png[/img]
[imagemap]
https://imagemap.com/banner.webp
coordinates here
[/imagemap]
[image]https://last.com/banner.jpg[/image]';

    $result = $parser->parseBanner($bbcode);
    expect($result)->toBe('https://imagemap.com/banner.webp');
});
