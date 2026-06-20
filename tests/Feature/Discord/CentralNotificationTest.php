<?php

use App\Models\DiscordChannel;
use App\Models\DiscordServer;
use App\Models\Tournament;
use App\Models\User;
use App\Services\DiscordService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Mock the Discord HTTP client
    Http::fake();

    // Set up test Discord channels
    DiscordChannel::factory()->create([
        'channel_name' => 'osu-badge-tournaments',
        'webhook_url' => 'https://discord.com/api/webhooks/123/testabc',
        'mode' => 'osu',
        'is_badge' => true,
        'role_mappings' => [
            ['threshold' => 1, 'role_id' => '111111111111111111'],
            ['threshold' => 1000, 'role_id' => '222222222222222222'],
            ['threshold' => 5000, 'role_id' => '333333333333333333'],
        ],
        'is_active' => true,
    ]);

    DiscordChannel::factory()->create([
        'channel_name' => 'taiko-badge-tournaments',
        'webhook_url' => 'https://discord.com/api/webhooks/456/testdef',
        'mode' => 'taiko',
        'is_badge' => true,
        'role_mappings' => [],
        'is_active' => true,
    ]);
});

test('posts tournament to appropriate central Discord channel on approval', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Test Badge Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'rank_range_min' => 1000,
        'rank_range_max' => 5000,
        'status' => 'pending_review',
    ]);

    // Mock successful Discord response
    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    // Act as admin and approve tournament
    // The job runs synchronously due to QUEUE_CONNECTION=sync in tests
    $response = $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament));

    $response->assertStatus(200);

    // Assert Discord webhook was called
    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://discord.com/api/webhooks/123/testabc'
            && str_contains($body['content'] ?? '', '<@&222222222222222222>'); // Role ping for 1K-5K range
    });
});

test('approval posts to multiple active destinations for same route with per-destination role pings', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Multi Destination Badge Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'rank_range_min' => 5000,
        'rank_range_max' => 50000,
        'status' => 'pending_review',
    ]);

    DiscordChannel::factory()->create([
        'channel_name' => 'osu-badge-second-destination',
        'webhook_url' => 'https://discord.com/api/webhooks/321/secondabc',
        'mode' => 'osu',
        'is_badge' => true,
        'role_mappings' => [
            ['threshold' => 1, 'role_id' => '444444444444444444'],
            ['threshold' => 5000, 'role_id' => '555555555555555555'],
        ],
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    $firstDestinationCalled = false;
    $secondDestinationCalled = false;

    Http::assertSent(function ($request) use (&$firstDestinationCalled, &$secondDestinationCalled) {
        $body = json_decode($request->body(), true);

        if ($request->url() === 'https://discord.com/api/webhooks/123/testabc') {
            $firstDestinationCalled = true;

            return str_contains($body['content'] ?? '', '<@&333333333333333333>')
                && in_array('333333333333333333', $body['allowed_mentions']['roles'] ?? [], true);
        }

        if ($request->url() === 'https://discord.com/api/webhooks/321/secondabc') {
            $secondDestinationCalled = true;

            return str_contains($body['content'] ?? '', '<@&555555555555555555>')
                && in_array('555555555555555555', $body['allowed_mentions']['roles'] ?? [], true);
        }

        return false;
    });

    expect($firstDestinationCalled)->toBeTrue();
    expect($secondDestinationCalled)->toBeTrue();
});

