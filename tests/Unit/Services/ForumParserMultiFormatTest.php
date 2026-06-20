<?php

use App\Services\ForumParser;

beforeEach(function () {
    $this->parser = app(ForumParser::class);
});

// ============================================================================
// FORMAT 1: Profile Tag Format (24 tournaments, ~600 staff)
// ============================================================================

test('extracts staff from profile tag format - tournament #1', function () {
    $bbcode = '[notice][b]Host:[/b] [profile=1576095]Sinaeb[/profile][img]https://osuflags.omkserver.nl/TW-20.png[/img]
[b]Admins:[/b] [profile=8590110]ANuko[/profile] | [profile=4798263]Mestro[/profile]
[b]Referees:[/b] [profile=1576095]Sinaeb[/profile][/notice]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    // Multi-role support: Sinaeb appears with both Host/organizer and Referee roles
    // Note: Deduplication groups by osu_id, so Sinaeb's roles are grouped together
    expect($result)->toHaveCount(4);
    expect($result[0]['osu_id'])->toBe(1576095);
    expect($result[0]['username'])->toBe('Sinaeb');
    expect($result[0]['role'])->toBe('organizer');
    expect($result[1]['osu_id'])->toBe(1576095);
    expect($result[1]['username'])->toBe('Sinaeb');
    expect($result[1]['role'])->toBe('referee');
    expect($result[2]['osu_id'])->toBe(8590110);
    expect($result[2]['username'])->toBe('ANuko');
    expect($result[2]['role'])->toBe('organizer');
    expect($result[3]['osu_id'])->toBe(4798263);
    expect($result[3]['username'])->toBe('Mestro');
    expect($result[3]['role'])->toBe('organizer');
});

test('extracts staff from profile tag format with multiple users per role', function () {
    $bbcode = '[b]Commentators:[/b] [profile=3840622]Konsy[/profile] | [profile=11677546]KorewaSmug[/profile] | [profile=7234537]Sekre[/profile]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['role'])->toBe('commentator');
    expect($result[1]['role'])->toBe('commentator');
    expect($result[2]['role'])->toBe('commentator');
});

test('extracts staff from profile tag format with flag images', function () {
    $bbcode = '[b]Mappoolers:[/b] [profile=14263970]edbones[/profile][img]https://osuflags.omkserver.nl/MX-20.png[/img]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['osu_id'])->toBe(14263970);
    expect($result[0]['username'])->toBe('edbones');
    expect($result[0]['role'])->toBe('mappooler');
});

// ============================================================================
// FORMAT 2: Color Tag List Format (3 tournaments, ~97 staff)
// ============================================================================

