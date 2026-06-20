<?php

/**
 * Role-Based Access Tests - Based on OpenAPI Contract
 *
 * Tests role-based access control per contracts/openapi.yaml:
 * - Admin endpoints require sessionAuth and admin/master role
 * - 401 Unauthorized for unauthenticated requests
 * - 403 Forbidden for users with insufficient permissions
 *
 * UserRole enum: [player, admin, master]
 *
 * Admin endpoints tested:
 * - GET /admin/tournaments/pending
 * - GET /admin/tournaments/{tournamentId}
 * - PATCH /admin/tournaments/{tournamentId}
 * - POST /admin/tournaments/{tournamentId}/approve
 * - POST /admin/tournaments/{tournamentId}/reject
 * - GET /admin/matches/pending
 * - POST /admin/matches/{matchId}/approve
 * - POST /admin/matches/{matchId}/reject
 * - GET /admin/audit-log
 * - GET /admin/users (Master only)
 * - PATCH /admin/users/{userId}/role (Master only)
 */

use App\Models\Tournament;
use App\Models\User;

describe('Admin Tournament Endpoints - Authentication', function () {
    it('returns 401 Unauthorized for unauthenticated GET /admin/tournaments/pending', function () {
        // Per OpenAPI: 401 $ref: '#/components/responses/Unauthorized'

        $response = $this->getJson('/admin/tournaments/pending');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns 401 Unauthorized for unauthenticated GET /admin/tournaments/{id}', function () {
        $tournament = Tournament::factory()->create();

        $response = $this->getJson("/admin/tournaments/{$tournament->id}");

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns 401 Unauthorized for unauthenticated PATCH /admin/tournaments/{id}', function () {
        $tournament = Tournament::factory()->create();

        $response = $this->patchJson("/admin/tournaments/{$tournament->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns 401 Unauthorized for unauthenticated POST /admin/tournaments/{id}/approve', function () {
        $tournament = Tournament::factory()->create();

        $response = $this->postJson("/admin/tournaments/{$tournament->id}/approve");

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns 401 Unauthorized for unauthenticated POST /admin/tournaments/{id}/reject', function () {
        $tournament = Tournament::factory()->create();

        $response = $this->postJson("/admin/tournaments/{$tournament->id}/reject", [
            'reason' => 'Test rejection',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });
});

describe('Admin Tournament Endpoints - Authorization (role: player)', function () {
    it('returns 403 Forbidden for player accessing GET /admin/tournaments/pending', function () {
        // Per OpenAPI: 403 $ref: '#/components/responses/Forbidden'

        $player = User::factory()->create(['role' => 'player']);
        $this->actingAs($player);

        $response = $this->getJson('/admin/tournaments/pending');

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });

    it('returns 403 Forbidden for player accessing GET /admin/tournaments/{id}', function () {
        $player = User::factory()->create(['role' => 'player']);
        $tournament = Tournament::factory()->create();
        $this->actingAs($player);

        $response = $this->getJson("/admin/tournaments/{$tournament->id}");

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });

    it('returns 403 Forbidden for player accessing PATCH /admin/tournaments/{id}', function () {
        $player = User::factory()->create(['role' => 'player']);
        $tournament = Tournament::factory()->create();
        $this->actingAs($player);

        $response = $this->patchJson("/admin/tournaments/{$tournament->id}", [
            'title' => 'Attempted Update',
        ]);

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });

    it('returns 403 Forbidden for player accessing POST /admin/tournaments/{id}/approve', function () {
        $player = User::factory()->create(['role' => 'player']);
        $tournament = Tournament::factory()->create();
        $this->actingAs($player);

        $response = $this->postJson("/admin/tournaments/{$tournament->id}/approve");

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });

    it('returns 403 Forbidden for player accessing POST /admin/tournaments/{id}/reject', function () {
        $player = User::factory()->create(['role' => 'player']);
        $tournament = Tournament::factory()->create();
        $this->actingAs($player);

        $response = $this->postJson("/admin/tournaments/{$tournament->id}/reject", [
            'reason' => 'Attempted rejection',
        ]);

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });
});

describe('Admin Tournament Endpoints - Authorization (role: admin)', function () {
    it('allows admin to access GET /admin/tournaments/pending', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->getJson('/admin/tournaments/pending');

        $response->assertStatus(200);
    });

    it('allows admin to access GET /admin/tournaments/{id}', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $tournament = Tournament::factory()->create(['status' => 'pending_review']);
        $this->actingAs($admin);

        $response = $this->getJson("/admin/tournaments/{$tournament->id}");

        $response->assertStatus(200);
    });

    it('allows admin to access PATCH /admin/tournaments/{id}', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $tournament = Tournament::factory()->create(['status' => 'pending_review']);
        $this->actingAs($admin);

        $response = $this->patchJson("/admin/tournaments/{$tournament->id}", [
            'title' => 'Updated by Admin',
        ]);

        $response->assertStatus(200);
    });

    it('allows admin to access POST /admin/tournaments/{id}/approve', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $tournament = Tournament::factory()->create(['status' => 'pending_review']);
        $this->actingAs($admin);

        $response = $this->postJson("/admin/tournaments/{$tournament->id}/approve");

        $response->assertStatus(200);
    });

    it('allows admin to access POST /admin/tournaments/{id}/reject', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $tournament = Tournament::factory()->create(['status' => 'pending_review']);
        $this->actingAs($admin);

        $response = $this->postJson("/admin/tournaments/{$tournament->id}/reject", [
            'reason' => 'Invalid tournament',
        ]);

        $response->assertStatus(200);
    });
});

