<?php

/**
 * Webhook Test Endpoint Tests - Based on OpenAPI Contract
 *
 * Tests webhook test endpoint per contracts/openapi.yaml:
 *
 * POST /notifications/webhook/test → 200, 400 BadRequest, or 401 Unauthorized
 *
 * Response:
 * - 200: Test message sent successfully
 * - 400: Webhook not configured or invalid (Error schema: { message: string })
 * - 401: Unauthorized
 */

use App\Models\User;
use App\Services\DiscordService;
use Mockery\MockInterface;

describe('POST /notifications/webhook/test', function () {
    it('returns 200 when test message sent successfully', function () {
        // Per OpenAPI: operationId: testWebhook
        // Response: 200 - Test message sent successfully

        $user = User::factory()->withSetup('osu')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz',
            'discord_webhook_valid' => true,
        ]);

        $this->mock(DiscordService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isValidWebhookFormat')
                ->once()
                ->andReturn(true);
            $mock->shouldReceive('sendTestMessage')
                ->once()
                ->andReturn(['success' => true]);
        });

        $this->actingAs($user);

        $response = $this->postJson('/notifications/webhook/test');

        $response->assertStatus(200);
    });

    it('returns 400 when webhook not configured', function () {
        // Per OpenAPI: 400 - Webhook not configured or invalid
        // Error schema: { message: string }

        $user = User::factory()->withSetup('osu')->create([
            'discord_webhook_url' => null,
            'discord_webhook_valid' => false,
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/notifications/webhook/test');

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    });

    it('returns 400 when webhook URL is empty string', function () {
        // Per OpenAPI: 400 - Webhook not configured or invalid

        $user = User::factory()->withSetup('osu')->create([
            'discord_webhook_url' => '',
            'discord_webhook_valid' => false,
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/notifications/webhook/test');

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    });

    it('returns 400 when webhook is invalid format', function () {
        // Per OpenAPI: 400 - Webhook not configured or invalid

        $user = User::factory()->withSetup('osu')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/invalid', // Bad format
            'discord_webhook_valid' => false,
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/notifications/webhook/test');

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    });

    it('returns 400 when Discord API fails', function () {
        // Per OpenAPI: 400 - Webhook not configured or invalid

        $user = User::factory()->withSetup('osu')->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz',
            'discord_webhook_valid' => true,
        ]);

        $this->mock(DiscordService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isValidWebhookFormat')
                ->once()
                ->andReturn(true);
            $mock->shouldReceive('sendTestMessage')
                ->once()
                ->andReturn(['success' => false, 'error' => 'Discord API error']);
        });

        $this->actingAs($user);

        $response = $this->postJson('/notifications/webhook/test');

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    });

    it('returns 401 Unauthorized for unauthenticated request', function () {
        // Per OpenAPI: 401 $ref: '#/components/responses/Unauthorized'

        $response = $this->postJson('/notifications/webhook/test');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });
});
