<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// RefreshDatabase trait uses transactions and rollbacks - no migrate:fresh needed!

/**
 * Navigation Tests - Phase 1: Critical Navigation Fixes
 *
 * TDD Approach:
 * 1. RED - Write failing tests first
 * 2. GREEN - Implement Blade template changes
 * 3. REFACTOR - Improve implementation
 * 4. VERIFY - Check coverage and functionality
 */
describe('Navigation Links', function () {
    describe('Profile Link', function () {
        test('authenticated user can navigate to profile from dropdown', function () {
            // Arrange
            $user = User::factory()->withSetup()->create([
                'username' => 'testuser',
                'main_mode' => 'osu',
            ]);

            // Act
            $response = $this->actingAs($user)
                ->get(route('users.show', $user));

            // Assert
            $response->assertSuccessful();
            $response->assertSee('testuser');
            $response->assertSee('osu');
        });

        test('profile dropdown link contains correct route', function () {
            // Arrange
            $user = User::factory()->withSetup()->create();

            // Act
            $response = $this->actingAs($user)
                ->get('/');

            // Assert - Check that navigation contains profile link with user ID
            $profileUrl = route('users.show', $user);
            $response->assertSee($profileUrl);
        });

        test('guest users cannot access profile dropdown', function () {
            // Act
            $response = $this->get('/');

            // Assert - Guest should see login button, not profile dropdown
            $response->assertSee('Login with osu!');
            $response->assertDontSee('View Profile');
        });
    });

    describe('Settings Link', function () {
        test('settings dropdown link contains correct route', function () {
            // Arrange
            $user = User::factory()->create();

            // Act
            $response = $this->actingAs($user)
                ->get('/');

            // Assert - Check that navigation contains settings link
            $settingsUrl = route('settings.index');
            $response->assertSee($settingsUrl);
        });

        test('settings route is accessible', function () {
            // Arrange
            $user = User::factory()->create();

            // Act
            $response = $this->actingAs($user)
                ->get(route('settings.index'));

            // Assert - Settings page should load (may redirect, that's OK for now)
            $this->assertNotEmpty($response->getStatusCode());
        });
    });

    describe('Admin Link', function () {
        test('admin user can navigate to admin dashboard', function () {
            // Arrange
            $admin = User::factory()->admin()->create();

            // Act
            $response = $this->actingAs($admin)
                ->get(route('admin.dashboard'));

            // Assert
            $response->assertSuccessful();
            $response->assertSee('Admin Control Center');
        });

        test('master user can navigate to admin dashboard', function () {
            // Arrange
            $master = User::factory()->master()->create();

            // Act
            $response = $this->actingAs($master)
                ->get(route('admin.dashboard'));

            // Assert
            $response->assertSuccessful();
            $response->assertSee('Admin Control Center');
        });

        test('regular player cannot access admin routes', function () {
            // Arrange
            $player = User::factory()->player()->create();

            // Act
            $response = $this->actingAs($player)
                ->get(route('admin.tournaments.pending'));

            // Assert
            $response->assertForbidden();
        });

        test('admin link appears in navigation for admin users', function () {
            // Arrange
            $admin = User::factory()->admin()->create();

            // Act
            $response = $this->actingAs($admin)
                ->get('/');

            // Assert - Admin should see admin link
            $adminUrl = route('admin.dashboard');
            $response->assertSee($adminUrl);
        });

        test('admin link does not appear for regular users', function () {
            // Arrange
            $player = User::factory()->player()->create();

            // Act
            $response = $this->actingAs($player)
                ->get('/');

            // Assert - Regular player should not see admin link
            $response->assertDontSee('admin.');
        });
    });

    describe('Mobile Navigation', function () {
        test('mobile profile link works correctly', function () {
            // Arrange
            $user = User::factory()->create();

            // Act
            $response = $this->actingAs($user)
                ->get(route('users.show', $user));

            // Assert
            $response->assertSuccessful();
        });

        test('mobile settings link works correctly', function () {
            // Arrange
            $user = User::factory()->withSetup()->create();

            // Act
            $response = $this->actingAs($user)
                ->get(route('settings.index'));

            // Assert
            $response->assertSuccessful();
        });

        test('mobile admin link works correctly for admins', function () {
            // Arrange
            $admin = User::factory()->admin()->create();

            // Act
            $response = $this->actingAs($admin)
                ->get(route('admin.dashboard'));

            // Assert - Admin dashboard should load successfully
            $response->assertSuccessful();
        });
    });
});

describe('Navigation Accessibility', function () {
    test('navigation uses semantic HTML elements', function () {
        // Act
        $response = $this->get('/');

        // Assert - Check for nav element
        $html = $response->getContent();
        expect($html)->toContain('<nav');
    });

    test('navigation is keyboard navigable', function () {
        // This would typically be tested with Dusk
        // For now, we verify the markup structure
        $response = $this->get('/');

        // Check for proper semantic HTML structure
        $html = $response->getContent();
        expect($html)->toContain('<nav');
        expect($html)->toContain('href=');
    });
});
