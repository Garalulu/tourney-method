<?php

use App\Models\Tournament;
use App\Services\BbcodeParser;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    Cache::flush();
});

test('parseHost extracts username from content', function () {
    $content = 'Host: @peppy';

    $parser = app(ForumParser::class);
    $result = $parser->parseHost($content);

    expect($result['host_username'])->toBe('peppy');
});

test('parseHost handles hosted by pattern', function () {
    $content = 'Hosted by @peppy';

    $parser = app(ForumParser::class);
    $result = $parser->parseHost($content);

    expect($result['host_username'])->toBe('peppy');
});

test('parseHost returns null when no host found', function () {
    $content = 'No details about hosting';

    $parser = app(ForumParser::class);
    $result = $parser->parseHost($content);

    expect($result['host_username'])->toBeNull();
});

test('parseModes finds osu mode', function () {
    $content = 'Modes: osu!';

    $parser = app(ForumParser::class);
    $result = $parser->parseModes($content);

    expect($result['modes'])->toBe([['mode' => 'osu', 'key_count' => null]]);
});

test('parseModes finds multiple modes', function () {
    $content = 'Modes: osu!, taiko, catch, mania';

    $parser = app(ForumParser::class);
    $result = $parser->parseModes($content);

    expect($result['modes'])->toContain(
        ['mode' => 'osu', 'key_count' => null],
        ['mode' => 'taiko', 'key_count' => null],
        ['mode' => 'catch', 'key_count' => null],
        ['mode' => 'mania', 'key_count' => null],
    );
});

test('parseBadgeDetection finds badge keyword', function () {
    $content = 'This tournament offers a badge to winners!';

    $parser = app(ForumParser::class);
    $result = $parser->parseBadgeDetection($content);

    expect($result['is_badge'])->toBeTrue();
});

test('parseBadgeDetection finds tcomm link', function () {
    $content = 'Check out https://tcomm.hivie.tn/tournament/123 for details';

    $parser = app(ForumParser::class);
    $result = $parser->parseBadgeDetection($content);

    expect($result['is_badge'])->toBeTrue();
    expect($result['tcomm_url'])->toBe('https://tcomm.hivie.tn/tournament/123');
});

test('parseBadgeDetection finds PIF link', function () {
    $content = 'Discussion: https://osu.ppy.sh/community/forums/topics/123456';

    $parser = app(ForumParser::class);
    $result = $parser->parseBadgeDetection($content);

    expect($result['is_badge'])->toBeTrue();
});

test('parseBadgeDetection returns false for non-badge tournaments', function () {
    $content = 'Regular tournament without badges or special links';

    $parser = app(ForumParser::class);
    $result = $parser->parseBadgeDetection($content);

    expect($result['is_badge'])->toBeFalse();
});

test('parseRankRange handles K format', function () {
    $content = 'Rank Range: 1K-10K';

    $parser = app(ForumParser::class);
    $result = $parser->parseRankRange($content);

    expect($result['rank_range_min'])->toBe(1000);
    expect($result['rank_range_max'])->toBe(10000);
});

test('parseRankRange handles #X-#Y format', function () {
    $content = 'Rank #5000-#15000';

    $parser = app(ForumParser::class);
    $result = $parser->parseRankRange($content);

    expect($result['rank_range_min'])->toBe(5000);
    expect($result['rank_range_max'])->toBe(15000);
});

test('parseRankRange handles "under X" format', function () {
    $content = 'Rank under 50000';

    $parser = app(ForumParser::class);
    $result = $parser->parseRankRange($content);

    expect($result['rank_range_min'])->toBeNull();
    expect($result['rank_range_max'])->toBe(50000);
});

test('parseRankRange handles "above Y" format', function () {
    $content = 'Rank above 1000';

    $parser = app(ForumParser::class);
    $result = $parser->parseRankRange($content);

    expect($result['rank_range_min'])->toBe(1000);
    expect($result['rank_range_max'])->toBeNull();
});

