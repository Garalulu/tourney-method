<?php

/**
 * DiscordService Unit Tests
 *
 * Tests Discord webhook validation, URL format checking, and embed building.
 * HTTP calls are mocked to avoid actual API requests.
 */

use App\Services\DiscordService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

describe('DiscordService', function () {
    beforeEach(function () {
        $this->service = new DiscordService;
    });

    describe('isValidWebhookFormat', function () {
        it('validates correct Discord webhook URLs', function () {
            $validUrls = [
                'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz',
                'https://discord.com/api/webhooks/999999999999999999/ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-',
                'https://discord.com/api/webhooks/1/a',
            ];

            foreach ($validUrls as $url) {
                expect($this->service->isValidWebhookFormat($url))->toBeTrue("URL should be valid: {$url}");
            }
        });

        it('rejects invalid webhook URL formats', function () {
            $invalidUrls = [
                '', // empty string
                'not-a-url', // not a URL
                'http://discord.com/api/webhooks/123/abc', // http instead of https
                'https://discordapp.com/api/webhooks/123/abc', // old domain
                'https://discord.com/webhooks/123/abc', // missing /api
                'https://discord.com/api/webhook/123/abc', // webhook not webhooks
                'https://discord.com/api/webhooks/abc/def', // non-numeric ID
                'https://discord.com/api/webhooks/123/', // empty token
                'https://discord.com/api/webhooks//abc', // empty ID
                'https://discord.com/api/webhooks/123/abc?extra=param', // query params
                'https://discord.com/api/webhooks/123/abc/extra', // extra path
                'https://other-site.com/api/webhooks/123/abc', // wrong domain
            ];

            foreach ($invalidUrls as $url) {
                expect($this->service->isValidWebhookFormat($url))->toBeFalse("URL should be invalid: {$url}");
            }
        });

        it('allows alphanumeric characters, hyphens, and underscores in token', function () {
            $url = 'https://discord.com/api/webhooks/123456789012345678/abcABC123_-';
            expect($this->service->isValidWebhookFormat($url))->toBeTrue();
        });
    });

    describe('validateWebhook', function () {
        it('returns true when Discord API responds successfully', function () {
            Http::fake([
                'discord.com/api/webhooks/*' => Http::response(['id' => '123'], 200),
            ]);

            $result = $this->service->validateWebhook(
                'https://discord.com/api/webhooks/123456789012345678/abcdef'
            );

            expect($result)->toBeTrue();
        });

        it('returns false when Discord API returns error', function () {
            Http::fake([
                'discord.com/api/webhooks/*' => Http::response(['message' => 'Unknown Webhook'], 404),
            ]);

            $result = $this->service->validateWebhook(
                'https://discord.com/api/webhooks/123456789012345678/invalid'
            );

            expect($result)->toBeFalse();
        });

        it('returns false for invalid URL format without making HTTP request', function () {
            Http::fake();

            $result = $this->service->validateWebhook('not-a-valid-url');

            expect($result)->toBeFalse();
            Http::assertNothingSent();
        });

        it('returns false when HTTP request times out', function () {
            Http::fake([
                'discord.com/api/webhooks/*' => fn () => throw new ConnectionException('Connection timeout'),
            ]);

            $result = $this->service->validateWebhook(
                'https://discord.com/api/webhooks/123456789012345678/abcdef'
            );

            expect($result)->toBeFalse();
        });
    });

    describe('sendEmbed', function () {
        it('sends embed successfully', function () {
            Http::fake([
                'discord.com/api/webhooks/*' => Http::response(null, 204),
            ]);

            $result = $this->service->sendEmbed(
                'https://discord.com/api/webhooks/123456789012345678/abcdef',
                ['title' => 'Test', 'description' => 'Test message']
            );

            expect($result['success'])->toBeTrue();

            Http::assertSent(function ($request) {
                return $request->hasHeader('Content-Type', 'application/json')
                    && isset($request['embeds'][0]['title']);
            });
        });

        it('includes content when provided', function () {
            Http::fake([
                'discord.com/api/webhooks/*' => Http::response(null, 204),
            ]);

            $result = $this->service->sendEmbed(
                'https://discord.com/api/webhooks/123456789012345678/abcdef',
                ['title' => 'Test'],
                '@everyone'
            );

            expect($result['success'])->toBeTrue();

            Http::assertSent(function ($request) {
                return $request['content'] === '@everyone';
            });
        });

        it('includes allowed_mentions when provided', function () {
            Http::fake([
                'discord.com/api/webhooks/*' => Http::response(null, 204),
            ]);

            $result = $this->service->sendEmbed(
                'https://discord.com/api/webhooks/123456789012345678/abcdef',
                ['title' => 'Test'],
                '@everyone',
                ['parse' => ['everyone']]
            );

            expect($result['success'])->toBeTrue();

            Http::assertSent(function ($request) {
                return $request['allowed_mentions']['parse'] === ['everyone'];
            });
        });

        it('returns error for invalid webhook URL format', function () {
            Http::fake();

            $result = $this->service->sendEmbed(
                'invalid-url',
                ['title' => 'Test']
            );

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toBe('Invalid webhook URL format');
            Http::assertNothingSent();
        });
    });

    describe('sendMessage', function () {
        it('sends text message successfully', function () {
            Http::fake([
                'discord.com/api/webhooks/*' => Http::response(null, 204),
            ]);

            $result = $this->service->sendMessage(
                'https://discord.com/api/webhooks/123456789012345678/abcdef',
                'Hello, world!'
            );

            expect($result['success'])->toBeTrue();

            Http::assertSent(function ($request) {
                return $request['content'] === 'Hello, world!';
            });
        });

        it('returns error for invalid webhook URL format', function () {
            Http::fake();

            $result = $this->service->sendMessage(
                'invalid-url',
                'Hello!'
            );

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toBe('Invalid webhook URL format');
        });
    });

    describe('sendTestMessage', function () {
        it('sends test message with correct embed structure', function () {
            Http::fake([
                'discord.com/api/webhooks/*' => Http::response(null, 204),
            ]);

            $result = $this->service->sendTestMessage(
                'https://discord.com/api/webhooks/123456789012345678/abcdef',
                'TestUser'
            );

            expect($result['success'])->toBeTrue();

            Http::assertSent(function ($request) {
                $embed = $request['embeds'][0] ?? null;

                return $embed !== null
                    && str_contains($embed['title'], 'Webhook Test Successful')
                    && $embed['color'] === 0x00FF00 // Green
                    && collect($embed['fields'])->contains(fn ($f) => $f['name'] === 'User' && $f['value'] === 'TestUser');
            });
        });
    });

    describe('buildTournamentEmbed', function () {
        it('builds registration reminder embed with orange color', function () {
            $tournament = [
                'title' => 'Test Tournament',
                'modes' => ['osu', 'taiko'],
                'rank_range_min' => 1000,
                'rank_range_max' => 10000,
                'registration_end' => 1704067200, // Unix timestamp
                'banner_url' => 'https://example.com/banner.png',
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'registration_reminder');

            expect($embed['title'])->toBe('Test Tournament');
            expect($embed['color'])->toBe(0xFFA500); // Orange
            expect($embed['image']['url'])->toBe('https://example.com/banner.png');
            expect($embed['description'])->toContain('24h Reminder');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('#1,000 - #10,000');

            $regField = collect($embed['fields'])->firstWhere('name', 'Registration Ends');
            expect($regField)->not->toBeNull();
        });

        it('builds tournament start embed with green color', function () {
            $tournament = ['title' => 'Starting Tournament'];

            $embed = $this->service->buildTournamentEmbed($tournament, 'tournament_start');

            expect($embed['title'])->toBe('Starting Tournament');
            expect($embed['color'])->toBe(0x00FF00); // Green
        });

        it('builds stream live embed with purple color and Twitch link', function () {
            $tournament = [
                'title' => 'Live Tournament',
                'twitch_url' => 'https://twitch.tv/testchannel',
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'stream_live');

            expect($embed['title'])->toBe('Live Tournament');
            expect($embed['color'])->toBe(0x9146FF); // Twitch purple
            expect($embed['description'])->toContain('LIVE NOW');

            $twitchField = collect($embed['fields'])->firstWhere('name', '🎥 Watch on Twitch');
            expect($twitchField['value'])->toContain('twitch.tv/testchannel');
        });

        it('handles open rank range correctly', function () {
            $tournament = [
                'title' => 'Open Rank Tournament',
                'rank_range_min' => null,
                'rank_range_max' => null,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'tournament_start');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('Open Rank');
        });

        it('handles min-only rank range', function () {
            $tournament = [
                'title' => 'High Rank Tournament',
                'rank_range_min' => 1000,
                'rank_range_max' => null,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'tournament_start');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('#1,000+');
        });

        it('handles max-only rank range', function () {
            $tournament = [
                'title' => 'Low Rank Tournament',
                'rank_range_min' => null,
                'rank_range_max' => 50000,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'tournament_start');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('#1 - #50,000');
        });

        it('includes forum post URL when available', function () {
            $tournament = [
                'title' => 'Forum Tournament',
                'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/12345',
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'tournament_start');

            expect($embed['url'])->toBe('https://osu.ppy.sh/community/forums/topics/12345');
        });

        it('uses tournament URL for new approval title link and keeps forum URL in links', function () {
            $tournament = [
                'title' => 'New Tournament',
                'tournament_url' => 'http://localhost/tournaments/123',
                'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/12345',
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            expect($embed['url'])->toBe('http://localhost/tournaments/123');

            $linksField = collect($embed['fields'])->firstWhere('name', 'Links');
            expect($linksField['value'])->toBe('[Forum Post](https://osu.ppy.sh/community/forums/topics/12345)');
        });

        it('links host to local user profile when local host user id exists', function () {
            $tournament = [
                'title' => 'Host Link Tournament',
                'host_username' => 'TournamentHost',
                'host_user_id' => 42,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $hostField = collect($embed['fields'])->firstWhere('name', 'Host');
            expect($hostField['value'])->toBe('[TournamentHost]('.route('users.show', 42).')');
        });

        it('keeps host as plain text when local host user id is missing', function () {
            $tournament = [
                'title' => 'Plain Host Tournament',
                'host_username' => 'TournamentHost',
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $hostField = collect($embed['fields'])->firstWhere('name', 'Host');
            expect($hostField['value'])->toBe('TournamentHost');
        });

        it('handles single mode as string', function () {
            $tournament = [
                'title' => 'Single Mode Tournament',
                'modes' => 'osu',
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'tournament_start');

            // Mode is not displayed in embed anymore
            expect($embed['title'])->toBe('Single Mode Tournament');
        });

        it('always includes footer and timestamp', function () {
            $tournament = ['title' => 'Test'];

            $embed = $this->service->buildTournamentEmbed($tournament, 'tournament_start');

            expect($embed['footer']['text'])->toBe('Tourney Method');
            expect($embed['timestamp'])->not->toBeNull();
        });

        it('defaults to blue color for unknown notification type', function () {
            $tournament = ['title' => 'Test'];

            $embed = $this->service->buildTournamentEmbed($tournament, 'unknown_type');

            expect($embed['color'])->toBe(0x0099FF); // Blue
        });

        it('formats rank range with commas', function () {
            $tournament = [
                'title' => 'Large Number Tournament',
                'rank_range_min' => 7500,
                'rank_range_max' => 99999,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('#7,500 - #99,999');
        });

        it('adds BWS suffix when tournament uses BWS ranking', function () {
            $tournament = [
                'title' => 'BWS Tournament',
                'rank_range_min' => 7500,
                'rank_range_max' => 99999,
                'is_bws' => true,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('#7,500 - #99,999 BWS');
        });

        it('does not add BWS suffix when is_bws is false', function () {
            $tournament = [
                'title' => 'Regular Tournament',
                'rank_range_min' => 7500,
                'rank_range_max' => 99999,
                'is_bws' => false,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('#7,500 - #99,999');
        });

        it('formats open rank with BWS correctly', function () {
            $tournament = [
                'title' => 'BWS Open Tournament',
                'rank_range_min' => null,
                'rank_range_max' => null,
                'is_bws' => true,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('Open Rank BWS');
        });

        it('formats min-only rank with BWS', function () {
            $tournament = [
                'title' => 'BWS Min Tournament',
                'rank_range_min' => 10000,
                'rank_range_max' => null,
                'is_bws' => true,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('#10,000+ BWS');
        });

        it('formats max-only rank with BWS', function () {
            $tournament = [
                'title' => 'BWS Max Tournament',
                'rank_range_min' => null,
                'rank_range_max' => 50000,
                'is_bws' => true,
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $rankField = collect($embed['fields'])->firstWhere('name', 'Rank');
            expect($rankField['value'])->toBe('#1 - #50,000 BWS');
        });

        it('formats new approval description with star rating team formation and progression', function () {
            $tournament = [
                'title' => 'Structured Tournament',
                'star_rating_first' => 6.6,
                'star_rating_last' => 7.7,
                'star_rating_qualifier' => 6.9,
                'team_formation_style_label' => 'World Cup',
                'progression_summary' => 'Qualifier -> Ro8 Double Elimination',
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $descriptionField = collect($embed['fields'])->firstWhere('name', 'Description');
            expect($descriptionField['value'])->toBe("`*6.6~*7.7 (QL *6.9)` World Cup\nQualifier -> Ro8 Double Elimination");
        });

        it('adds links in the requested order and omits missing URLs', function () {
            $tournament = [
                'title' => 'Linked Tournament',
                'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/12345',
                'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/example',
                'registration_url' => 'https://forms.gle/example',
                'discord_url' => 'https://discord.gg/example',
            ];

            $embed = $this->service->buildTournamentEmbed($tournament, 'new_approval');

            $linksField = collect($embed['fields'])->firstWhere('name', 'Links');
            expect($linksField['value'])->toBe(implode("\n", [
                '[Forum Post](https://osu.ppy.sh/community/forums/topics/12345)',
                '[Main Sheet](https://docs.google.com/spreadsheets/d/example)',
                '[Player Reg](https://forms.gle/example)',
                '[Discord](https://discord.gg/example)',
            ]));
        });
    });
});
