<?php

use App\Jobs\PostTournamentToDiscordJob;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

test('admin can approve pending tournament', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $response->assertOk();

    // Verify tournament status updated
    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'status' => 'approved',
        'reviewed_by' => $admin->id,
    ]);

    // Verify reviewed_at timestamp is set
    $tournament->refresh();
    expect($tournament->reviewed_at)->not->toBeNull();

    // Verify Discord job was dispatched (default behavior)
    Bus::assertDispatched(PostTournamentToDiscordJob::class, function ($job) use ($tournament) {
        return $job->tournamentId === $tournament->id;
    });
});

test('admin can reject pending tournament with reason', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'pending_review',
    ]);

    $rejectionReason = 'Duplicate tournament - already exists in system';

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => $rejectionReason, // OpenAPI spec uses 'reason' not 'rejection_reason'
    ]);

    $response->assertOk();

    // Verify tournament status and rejection reason
    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'status' => 'rejected',
        'rejection_reason' => $rejectionReason,
        'reviewed_by' => $admin->id,
    ]);

    $tournament->refresh();
    expect($tournament->reviewed_at)->not->toBeNull();
});

test('rejection reason is optional', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => '',
    ]);

    $response->assertStatus(200);

    // Verify tournament is rejected with default reason
    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'status' => 'rejected',
        'rejection_reason' => 'No reason provided',
    ]);
});

test('admin can update tournament fields before approval', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Old Title',
        'status' => 'pending_review',
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
    ]);

    $response = $this->actingAs($admin)->patch("/admin/tournaments/{$tournament->id}", [
        'title' => 'Updated Title',
        'rank_range_min' => 2000,
        'rank_range_max' => 8000,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'title' => 'Updated Title',
        'rank_range_min' => 2000,
        'rank_range_max' => 8000,
    ]);
});

test('cannot approve already approved tournament', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->approved()->create();

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $response->assertStatus(400); // Bad Request per OpenAPI spec
});

test('cannot reject already approved tournament', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->approved()->create();

    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => 'Test reason',
    ]);

    $response->assertStatus(400); // Bad Request per OpenAPI spec
});

test('approval creates audit log entry', function () {
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
        'action' => 'tournament.approved',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);
});

test('rejection creates audit log entry', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $rejectionReason = 'Duplicate tournament';

    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => $rejectionReason,
    ]);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'tournament.rejected',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    // Verify rejection reason in audit log details
    $auditLog = AdminAuditLog::where('entity_id', $tournament->id)
        ->where('action', 'tournament.rejected')
        ->first();

    expect($auditLog->details)->toHaveKey('rejection_reason');
    expect($auditLog->details['rejection_reason'])->toBe($rejectionReason);
});

test('update creates audit log entry with changes', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Old Title',
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)->patch("/admin/tournaments/{$tournament->id}", [
        'title' => 'New Title',
    ]);

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'tournament.updated',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    // Verify changes tracked in audit log
    $auditLog = AdminAuditLog::where('entity_id', $tournament->id)
        ->where('action', 'tournament.updated')
        ->first();

    expect($auditLog->details)->toHaveKey('changes');
});

test('non-admin cannot approve tournament', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($player)->post("/admin/tournaments/{$tournament->id}/approve");

    $response->assertForbidden();

    // Verify tournament unchanged
    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'status' => 'pending_review',
    ]);
});

test('non-admin cannot reject tournament', function () {
    $player = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'player',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($player)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => 'Test reason',
    ]);

    $response->assertForbidden();
});

test('master can approve tournament', function () {
    $master = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'master',
    ]);

    $tournament = Tournament::factory()->create([
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($master)->post("/admin/tournaments/{$tournament->id}/approve");

    $response->assertOk();

    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'status' => 'approved',
        'reviewed_by' => $master->id,
    ]);
});

test('approved tournament becomes visible in public listing', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'pending_review',
        'modes' => ['osu'],
        'tournament_end' => now()->addMonth(),
    ]);

    // Verify not visible before approval
    $beforeResponse = $this->getJson('/tournaments');
    expect(collect($beforeResponse->json('data'))->pluck('id'))->not->toContain($tournament->id);

    // Approve tournament
    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    // Verify visible after approval
    $afterResponse = $this->getJson('/tournaments');
    expect(collect($afterResponse->json('data'))->pluck('id'))->toContain($tournament->id);
});

test('rejected tournament remains hidden from public listing', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'pending_review',
    ]);

    // Reject tournament
    $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/reject", [
        'reason' => 'Duplicate',
    ]);

    // Verify not visible
    $response = $this->get('/tournaments');
    $response->assertDontSee('Test Tournament');
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

test('admin can approve tournament without discord webhook', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)->postJson("/admin/tournaments/{$tournament->id}/approve", [
        'skip_discord_webhook' => true,
    ]);

    $response->assertOk();

    // Verify tournament approved
    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'status' => 'approved',
        'reviewed_by' => $admin->id,
    ]);

    // Verify Discord job was NOT dispatched
    Bus::assertNotDispatched(PostTournamentToDiscordJob::class);
});

test('admin can explicitly send discord webhook on approval', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($admin)->postJson("/admin/tournaments/{$tournament->id}/approve", [
        'skip_discord_webhook' => false,
    ]);

    $response->assertOk();

    // Verify Discord job was dispatched
    Bus::assertDispatched(PostTournamentToDiscordJob::class, function ($job) use ($tournament) {
        return $job->tournamentId === $tournament->id;
    });
});

test('approval defaults to sending discord webhook', function () {
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'pending_review',
    ]);

    // Approve without any skip_discord_webhook parameter
    $response = $this->actingAs($admin)->post("/admin/tournaments/{$tournament->id}/approve");

    $response->assertOk();

    // Verify Discord job was dispatched by default
    Bus::assertDispatched(PostTournamentToDiscordJob::class);
});