test('parseRankRange returns null when not found', function () {
    $content = 'No rank information here';

    $parser = app(ForumParser::class);
    $result = $parser->parseRankRange($content);

    expect($result['rank_range_min'])->toBeNull();
    expect($result['rank_range_max'])->toBeNull();
});

test('parseRankRange treats Open Rank as explicit null bounds', function () {
    $content = 'Open Rank tournament. Ignore this older-looking range: Rank 1K-10K.';

    $parser = app(ForumParser::class);
    $result = $parser->parseRankRange($content);

    expect($result['rank_range_is_open'])->toBeTrue();
    expect($result['rank_range_min'])->toBeNull();
    expect($result['rank_range_max'])->toBeNull();
});

test('parseRankRange handles word to separator without matching separator letters', function () {
    $content = 'Rank 1K to 10K';

    $parser = app(ForumParser::class);
    $result = $parser->parseRankRange($content);

    expect($result['rank_range_min'])->toBe(1000);
    expect($result['rank_range_max'])->toBe(10000);
});

test('parseTeamFormationStyle detects first mentioned style', function () {
    $parser = app(ForumParser::class);

    expect($parser->parseTeamFormationStyle('World Cup teams')['team_formation_style'])
        ->toBe(Tournament::TEAM_FORMATION_WORLD_CUP);
    expect($parser->parseTeamFormationStyle('Suiji style event')['team_formation_style'])
        ->toBe(Tournament::TEAM_FORMATION_SUIJI);
    expect($parser->parseTeamFormationStyle('Draft tournament with auction notes')['team_formation_style'])
        ->toBe(Tournament::TEAM_FORMATION_DRAFT);
    expect($parser->parseTeamFormationStyle('Auction tournament with draft notes')['team_formation_style'])
        ->toBe(Tournament::TEAM_FORMATION_AUCTION);
    expect($parser->parseTeamFormationStyle('Regular tournament')['team_formation_style'])
        ->toBe(Tournament::TEAM_FORMATION_STANDARD);
});

test('parseLinks finds Discord URL', function () {
    $content = 'Join our Discord: https://discord.gg/tourneymethod';

    $parser = app(ForumParser::class);
    $result = $parser->parseLinks($content);

    expect($result['discord_url'])->toBe('https://discord.gg/tourneymethod');
});

test('parseLinks finds spreadsheet URL', function () {
    $content = 'Spreadsheet: https://docs.google.com/spreadsheets/d/abc123';

    $parser = app(ForumParser::class);
    $result = $parser->parseLinks($content);

    expect($result['spreadsheet_url'])->toStartWith('https://docs.google.com');
});

test('parseLinks finds registration form URL', function () {
    $content = 'Register here: https://forms.gle/xyz789';

    $parser = app(ForumParser::class);
    $result = $parser->parseLinks($content);

    expect($result['registration_url'])->toBe('https://forms.gle/xyz789');
});

test('parseLinksFromBbcode extracts tournament links from imagemap', function () {
    $bbcode = <<<'BBCODE'
[imagemap]
https://content.5teven.xyz/gfx/6wc/area_intro_top.png
73.2582 13.4765 26.7418 9.7547 https://forms.gle/dhZ2X1gf4fKbv1od6 SIGN UP TO TOURNEY
73.0533 22.2069 26.7418 9.8134 https://docs.google.com/spreadsheets/d/1-TX1ykmECyrxFKbNDCI9Z60-hSZ0shG99hxuVnvay2k/edit?gid=0#gid=0 VIEW SHEET
73.0533 31.0613 26.9467 9.6008 https://docs.google.com/document/d/1YrbcI-TuG9tPkQbDxIV-NyTxkGxZ9V6WkqY6qF8ZmBY/edit?tab=t.0 READ THE RULES
72.9508 39.9289 27.0492 9.7381 https://www.twitch.tv/6wc_osu SEE TWITCH STREAM
72.9508 48.8586 27.0492 9.4502 https://discord.gg/VQhYSKucNn JOIN THE DISCORD
73.3607 75.0818 26.6393 10.1212 https://challonge.com/6WC26 CHECK CHALLONGE
[/imagemap]
BBCODE;

    $parser = app(ForumParser::class);
    $result = $parser->parseLinksFromBbcode($bbcode);

    expect($result['registration_url'])->toBe('https://forms.gle/dhZ2X1gf4fKbv1od6');
    expect($result['spreadsheet_url'])->toStartWith('https://docs.google.com/spreadsheets');
    expect($result['spreadsheet_url'])->not->toContain('document/d');
    expect($result['twitch_url'])->toBe('https://www.twitch.tv/6wc_osu');
    expect($result['discord_url'])->toBe('https://discord.gg/VQhYSKucNn');
    expect($result['bracket_url'])->toBe('https://challonge.com/6WC26');
});

