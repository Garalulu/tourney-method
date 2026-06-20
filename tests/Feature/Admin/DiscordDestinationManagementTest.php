<?php

use App\Models\AdminAuditLog;
use App\Models\DiscordChannel;
use App\Models\DiscordServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('admin is redirected from discord destinations page to servers', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/discord-destinations')
        ->assertRedirect('/admin/discord-servers');
});

test('admin can create discord server with destinations', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.discord-servers.store'), [
            'server_name' => 'Main Server',
            'send_unmatched_rank_alerts' => '1',
            'destinations' => [
                [
                    'channel_name' => 'osu-badge-extra',
                    'webhook_url' => 'https://discord.com/api/webhooks/123456789/createabc',
                    'mode' => 'osu',
                    'is_badge' => '1',
                    'is_active' => '1',
                ],
            ],
        ]);

    $response->assertRedirect(route('admin.discord-servers.index'));

    $server = DiscordServer::query()->where('server_name', 'Main Server')->firstOrFail();

    $destination = DiscordChannel::query()->where('channel_name', 'osu-badge-extra')->firstOrFail();

    expect($destination->discord_server_id)->toBe($server->id);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'discord_destination.created',
        'entity_type' => DiscordChannel::class,
        'entity_id' => $destination->id,
    ]);
});

test('admin can update add and remove discord server destinations', function () {
    $admin = User::factory()->admin()->create();
    $server = DiscordServer::factory()->create(['server_name' => 'Secondary Server']);
    $destination = DiscordChannel::factory()->create([
        'discord_server_id' => $server->id,
        'channel_name' => 'catch-general',
        'webhook_url' => 'https://discord.com/api/webhooks/123456789/original',
        'mode' => 'catch',
        'is_badge' => false,
        'is_active' => true,
    ]);
    $removedDestination = DiscordChannel::factory()->create([
        'discord_server_id' => $server->id,
        'channel_name' => 'mania-general-delete',
        'webhook_url' => 'https://discord.com/api/webhooks/123456789/delete',
        'mode' => 'mania',
        'is_badge' => false,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.discord-servers.update', $server), [
            'server_name' => 'Secondary Server',
            'send_unmatched_rank_alerts' => '0',
            'destinations' => [
                [
                    'id' => $destination->id,
                    'channel_name' => 'catch-general-disabled',
                    'webhook_url' => null,
                    'mode' => 'catch',
                    'is_badge' => '0',
                    'is_active' => '0',
                ],
                [
                    'channel_name' => 'osu-badge-added',
                    'webhook_url' => 'https://discord.com/api/webhooks/123456789/added',
                    'mode' => 'osu',
                    'is_badge' => '1',
                    'is_active' => '1',
                ],
            ],
        ])
        ->assertRedirect(route('admin.discord-servers.index'));

    $destination->refresh();

    expect($destination->channel_name)->toBe('catch-general-disabled')
        ->and($destination->discord_server_id)->toBe($server->id)
        ->and($destination->is_active)->toBeFalse()
        ->and($destination->webhook_url)->toBeNull()
        ->and($server->fresh()->send_unmatched_rank_alerts)->toBeFalse();

    $this->assertDatabaseMissing('discord_channels', [
        'id' => $removedDestination->id,
    ]);
    $this->assertDatabaseHas('discord_channels', [
        'channel_name' => 'osu-badge-added',
        'discord_server_id' => $server->id,
    ]);
    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'discord_destination.updated',
        'entity_type' => DiscordChannel::class,
        'entity_id' => $destination->id,
    ]);
    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'discord_destination.deleted',
        'entity_type' => DiscordChannel::class,
        'entity_id' => $removedDestination->id,
    ]);
});

test('admin can send test message and logs masked webhook details', function () {
    $admin = User::factory()->admin()->create();
    $destination = DiscordChannel::factory()->create([
        'channel_name' => 'osu-test-send',
        'webhook_url' => 'https://discord.com/api/webhooks/123456789/testsend',
        'mode' => 'osu',
        'is_badge' => true,
    ]);

    Http::fake([
        'discord.com/api/webhooks/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.discord-destinations.test-send', $destination))
        ->assertRedirect();

    Http::assertSent(fn ($request): bool => $request->url() === $destination->webhook_url);

    $log = AdminAuditLog::where('action', 'discord_destination.tested')->firstOrFail();

    expect(data_get($log->details, 'destination.webhook_url'))->toContain('***')
        ->and(data_get($log->details, 'destination.webhook_url'))->not->toContain('testsend');
});

