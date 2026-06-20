<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('search returns registered users with registered flag', function () {
    // Create admin with username that won't match "Play"
    $admin = User::factory()->create([
        'main_mode' => 'osu',
        'role' => 'admin',
        'username' => 'AdminUser_XYZ', // Unique username without "play"
    ]);
    User::factory()->create(['username' => 'PlayerOne', 'osu_id' => 12345]);

    $response = $this->actingAs($admin)->getJson('/api/users/search?q=Play');

    $response->assertStatus(200);
    // Returns: PlayerOne (registered) + new:Play (unregistered option, no exact match)
    $response->assertJsonCount(2);
    $response->assertJsonFragment([
        'username' => 'PlayerOne',
        'osu_id' => 12345,
        'registered' => true,
    ]);
    $response->assertJsonFragment([
        'id' => 'new:Play',
        'username' => 'Play',
        'registered' => false,
        'is_new' => true,
    ]);
});

test('search includes unregistered option when no exact match', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);

    $response = $this->actingAs($admin)->getJson('/api/users/search?q=NonExistentUser');

    $response->assertStatus(200);
    $response->assertJsonCount(1);

    $response->assertJsonFragment([
        'id' => 'new:NonExistentUser',
        'username' => 'NonExistentUser',
        'registered' => false,
        'is_new' => true,
        'osu_id' => null,
    ]);
});

test('search does not include unregistered option when exact match exists', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    User::factory()->create(['username' => 'ExactMatch', 'osu_id' => 54321]);

    $response = $this->actingAs($admin)->getJson('/api/users/search?q=ExactMatch');

    $response->assertStatus(200);
    $response->assertJsonCount(1);

    $response->assertJsonFragment([
        'username' => 'ExactMatch',
        'registered' => true,
    ]);

    // Should NOT have unregistered option
    $response->assertJsonMissing([
        'id' => 'new:ExactMatch',
        'registered' => false,
    ]);
});

test('search requires minimum 3 characters', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);

    $response = $this->actingAs($admin)->getJson('/api/users/search?q=ab');

    $response->assertStatus(200);
    $response->assertJsonCount(0);
});

test('search is case-insensitive', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);
    User::factory()->create(['username' => 'TestUser', 'osu_id' => 11111]);

    $response = $this->actingAs($admin)->getJson('/api/users/search?q=testuser');

    $response->assertStatus(200);
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['username' => 'TestUser']);
});

test('search limits results to 10 users', function () {
    $admin = User::factory()->create(['main_mode' => 'osu', 'role' => 'admin']);

    // Create 15 users with similar names
    User::factory()->count(15)->create([
        'username' => 'Test',
        'main_mode' => 'osu',
    ]);

    $response = $this->actingAs($admin)->getJson('/api/users/search?q=Test');

    $response->assertStatus(200);
    $response->assertJsonCount(10); // Limited to 10
});

test('player cannot access user search api', function () {
    $player = User::factory()->create(['main_mode' => 'osu', 'role' => 'player']);

    $response = $this->actingAs($player)->getJson('/api/users/search?q=test');

    $response->assertStatus(403);
});

test('guest cannot access user search api', function () {
    $response = $this->getJson('/api/users/search?q=test');

    $response->assertStatus(401);
});
