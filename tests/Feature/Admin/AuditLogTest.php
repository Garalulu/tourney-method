<?php

use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit log records admin user id', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);
});

test('audit log records action type', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $auditLog = AdminAuditLog::where('entity_id', $tournament->id)->first();

    expect($auditLog->action)->toBe('tournament.approved');
});

test('audit log records entity type and id', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $auditLog = AdminAuditLog::where('admin_id', $admin->id)->first();

    expect($auditLog->entity_type)->toBe(Tournament::class);
    expect($auditLog->entity_id)->toBe($tournament->id);
});

test('audit log records IP address', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $auditLog = AdminAuditLog::where('admin_id', $admin->id)->first();

    expect($auditLog->ip_address)->not->toBeNull();
});

test('audit log records timestamp', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $beforeTime = now()->subSecond(); // Give 1 second buffer for database precision
    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");
    $afterTime = now()->addSecond(); // Give 1 second buffer for database precision

    $auditLog = AdminAuditLog::where('admin_id', $admin->id)->first();

    // Check timestamp exists and is recent (within buffer range)
    expect($auditLog->created_at)->not->toBeNull();
    expect($auditLog->created_at->timestamp)->toBeGreaterThanOrEqual($beforeTime->timestamp);
    expect($auditLog->created_at->timestamp)->toBeLessThanOrEqual($afterTime->timestamp);
});

test('audit log stores additional details as JSON', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $rejectionReason = 'Duplicate tournament';

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => $rejectionReason, // OpenAPI uses 'reason' field
    ]);

    $auditLog = AdminAuditLog::where('action', 'tournament.rejected')->first();

    expect($auditLog->details)->toBeArray();
    expect($auditLog->details)->toHaveKey('rejection_reason');
    expect($auditLog->details['rejection_reason'])->toBe($rejectionReason);
});

test('update action records field changes', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Old Title',
        'rank_range_min' => 1000,
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)->patch("/admin/tournaments/{$tournament->id}", [
        'title' => 'New Title',
        'rank_range_min' => 2000,
        'status' => 'pending_review',
    ]);

    $auditLog = AdminAuditLog::where('action', 'tournament.updated')->first();

    expect($auditLog->details)->toHaveKey('changes');
    expect($auditLog->details['changes'])->toBeArray();
    expect($auditLog->details['changes']['title'])->toEqual([
        'old' => 'Old Title',
        'new' => 'New Title',
    ]);
    expect($auditLog->details['changes']['rank_range_min'])->toEqual([
        'old' => 1000,
        'new' => 2000,
    ]);
    expect($auditLog->details['changes'])->not->toHaveKey('status');
});

test('multiple actions create separate audit log entries', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament1 = Tournament::factory()->create(['status' => 'pending_review']);
    $tournament2 = Tournament::factory()->create(['status' => 'pending_review']);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament1->id}/approve");
    $this->actingAs($admin)->post("/admin/tournaments/{$tournament2->id}/reject", [
        'reason' => 'Test', // OpenAPI uses 'reason' field
    ]);

    $logs = AdminAuditLog::where('admin_id', $admin->id)->get();

    expect($logs)->toHaveCount(2);
});

test('different admins have separate audit logs', function () {
    $admin1 = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $admin2 = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);

    $tournament1 = Tournament::factory()->create(['status' => 'pending_review']);
    $tournament2 = Tournament::factory()->create(['status' => 'pending_review']);

    $this->actingAs($admin1)->post("/admin/tournaments/{$tournament1->id}/approve");
    $this->actingAs($admin2)->post("/admin/tournaments/{$tournament2->id}/approve");

    $admin1Logs = AdminAuditLog::where('admin_id', $admin1->id)->get();
    $admin2Logs = AdminAuditLog::where('admin_id', $admin2->id)->get();

    expect($admin1Logs)->toHaveCount(1);
    expect($admin2Logs)->toHaveCount(1);
    expect($admin1Logs->first()->admin_id)->toBe($admin1->id);
    expect($admin2Logs->first()->admin_id)->toBe($admin2->id);
});

test('audit log has relationship to admin user', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
        'username' => 'TestAdmin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $auditLog = AdminAuditLog::where('entity_id', $tournament->id)->first();

    expect($auditLog->admin)->not->toBeNull();
    expect($auditLog->admin->username)->toBe('TestAdmin');
});

test('audit log has morphTo relationship to entity', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $auditLog = AdminAuditLog::where('entity_id', $tournament->id)->first();

    expect($auditLog->entity)->not->toBeNull();
    expect($auditLog->entity)->toBeInstanceOf(Tournament::class);
    expect($auditLog->entity->title)->toBe('Test Tournament');
});

test('admin can view audit log history', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    // Create some audit log entries
    $this->actingAs($admin)->patch("/admin/tournaments/{$tournament->id}", [
        'title' => 'Updated Title',
    ]);
    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $response = $this->actingAs($admin)->get('/admin/audit-log'); // OpenAPI uses singular

    $response->assertStatus(200);
});

test('audit log entries are ordered by most recent first', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament1 = Tournament::factory()->create(['status' => 'pending_review']);

    // Time travel to ensure different timestamps (no sleep needed)
    $this->travel(1)->seconds();

    $tournament2 = Tournament::factory()->create(['status' => 'pending_review']);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament1->id}/approve");

    // Time travel again for second approval
    $this->travel(1)->seconds();

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament2->id}/approve");

    $logs = AdminAuditLog::where('admin_id', $admin->id)
        ->orderBy('created_at', 'desc')
        ->get();

    expect($logs->first()->entity_id)->toBe($tournament2->id);
    expect($logs->last()->entity_id)->toBe($tournament1->id);
});

test('audit log retains data even if entity is deleted', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create(['status' => 'pending_review']);

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $tournamentId = $tournament->id;
    $tournament->delete();

    // Audit log should still exist
    $this->assertDatabaseHas('admin_audit_logs', [
        'entity_type' => Tournament::class,
        'entity_id' => $tournamentId,
    ]);
});

test('non-admin cannot view audit logs', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $response = $this->actingAs($player)->get('/admin/audit-log'); // OpenAPI uses singular

    $response->assertForbidden();
});

test('audit log helper method creates log entry', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create();

    // Use the static log helper method
    AdminAuditLog::log($admin, 'tournament.custom_action', $tournament, [
        'custom_field' => 'custom_value',
    ]);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'tournament.custom_action',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $auditLog = AdminAuditLog::where('action', 'tournament.custom_action')->first();
    expect($auditLog->details['custom_field'])->toBe('custom_value');
});
