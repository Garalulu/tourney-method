<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// RefreshDatabase trait uses transactions and rollbacks - no migrate:fresh needed!

/**
 * Settings Page Tests
 *
 * TDD Approach:
 * 1. RED - Write failing tests first
 * 2. GREEN - Implement settings page improvements
 * 3. REFACTOR - Improve implementation
 * 4. VERIFY - Check coverage and functionality
 */
describe('Settings Page - Access', function () {
    test('authenticated users can access settings page', function () {
        // Arrange
        $user = User::factory()->withSetup()->create();

        // Act
        $response = $this->actingAs($user)
            ->get(route('settings.index'));

        // Assert
        $response->assertSuccessful();
        $response->assertSee('Settings');
    });

    test('guest users cannot access settings page', function () {
        // Act
        $response = $this->get(route('settings.index'));

        // Assert
        $response->assertRedirect(route('login'));
    });

    test('settings page displays current user settings', function () {
        // Arrange
        $user = User::factory()->withSetup()->create([
            'main_mode' => 'taiko',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('settings.index'));

        // Assert
        $response->assertSuccessful();
        $response->assertSee('taiko');
    });
});

describe('Settings Page - Existing Settings Sections', function () {
    test('settings page displays discord webhook section', function () {
        // Arrange
        $user = User::factory()->withSetup()->create();

        // Act
        $response = $this->actingAs($user)
            ->get(route('settings.index'));

        // Assert
        $response->assertSuccessful();
        $response->assertSee('Discord Webhook');
    });

    test('settings page displays notification preferences', function () {
        // Arrange
        $user = User::factory()->withSetup()->create();

        // Act
        $response = $this->actingAs($user)
            ->get(route('settings.index'));

        // Assert
        $response->assertSuccessful();
        $response->assertSee('Notification Preferences');
    });

    test('settings page displays game mode selection', function () {
        // Arrange
        $user = User::factory()->withSetup()->create();

        // Act
        $response = $this->actingAs($user)
            ->get(route('settings.index'));

        // Assert
        $response->assertSuccessful();
        $response->assertSee('Main Game Mode');
    });
});

describe('Settings Page - Save Workflow', function () {
    test('normal form submission redirects back to the settings page instead of rendering json', function () {
        $user = User::factory()->withSetup()->create([
            'main_mode' => 'osu',
        ]);

        $response = $this->actingAs($user)
            ->post(route('settings.update'), [
                '_method' => 'PATCH',
                'main_mode' => 'taiko',
            ]);

        $response
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('success', __('settings.saved'));

        expect($user->fresh()->main_mode)->toBe('taiko');
    });

    test('ajax settings submission keeps returning json', function () {
        $user = User::factory()->withSetup()->create([
            'main_mode' => 'osu',
        ]);

        $this->actingAs($user)
            ->patchJson(route('settings.update'), [
                'main_mode' => 'mania',
            ])
            ->assertOk()
            ->assertJsonPath('main_mode', 'mania');
    });
});
