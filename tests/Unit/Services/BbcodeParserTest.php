<?php

use App\Services\BbcodeParser;

beforeEach(function () {
    $this->parser = new BbcodeParser;
});

test('extracts urls from [url=link]text[/url] format', function () {
    $bbcode = '[url=https://discord.gg/abc123]Join Discord[/url]';

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toContain('https://discord.gg/abc123');
});

test('extracts urls from [url]link[/url] format', function () {
    $bbcode = '[url]https://twitch.tv/teststream[/url]';

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toContain('https://twitch.tv/teststream');
});

test('converts [b]bold[/b] to markdown **bold**', function () {
    $bbcode = 'This is [b]bold text[/b] here';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('This is **bold text** here');
});

test('converts [i]italic[/i] to markdown *italic*', function () {
    $bbcode = 'This is [i]italic text[/i] here';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('This is *italic text* here');
});

test('converts [u]underline[/u] to markdown __underline__', function () {
    $bbcode = 'This is [u]underlined text[/u] here';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('This is __underlined text__ here');
});

test('converts [s]strikethrough[/s] to markdown ~~strikethrough~~', function () {
    $bbcode = 'This is [s]struck text[/s] here';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('This is ~~struck text~~ here');
});

test('converts [list][*]items[/list] to markdown list', function () {
    $bbcode = '[list][*]First item[*]Second item[*]Third item[/list]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toContain('* First item');
    expect($markdown)->toContain('* Second item');
    expect($markdown)->toContain('* Third item');
});

test('extracts plain text with preserved newlines', function () {
    $bbcode = "Line 1\nLine 2\nLine 3";

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toContain("\n");
});

test('handles malformed bbcode gracefully', function () {
    $bbcode = '[url=https://example.com]Unclosed tag';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBeString();
});

test('handles nested bbcode tags', function () {
    $bbcode = '[b][url=https://example.com]Bold Link[/url][/b]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toContain('**');
    expect($markdown)->toContain('https://example.com');
});

test('extracts discord urls specifically', function () {
    $bbcode = '[url=https://discord.gg/test]Discord[/url] and [url=https://discord.com/invite/abc]Another[/url]';

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toContain('https://discord.gg/test');
    expect($urls)->toContain('https://discord.com/invite/abc');
});

test('extracts twitch urls specifically', function () {
    $bbcode = '[url=https://www.twitch.tv/teststream]Twitch[/url]';

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toContain('https://www.twitch.tv/teststream');
});

test('extracts google spreadsheet urls', function () {
    $bbcode = '[url=https://docs.google.com/spreadsheets/d/123abc/edit]Sheet[/url]';

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toContain('https://docs.google.com/spreadsheets/d/123abc/edit');
});

test('extracts challonge bracket urls', function () {
    $bbcode = '[url=https://challonge.com/tournament123]Bracket[/url]';

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toContain('https://challonge.com/tournament123');
});

test('extracts google forms registration urls', function () {
    $bbcode = '[url=https://forms.gle/abc123]Register[/url]';

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toContain('https://forms.gle/abc123');
});

