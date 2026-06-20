<?php

/**
 * User Settings Tests - Based on OpenAPI Contract
 *
 * Tests user settings endpoints per contracts/openapi.yaml:
 *
 * GET /users/me/settings → 200 with UserSettings schema, or 401 Unauthorized
 * PATCH /users/me/settings → 200 with UserSettings schema, 400 BadRequest, or 401 Unauthorized
 *
 * UserSettings schema:
 * {
 *   main_mode: GameMode (nullable),
 *   discord_webhook_url: string (nullable),
 *   discord_webhook_valid: boolean,
 *   notify_registration: boolean,
 *   notify_stream: boolean
 * }
 *
 * UserSettingsUpdate schema:
 * {
 *   main_mode: GameMode,
 *   discord_webhook_url: string (nullable),
 *   notify_registration: boolean,
 *   notify_stream: boolean
 * }
 */

use App\Models\User;
use App\Services\DiscordService;
use Mockery\MockInterface;

describe('GET /users/me/settings', function () {
    it('returns 200 with UserSettings schema for authenticated user', function () {
        // Per OpenAPI: operationId: getMySettings
        // Response: 200 with UserSettings schema

        $user = User::factory()->withSetup('osu')->create([
            'discord_webhook_url' => null,
            'discord_webhook_valid' => false,
            'notify_registration' => true,
            'notify_stream' => true,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/users/me/settings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'main_mode',
            'discord_webhook_url',
            'discord_webhook_valid',
            'notify_registration',
            'notify_stream',
        ]);
        $response->assertJson([
            'main_mode' => 'osu',
            'discord_webhook_url' => null,
            'discord_webhook_valid' => false,
            'notify_registration' => true,
            'notify_stream' => true,
        ]);
    });

    it('returns settings with configured webhook', function () {
        // Per OpenAPI: discord_webhook_url can be string, discord_webhook_valid is boolean

        $user = User::factory()->withSetup('taiko')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc',
            'discord_webhook_valid' => true,
            'notify_registration' => false,
            'notify_stream' => true,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/users/me/settings');

        $response->assertStatus(200);
        $response->assertJson([
            'main_mode' => 'taiko',
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc',
            'discord_webhook_valid' => true,
            'notify_registration' => false,
            'notify_stream' => true,
        ]);
    });

    it('returns 401 Unauthorized for unauthenticated request', function () {
        // Per OpenAPI: 401 $ref: '#/components/responses/Unauthorized'

        $response = $this->getJson('/users/me/settings');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns all GameMode values correctly', function (string $mode) {
        // Per OpenAPI: GameMode enum: [osu, taiko, catch, mania]

        $user = User::factory()->withSetup($mode)->create();
        $this->actingAs($user);

        $response = $this->getJson('/users/me/settings');

        $response->assertStatus(200);
        $response->assertJson(['main_mode' => $mode]);
    })->with(['osu', 'taiko', 'catch', 'mania']);
});

describe('PATCH /users/me/settings', function () {
    it('returns 200 with UserSettings schema when updating main_mode', function () {
        // Per OpenAPI: operationId: updateMySettings
        // Request: UserSettingsUpdate schema
        // Response: 200 with UserSettings schema

        $user = User::factory()->withSetup('osu')->create();
        $this->actingAs($user);

        $response = $this->patchJson('/users/me/settings', [
            'main_mode' => 'taiko',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'main_mode',
            'discord_webhook_url',
            'discord_webhook_valid',
            'notify_registration',
            'notify_stream',
        ]);
        $response->assertJson(['main_mode' => 'taiko']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'main_mode' => 'taiko',
        ]);
    });

    it('accepts all valid GameMode values', function (string $mode) {
        // Per OpenAPI: GameMode enum: [osu, taiko, catch, mania]

        $user = User::factory()->withSetup('osu')->create();
        $this->actingAs($user);

        $response = $this->patchJson('/users/me/settings', [
            'main_mode' => $mode,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['main_mode' => $mode]);
    })->with(['osu', 'taiko', 'catch', 'mania']);

    it('updates notification preferences', function () {
        // Per OpenAPI: notify_registration and notify_stream are boolean

        $user = User::factory()->withSetup('osu')->create([
            'notify_registration' => true,
            'notify_stream' => true,
        ]);
        $this->actingAs($user);

        $response = $this->patchJson('/users/me/settings', [
            'notify_registration' => false,
            'notify_stream' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'notify_registration' => false,
            'notify_stream' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'notify_registration' => false,
            'notify_stream' => false,
        ]);
    });

    it('updates discord_webhook_url and validates', function () {
        // Per OpenAPI: discord_webhook_url is string (nullable)
        // Per implementation: webhook is validated and discord_webhook_valid is set

        $user = User::factory()->withSetup('osu')->create([
            'discord_webhook_url' => null,
            'discord_webhook_valid' => false,
        ]);

        $this->mock(DiscordService::class, function (MockInterface $mock) {
            $mock->shouldReceive('validateWebhook')
                ->once()
                ->andReturn(true);
        });

        $this->actingAs($user);

        $response = $this->patchJson('/users/me/settings', [
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ123456',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'discord_webhook_url',
            'discord_webhook_valid',
        ]);
        $response->assertJson([
            'discord_webhook_valid' => true,
        ]);
    });

    it('clears discord_webhook_url when null is sent', function () {
        // Per OpenAPI: discord_webhook_url is nullable

        $user = User::factory()->withSetup('osu')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc',
            'discord_webhook_valid' => true,
        ]);
        $this->actingAs($user);

        $response = $this->patchJson('/users/me/settings', [
            'discord_webhook_url' => null,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'discord_webhook_url' => null,
            'discord_webhook_valid' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'discord_webhook_url' => null,
            'discord_webhook_valid' => false,
        ]);
    });

    it('returns 400 BadRequest for invalid main_mode', function () {
        // Per OpenAPI: 400 $ref: '#/components/responses/BadRequest'

        $user = User::factory()->withSetup('osu')->create();
        $this->actingAs($user);

        $response = $this->patchJson('/users/me/settings', [
            'main_mode' => 'invalid_mode',
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'errors' => ['main_mode'],
        ]);
    });

    it('returns 400 BadRequest for invalid discord webhook URL format', function () {
        // Per OpenAPI: 400 $ref: '#/components/responses/BadRequest'

        $user = User::factory()->withSetup('osu')->create();
        $this->actingAs($user);

        $response = $this->patchJson('/users/me/settings', [
            'discord_webhook_url' => 'not-a-valid-webhook-url',
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'errors' => ['discord_webhook_url'],
        ]);
    });

    it('returns 401 Unauthorized for unauthenticated request', function () {
        // Per OpenAPI: 401 $ref: '#/components/responses/Unauthorized'

        $response = $this->patchJson('/users/me/settings', [
            'main_mode' => 'osu',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('allows partial updates (only some fields)', function () {
        // Per OpenAPI: UserSettingsUpdate fields are all optional for partial update

        $user = User::factory()->withSetup('osu')->create([
            'notify_registration' => true,
            'notify_stream' => false,
        ]);
        $this->actingAs($user);

        $response = $this->patchJson('/users/me/settings', [
            'notify_stream' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'main_mode' => 'osu', // unchanged
            'notify_registration' => true, // unchanged
            'notify_stream' => true, // updated
        ]);
    });
});
