<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake();
    Queue::fake();

    $this->admin = User::factory()->admin()->create();
});

/**
 * Integration test to verify the complete reparse protection flow:
 * 1. Admin creates a tournament
 * 2. Admin edits some fields (title, dates)
 * 3. Admin triggers reparse
 * 4. Verify admin edits are preserved (not overwritten)
 */
test('complete_reparse_protection_flow', function () {
    // Step 1: Create tournament via parsing
    Http::fake([
        'osu.ppy.sh/*' => Http::response([
            'id' => 12345,
            'username' => 'HostUser',
            'country_code' => 'KR',
        ], 200),
    ]);

    $tournament = Tournament::factory()->create([
        'forum_topic_id' => 999999,
        'title' => 'Original Tournament Title',
        'description' => 'Original Description',
        'host_osu_id' => 12345,
        'host_username' => 'HostUser',
        'registration_start' => '2026-03-01 00:00:00',
        'registration_end' => '2026-03-15 00:00:00',
        'tournament_start' => '2026-03-20 00:00:00',
        'tournament_end' => '2026-04-01 00:00:00',
        'rank_range_min' => 1000,
        'rank_range_max' => 50000,
        'modes' => [['mode' => 'osu', 'key_count' => null]],
    ]);

    expect($tournament->title)->toBe('Original Tournament Title');

    // Step 2: Admin edits fields
    $this->actingAs($this->admin)
        ->patch(route('admin.tournaments.update', $tournament), [
            'title' => 'Admin Edited Title',
            'registration_start' => '2026-04-01',
            'registration_end' => '2026-04-15',
            'rank_range_min' => 5000,
        ]);

    $tournament->refresh();

    // Verify admin edits worked
    expect($tournament->title)->toBe('Admin Edited Title');
    expect($tournament->registration_start->format('Y-m-d'))->toBe('2026-04-01');
    expect($tournament->registration_end->format('Y-m-d'))->toBe('2026-04-15');
    expect($tournament->rank_range_min)->toBe(5000);

    // CRITICAL: Verify field_sources marks edited fields as 'manual'
    expect($tournament->field_sources['title'])->toBe('manual');
    expect($tournament->field_sources['registration_start'])->toBe('manual');
    expect($tournament->field_sources['registration_end'])->toBe('manual');
    expect($tournament->field_sources['rank_range_min'])->toBe('manual');

    // Step 3: Simulate reparse with conflicting data
    $parsedData = [
        'forum_topic_id' => $tournament->forum_topic_id,
        'forum_post_url' => $tournament->forum_post_url,
        'title' => 'Parsed Title (should NOT override)',
        'description' => 'Parsed Description (SHOULD override)',
        'host_osu_id' => 12345,
        'host_username' => 'HostUser',
        'modes' => [['mode' => 'osu', 'key_count' => null]],
        'registration_start' => '2026-05-01 00:00:00', // Should NOT override
        'registration_end' => '2026-05-15 00:00:00', // Should NOT override
        'tournament_start' => '2026-05-20 00:00:00', // Should override
        'tournament_end' => '2026-06-01 00:00:00', // Should override
        'rank_range_min' => 100, // Should NOT override
        'rank_range_max' => 100000, // Should override
    ];

    $tournament->updateFromParsedData($parsedData, $this->admin->id);
    $tournament->refresh();

    // Step 4: Verify manual fields are PRESERVED
    expect($tournament->title)->toBe('Admin Edited Title'); // Preserved!
    expect($tournament->registration_start->format('Y-m-d H:i:s'))->toBe('2026-04-01 12:00:00'); // Preserved!
    expect($tournament->registration_end->format('Y-m-d H:i:s'))->toBe('2026-04-15 12:00:00'); // Preserved!
    expect($tournament->rank_range_min)->toBe(5000); // Preserved!

    // Verify non-manual fields are UPDATED
    expect($tournament->description)->toBe('Parsed Description (SHOULD override)'); // Updated!
    expect($tournament->tournament_start->format('Y-m-d H:i:s'))->toBe('2026-05-20 00:00:00'); // Updated!
    expect($tournament->tournament_end->format('Y-m-d H:i:s'))->toBe('2026-06-01 00:00:00'); // Updated!
    expect($tournament->rank_range_max)->toBe(100000); // Updated!

    // Verify field_sources still marks manual fields correctly
    expect($tournament->field_sources['title'])->toBe('manual');
    expect($tournament->field_sources['registration_start'])->toBe('manual');
    expect($tournament->field_sources['registration_end'])->toBe('manual');
    expect($tournament->field_sources['rank_range_min'])->toBe('manual');
    expect($tournament->field_sources['description'])->toBe('parsed'); // Updated by parse
    expect($tournament->field_sources['tournament_start'])->toBe('parsed'); // Updated by parse
    expect($tournament->field_sources['rank_range_max'])->toBe('parsed'); // Updated by parse
});

test('multiple_admin_updates_before_reparse', function () {
    $tournament = Tournament::factory()->create([
        'title' => 'Original',
        'rank_range_min' => 1000,
        'rank_range_max' => 50000,
    ]);

    // First admin update
    $this->actingAs($this->admin)
        ->patch(route('admin.tournaments.update', $tournament), [
            'title' => 'First Edit',
        ]);

    // Second admin update
    $this->actingAs($this->admin)
        ->patch(route('admin.tournaments.update', $tournament), [
            'rank_range_min' => 2000,
        ]);

    // Third admin update
    $this->actingAs($this->admin)
        ->patch(route('admin.tournaments.update', $tournament), [
            'rank_range_max' => 60000,
        ]);

    $tournament->refresh();

    // All three fields should be marked as manual
    expect($tournament->field_sources['title'])->toBe('manual');
    expect($tournament->field_sources['rank_range_min'])->toBe('manual');
    expect($tournament->field_sources['rank_range_max'])->toBe('manual');

    // Simulate reparse
    $tournament->updateFromParsedData([
        'title' => 'Parsed Title',
        'rank_range_min' => 100,
        'rank_range_max' => 100000,
        'forum_topic_id' => $tournament->forum_topic_id,
        'forum_post_url' => $tournament->forum_post_url,
        'modes' => $tournament->modes,
    ]);

    $tournament->refresh();

    // All manual fields should be preserved
    expect($tournament->title)->toBe('First Edit');
    expect($tournament->rank_range_min)->toBe(2000);
    expect($tournament->rank_range_max)->toBe(60000);
});