test('extracts staff from color tag list format - tournament #3', function () {
    $bbcode = '[notice][list]
[*][color=#0e8bff]Host[/color]: [url=https://osu.ppy.sh/users/21404004][color=white]WheelsWaz[/url][/color]
[*][color=#0e8bff]Admin[/color]: [url=https://osu.ppy.sh/users/27899791][color=white]Remorias[/url][/color], [url=https://osu.ppy.sh/users/17404996/osu][color=white]Thomson02[/url][/color]
[/list][/notice]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['osu_id'])->toBe(21404004);
    expect($result[0]['username'])->toBe('WheelsWaz');
    expect($result[0]['role'])->toBe('organizer');
    expect($result[1]['osu_id'])->toBe(27899791);
    expect($result[1]['role'])->toBe('organizer');
});

test('extracts staff from color tag list with nested color tags in usernames', function () {
    $bbcode = '[*][color=#0e8bff]GFX[/color]: [url=https://osu.ppy.sh/users/27266540][color=white]Pilot_BFFRI[/url][/color]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['osu_id'])->toBe(27266540);
    expect($result[0]['username'])->toBe('Pilot_BFFRI');
    expect($result[0]['role'])->toBe('gfx');
});

test('extracts staff from color tag list with comma-separated users', function () {
    $bbcode = '[*][color=#0e8bff]Mappoolers[/color]: [url=https://osu.ppy.sh/users/123]User1[/url], [url=https://osu.ppy.sh/users/456]User2[/url], [url=https://osu.ppy.sh/users/789]User3[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['username'])->toBe('User1');
    expect($result[1]['username'])->toBe('User2');
    expect($result[2]['username'])->toBe('User3');
});

// ============================================================================
// FORMAT 3: Nested Bold+Color Format (1 tournament, 126 staff)
// ============================================================================

test('extracts staff from nested bold color format - tournament #6', function () {
    $bbcode = '[b][color=#cf68dd]Organizers:[/color][/b] [url=https://osu.ppy.sh/users/11801407]KladVomzin01[/url], [url=https://osu.ppy.sh/users/11148079]MiquelVZLA[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0]['osu_id'])->toBe(11801407);
    expect($result[0]['role'])->toBe('organizer');
});

test('extracts staff from nested bold color format with comma-separated users', function () {
    $bbcode = '[b][color=#cf68dd]Mappoolers:[/color][/b] [url=https://osu.ppy.sh/users/1111]Pooler1[/url], [url=https://osu.ppy.sh/users/2222]Pooler2[/url], [url=https://osu.ppy.sh/users/3333]Pooler3[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['role'])->toBe('mappooler');
    expect($result[1]['role'])->toBe('mappooler');
    expect($result[2]['role'])->toBe('mappooler');
});

// ============================================================================
// FORMAT 4: Standard Bold Format (9 tournaments, ~250 staff) - Already Working
// ============================================================================

test('extracts staff from standard bold format - tournament #48', function () {
    $bbcode = '[b]Organizer:[/b] [url=https://osu.ppy.sh/users/13893715]NO sliderends[/url]
[b]Mappooler:[/b] [url=https://osu.ppy.sh/users/9929781]EvilGamings[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[1]['role'])->toBe('mappooler');
});

test('extracts staff from standard bold format with no color tags', function () {
    $bbcode = '[b]Host:[/b] [url=https://osu.ppy.sh/users/21404004]WheelsWaz[/url]
[b]Referee:[/b] [url=https://osu.ppy.sh/users/12345]RefUser[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[1]['role'])->toBe('referee');
});

// ============================================================================
// FORMAT 5: Plain Text Format - Intentionally rejected without numeric IDs
// ============================================================================

test('rejects staff from plain text format without numeric ids', function () {
    $bbcode = '[b]Host:[/b] AdrianLazer
[b]Co-Host[/b] GamerChris
[b]Admins:[/b] SomeOtherUser';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('rejects comma-separated plain text usernames', function () {
    $bbcode = '[b]Streamers:[/b] User1, User2, User3';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('plain text format does not extract when user links exist', function () {
    // When user links exist, should use other extractors, not plain text
    $bbcode = '[b]Host:[/b] [url=https://osu.ppy.sh/users/123]RealUser[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[0]['username'])->toBe('RealUser');
});

// ============================================================================
// EDGE CASES
// ============================================================================

test('returns empty array for empty input', function () {
    $result = $this->parser->parseStaffFromBBcode('');

    expect($result)->toBeEmpty();
});

test('returns empty array for content without staff', function () {
    $bbcode = '[notice]This is just a notice without any staff listings[/notice]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('preserves all roles for same user (multi-role support)', function () {
    $bbcode = '[b]Hosts:[/b] [profile=123]User1[/profile]
[b]Referees:[/b] [profile=123]User1[/profile]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    // Multi-role support: User1 appears with both Host and Referee roles
    expect($result)->toHaveCount(2);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[1]['osu_id'])->toBe(123);
    expect($result[1]['role'])->toBe('referee');
});

test('handles mixed formats in single post', function () {
    $bbcode = '[b]Host:[/b] [profile=123]User1[/profile]
[*][color=#0e8bff]Admin:[/color]: [url=https://osu.ppy.sh/users/456]User2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[0]['username'])->toBe('User1');
    expect($result[1]['osu_id'])->toBe(456);
    expect($result[1]['username'])->toBe('User2');
});

test('handles emoji in usernames', function () {
    $bbcode = '[b]Mappooler:[/b] [profile=123]🎮Gamer🎮[/profile]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['username'])->toBe('🎮Gamer🎮');
});

test('handles emoji in role names', function () {
    $bbcode = '[b]🏆Organizers🏆:[/b] [url=https://osu.ppy.sh/users/123]User1[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['role'])->toBe('organizer'); // Emoji stripped
});

test('handles pipe-separated users in profile tag format', function () {
    $bbcode = '[b]Admins:[/b] [profile=123]User1[/profile] | [profile=456]User2[/profile] | [profile=789]User3[/profile]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[1]['osu_id'])->toBe(456);
    expect($result[2]['osu_id'])->toBe(789);
});

test('early exit for content without bold tags', function () {
    $bbcode = 'This is just plain text without any [b]bold[/b] tags or user links';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('early exit for content without user links or profile tags', function () {
    $bbcode = '[b]Host:[/b] Some text here but no actual user links or profile tags';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('handles multiline staff listings', function () {
    $bbcode = '[b]Host:[/b] [profile=123]User1[/profile]
[b]Admins:[/b] [profile=456]User2[/profile] | [profile=789]User3[/profile]
[b]Mappoolers:[/b] [profile=111]Mapper1[/profile], [profile=222]Mapper2[/profile]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(5);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[1]['osu_id'])->toBe(456);
    expect($result[2]['osu_id'])->toBe(789);
    expect($result[3]['osu_id'])->toBe(111);
    expect($result[4]['osu_id'])->toBe(222);
});

test('rejects duplicate plain text staff with no numeric ids', function () {
    // Add padding text to meet minimum length requirement
    $bbcode = '[b]Host:[/b] SameUser
[b]Admin:[/b] SameUser

Additional text to meet minimum length requirements for parsing.';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('extracts staff when role headers and identities are on separate lines', function () {
    $bbcode = <<<'BBCODE'
[b]HOSTS[/b]
[img]https://example.com/us.png[/img] [url=https://osu.ppy.sh/users/7075211][b]Tekkito[/b][/url]
[b]SPREADSHEETS[/b]
[url=https://osu.ppy.sh/users/11633961]HitomiChan_[/url]
[b]REFEREES[/b]
[color=#aaa]Applications are still open.[/color]
[centre]graphics by [url=https://osu.ppy.sh/users/10292384]ikyy[/url][/centre]
BBCODE;

    expect($this->parser->parseStaffFromBBcode($bbcode))->toBe([
        ['osu_id' => 7075211, 'username' => 'Tekkito', 'role' => 'organizer'],
        ['osu_id' => 11633961, 'username' => 'HitomiChan_', 'role' => 'sheeter'],
    ]);
});

test('extracts staff when role and identities share one bold block', function () {
    $bbcode = <<<'BBCODE'
[list][*][b]Host: [url=https://osu.ppy.sh/users/6044237]huu[/url][/b]
[*][b]Map poolers: [url=https://osu.ppy.sh/users/15218817]Ksyro[/url], [url=https://osu.ppy.sh/users/1109122]roufou[/url][/b][/list]
BBCODE;

    expect($this->parser->parseStaffFromBBcode($bbcode))->toBe([
        ['osu_id' => 6044237, 'username' => 'huu', 'role' => 'organizer'],
        ['osu_id' => 15218817, 'username' => 'Ksyro', 'role' => 'mappooler'],
        ['osu_id' => 1109122, 'username' => 'roufou', 'role' => 'mappooler'],
    ]);
});

test('extracts staff from color-only role headers', function () {
    $bbcode = <<<'BBCODE'
[color=#e91e63]Host[/color]: [profile=38937909]plushbun[/profile] | [profile=10024264]Tre[/profile]
[color=#11806a]Referee[/color]: [profile=21353706]Nikva[/profile]
BBCODE;

    expect($this->parser->parseStaffFromBBcode($bbcode))->toBe([
        ['osu_id' => 38937909, 'username' => 'plushbun', 'role' => 'organizer'],
        ['osu_id' => 10024264, 'username' => 'Tre', 'role' => 'organizer'],
        ['osu_id' => 21353706, 'username' => 'Nikva', 'role' => 'referee'],
    ]);
});

test('preserves bracketed usernames while removing formatting tags', function () {
    $bbcode = '[b]Organizers:[/b] [profile=23890527][Crz]ChenXi[/profile]
[b]Mappers:[/b] [url=https://osu.ppy.sh/users/16406942][color=#fff][b][SHK]SparkNight[/b][/color][/url]';

    expect($this->parser->parseStaffFromBBcode($bbcode))->toBe([
        ['osu_id' => 23890527, 'username' => '[Crz]ChenXi', 'role' => 'organizer'],
        ['osu_id' => 16406942, 'username' => '[SHK]SparkNight', 'role' => 'mapper'],
    ]);
});

test('expands combined role headers into multiple roles', function () {
    $bbcode = <<<'BBCODE'
[b]Pooling & Playtesting Team:[/b] [profile=5472693]Agent5d[/profile]
[b]Streamers/Commentators:[/b] [url=https://osu.ppy.sh/users/3929829]PatyYe-[/url]
BBCODE;

    expect($this->parser->parseStaffFromBBcode($bbcode))->toBe([
        ['osu_id' => 5472693, 'username' => 'Agent5d', 'role' => 'mappooler'],
        ['osu_id' => 5472693, 'username' => 'Agent5d', 'role' => 'playtester'],
        ['osu_id' => 3929829, 'username' => 'PatyYe-', 'role' => 'streamer'],
        ['osu_id' => 3929829, 'username' => 'PatyYe-', 'role' => 'commentator'],
    ]);
});

test('maps various role name variations correctly', function () {
    $bbcode = '[b]Host:[/b] [profile=1]User1[/profile]
[b]Co-Host:[/b] [profile=2]User2[/profile]
[b]CoHost:[/b] [profile=3]User3[/profile]
[b]Admin:[/b] [profile=4]User4[/profile]
[b]Admins:[/b] [profile=5]User5[/profile]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(5);

    // All should map to 'organizer'
    foreach ($result as $staff) {
        expect($staff['role'])->toBe('organizer');
    }
});

test('handles URL fragments and query parameters', function () {
    $bbcode = '[b]Host:[/b] [url=https://osu.ppy.sh/users/12345/stats]UserName[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['osu_id'])->toBe(12345);
    expect($result[0]['username'])->toBe('UserName');
});

test('cleans nested BBcode tags from usernames', function () {
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/123][color=white][b]BoldUser[/b][/color][/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['username'])->toBe('BoldUser');
});

test('extracts only numeric osu ids from nested formatted username links', function () {
    $bbcode = <<<'BBCODE'
[notice][b]Host:[/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/townes][color=#F5F5F5][b]townes[/b][/color][/url], [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/hygeo ][color=#F5F5F5][b]hygeo [/b][/color][/url]
[b]Poolers:[/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/townes][color=#F5F5F5][b]townes[/b][/color][/url], [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/hygeo][color=#F5F5F5][b]hygeo[/b][/color][/url]
[b]Playtesters:[/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/Nazuna Nanakusa][color=#F5F5F5][b]Nazuna Nanakusa[/b][/color][/url]
[b]Referees:[/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/townes][color=#F5F5F5][b]townes[/b][/color][/url], [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/hygeo][color=#F5F5F5][b]hygeo[/b][/color][/url], [img]https://osuflags.omkserver.nl/CA-20.png[/img] [url=https://osu.ppy.sh/users/GarciXD][color=#F5F5F5][b]GarciXD[/b][/color][/url], [img]https://osuflags.omkserver.nl/PH-20.png[/img] [url=https://osu.ppy.sh/users/Jercy][color=#F5F5F5][b]Jercy[/b][/color][/url], [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/fluvry][color=#F5F5F5][b]fluvry[/b][/color][/url], [img]https://osuflags.omkserver.nl/PL-20.png[/img] [url=https://osu.ppy.sh/users/krencior__][color=#F5F5F5][b]krencior__[/b][/color][/url]
[b]Streamers:[/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/townes][color=#F5F5F5][b]townes[/b][/color][/url], [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/hygeo][color=#F5F5F5][b]hygeo[/b][/color][/url], [img]https://osuflags.omkserver.nl/CL-20.png[/img] [url=https://osu.ppy.sh/users/Touche][color=#F5F5F5][b]Touche[/b][/color][/url]
[b]Commentators:[/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/townes][color=#F5F5F5][b]townes[/b][/color][/url], [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/hygeo][color=#F5F5F5][b]hygeo[/b][/color][/url], [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/hubbawubba][color=#F5F5F5][b]hubbawubba[/b][/color][/url]
[b]GFX:[/b] [img]https://osuflags.omkserver.nl/FR-20.png[/img] [url=https://osu.ppy.sh/users/Kheops][color=#F5F5F5][b]Kheops[/b][/color][/url]
[b]Mappers:[/b] [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/Wisdom%20Weir][color=#F5F5F5][b]Wisdom Weir[/b][/color][/url], [img]https://osuflags.omkserver.nl/CA-20.png[/img] [url=https://osu.ppy.sh/users/songpyeon][color=#F5F5F5][b]songpyeon[/b][/color][/url], [img]https://osuflags.omkserver.nl/GB-20.png[/img] [url=https://osu.ppy.sh/users/im%20cute][color=#F5F5F5][b]im cute[/b][/color][/url], [img]https://osuflags.omkserver.nl/US-20.png[/img] [url=https://osu.ppy.sh/users/7306698][color=#F5F5F5][b]1103[/b][/color][/url][/notice]
BBCODE;

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBe([
        [
            'osu_id' => 7306698,
            'username' => '1103',
            'role' => 'mapper',
        ],
    ]);
});

test('rejects numeric-looking user paths that are not valid numeric ids', function () {
    $bbcode = <<<'BBCODE'
[b]Host:[/b] [url=https://osu.ppy.sh/users/123abc][b]AlphaSuffix[/b][/url]
[b]Mapper:[/b] [url=https://osu.ppy.sh/users/456 ][b]TrailingSpace[/b][/url]
[b]Referee:[/b] [url=https://osu.ppy.sh/users/789?mode=osu][b]ValidNumeric[/b][/url]
BBCODE;

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBe([
        [
            'osu_id' => 789,
            'username' => 'ValidNumeric',
            'role' => 'referee',
        ],
    ]);
});
