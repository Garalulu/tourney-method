<?php

declare(strict_types=1);

/**
 * Tournament Watch Tests per OpenAPI Contract
 *
 * Tests for:
 * - POST /tournaments/{tournamentId}/watch (watchTournament)
 * - DELETE /tournaments/{tournamentId}/watch (unwatchTournament)
 *
 * Per OpenAPI spec, watch endpoints require:
 * - Authentication (sessionAuth)
 * - watch_type enum: [watching, stream]
 * - Returns 200 on success, 401 on unauthenticated, 404 on not found
 */

use App\Models\Tournament;
use App\Models\TournamentWatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a user with completed setup
    $this->user = User::factory()->create([
        'main_mode' => 'osu',
    ]);

    $this->user->rankHistory()->create([
        'mode' => 'osu',
        'rank' => 5000,
        'pp' => 6000,
        'recorded_at' => now(),
    ]);

    // Create an approved tournament
    $this->tournament = Tournament::factory()->create([
        'status' => 'approved',
        'modes' => ['osu'],
    ]);
});

// ============================================================================
// POST /tournaments/{tournamentId}/watch - watchTournament
// ============================================================================

describe('POST /tournaments/{tournamentId}/watch', function () {
    it('returns 401 when unauthenticated', function () {
        $response = $this->postJson("/tournaments/{$this->tournament->id}/watch", [
            'watch_type' => 'watching',
        ]);

        $response->assertStatus(401);
    });

    it('returns 200 and creates watch with valid watch_type', function () {
        $response = $this->actingAs($this->user)->postJson("/tournaments/{$this->tournament->id}/watch", [
            'watch_type' => 'watching',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'watch_type']);

        $this->assertDatabaseHas('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);
    });

    it('allows watch_type = watching per OpenAPI enum', function () {
        $response = $this->actingAs($this->user)->postJson("/tournaments/{$this->tournament->id}/watch", [
            'watch_type' => 'watching',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);
    });

    it('allows watch_type = stream per OpenAPI enum', function () {
        $response = $this->actingAs($this->user)->postJson("/tournaments/{$this->tournament->id}/watch", [
            'watch_type' => 'stream',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'stream',
        ]);
    });

    it('rejects invalid watch_type values', function () {
        $response = $this->actingAs($this->user)->postJson("/tournaments/{$this->tournament->id}/watch", [
            'watch_type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    });

    it('rejects missing watch_type (required per OpenAPI)', function () {
        $response = $this->actingAs($this->user)->postJson("/tournaments/{$this->tournament->id}/watch", []);

        $response->assertStatus(422);
    });

    it('updates existing watch when called again', function () {
        // Create initial watch
        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        // Update to different type
        $response = $this->actingAs($this->user)->postJson("/tournaments/{$this->tournament->id}/watch", [
            'watch_type' => 'stream',
        ]);

        $response->assertStatus(200);

        // Should only have one record with updated type
        expect(TournamentWatch::where('user_id', $this->user->id)
            ->where('tournament_id', $this->tournament->id)
            ->count()
        )->toBe(1);

        $this->assertDatabaseHas('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'stream',
        ]);
    });

    it('returns 404 for non-existent tournament', function () {
        $response = $this->actingAs($this->user)->postJson('/tournaments/999999/watch', [
            'watch_type' => 'watching',
        ]);

        $response->assertStatus(404);
    });

    it('returns 404 for non-approved tournament', function () {
        $pendingTournament = Tournament::factory()->create([
            'status' => 'pending_review',
            'modes' => ['osu'],
        ]);

        $response = $this->actingAs($this->user)->postJson("/tournaments/{$pendingTournament->id}/watch", [
            'watch_type' => 'watching',
        ]);

        $response->assertStatus(404);
    });
});

// ============================================================================
// DELETE /tournaments/{tournamentId}/watch - unwatchTournament
// ============================================================================

describe('DELETE /tournaments/{tournamentId}/watch', function () {
    it('returns 401 when unauthenticated', function () {
        $response = $this->deleteJson("/tournaments/{$this->tournament->id}/watch");

        $response->assertStatus(401);
    });

    it('returns 200 and removes watch', function () {
        // Create initial watch
        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/tournaments/{$this->tournament->id}/watch");

        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
        ]);
    });

    it('returns 200 even when no watch exists (idempotent)', function () {
        // No watch exists
        $response = $this->actingAs($this->user)->deleteJson("/tournaments/{$this->tournament->id}/watch");

        $response->assertStatus(200);
    });

    it('only removes watch for authenticated user', function () {
        // Create watches for multiple users
        $otherUser = User::factory()->create(['main_mode' => 'osu']);
        $otherUser->rankHistory()->create([
            'mode' => 'osu',
            'rank' => 5000,
            'pp' => 6000,
            'recorded_at' => now(),
        ]);

        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        TournamentWatch::create([
            'user_id' => $otherUser->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'stream',
        ]);

        // Delete as this user
        $response = $this->actingAs($this->user)->deleteJson("/tournaments/{$this->tournament->id}/watch");

        $response->assertStatus(200);

        // User's watch should be removed
        $this->assertDatabaseMissing('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
        ]);

        // Other user's watch should still exist
        $this->assertDatabaseHas('tournament_watches', [
            'user_id' => $otherUser->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'stream',
        ]);
    });
});

// ============================================================================
// user_watch_status in TournamentDetail response
// ============================================================================

describe('TournamentDetail user_watch_status', function () {
    it('returns null for guest users', function () {
        $response = $this->getJson("/tournaments/{$this->tournament->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'user_watch_status' => null,
            ],
        ]);
    });

    it('returns null when user has no watch', function () {
        $response = $this->actingAs($this->user)->getJson("/tournaments/{$this->tournament->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'user_watch_status' => null,
            ],
        ]);
    });

    it('returns watch_type when user has watch', function () {
        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        $response = $this->actingAs($this->user)->getJson("/tournaments/{$this->tournament->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'user_watch_status' => 'watching',
            ],
        ]);
    });
});
