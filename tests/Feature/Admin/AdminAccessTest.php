<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('player cannot access tournament review queue', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $response = $this->actingAs($player)->get('/admin/tournaments/pending');

    $response->assertForbidden(); // 403 per OpenAPI spec
});

test('player cannot approve tournament', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($player)->post("/admin/tournaments/{$tournament->id}/approve");

    $response->assertForbidden(); // 403 per OpenAPI spec
});

test('player cannot reject tournament', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($player)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => 'Test',
    ]);

    $response->assertForbidden(); // 403 per OpenAPI spec
});

test('player cannot update tournament', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($player)->patch("/admin/tournaments/{$tournament->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertForbidden(); // 403 per OpenAPI spec
});

test('player cannot view audit logs', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $response = $this->actingAs($player)->get('/admin/audit-log'); // OpenAPI uses singular

    $response->assertForbidden(); // 403 per OpenAPI spec
});

test('player cannot view tournament for review', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($player)->get("/admin/tournaments/{$tournament->id}");

    $response->assertForbidden(); // 403 per OpenAPI spec
});

test('admin can access tournament review queue', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->get('/admin/tournaments/pending');

    $response->assertOk(); // 200 per OpenAPI spec
});

test('admin can view tournament for review', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)->get("/admin/tournaments/{$tournament->id}");

    $response->assertOk(); // 200 per OpenAPI spec
});

test('admin can approve tournament', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $response->assertOk(); // 200 per OpenAPI spec
    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'status' => 'approved',
    ]);
});

test('admin can reject tournament', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => 'Test reason', // OpenAPI uses 'reason' field
    ]);

    $response->assertOk(); // 200 per OpenAPI spec
    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'status' => 'rejected',
    ]);
});

test('admin can update tournament', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Old Title',
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)->patch("/admin/tournaments/{$tournament->id}", [
        'title' => 'New Title',
    ]);

    $response->assertOk(); // 200 per OpenAPI spec
    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'title' => 'New Title',
    ]);
});

test('admin can view audit logs', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->get('/admin/audit-log'); // OpenAPI uses singular

    $response->assertOk(); // 200 per OpenAPI spec
});

test('master can access all admin routes', function () {
    $master = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'master',
    ]);

    $queueResponse = $this->actingAs($master)->get('/admin/tournaments/pending');
    $auditResponse = $this->actingAs($master)->get('/admin/audit-log');

    $queueResponse->assertOk();
    $auditResponse->assertOk();
});

test('master can approve and reject tournaments', function () {
    $master = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'master',
    ]);

    $tournament1 = Tournament::factory()->create(['status' => 'pending_review']);
    $tournament2 = Tournament::factory()->create(['status' => 'pending_review']);

    $approveResponse = $this->actingAs($master)->post("/admin/tournaments/{$tournament1->id}/approve");
    $rejectResponse = $this->actingAs($master)->post("/admin/tournaments/{$tournament2->id}/reject", [
        'reason' => 'Test',
    ]);

    $approveResponse->assertOk();
    $rejectResponse->assertOk();

    $this->assertDatabaseHas('tournaments', ['id' => $tournament1->id, 'status' => 'approved']);
    $this->assertDatabaseHas('tournaments', ['id' => $tournament2->id, 'status' => 'rejected']);
});

test('guest cannot access tournament review queue', function () {
    $response = $this->get('/admin/tournaments/pending');

    $response->assertStatus(401); // Unauthorized per OpenAPI spec
});

test('guest cannot approve tournament', function () {
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    $response = $this->post("/admin/tournaments/{$tournament->id}/approve");

    $response->assertStatus(401); // Unauthorized per OpenAPI spec
});

test('guest cannot reject tournament', function () {
    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    $response = $this->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => 'Test',
    ]);

    $response->assertStatus(401); // Unauthorized per OpenAPI spec
});

test('guest cannot view audit log', function () {
    $response = $this->get('/admin/audit-log');

    $response->assertStatus(401); // Unauthorized per OpenAPI spec
});

test('admin with completed setup can access admin routes', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu', // Setup complete
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->get('/admin/tournaments/pending');

    $response->assertOk();
});

test('role middleware properly validates admin role', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $adminResponse = $this->actingAs($admin)->get('/admin/tournaments/pending');
    $playerResponse = $this->actingAs($player)->get('/admin/tournaments/pending');

    $adminResponse->assertOk();
    $playerResponse->assertForbidden();
});

test('role middleware properly validates master role', function () {
    $master = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'master',
    ]);

    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $masterResponse = $this->actingAs($master)->get('/admin/tournaments/pending');
    $playerResponse = $this->actingAs($player)->get('/admin/tournaments/pending');

    $masterResponse->assertOk();
    $playerResponse->assertForbidden();
});

test('admin routes require both auth and role middleware', function () {
    // Test unauthenticated - should return 401
    $guestResponse = $this->get('/admin/tournaments/pending');
    $guestResponse->assertStatus(401);

    // Test authenticated but wrong role - should return 403
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);
    $playerResponse = $this->actingAs($player)->get('/admin/tournaments/pending');
    $playerResponse->assertForbidden();

    // Test authenticated with correct role - should return 200
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);
    $adminResponse = $this->actingAs($admin)->get('/admin/tournaments/pending');
    $adminResponse->assertOk();
});

test('admin access logged in audit trail', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'tournament.approved',
    ]);
});

test('failed admin access not logged in audit trail', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    $this->actingAs($player)->post("/admin/tournaments/{$tournament->id}/approve");

    // Should not create audit log for failed access
    $this->assertDatabaseMissing('admin_audit_logs', [
        'admin_id' => $player->id,
    ]);
});
