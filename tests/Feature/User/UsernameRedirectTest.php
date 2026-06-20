<?php

use App\Models\User;

test('exact current username redirects to the canonical numeric profile', function () {
    $user = User::factory()->create(['username' => 'CurrentPlayer']);

    $this->get('/users/currentplayer')
        ->assertRedirect(route('users.show', $user));
});

test('exact previous username redirects case insensitively', function () {
    $user = User::factory()->create([
        'username' => 'CurrentPlayer',
        'previous_usernames' => ['FormerPlayer'],
    ]);

    $this->get('/users/fOrMeRpLaYeR')
        ->assertRedirect(route('users.show', $user));
});

test('current username takes priority over another users previous username', function () {
    $current = User::factory()->create(['username' => 'SharedName']);
    User::factory()->create([
        'username' => 'OtherPlayer',
        'previous_usernames' => ['SharedName'],
        'updated_at' => now()->addMinute(),
    ]);

    $this->get('/users/sharedname')
        ->assertRedirect(route('users.show', $current));
});

test('most recently updated user wins a previous username collision', function () {
    User::factory()->create([
        'username' => 'OlderOwner',
        'previous_usernames' => ['HistoricalName'],
        'updated_at' => now()->subDay(),
    ]);
    $newer = User::factory()->create([
        'username' => 'NewerOwner',
        'previous_usernames' => ['HistoricalName'],
        'updated_at' => now(),
    ]);

    $this->get('/users/historicalname')
        ->assertRedirect(route('users.show', $newer));
});

test('unknown username returns not found', function () {
    $this->get('/users/not-a-real-local-user')->assertNotFound();
});