test('global approval posts to destinations on servers with and without countries', function () {
    $admin = User::factory()->admin()->create();
    $server = DiscordServer::factory()->create([
        'server_name' => 'Korea Alerts',
        'countries' => ['KR'],
    ]);
    $tournament = Tournament::factory()->create([
        'title' => 'Global Badge Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'status' => 'pending_review',
        'restricted_countries' => [],
    ]);

    DiscordChannel::factory()->create([
        'discord_server_id' => $server->id,
        'channel_name' => 'osu-badge-korea-global',
        'webhook_url' => 'https://discord.com/api/webhooks/700/koreaglobal',
        'mode' => 'osu',
        'is_badge' => true,
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/123/testabc');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/700/koreaglobal');
});

test('region restricted approval posts only to matching server countries', function () {
    $admin = User::factory()->admin()->create();
    $koreaServer = DiscordServer::factory()->create([
        'server_name' => 'Korea Alerts',
        'countries' => ['KR'],
    ]);
    $japanServer = DiscordServer::factory()->create([
        'server_name' => 'Japan Alerts',
        'countries' => ['JP'],
    ]);
    $emptyServer = DiscordServer::factory()->create([
        'server_name' => 'Global Only Alerts',
        'countries' => [],
    ]);
    $tournament = Tournament::factory()->create([
        'title' => 'Korea Restricted Badge Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'status' => 'pending_review',
        'restricted_countries' => ['KR'],
    ]);

    DiscordChannel::factory()->create([
        'discord_server_id' => $koreaServer->id,
        'channel_name' => 'osu-badge-korea-restricted',
        'webhook_url' => 'https://discord.com/api/webhooks/701/korearestricted',
        'mode' => 'osu',
        'is_badge' => true,
        'is_active' => true,
    ]);
    DiscordChannel::factory()->create([
        'discord_server_id' => $japanServer->id,
        'channel_name' => 'osu-badge-japan-restricted',
        'webhook_url' => 'https://discord.com/api/webhooks/702/japanrestricted',
        'mode' => 'osu',
        'is_badge' => true,
        'is_active' => true,
    ]);
    DiscordChannel::factory()->create([
        'discord_server_id' => $emptyServer->id,
        'channel_name' => 'osu-badge-empty-restricted',
        'webhook_url' => 'https://discord.com/api/webhooks/703/emptyrestricted',
        'mode' => 'osu',
        'is_badge' => true,
        'is_active' => true,
    ]);
    DiscordChannel::factory()->create([
        'discord_server_id' => null,
        'channel_name' => 'osu-badge-no-server-restricted',
        'webhook_url' => 'https://discord.com/api/webhooks/704/noserverrestricted',
        'mode' => 'osu',
        'is_badge' => true,
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/701/korearestricted');
    Http::assertNotSent(fn ($request): bool => in_array($request->url(), [
        'https://discord.com/api/webhooks/123/testabc',
        'https://discord.com/api/webhooks/702/japanrestricted',
        'https://discord.com/api/webhooks/703/emptyrestricted',
        'https://discord.com/api/webhooks/704/noserverrestricted',
    ], true));
});

test('region restricted approval uses any matching country overlap', function () {
    $admin = User::factory()->admin()->create();
    $japanServer = DiscordServer::factory()->create([
        'server_name' => 'Japan Alerts',
        'countries' => ['JP'],
    ]);
    $usaServer = DiscordServer::factory()->create([
        'server_name' => 'USA Alerts',
        'countries' => ['US'],
    ]);
    $tournament = Tournament::factory()->create([
        'title' => 'Korea Japan Restricted Badge Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'status' => 'pending_review',
        'restricted_countries' => ['KR', 'JP'],
    ]);

    DiscordChannel::factory()->create([
        'discord_server_id' => $japanServer->id,
        'channel_name' => 'osu-badge-japan-overlap',
        'webhook_url' => 'https://discord.com/api/webhooks/705/japanoverlap',
        'mode' => 'osu',
        'is_badge' => true,
        'is_active' => true,
    ]);
    DiscordChannel::factory()->create([
        'discord_server_id' => $usaServer->id,
        'channel_name' => 'osu-badge-usa-overlap',
        'webhook_url' => 'https://discord.com/api/webhooks/706/usaoverlap',
        'mode' => 'osu',
        'is_badge' => true,
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/705/japanoverlap');
    Http::assertNotSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/706/usaoverlap');
});

test('destinations attached to same server share server role mappings', function () {
    $admin = User::factory()->admin()->create();
    $server = DiscordServer::factory()->create([
        'server_name' => 'Shared Role Server',
        'role_mappings' => [
            ['threshold' => 1, 'role_id' => '111111111111111111'],
            ['threshold' => 5000, 'role_id' => '555555555555555555'],
        ],
    ]);
    $tournament = Tournament::factory()->create([
        'title' => 'Shared Server Roles Tournament',
        'modes' => ['mania'],
        'is_badge' => true,
        'rank_range_min' => 5000,
        'rank_range_max' => 50000,
        'status' => 'pending_review',
    ]);

    DiscordChannel::factory()->create([
        'discord_server_id' => $server->id,
        'channel_name' => 'mania-badge-first-server-destination',
        'webhook_url' => 'https://discord.com/api/webhooks/901/serverfirst',
        'mode' => 'mania',
        'is_badge' => true,
        'role_mappings' => [],
        'is_active' => true,
    ]);
    DiscordChannel::factory()->create([
        'discord_server_id' => $server->id,
        'channel_name' => 'mania-badge-second-server-destination',
        'webhook_url' => 'https://discord.com/api/webhooks/902/serversecond',
        'mode' => 'mania',
        'is_badge' => true,
        'role_mappings' => [],
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return in_array($request->url(), [
            'https://discord.com/api/webhooks/901/serverfirst',
            'https://discord.com/api/webhooks/902/serversecond',
        ], true)
            && str_contains($body['content'] ?? '', '<@&555555555555555555>');
    });
});

test('far rank tournament sends without role ping when server allows unmatched alerts', function () {
    $admin = User::factory()->admin()->create();
    $server = DiscordServer::factory()->create([
        'server_name' => 'Wide Rank Server',
        'role_mappings' => [
            ['threshold' => 10000, 'role_id' => '101010101010101010'],
        ],
        'send_unmatched_rank_alerts' => true,
    ]);
    $tournament = Tournament::factory()->create([
        'title' => 'Far Rank Tournament',
        'modes' => ['mania'],
        'is_badge' => true,
        'rank_range_min' => 100000,
        'rank_range_max' => null,
        'status' => 'pending_review',
    ]);

    DiscordChannel::factory()->create([
        'discord_server_id' => $server->id,
        'channel_name' => 'mania-badge-wide-rank',
        'webhook_url' => 'https://discord.com/api/webhooks/910/widerank',
        'mode' => 'mania',
        'is_badge' => true,
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://discord.com/api/webhooks/910/widerank'
            && ! isset($body['content'])
            && ! isset($body['allowed_mentions']);
    });
});

test('far rank tournament skips destination when server blocks unmatched alerts', function () {
    $admin = User::factory()->admin()->create();
    $server = DiscordServer::factory()->create([
        'server_name' => 'Strict Rank Server',
        'role_mappings' => [
            ['threshold' => 10000, 'role_id' => '101010101010101010'],
        ],
        'send_unmatched_rank_alerts' => false,
    ]);
    $tournament = Tournament::factory()->create([
        'title' => 'Skipped Far Rank Tournament',
        'modes' => ['mania'],
        'is_badge' => true,
        'rank_range_min' => 100000,
        'rank_range_max' => null,
        'status' => 'pending_review',
    ]);

    DiscordChannel::factory()->create([
        'discord_server_id' => $server->id,
        'channel_name' => 'mania-badge-strict-rank',
        'webhook_url' => 'https://discord.com/api/webhooks/911/strictrank',
        'mode' => 'mania',
        'is_badge' => true,
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertNotSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/911/strictrank');
});

test('rank below far boundary still pings matching server role', function () {
    $admin = User::factory()->admin()->create();
    $server = DiscordServer::factory()->create([
        'server_name' => 'Boundary Rank Server',
        'role_mappings' => [
            ['threshold' => 10000, 'role_id' => '101010101010101010'],
        ],
        'send_unmatched_rank_alerts' => false,
    ]);
    $tournament = Tournament::factory()->create([
        'title' => 'Boundary Rank Tournament',
        'modes' => ['mania'],
        'is_badge' => true,
        'rank_range_min' => 99999,
        'rank_range_max' => null,
        'status' => 'pending_review',
    ]);

    DiscordChannel::factory()->create([
        'discord_server_id' => $server->id,
        'channel_name' => 'mania-badge-boundary-rank',
        'webhook_url' => 'https://discord.com/api/webhooks/912/boundaryrank',
        'mode' => 'mania',
        'is_badge' => true,
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://discord.com/api/webhooks/912/boundaryrank'
            && str_contains($body['content'] ?? '', '<@&101010101010101010>')
            && in_array('101010101010101010', $body['allowed_mentions']['roles'] ?? [], true);
    });
});

test('failed destination does not prevent remaining destinations from receiving alert', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Partial Discord Delivery Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'status' => 'pending_review',
    ]);

    DiscordChannel::factory()->create([
        'channel_name' => 'osu-badge-still-posts',
        'webhook_url' => 'https://discord.com/api/webhooks/654/stillposts',
        'mode' => 'osu',
        'is_badge' => true,
        'role_mappings' => [],
        'is_active' => true,
    ]);

    Http::fake([
        'https://discord.com/api/webhooks/123/testabc' => Http::response(['message' => 'bad webhook'], 400),
        'https://discord.com/api/webhooks/654/stillposts' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/123/testabc');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/654/stillposts');
});

test('approval webhook uses local tournament and host profile links', function () {
    $admin = User::factory()->admin()->create();
    $host = User::factory()->create([
        'osu_id' => 987654,
        'username' => 'LocalHost',
    ]);
    $tournament = Tournament::factory()->create([
        'title' => 'Local Link Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'status' => 'pending_review',
        'host_osu_id' => $host->osu_id,
        'host_username' => $host->username,
        'forum_topic_id' => 123456,
        'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/example',
        'registration_url' => 'https://forms.gle/example',
        'discord_url' => 'https://discord.gg/example',
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    Http::assertSent(function ($request) use ($host, $tournament) {
        $body = json_decode($request->body(), true);
        $embed = $body['embeds'][0] ?? [];
        $fields = collect($embed['fields'] ?? []);
        $hostField = $fields->firstWhere('name', 'Host');
        $linksField = $fields->firstWhere('name', 'Links');

        return $request->url() === 'https://discord.com/api/webhooks/123/testabc'
            && ($embed['url'] ?? null) === route('tournaments.show', $tournament)
            && ($hostField['value'] ?? null) === '[LocalHost]('.route('users.show', $host->id).')'
            && str_contains($linksField['value'] ?? '', '[Forum Post]('.$tournament->forum_post_url.')')
            && str_contains($linksField['value'] ?? '', '[Main Sheet](https://docs.google.com/spreadsheets/d/example)')
            && str_contains($linksField['value'] ?? '', '[Player Reg](https://forms.gle/example)')
            && str_contains($linksField['value'] ?? '', '[Discord](https://discord.gg/example)');
    });
});

test('posts to correct channel based on game mode', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Taiko Badge Tournament',
        'modes' => ['taiko'],
        'is_badge' => true,
        'status' => 'pending_review',
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    // Should use taiko channel
    Http::assertSent(function ($request) {
        return $request->url() === 'https://discord.com/api/webhooks/456/testdef';
    });
});

test('includes role pings based on rank range', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Open Rank Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'rank_range_min' => 5000,
        'rank_range_max' => 99999,
        'status' => 'pending_review',
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    // Should include role ping for 5000+
    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['allowed_mentions']['roles'])
            && in_array('333333333333333333', $body['allowed_mentions']['roles'])
            && str_contains($body['content'] ?? '', '<@&333333333333333333>');
    });
});

test('non-badge tournaments do not ping roles', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'General Tournament',
        'modes' => ['osu'],
        'is_badge' => false,
        'status' => 'pending_review',
    ]);

    // Create a general channel (non-badge)
    DiscordChannel::factory()->create([
        'channel_name' => 'osu-general-tournaments',
        'webhook_url' => 'https://discord.com/api/webhooks/789/testghi',
        'mode' => 'osu',
        'is_badge' => false,
        'role_mappings' => [],
        'is_active' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    // Should use general channel and not have role pings
    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://discord.com/api/webhooks/789/testghi'
            && empty($body['allowed_mentions']['roles'] ?? []);
    });
});