test('parseLinksFromBbcode includes braacket but excludes osu community tournament pages as bracket urls', function () {
    $bbcode = '[url=https://osu.ppy.sh/community/tournaments/123]osu tournament page[/url] '
        .'[url=https://braacket.com/league/example/ranking]Braacket[/url]';

    $parser = app(ForumParser::class);
    $result = $parser->parseLinksFromBbcode($bbcode);

    expect($result['bracket_url'])->toBe('https://braacket.com/league/example/ranking');
});

test('parseDates handles DD/MM/YYYY format', function () {
    $content = 'Registration: 15/01/2024 - 30/01/2024';

    $parser = app(ForumParser::class);
    $result = $parser->parseDates($content);

    expect($result['registration_start'])->not->toBeNull();
    expect($result['registration_end'])->not->toBeNull();
});

test('parseDates handles Month DD, YYYY format', function () {
    $content = 'Tournament: February 1, 2024 - February 28, 2024';

    $parser = app(ForumParser::class);
    $result = $parser->parseDates($content);

    expect($result['tournament_start'])->not->toBeNull();
    expect($result['tournament_end'])->not->toBeNull();
});

test('parseDates infers ordinal schedule dates from title year', function () {
    $content = '[list][*][b]REGISTRATION[/b]: May 12th - June 9th'."\n"
        .'[*][b]QUALIFIERS[/b]: June 22nd - June 23rd'."\n"
        .'[*][b]ROUND OF 32[/b]: June 29th - June 30th'."\n"
        .'[*][b]GRAND FINALS[/b]: August 3rd - August 4th[/list]';

    $parser = app(ForumParser::class);
    $result = $parser->parseDates($content, 'pls enjoy tournament 2019');

    expect(Carbon\Carbon::parse($result['registration_start'])->format('Y-m-d'))->toBe('2019-05-12');
    expect(Carbon\Carbon::parse($result['registration_end'])->format('Y-m-d'))->toBe('2019-06-09');
    expect(Carbon\Carbon::parse($result['tournament_start'])->format('Y-m-d'))->toBe('2019-06-22');
    expect(Carbon\Carbon::parse($result['tournament_end'])->format('Y-m-d'))->toBe('2019-08-04');
});

test('parseTournamentStructure extracts vs size and team size', function () {
    $content = 'This is a 2v2 Last Man Standing tournament. Players will sign up in teams of 3 - 4 players.';

    $parser = app(ForumParser::class);
    $result = $parser->parseTournamentStructure($content);

    expect($result['vs_size'])->toBe(2);
    expect($result['team_size_min'])->toBe(3);
    expect($result['team_size_max'])->toBe(4);
});

test('parseBanner extracts first imagemap url', function () {
    $bbcode = "[imagemap]\nhttps://content.5teven.xyz/gfx/6wc/area_intro_top.png\n"
        ."73.2582 13.4765 26.7418 9.7547 https://forms.gle/dhZ2X1gf4fKbv1od6 SIGN UP\n[/imagemap]";

    $parser = app(ForumParser::class);

    expect($parser->parseBanner($bbcode))->toBe('https://content.5teven.xyz/gfx/6wc/area_intro_top.png');
});