test('converts [img] tags to markdown image syntax', function () {
    $bbcode = '[img]https://example.com/image.png[/img]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toContain('![image](https://example.com/image.png)');
});

test('converts [url=link][img]image[/img][/url] to markdown image', function () {
    $bbcode = '[url=https://example.com][img]https://example.com/image.png[/img][/url]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toContain('![image](https://example.com/image.png)');
});

test('converts [color] tags - strips color tags', function () {
    $bbcode = '[color=#FF0000]Red text[/color]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('Red text');
});

test('converts [size] tags - strips size tags', function () {
    $bbcode = '[size=150]Large text[/size]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('Large text');
});

test('converts [centre] tags - strips center tags', function () {
    $bbcode = '[centre]Centered text[/centre]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('Centered text');
});

test('converts [notice] tags - strips notice tags', function () {
    $bbcode = '[notice]Notice text[/notice]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('Notice text');
});

test('converts [heading] tags to markdown heading', function () {
    $bbcode = '[heading]Heading text[/heading]';

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toBe('### Heading text');
});

test('handles complex real-world tournament bbcode', function () {
    $bbcode = <<<'BBCODE'
[centre][notice]
[url=https://discord.gg/test][img]https://i.ibb.co/banner.png[/img][/url]
[size=125][b][url=https://docs.google.com/forms/d/123]Register[/url] | [url=https://discord.gg/abc]Discord[/url][/b][/size]
[/notice][/centre]

[b]Tournament Information[/b]

[list][*]Registration: Jan 1-31[*]Tournament: Feb 15-20[/list]

This is a [b]1v1[/b] tournament for players in rank range [b]1K-10K[/b].
BBCODE;

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toContain('**Tournament Information**');
    expect($markdown)->toContain('* Registration: Jan 1-31');
    expect($markdown)->toContain('https://discord.gg/abc');
});

test('extracts all urls from real tournament post', function () {
    $bbcode = <<<'BBCODE'
[url=https://discord.gg/6d4pF59]Discord Link[/url] | [url=https://docs.google.com/spreadsheets/d/1XivjcP3pYHtmUjEQB8InPS1_GBLm7uuzIgHMZielXoY/edit]Sheets[/url] | [url=https://www.twitch.tv/sinbae_]Twitch[/url]
[url=https://braacket.com/league/D70B2D1B-D59B-46A9-81FF-E351CB0A4D23/ranking]ranking[/url]
BBCODE;

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toHaveCount(4);
    expect($urls)->toContain('https://discord.gg/6d4pF59');
    expect($urls)->toContain('https://docs.google.com/spreadsheets/d/1XivjcP3pYHtmUjEQB8InPS1_GBLm7uuzIgHMZielXoY/edit');
    expect($urls)->toContain('https://www.twitch.tv/sinbae_');
    expect($urls)->toContain('https://braacket.com/league/D70B2D1B-D59B-46A9-81FF-E351CB0A4D23/ranking');
});

test('extracts urls from imagemap coordinate rows', function () {
    $bbcode = <<<'BBCODE'
[imagemap]
https://content.5teven.xyz/gfx/6wc/area_intro_top.png
73.2582 13.4765 26.7418 9.7547 https://forms.gle/dhZ2X1gf4fKbv1od6 SIGN UP TO TOURNEY
73.0533 22.2069 26.7418 9.8134 https://docs.google.com/spreadsheets/d/1-TX1ykmECyrxFKbNDCI9Z60-hSZ0shG99hxuVnvay2k/edit?gid=0#gid=0 VIEW SHEET
72.9508 39.9289 27.0492 9.7381 https://www.twitch.tv/6wc_osu SEE TWITCH STREAM
72.9508 48.8586 27.0492 9.4502 https://discord.gg/VQhYSKucNn JOIN THE DISCORD
73.3607 75.0818 26.6393 10.1212 https://challonge.com/6WC26 CHECK CHALLONGE
[/imagemap]
BBCODE;

    $urls = $this->parser->extractUrls($bbcode);

    expect($urls)->toContain(
        'https://content.5teven.xyz/gfx/6wc/area_intro_top.png',
        'https://forms.gle/dhZ2X1gf4fKbv1od6',
        'https://docs.google.com/spreadsheets/d/1-TX1ykmECyrxFKbNDCI9Z60-hSZ0shG99hxuVnvay2k/edit?gid=0#gid=0',
        'https://www.twitch.tv/6wc_osu',
        'https://discord.gg/VQhYSKucNn',
        'https://challonge.com/6WC26',
    );
});

test('bracket urls include braacket but exclude osu community tournament pages', function () {
    $bbcode = '[url=https://braacket.com/league/test/ranking]Braacket[/url] '
        .'[url=https://osu.ppy.sh/community/tournaments/123]osu tournament page[/url]';

    $urls = $this->parser->extractBracketUrls($bbcode);

    expect($urls)->toContain('https://braacket.com/league/test/ranking');
    expect($urls)->not->toContain('https://osu.ppy.sh/community/tournaments/123');
});

test('preserves line breaks from bbcode', function () {
    $bbcode = "Line 1\nLine 2\n\nLine 3";

    $markdown = $this->parser->toMarkdown($bbcode);

    expect($markdown)->toContain("\n");
});

test('handles empty string input', function () {
    $markdown = $this->parser->toMarkdown('');

    expect($markdown)->toBe('');
});

test('handles null input gracefully', function () {
    $markdown = $this->parser->toMarkdown(null);

    expect($markdown)->toBe('');
});