describe('Admin Tournament Endpoints - Authorization (role: master)', function () {
    it('allows master to access GET /admin/tournaments/pending', function () {
        $master = User::factory()->create(['role' => 'master']);
        $this->actingAs($master);

        $response = $this->getJson('/admin/tournaments/pending');

        $response->assertStatus(200);
    });

    it('allows master to access all tournament admin endpoints', function () {
        $master = User::factory()->create(['role' => 'master']);
        $tournament = Tournament::factory()->create(['status' => 'pending_review']);
        $this->actingAs($master);

        // GET
        $this->getJson("/admin/tournaments/{$tournament->id}")
            ->assertStatus(200);

        // PATCH
        $this->patchJson("/admin/tournaments/{$tournament->id}", ['title' => 'Master Update'])
            ->assertStatus(200);
    });
});

describe('Admin Audit Log Endpoint', function () {
    it('returns 401 Unauthorized for unauthenticated GET /admin/audit-log', function () {
        // Per OpenAPI: GET /admin/audit-log requires sessionAuth

        $response = $this->getJson('/admin/audit-log');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns 403 Forbidden for player accessing GET /admin/audit-log', function () {
        $player = User::factory()->create(['role' => 'player']);
        $this->actingAs($player);

        $response = $this->getJson('/admin/audit-log');

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });

    it('allows admin to access GET /admin/audit-log', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->getJson('/admin/audit-log');

        $response->assertStatus(200);
    });

    it('allows master to access GET /admin/audit-log', function () {
        $master = User::factory()->create(['role' => 'master']);
        $this->actingAs($master);

        $response = $this->getJson('/admin/audit-log');

        $response->assertStatus(200);
    });
});

describe('Master-Only Endpoints - GET /admin/users', function () {
    it('returns 401 Unauthorized for unauthenticated request', function () {
        // Per OpenAPI: GET /admin/users (Master only)

        $response = $this->getJson('/admin/users');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns 403 Forbidden for player', function () {
        $player = User::factory()->create(['role' => 'player']);
        $this->actingAs($player);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });

    it('returns 403 Forbidden for admin (not master)', function () {
        // Per OpenAPI: This endpoint is Master only
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });

    it('allows master to access GET /admin/users', function () {
        $master = User::factory()->create(['role' => 'master']);
        $this->actingAs($master);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200);
    });
});

describe('Master-Only Endpoints - PATCH /admin/users/{userId}/role', function () {
    it('returns 401 Unauthorized for unauthenticated request', function () {
        // Per OpenAPI: PATCH /admin/users/{userId}/role (Master only)

        $user = User::factory()->create();

        $response = $this->patchJson("/admin/users/{$user->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });

    it('returns 403 Forbidden for admin trying to change roles', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $targetUser = User::factory()->create(['role' => 'player']);
        $this->actingAs($admin);

        $response = $this->patchJson("/admin/users/{$targetUser->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    });

    it('allows master to change user role', function () {
        $master = User::factory()->create(['role' => 'master']);
        $targetUser = User::factory()->create(['role' => 'player']);
        $this->actingAs($master);

        $response = $this->patchJson("/admin/users/{$targetUser->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role' => 'admin',
        ]);
    });

    it('returns 400 BadRequest for invalid role value', function () {
        // Per OpenAPI: role enum: [player, admin] (cannot set master via API)

        $master = User::factory()->create(['role' => 'master']);
        $targetUser = User::factory()->create(['role' => 'player']);
        $this->actingAs($master);

        $response = $this->patchJson("/admin/users/{$targetUser->id}/role", [
            'role' => 'invalid_role',
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['message', 'errors']);
    });
});