test('non-admin users cannot manage discord server destinations', function () {
    $player = User::factory()->player()->create();
    $server = DiscordServer::factory()->create();

    $this->actingAs($player)
        ->get(route('admin.discord-servers.index'))
        ->assertForbidden();

    $this->actingAs($player)
        ->post(route('admin.discord-servers.store'), [
            'server_name' => 'Blocked',
            'destinations' => [
                [
                    'channel_name' => 'blocked',
                    'webhook_url' => 'https://discord.com/api/webhooks/123456789/blocked',
                    'mode' => 'osu',
                    'is_active' => '1',
                ],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($player)
        ->patch(route('admin.discord-servers.update', $server), [
            'server_name' => 'Blocked',
        ])
        ->assertForbidden();
});

test('discord server destination validation rejects malformed webhook', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.discord-servers.create'))
        ->post(route('admin.discord-servers.store'), [
            'server_name' => 'Invalid Destination',
            'destinations' => [
                [
                    'channel_name' => 'invalid-webhook',
                    'webhook_url' => 'https://example.com/not-discord',
                    'mode' => 'osu',
                    'is_active' => '1',
                ],
            ],
        ])
        ->assertRedirect(route('admin.discord-servers.create'))
        ->assertSessionHasErrors([
            'destinations.0.webhook_url',
        ]);
});

test('admin can manage discord server role mappings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.discord-servers.store'), [
            'server_name' => 'Shared Role Server',
            'role_mappings' => [
                ['threshold' => '5000', 'role_id' => '555555555555555555'],
                ['threshold' => '1', 'role_id' => '111111111111111111'],
            ],
        ])
        ->assertRedirect(route('admin.discord-servers.index'));

    $server = DiscordServer::query()->where('server_name', 'Shared Role Server')->firstOrFail();

    expect($server->role_mappings)->toHaveCount(2)
        ->and($server->role_mappings[0]['threshold'])->toBe(1)
        ->and($server->role_mappings[0]['role_id'])->toBe('111111111111111111')
        ->and($server->role_mappings[1]['threshold'])->toBe(5000)
        ->and($server->role_mappings[1]['role_id'])->toBe('555555555555555555');

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'discord_server.created',
        'entity_type' => DiscordServer::class,
        'entity_id' => $server->id,
    ]);
});

test('admin can manage discord server alert countries', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.discord-servers.store'), [
            'server_name' => 'Korea Japan Server',
            'countries' => ['kr', 'JP', 'kr'],
        ])
        ->assertRedirect(route('admin.discord-servers.index'));

    $server = DiscordServer::query()->where('server_name', 'Korea Japan Server')->firstOrFail();

    expect($server->countries)->toBe(['JP', 'KR']);

    $this->actingAs($admin)
        ->patch(route('admin.discord-servers.update', $server), [
            'server_name' => 'Korea Server',
            'countries' => ['KR'],
        ])
        ->assertRedirect(route('admin.discord-servers.index'));

    expect($server->fresh()->countries)->toBe(['KR']);
});

test('discord server form renders searchable country templates', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.discord-servers.create'))
        ->assertOk()
        ->assertSee('Destinations')
        ->assertSee('Send far-rank alerts without ping')
        ->assertSee('Alert Countries')
        ->assertSee('Region Templates')
        ->assertSee('Eastern Europe')
        ->assertSee('Search country or code...');
});

test('discord server validation rejects invalid country codes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.discord-servers.create'))
        ->post(route('admin.discord-servers.store'), [
            'server_name' => 'Invalid Countries',
            'countries' => ['KR', 'XX', 'USA'],
        ])
        ->assertRedirect(route('admin.discord-servers.create'))
        ->assertSessionHasErrors([
            'countries.1',
            'countries.2',
        ]);
});

test('discord server validation rejects invalid role ids', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.discord-servers.create'))
        ->post(route('admin.discord-servers.store'), [
            'server_name' => 'Invalid Roles',
            'role_mappings' => [
                ['threshold' => '0', 'role_id' => 'not-a-snowflake'],
            ],
        ])
        ->assertRedirect(route('admin.discord-servers.create'))
        ->assertSessionHasErrors([
            'role_mappings.0.threshold',
            'role_mappings.0.role_id',
        ]);
});
