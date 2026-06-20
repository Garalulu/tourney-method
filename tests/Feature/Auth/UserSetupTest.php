<?php

/**
 * User Setup Tests - Based on OpenAPI Contract
 *
 * Tests user setup endpoint per contracts/openapi.yaml:
 * - POST /users/setup → 200 with User schema, 400 BadRequest, or 401 Unauthorized
 *
 * Request body schema:
 * {
 *   main_mode: GameMode (required) - enum: [osu, taiko, catch, mania]
 * }
 *
 * Response User schema:
 * {
 *   id: integer,
 *   osu_id: integer,
 *   username: string,
 *   avatar_url: string,
 *   country_code: string,
 *   main_mode: GameMode,
 *   role: UserRole,
 *   setup_complete: boolean,
 *   created_at: date-time
 * }
 */

use App\Models\User;

describe('POST /users/setup', function () {
    it('returns 200 with User schema when setup completes successfully', function () {
        // Per OpenAPI: operationId: completeSetup
        // Request: { main_mode: GameMode }
        // Response: 200 with User schema

        $user = User::factory()->create([
            'main_mode' => null, // User hasn't completed setup
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/users/setup', [
            'main_mode' => 'osu',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'osu_id',
            'username',
            'avatar_url',
            'country_code',
            'main_mode',
            'role',
            'setup_complete',
            'created_at',
        ]);
        $response->assertJson([
            'main_mode' => 'osu',
            'setup_complete' => true,
        ]);

        // Verify database updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'main_mode' => 'osu',
        ]);
    });

    it('accepts all valid GameMode values', function (string $mode) {
        // Per OpenAPI: GameMode enum: [osu, taiko, catch, mania]

        $user = User::factory()->create(['main_mode' => null]);
        $this->actingAs($user);

        $response = $this->postJson('/users/setup', [
            'main_mode' => $mode,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'main_mode' => $mode,
            'setup_complete' => true,
        ]);
    })->with(['osu', 'taiko', 'catch', 'mania']);

    it('returns 400 BadRequest when main_mode is missing', function () {
        // Per OpenAPI: 400 $ref: '#/components/responses/BadRequest'
        // Error schema: { message: string, errors?: { field: [string] } }

        $user = User::factory()->create(['main_mode' => null]);
        $this->actingAs($user);

        $response = $this->postJson('/users/setup', []);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'errors' => ['main_mode'],
        ]);
    });

    it('returns 400 BadRequest when main_mode is invalid', function () {
        // Per OpenAPI: GameMode must be one of: osu, taiko, catch, mania

        $user = User::factory()->create(['main_mode' => null]);
        $this->actingAs($user);

        $response = $this->postJson('/users/setup', [
            'main_mode' => 'invalid_mode',
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'errors' => ['main_mode'],
        ]);
    });

    it('returns 401 Unauthorized for unauthenticated request', function () {
        // Per OpenAPI: 401 $ref: '#/components/responses/Unauthorized'
        // Error schema: { message: string }

        $response = $this->postJson('/users/setup', [
            'main_mode' => 'osu',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns User with setup_complete true after successful setup', function () {
        // Per OpenAPI: User.setup_complete should be true after setup

        $user = User::factory()->create(['main_mode' => null]);
        $this->actingAs($user);

        $response = $this->postJson('/users/setup', [
            'main_mode' => 'taiko',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'setup_complete' => true,
        ]);
    });

    it('preserves existing user data when updating main_mode', function () {
        // Setup should only update main_mode, preserving other fields

        $user = User::factory()->create([
            'osu_id' => 99999999,
            'username' => 'PreservedUsername',
            'country_code' => 'GB',
            'main_mode' => null,
            'role' => 'player',
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/users/setup', [
            'main_mode' => 'catch',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'osu_id' => 99999999,
            'username' => 'PreservedUsername',
            'avatar_url' => 'https://a.ppy.sh/99999999',
            'country_code' => 'GB',
            'main_mode' => 'catch',
            'role' => 'player',
        ]);
    });
});

describe('GET /users/setup (web form)', function () {
    it('shows setup form for authenticated user without main_mode', function () {
        $user = User::factory()->create(['main_mode' => null]);
        $this->actingAs($user);

        $response = $this->get('/users/setup');

        $response->assertStatus(200);
        $response->assertViewIs('auth.setup');
    });

    it('redirects to dashboard if user already completed setup', function () {
        $user = User::factory()->create([
            'main_mode' => 'osu',
            'main_mode_source' => 'oauth_setup',
        ]);
        $this->actingAs($user);

        $response = $this->get('/users/setup');

        $response->assertRedirect(route('dashboard'));
    });

    it('redirects to login for unauthenticated users', function () {
        $response = $this->get('/users/setup');

        $response->assertRedirect(route('login'));
    });
});
