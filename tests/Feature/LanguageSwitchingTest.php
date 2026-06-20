<?php

use App\Models\User;

test('authenticated user locale is loaded from database when session has no locale', function () {
    $user = User::factory()->create(['locale' => 'ko']);

    $response = $this
        ->actingAs($user)
        ->get(route('tournaments.index'));

    $response->assertOk();
    expect(app()->getLocale())->toBe('ko');
    expect(session('locale'))->toBe('ko');
});

test('guest can switch language and it persists in session', function () {
    $response = $this->post(route('language.switch'), [
        'locale' => 'ru',
    ]);

    $response->assertRedirect();
    $this->assertEquals('ru', session('locale'));
    $this->assertEquals('ru', session('guest_locale'));
});

test('guest can switch back to english', function () {
    session(['locale' => 'ko']);

    $response = $this->post(route('language.switch'), [
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    $this->assertEquals('en', session('locale'));
});

test('authenticated user can switch language and it saves to database', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)
        ->post(route('language.switch'), [
            'locale' => 'ru',
        ]);

    $response->assertRedirect();
    $this->assertEquals('ru', session('locale'));
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'locale' => 'ru',
    ]);
});

test('authenticated user locale preference is saved in database', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->post(route('language.switch'), ['locale' => 'ko']);

    // Refresh user from database
    $user->refresh();

    $this->assertEquals('ko', $user->locale);
});

test('language switch updates UI elements', function () {
    $response = $this->post(route('language.switch'), ['locale' => 'ko']);

    $response = $this->get(route('tournaments.index'));
    $response->assertSee('토너먼트'); // Korean for Tournaments
});

test('invalid locale is rejected', function () {
    $response = $this->post(route('language.switch'), [
        'locale' => 'invalid',
    ]);

    $response->assertSessionHasErrors();
});

test('unsupported locale is rejected', function () {
    $response = $this->post(route('language.switch'), [
        'locale' => 'ja', // Japanese not supported yet
    ]);

    $response->assertSessionHasErrors();
});

test('language switch redirects back to previous page', function () {
    $response = $this->from(route('tournaments.index'))
        ->post(route('language.switch'), ['locale' => 'ko']);

    $response->assertRedirect(route('tournaments.index'));
});

test('language switch uses submitted current url over stale referrer', function () {
    $staleUrl = route('tournaments.index', [
        'tab' => 'ended',
        'year' => 2022,
    ]);

    $currentUrl = route('tournaments.index', [
        'tab' => 'ended',
    ]);

    $response = $this->from($staleUrl)
        ->post(route('language.switch'), [
            'locale' => 'ko',
            'redirect_url' => $currentUrl,
        ]);

    $response->assertRedirect($currentUrl);
});

test('language switch rejects external redirect url', function () {
    $fallbackUrl = route('tournaments.index');

    $response = $this->from($fallbackUrl)
        ->post(route('language.switch'), [
            'locale' => 'ko',
            'redirect_url' => 'https://example.com/tournaments?tab=ended',
        ]);

    $response->assertRedirect($fallbackUrl);
});

test('language switch sets success message', function () {
    $response = $this->post(route('language.switch'), ['locale' => 'ko']);

    $response->assertSessionHas('success');
    $response->assertSessionHas('locale_changed', 'ko');
});