test('parseBws detects BWS ranking text', function () {
    $parser = app(ForumParser::class);

    expect($parser->parseBws('Rank range is 1,000 - 10,000 BWS')['is_bws'])->toBeTrue();
    expect($parser->parseBws('Rank range is 1,000 - 10,000')['is_bws'])->toBeFalse();
});

test('parseForumTopicData extracts topic 904607 style metadata', function () {
    $bbcode = '[centre][img]https://imgur-archive.ppy.sh/I9nDyAj.png[/img][/centre]
[notice][centre][b][url=https://forms.gle/gtfYG7Weeb25D6KY7]registrations[/url] | [url=https://discord.gg/cjUkraA]discord[/url] | [url=https://docs.google.com/spreadsheets/d/1U9NMyAA3zJqAIrb0_jKfxXWI35A2YP2kho6cr7rx58U/edit#gid=0]spreadsheet[/url] | [url=https://challonge.com/PET_2019_Upper]challonge 1k-10k[/url][/b][/centre]
[centre][b]pls enjoy tournament[/b] is a [b]2v2 Last Man Standing[/b] tournament.[/centre][/notice]
[list][*]The rank range for the [b]upper division[/b] is [color=#FF0000]1,000 - 10,000 BWS[/color].
[*]To participate, players will sign up in teams of [color=#FF0000]3 - 4 players[/color].[/list]
[notice][list][*][b]REGISTRATION[/b]: May 12th - June 9th
[*][b]QUALIFIERS[/b]: June 22nd - June 23rd
[*][b]GRAND FINALS[/b]: August 3rd - August 4th[/list][/notice]
[notice][centre][b][u]PENDING[/u] Profile Badge[/b][/centre][/notice]';

    $parser = app(ForumParser::class);
    $result = $parser->parseForumTopicData(904607, [
        'topic' => [
            'id' => 904607,
            'title' => '[osu!std] pls enjoy tournament 2019 [1k-10k | 10k-50k Modified BWS] [LMS]',
            'user_id' => 3545323,
            'created_at' => '2019-05-12T00:00:00+00:00',
        ],
        'posts' => [
            ['body' => ['raw' => $bbcode]],
        ],
    ], $bbcode, false);

    expect($result['is_badge'])->toBeTrue();
    expect($result['is_bws'])->toBeTrue();
    expect($result['vs_size'])->toBe(2);
    expect($result['team_size_min'])->toBe(3);
    expect($result['team_size_max'])->toBe(4);
    expect($result['rank_range_min'])->toBe(1000);
    expect($result['rank_range_max'])->toBe(10000);
    expect($result['discord_url'])->toBe('https://discord.gg/cjUkraA');
    expect($result['spreadsheet_url'])->toStartWith('https://docs.google.com/spreadsheets');
    expect($result['bracket_url'])->toBe('https://challonge.com/PET_2019_Upper');
    expect($result['registration_url'])->toBe('https://forms.gle/gtfYG7Weeb25D6KY7');
    expect(Carbon\Carbon::parse($result['registration_start'])->format('Y-m-d'))->toBe('2019-05-12');
    expect(Carbon\Carbon::parse($result['tournament_end'])->format('Y-m-d'))->toBe('2019-08-04');
});

test('stores raw bbcode in description field', function () {
    $bbcode = '[b]Tournament Info[/b][list][*]Date: Feb 15[*]Rank: 1K-10K[/list] Modes: osu! Tournament registration';

    $parser = app(ForumParser::class);

    // Mock fetchForumTopic to return a valid tournament structure
    $topicData = [
        'topic' => [
            'id' => 123,
            'title' => 'Test Tournament',
            'user_id' => 456,
        ],
        'posts' => [
            ['body' => ['raw' => $bbcode, 'html' => '<p>test</p>']],
        ],
    ];

    // Use reflection to call the private fetchForumTopic method with our mock data
    $reflection = new ReflectionClass($parser);
    $method = $reflection->getMethod('fetchForumTopic');
    $method->setAccessible(true);

    // We'll mock the OsuApiService instead
    $osuApiMock = Mockery::mock(OsuApiService::class);
    $osuApiMock->shouldReceive('getForumTopic')->with(123)->andReturn($topicData);
    app()->instance(OsuApiService::class, $osuApiMock);

    $result = $parser->parseForumTopic(123, $bbcode);

    expect($result)->not->toBeNull();
    expect($result['description'])->toBe($bbcode);
});

test('uses bbcode parser for link extraction', function () {
    $bbcode = '[url=https://discord.gg/test]Join Discord[/url] and [url=https://twitch.tv/stream]Watch[/url] Modes: osu! Tournament registration open';

    $parser = app(ForumParser::class);

    // Mock fetchForumTopic to return a valid tournament structure
    $topicData = [
        'topic' => [
            'id' => 123,
            'title' => 'Test Tournament',
            'user_id' => 456,
        ],
        'posts' => [
            ['body' => ['raw' => $bbcode, 'html' => '<p>test</p>']],
        ],
    ];

    // Mock the OsuApiService
    $osuApiMock = Mockery::mock(OsuApiService::class);
    $osuApiMock->shouldReceive('getForumTopic')->with(123)->andReturn($topicData);
    app()->instance(OsuApiService::class, $osuApiMock);

    $result = $parser->parseForumTopic(123, $bbcode);

    expect($result)->not->toBeNull();
    expect($result['discord_url'])->toBe('https://discord.gg/test');
    expect($result['twitch_url'])->toBe('https://twitch.tv/stream');
});

test('converts bbcode to markdown for display', function () {
    $bbcode = '[b]Bold[/b] and [i]italic[/i]';

    $bbcodeParser = app(BbcodeParser::class);
    $markdown = $bbcodeParser->toMarkdown($bbcode);

    expect($markdown)->toContain('**Bold**');
    expect($markdown)->toContain('*italic*');
});

// ============================================================================
// Phase 7: Host Parsing from osu! API Tests
// ============================================================================

test('parseHostFromTopic extracts user_id from forum topic', function () {
    $topicData = [
        'topic' => [
            'id' => 123,
            'title' => 'Test Tournament',
            'user_id' => 456789,
        ],
        'posts' => [
            ['body' => ['raw' => 'Content here Modes: osu! Tournament', 'html' => '<p>Content</p>']],
        ],
    ];

    $osuApiMock = Mockery::mock(OsuApiService::class);
    $osuApiMock->shouldReceive('getForumTopic')->with(123)->andReturn($topicData);
    $osuApiMock->shouldReceive('getUser')->with(456789)->andReturn([
        'id' => 456789,
        'username' => 'testuser',
    ]);
    app()->instance(OsuApiService::class, $osuApiMock);

    $parser = app(ForumParser::class);
    $result = $parser->parseForumTopic(123);

    expect($result)->not->toBeNull();
    expect($result['host_osu_id'])->toBe(456789);
    // host_username is no longer fetched by ForumParser
    // It will be populated by BatchMergeStaffJob from staff organizer role
    expect($result['host_username'])->toBeNull();
});

test('parseHostFromTopic handles missing user_id gracefully', function () {
    $topicData = [
        'topic' => [
            'id' => 123,
            'title' => 'Test Tournament',
            // No user_id
        ],
        'posts' => [
            ['body' => ['raw' => 'Content here Modes: osu! Tournament', 'html' => '<p>Content</p>']],
        ],
    ];

    $osuApiMock = Mockery::mock(OsuApiService::class);
    $osuApiMock->shouldReceive('getForumTopic')->with(123)->andReturn($topicData);
    app()->instance(OsuApiService::class, $osuApiMock);

    $parser = app(ForumParser::class);
    $result = $parser->parseForumTopic(123);

    expect($result)->not->toBeNull();
    expect($result['host_osu_id'])->toBeNull();
    expect($result['host_username'])->toBeNull();
});

test('parseHostFromTopic handles osu! API 404 error', function () {
    $topicData = [
        'topic' => [
            'id' => 123,
            'title' => 'Test Tournament',
            'user_id' => 999999,
        ],
        'posts' => [
            ['body' => ['raw' => 'Content here Modes: osu! Tournament', 'html' => '<p>Content</p>']],
        ],
    ];

    $osuApiMock = Mockery::mock(OsuApiService::class);
    $osuApiMock->shouldReceive('getForumTopic')->with(123)->andReturn($topicData);
    $osuApiMock->shouldReceive('getUser')->with(999999)->andThrow(new Exception('User not found'));
    app()->instance(OsuApiService::class, $osuApiMock);

    $parser = app(ForumParser::class);
    $result = $parser->parseForumTopic(123);

    expect($result)->not->toBeNull();
    expect($result['host_osu_id'])->toBe(999999);
    expect($result['host_username'])->toBeNull();
});

test('parseHostFromTopic handles osu! API 500 error', function () {
    $topicData = [
        'topic' => [
            'id' => 123,
            'title' => 'Test Tournament',
            'user_id' => 456789,
        ],
        'posts' => [
            ['body' => ['raw' => 'Content here Modes: osu! Tournament', 'html' => '<p>Content</p>']],
        ],
    ];

    $osuApiMock = Mockery::mock(OsuApiService::class);
    $osuApiMock->shouldReceive('getForumTopic')->with(123)->andReturn($topicData);
    $osuApiMock->shouldReceive('getUser')->with(456789)->andThrow(new Exception('API server error'));
    app()->instance(OsuApiService::class, $osuApiMock);

    $parser = app(ForumParser::class);
    $result = $parser->parseForumTopic(123);

    expect($result)->not->toBeNull();
    expect($result['host_osu_id'])->toBe(456789);
    expect($result['host_username'])->toBeNull();
});

// ============================================================================
// Phase 8: Staff Parsing from BBcode Tests
// ============================================================================

test('parseStaffFromBBcode extracts staff with REAL BBcode format 1 (5WC2026 style)', function () {
    // Real format from tournament ID 62
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/11292327]Convex[/url] | [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/7113149]Gigi Murin[/url]
[b][color=#cf68dd]Mappooler:[/color][/b] [img]https://osuflags.omkserver.nl/MX-20.png[/img] [url=https://osu.ppy.sh/users/14263970]edbones[/url]
[b][color=#cf68dd]Referee:[/color][/b] [img]https://osuflags.omkserver.nl/PH-20.png[/img] [url=https://osu.ppy.sh/users/10166961]Bens_[/url]';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(4);
    expect($result[0])->toMatchArray([
        'osu_id' => 11292327,
        'username' => 'Convex',
        'role' => 'organizer', // Admin -> organizer
    ]);
    expect($result[1])->toMatchArray([
        'osu_id' => 7113149,
        'username' => 'Gigi Murin',
        'role' => 'organizer',
    ]);
    expect($result[2])->toMatchArray([
        'osu_id' => 14263970,
        'username' => 'edbones',
        'role' => 'mappooler', // NEW ROLE
    ]);
    expect($result[3])->toMatchArray([
        'osu_id' => 10166961,
        'username' => 'Bens_',
        'role' => 'referee',
    ]);
});

test('parseStaffFromBBcode extracts staff with REAL BBcode format 2 (ANZT style)', function () {
    // Alternate format from tournament ID 91 (image INSIDE url tag)
    $bbcode = '[b][color=#EEAA75]Host: [/color][/b][url=https://osu.ppy.sh/users/7262064][img]https://osuflags.omkserver.nl/AU-20.png[/img] dGeist[/url]
[b][color=#EEAA75]Mappooler: [/color][/b][url=https://osu.ppy.sh/users/14754208][img]https://osuflags.omkserver.nl/AU-20.png[/img] cocaine bear[/url]
[b][color=#EEAA75]Referee: [/color][/b][url=https://osu.ppy.sh/users/30644569][img]https://osuflags.omkserver.nl/VN-20.png[/img] --Glitchy--[/url]';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0])->toMatchArray([
        'osu_id' => 7262064,
        'username' => 'dGeist',
        'role' => 'organizer', // Host -> organizer
    ]);
    expect($result[1])->toMatchArray([
        'osu_id' => 14754208,
        'username' => 'cocaine bear',
        'role' => 'mappooler', // Mappooler -> mappooler
    ]);
    expect($result[2])->toMatchArray([
        'osu_id' => 30644569,
        'username' => '--Glitchy--',
        'role' => 'referee',
    ]);
});

test('parseStaffFromBBcode handles NEW role types: playtester, gfx, sheeter', function () {
    $bbcode = '[b][color=#cf68dd]Playtester:[/color][/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/1111]TesterOne[/url]
[b][color=#cf68dd]GFX:[/color][/b] [img]https://osuflags.omkserver.nl/CA-20.png[/img] [url=https://osu.ppy.sh/users/2222]Artist[/url]
[b][color=#cf68dd]Sheeter:[/color][/b] [img]https://osuflags.omkserver.nl/UK-20.png[/img] [url=https://osu.ppy.sh/users/3333]SheetMaster[/url]';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['role'])->toBe('playtester');
    expect($result[1]['role'])->toBe('gfx');
    expect($result[2]['role'])->toBe('sheeter');
});

test('parseStaffFromBBcode maps role name variations to standard roles', function () {
    $bbcode = '[b][color=#cf68dd]Host:[/color][/b] [url=https://osu.ppy.sh/users/1]HostUser[/url]
[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/2]AdminUser[/url]
[b][color=#cf68dd]Mapper:[/color][/b] [url=https://osu.ppy.sh/users/3]MapperUser[/url]
[b][color=#cf68dd]Custom Mapper:[/color][/b] [url=https://osu.ppy.sh/users/4]CustomUser[/url]
[b][color=#cf68dd]Map Selector:[/color][/b] [url=https://osu.ppy.sh/users/5]SelectorUser[/url]
[b][color=#cf68dd]Mappooler:[/color][/b] [url=https://osu.ppy.sh/users/6]PoolerUser[/url]
[b][color=#cf68dd]Replayer:[/color][/b] [url=https://osu.ppy.sh/users/7]ReplayerUser[/url]
[b][color=#cf68dd]Moderators:[/color][/b] [url=https://osu.ppy.sh/users/8]ModUser[/url]';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(8);

    $roles = array_column($result, 'role');

    // All these should map to 'organizer'
    expect($roles[0])->toBe('organizer'); // Host
    expect($roles[1])->toBe('organizer'); // Admin

    // Mapper variants all map to 'mapper'
    expect($roles[2])->toBe('mapper'); // Mapper
    expect($roles[3])->toBe('mapper'); // Custom Mapper (united!)
    expect($roles[4])->toBe('mappooler'); // Map Selector -> mappooler
    expect($roles[5])->toBe('mappooler'); // Mappooler

    // Replayer -> playtester
    expect($roles[6])->toBe('playtester');

    // Moderators -> other
    expect($roles[7])->toBe('other');
});

// REMOVED: Old Markdown-format test (no longer relevant)
// The parser now uses BBcode format, not Markdown format
// See tests above for real BBcode format tests

test('parseStaffFromBBcode handles malformed BBcode gracefully', function () {
    $bbcode = 'Some text without proper format
**Admin:** No link here
Just random text [brokenlink](invalid-url)';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeArray();
    // Should return empty array or handle gracefully
    expect($result)->toHaveCount(0);
});

test('parseStaffFromBBcode extracts user IDs from various BBcode URL formats', function () {
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/12345]Name1[/url]
[b][color=#cf68dd]Mapper:[/color][/b] [url=https://osu.ppy.sh/users/6789#stats]Name2[/url]
[b][color=#cf68dd]Ref:[/color][/b] [url=http://osu.ppy.sh/users/999]Name3[/url]';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['osu_id'])->toBe(12345);
    expect($result[1]['osu_id'])->toBe(6789);
    expect($result[2]['osu_id'])->toBe(999);
});

test('parseStaffFromBBcode handles duplicate entries', function () {
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/123]SameUser[/url]
[b][color=#cf68dd]Mapper:[/color][/b] [url=https://osu.ppy.sh/users/123]SameUser[/url]
[b][color=#cf68dd]Referee:[/color][/b] [url=https://osu.ppy.sh/users/123]SameUser[/url]';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    // NEW BEHAVIOR: Returns all unique (user_id, role) combinations
    // This supports multi-role users - same user with different roles
    expect($result)->toHaveCount(3);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[0]['username'])->toBe('SameUser');
    expect($result[0]['role'])->toBe('organizer'); // Admin -> organizer
    expect($result[1]['osu_id'])->toBe(123);
    expect($result[1]['role'])->toBe('mapper'); // Mapper -> mapper
    expect($result[2]['osu_id'])->toBe(123);
    expect($result[2]['role'])->toBe('referee'); // Referee -> referee
});

test('parseStaffFromBBcode handles empty content', function () {
    $bbcode = '';

    $parser = app(ForumParser::class);
    $result = $parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(0);
});

// ============================================================================
// Phase 9: Star Rating Parsing from Pool Size Text Tests
// ============================================================================

test('parseStarRatings extracts qualifier SR', function () {
    $content = 'Pool size: 4.25* qualifier';

    $parser = app(ForumParser::class);
    $result = $parser->parseStarRatings($content);

    expect($result['star_rating_qualifier'])->toBe(4.25);
});

test('parseStarRatings extracts first and last round SR', function () {
    $content = 'First round: 5.12* stars
Finals: 6.78*';

    $parser = app(ForumParser::class);
    $result = $parser->parseStarRatings($content);

    expect($result['star_rating_first'])->toBe(5.12);
    expect($result['star_rating_last'])->toBe(6.78);
});

test('parseStarRatings extracts all three SR values', function () {
    $content = 'Qualifier pool: 4.25* stars
Round 1: 5.12*
Finals: 6.78*';

    $parser = app(ForumParser::class);
    $result = $parser->parseStarRatings($content);

    expect($result['star_rating_qualifier'])->toBe(4.25);
    expect($result['star_rating_first'])->toBe(5.12);
    expect($result['star_rating_last'])->toBe(6.78);
});

test('parseStarRatings handles various text formats', function () {
    $content = 'Pool size is 3.50* for qualifiers
First round pool size: 4.75*
Last round: 7.20* stars';

    $parser = app(ForumParser::class);
    $result = $parser->parseStarRatings($content);

    expect($result['star_rating_qualifier'])->toBe(3.50);
    expect($result['star_rating_first'])->toBe(4.75);
    expect($result['star_rating_last'])->toBe(7.20);
});

test('parseStarRatings handles missing star rating info', function () {
    $content = 'No star rating information here';

    $parser = app(ForumParser::class);
    $result = $parser->parseStarRatings($content);

    expect($result['star_rating_qualifier'])->toBeNull();
    expect($result['star_rating_first'])->toBeNull();
    expect($result['star_rating_last'])->toBeNull();
});

test('parseStarRatings handles decimal variations', function () {
    $content = 'Qualifier: 4* stars
Round 1: 5.5*
Finals: 6.123*';

    $parser = app(ForumParser::class);
    $result = $parser->parseStarRatings($content);

    expect($result['star_rating_qualifier'])->toBe(4.0);
    expect($result['star_rating_first'])->toBe(5.5);
    expect($result['star_rating_last'])->toBe(6.123);
});

test('parseStarRatings handles SR without star symbol', function () {
    $content = 'Qualifier pool: 4.25
First round: 5.12
Finals: 6.78';

    $parser = app(ForumParser::class);
    $result = $parser->parseStarRatings($content);

    expect($result['star_rating_qualifier'])->toBe(4.25);
    expect($result['star_rating_first'])->toBe(5.12);
    expect($result['star_rating_last'])->toBe(6.78);
});
