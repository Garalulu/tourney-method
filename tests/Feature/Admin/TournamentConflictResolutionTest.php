<?php

use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create admin user
    $admin = User::factory()->admin()->create();
    $this->admin = $admin;

    // Create tournament
    $this->tournament = Tournament::factory()->create([
        'forum_topic_id' => 12345,
        'title' => 'Test Tournament',
    ]);
});

afterEach(function () {
    // Clean up
    AdminAuditLog::where('action', 'tournament.reparse_resolved')->forceDelete();
});

test('admin can preview conflicts before re-parse', function () {
    // Set up tournament with manual fields
    $this->tournament->update([
        'title' => 'Manual Title',
        'description' => 'Manual Description',
        'field_sources' => [
            'title' => 'manual',
            'description' => 'manual',
        ],
    ]);

    // Simulate parsed data that conflicts
    $parsedData = [
        'title' => 'Parsed Title', // Conflicts!
        'description' => 'Parsed Description', // Conflicts!
        'rank_range_min' => 5000, // No conflict (not manual)
    ];

    $response = $this->actingAs($this->admin)
        ->getJson("/admin/tournaments/{$this->tournament->id}/conflicts?preview_data=".urlencode(json_encode($parsedData)));

    $response->assertStatus(200);

    // Should detect 2 conflicts (title and description)
    expect($response->json('conflicts'))->toHaveCount(2);

    // Verify conflict structure
    expect($response->json('conflicts')['title'])->not->toBeNull();
    expect($response->json('conflicts')['title']['current'])->toBe('Manual Title');
    expect($response->json('conflicts')['title']['parsed'])->toBe('Parsed Title');
    expect($response->json('conflicts')['title']['source'])->toBe('manual');

    expect($response->json('conflicts')['description'])->not->toBeNull();
    expect($response->json('conflicts')['description']['current'])->toBe('Manual Description');
    expect($response->json('conflicts')['description']['parsed'])->toBe('Parsed Description');
    expect($response->json('conflicts')['description']['source'])->toBe('manual');

    // rank_range_min should NOT be in conflicts
    expect($response->json('conflicts')['rank_range_min'] ?? null)->toBeNull();
});

test('admin can preview conflicts with no manual fields', function () {
    // Tournament with no manual fields
    $parsedData = [
        'title' => 'Any Title',
        'description' => 'Any Description',
    ];

    $response = $this->actingAs($this->admin)
        ->getJson("/admin/tournaments/{$this->tournament->id}/conflicts?preview_data=".urlencode(json_encode($parsedData)));

    $response->assertStatus(200);

    // Should have no conflicts
    expect($response->json('conflicts'))->toBeEmpty();
    expect($response->json('count'))->toBe(0);
});

test('admin can resolve conflicts and update', function () {
    // Set up tournament with manual fields
    $this->tournament->update([
        'title' => 'Manual Title',
        'description' => 'Manual Description',
        'host_osu_id' => 11111,
        'field_sources' => [
            'title' => 'manual',
            'description' => 'manual',
        ],
    ]);

    // Parsed data that conflicts
    $parsedData = [
        'title' => 'Parsed Title',
        'description' => 'Parsed Description',
        'host_osu_id' => 99999,
    ];

    // Resolution: Keep title manual, use parsed description, use parsed host_osu_id
    $resolution = [
        'title' => 'manual',     // Keep manual
        'description' => 'parsed',  // Use parsed
        'host_osu_id' => 'parsed', // Use parsed
    ];

    $response = $this->actingAs($this->admin)
        ->postJson("/admin/tournaments/{$this->tournament->id}/conflicts/reparse", [
            'parsed_data' => $parsedData,
            'resolution' => $resolution,
        ]);

    $response->assertStatus(200);

    // Verify field_sources after update
    $this->tournament->refresh();
    expect($this->tournament->title)->toBe('Manual Title'); // Kept manual
    expect($this->tournament->description)->toBe('Parsed Description'); // Used parsed
    expect($this->tournament->host_osu_id)->toBe(99999); // Used parsed

    // Verify field_sources reflects resolution
    expect($this->tournament->field_sources['title'])->toBe('manual'); // Still manual
    expect($this->tournament->field_sources['description'])->toBe('parsed'); // Now parsed
    expect($this->tournament->field_sources['host_osu_id'])->toBe('parsed'); // Now parsed

    // Verify audit log
    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_id' => $this->admin->id,
        'action' => 'tournament.reparse_resolved',
        'entity_type' => Tournament::class,
        'entity_id' => $this->tournament->id,
    ]);
});

test('reparse with resolution logs which fields were kept vs updated', function () {
    $this->tournament->update([
        'title' => 'Manual Title',
        'field_sources' => ['title' => 'manual'],
    ]);

    $parsedData = ['title' => 'Parsed Title'];

    $resolution = ['title' => 'manual']; // Keep manual

    $this->actingAs($this->admin)
        ->postJson("/admin/tournaments/{$this->tournament->id}/conflicts/reparse", [
            'parsed_data' => $parsedData,
            'resolution' => $resolution,
        ]);

    $auditLog = AdminAuditLog::where('action', 'tournament.reparse_resolved')->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->details['kept_manual'])->toBe(['title']);
    expect($auditLog->details['used_parsed'])->toBe([]);
});

test('admin cannot reparse without authentication', function () {
    $parsedData = ['title' => 'Test'];

    $response = $this->getJson("/admin/tournaments/{$this->tournament->id}/conflicts?preview_data=".urlencode(json_encode($parsedData)));

    $response->assertStatus(401);
});

test('admin cannot preview conflicts with invalid data', function () {
    $response = $this->actingAs($this->admin)
        ->getJson("/admin/tournaments/{$this->tournament->id}/conflicts?preview_data=invalid");

    $response->assertStatus(422); // Validation error
});