test('does not post if no matching channel configured', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Mania Tournament',
        'modes' => ['mania'],
        'is_badge' => true,
        'status' => 'pending_review',
    ]);

    Http::fake();

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    // No HTTP request should have been made
    Http::assertNothingSent();
});

test('inactive channels are not used', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Catch Tournament',
        'modes' => ['catch'],
        'is_badge' => true,
        'status' => 'pending_review',
    ]);

    // Create inactive channel
    DiscordChannel::factory()->create([
        'channel_name' => 'catch-badge-tournaments',
        'webhook_url' => 'https://discord.com/api/webhooks/999/inactive',
        'mode' => 'catch',
        'is_badge' => true,
        'is_active' => false,
    ]);

    Http::fake();

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    // Inactive channel should not be used
    Http::assertNothingSent();
});

test('skip discord webhook sends to no central destinations', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Skipped Discord Tournament',
        'modes' => ['osu'],
        'is_badge' => true,
        'status' => 'pending_review',
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament), [
            'skip_discord_webhook' => true,
        ])
        ->assertStatus(200);

    Http::assertNothingSent();
});

test('DiscordService builds rich embed for tournament', function () {
    $service = app(DiscordService::class);

    $tournament = [
        'title' => 'Awesome Tournament 2024',
        'description' => 'An amazing osu! tournament',
        'mode' => 'osu',
        'is_badge' => true,
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
        'registration_end' => now()->addDays(7)->timestamp,
        'banner_url' => 'https://example.com/banner.png',
        'tournament_url' => 'http://localhost/tournaments/123',
        'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/123456',
    ];

    $embed = $service->buildTournamentEmbed($tournament, 'new_approval');

    expect($embed)->toBeArray();
    expect($embed['title'])->toBe('Awesome Tournament 2024');
    expect($embed['url'])->toBe('http://localhost/tournaments/123');
    expect($embed['image']['url'])->toBe('https://example.com/banner.png');
    expect($embed['color'])->toBe(0x00FF00); // Green for new_approval
    expect($embed['fields'])->toBeArray();

    $linksField = collect($embed['fields'])->firstWhere('name', 'Links');
    expect($linksField['value'])->toBe('[Forum Post](https://osu.ppy.sh/community/forums/topics/123456)');
});

