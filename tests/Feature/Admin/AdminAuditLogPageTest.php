<?php

use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view audit log page with html response', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/audit-log');

    $response->assertStatus(200);
    $response->assertViewIs('admin.audit-log.index');
});

test('admin can get audit logs as json response', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create();

    AdminAuditLog::factory()->count(5)->for($admin, 'admin')->create([
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/audit-log');

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
});

test('admin can filter audit logs by action', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create();

    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'action' => 'tournament.approved',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);
    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'action' => 'tournament.rejected',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);
    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'action' => 'tournament.updated',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/audit-log?action=tournament.approved');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.action', 'tournament.approved');
});

test('admin can filter audit logs by entity_type', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create();

    AdminAuditLog::factory()->count(3)->for($admin, 'admin')->create([
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/audit-log?entity_type='.Tournament::class);

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
});

test('admin can filter audit logs by canonical and enum entity aliases', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create();

    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);
    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'entity_type' => 'Tournament',
        'entity_id' => $tournament->id,
    ]);
    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'entity_type' => '0',
        'entity_id' => $tournament->id,
    ]);
    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'entity_type' => 'user',
        'entity_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/audit-log?entity_type=tournament');

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
});

test('admin can filter audit logs by enum action aliases', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create();

    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'action' => 'tournament.approved',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);
    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'action' => 'staff.approve',
        'entity_type' => 'TournamentStaff',
        'entity_id' => 123,
        'details' => ['tournament_id' => $tournament->id],
    ]);
    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'action' => 'tournament.rejected',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/audit-log?action=0');

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});

test('audit log page renders readable labels and tournament targets', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create(['title' => 'Readable Cup']);

    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'action' => '2',
        'entity_type' => '0',
        'entity_id' => $tournament->id,
        'details' => [
            'changes' => [
                'title' => ['old' => 'Old Cup', 'new' => 'Readable Cup'],
            ],
        ],
    ]);

    $response = $this->actingAs($admin)->get('/admin/audit-log');

    $response->assertStatus(200);
    $response->assertSee('Update');
    $response->assertSee('Tournament');
    $response->assertSee('Readable Cup #'.$tournament->id);
    $response->assertSee('Old Cup');
    $response->assertDontSee('App\\Models\\Tournament');
    $response->assertDontSee('>0<', false);
});

test('admin can filter audit logs by admin_id', function () {
    $admin1 = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $admin2 = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create();

    AdminAuditLog::factory()->count(3)->for($admin1, 'admin')->create([
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);
    AdminAuditLog::factory()->count(2)->for($admin2, 'admin')->create([
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin1)->getJson("/admin/audit-log?admin_id={$admin1->id}");

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');

    foreach ($response->json('data') as $log) {
        expect($log['admin_id'])->toBe($admin1->id);
    }
});

test('audit logs are ordered by most recent first', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create();

    $log1 = AdminAuditLog::factory()->for($admin, 'admin')->create([
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
        'created_at' => now()->subDays(3),
    ]);

    $this->travel(1)->seconds();

    $log2 = AdminAuditLog::factory()->for($admin, 'admin')->create([
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
        'created_at' => now()->subDays(1),
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/audit-log');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.id', $log2->id);
    $response->assertJsonPath('data.1.id', $log1->id);
});

test('audit logs are paginated', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    $tournament = Tournament::factory()->create();

    AdminAuditLog::factory()->count(25)->for($admin, 'admin')->create([
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/audit-log?per_page=10');

    $response->assertStatus(200);
    $response->assertJsonCount(10, 'data');
    $response->assertJsonPath('total', 25);
});

test('player cannot view audit log page', function () {
    $player = User::factory()->create(['main_mode' => 'osu', 'role' => 'player']);

    $response = $this->actingAs($player)->get('/admin/audit-log');

    $response->assertForbidden();
});

test('guest cannot view audit log page', function () {
    $response = $this->get('/admin/audit-log');

    $response->assertStatus(401);
});
