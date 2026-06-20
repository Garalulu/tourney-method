<?php

use App\Models\User;

test('notification json requests do not replace the previous html page', function () {
    $user = User::factory()->withSetup()->create();
    $previousUrl = route('home');

    $this->actingAs($user)
        ->get($previousUrl)
        ->assertOk();

    $this->getJson(route('notifications.index'))
        ->assertOk();

    $this->post(route('language.switch'), ['locale' => 'ko'])
        ->assertRedirect($previousUrl);
});

test('settings json requests do not replace the previous html page', function () {
    $user = User::factory()->withSetup()->create();
    $previousUrl = route('home');

    $this->actingAs($user)
        ->get($previousUrl)
        ->assertOk();

    $this->getJson(route('settings.index'))
        ->assertOk()
        ->assertJsonPath('main_mode', $user->main_mode);

    $this->post(route('language.switch'), ['locale' => 'ko'])
        ->assertRedirect($previousUrl);
});

test('normal html settings visits remain eligible as the previous page', function () {
    $user = User::factory()->withSetup()->create();
    $settingsUrl = route('settings.index');

    $this->actingAs($user)
        ->get($settingsUrl)
        ->assertOk()
        ->assertViewIs('settings.index');

    $this->post(route('language.switch'), ['locale' => 'ko'])
        ->assertRedirect($settingsUrl);
});

test('other web json get requests do not replace the previous html page', function () {
    $user = User::factory()->withSetup()->create();
    $previousUrl = route('home');

    $this->actingAs($user)
        ->get($previousUrl)
        ->assertOk();

    $this->getJson(route('users.stats', $user))
        ->assertOk();

    $this->post(route('language.switch'), ['locale' => 'ko'])
        ->assertRedirect($previousUrl);
});