test('posts multi-mode tournament to all relevant channels', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Multi-Mode Hybrid Tournament',
        'modes' => ['osu', 'taiko'],
        'is_badge' => true,
        'rank_range_min' => 1000,
        'rank_range_max' => 5000,
        'status' => 'pending_review',
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    // Should post to BOTH osu and taiko channels
    $osuChannelCalled = false;
    $taikoChannelCalled = false;

    Http::assertSent(function ($request) use (&$osuChannelCalled, &$taikoChannelCalled) {
        if ($request->url() === 'https://discord.com/api/webhooks/123/testabc') {
            $osuChannelCalled = true;
            $body = json_decode($request->body(), true);

            return str_contains($body['content'] ?? '', '<@&222222222222222222>');
        }
        if ($request->url() === 'https://discord.com/api/webhooks/456/testdef') {
            $taikoChannelCalled = true;

            return true;
        }

        return false;
    });

    expect($osuChannelCalled)->toBeTrue('osu channel should be called');
    expect($taikoChannelCalled)->toBeTrue('taiko channel should be called');
});

test('handles multi-mode tournament with enhanced mode format', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Enhanced Format Tournament',
        'modes' => [
            ['mode' => 'osu'],
            ['mode' => 'taiko', 'key_count' => 4],
        ],
        'is_badge' => true,
        'status' => 'pending_review',
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    // Should post to both channels despite enhanced format
    $channelsCalled = [];

    Http::assertSent(function ($request) use (&$channelsCalled) {
        $channelsCalled[] = $request->url();

        return true;
    });

    expect($channelsCalled)->toContain('https://discord.com/api/webhooks/123/testabc');
    expect($channelsCalled)->toContain('https://discord.com/api/webhooks/456/testdef');
});

test('deduplicates duplicate modes in multi-mode tournament', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'title' => 'Duplicate Modes Tournament',
        'modes' => ['osu', 'osu', 'taiko'], // osu appears twice
        'is_badge' => true,
        'status' => 'pending_review',
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.approve', $tournament))
        ->assertStatus(200);

    // Should only post once to osu channel (deduplicated)
    $osuChannelCallCount = 0;

    Http::assertSent(function ($request) use (&$osuChannelCallCount) {
        if ($request->url() === 'https://discord.com/api/webhooks/123/testabc') {
            $osuChannelCallCount++;
        }

        return true;
    });

    expect($osuChannelCallCount)->toBe(1, 'osu channel should only be called once despite duplicate mode');
});

test('DiscordChannel returns correct role pings for rank range', function () {
    $channel = DiscordChannel::where('channel_name', 'osu-badge-tournaments')->first();

    // Test overlapping range - should return 1000+ role (highest threshold <= 1000)
    $pings = $channel->getRolePings(1000, 5000);
    expect($pings)->not->toBeEmpty();
    expect($pings[0])->toBe('<@&222222222222222222>');

    // Test range that starts at 1 - should return the highest threshold role
    // Since thresholds are 1, 1000, 5000, the highest <= 1 is 1
    $pings = $channel->getRolePings(1, 10000);
    expect($pings)->toHaveCount(1);
    expect($pings[0])->toBe('<@&111111111111111111>');

    // Test range that overlaps with the 1-999 role
    $pings = $channel->getRolePings(1, 500);
    expect($pings)->not->toBeEmpty();
    expect($pings[0])->toBe('<@&111111111111111111>');

    // Test open range - should ping threshold 1 role
    $pings = $channel->getRolePings(null, null);
    expect($pings)->toHaveCount(1);
    expect($pings[0])->toBe('<@&111111111111111111>');
});
