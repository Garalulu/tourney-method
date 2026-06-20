<?php

/**
 * OAuth Flow Tests - Based on OpenAPI Contract
 *
 * Tests authentication endpoints per contracts/openapi.yaml:
 * - GET /auth/login → 302 redirect to osu! OAuth
 * - GET /auth/callback → 302 redirect (dashboard/setup) or 400 BadRequest
 * - POST /auth/logout → 200 success or 401 Unauthorized
 * - GET /auth/me → 200 with User schema or 401 Unauthorized
 */

use App\Jobs\SyncUserFromOsu;
use App\Models\Tournament;
use App\Models\User;
use App\Support\QueueNames;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

beforeEach(function () {
    // Prevent jobs from actually running during OAuth tests
    Queue::fake();
});

describe('GET /auth/login', function () {
    it('returns 302 redirect to osu! OAuth authorization page', function () {
        // Per OpenAPI: operationId: initiateLogin
        // Response: 302 with Location header to osu! OAuth
        $response = $this->get('/auth/login');

        $response->assertStatus(302);
        $response->assertRedirectContains('osu.ppy.sh/oauth/authorize');
    });

    it('stores public tournament page as post-login return URL', function () {
        $tournament = Tournament::factory()->create(['status' => 'approved']);

        $response = $this->from(route('tournaments.show', $tournament))
            ->get('/auth/login');

        $response->assertStatus(302);
        $response->assertSessionHas('login_redirect_url', route('tournaments.show', $tournament));
    });

    it('does not store home page as post-login return URL', function () {
        $response = $this->from(route('home'))
            ->get('/auth/login');

        $response->assertStatus(302);
        $response->assertSessionMissing('login_redirect_url');
    });
});

describe('GET /auth/callback', function () {
    it('returns 302 redirect to setup for new users without main_mode', function () {
        // Per OpenAPI: operationId: handleCallback
        // New user (main_mode is null) → redirect to setup

        $mockSocialiteUser = new SocialiteUser;
        $mockSocialiteUser->id = 12345678;
        $mockSocialiteUser->nickname = 'TestPlayer';
        $mockSocialiteUser->avatar = 'https://a.ppy.sh/12345678';
        $mockSocialiteUser->token = 'mock_access_token';
        $mockSocialiteUser->user = [
            'id' => 12345678,
            'username' => 'TestPlayer',
            'avatar_url' => 'https://a.ppy.sh/12345678',
            'country_code' => 'US',
        ];

        $this->mock(SocialiteFactory::class, function (MockInterface $mock) use ($mockSocialiteUser) {
            $provider = Mockery::mock(Provider::class);
            $provider->shouldReceive('user')->once()->andReturn($mockSocialiteUser);
            $provider->shouldReceive('redirect')->andReturn(redirect('https://osu.ppy.sh/oauth/authorize'));

            $mock->shouldReceive('driver')->with('osu')->andReturn($provider);
        });

        $response = $this->get('/auth/callback?code=mock_code&state=mock_state');

        $response->assertStatus(302);
        $response->assertRedirect(route('setup.show'));

        // Verify user created in database
        $this->assertDatabaseHas('users', [
            'osu_id' => 12345678,
            'username' => 'TestPlayer',
            'main_mode' => null,
        ]);

        Queue::assertPushed(
            SyncUserFromOsu::class,
            fn (SyncUserFromOsu $job) => $job->queue === QueueNames::OSU_USER
        );
    });

    it('returns 302 redirect to dashboard for returning users with main_mode set', function () {
        // Per OpenAPI: Existing user with setup complete → redirect to dashboard

        // Create existing user with main_mode set
        $existingUser = User::factory()->create([
            'osu_id' => 87654321,
            'username' => 'ExistingPlayer',
            'main_mode' => 'osu',
            'main_mode_source' => 'oauth_setup',
        ]);

        $mockSocialiteUser = new SocialiteUser;
        $mockSocialiteUser->id = 87654321;
        $mockSocialiteUser->nickname = 'ExistingPlayer';
        $mockSocialiteUser->avatar = 'https://a.ppy.sh/87654321';
        $mockSocialiteUser->token = 'mock_access_token';
        $mockSocialiteUser->user = [
            'id' => 87654321,
            'username' => 'ExistingPlayer',
            'avatar_url' => 'https://a.ppy.sh/87654321',
            'country_code' => 'JP',
        ];

        $this->mock(SocialiteFactory::class, function (MockInterface $mock) use ($mockSocialiteUser) {
            $provider = Mockery::mock(Provider::class);
            $provider->shouldReceive('user')->once()->andReturn($mockSocialiteUser);

            $mock->shouldReceive('driver')->with('osu')->andReturn($provider);
        });

        $response = $this->get('/auth/callback?code=mock_code&state=mock_state');

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));

        expect($existingUser->fresh()->last_login_at)->not->toBeNull();
    });

    it('returns to public page captured before login', function () {
        User::factory()->create([
            'osu_id' => 87654323,
            'username' => 'ReturningFromPublicPage',
            'main_mode' => 'osu',
            'main_mode_source' => 'oauth_setup',
        ]);
        $profileUser = User::factory()->create();

        $mockSocialiteUser = new SocialiteUser;
        $mockSocialiteUser->id = 87654323;
        $mockSocialiteUser->nickname = 'ReturningFromPublicPage';
        $mockSocialiteUser->avatar = 'https://a.ppy.sh/87654323';
        $mockSocialiteUser->token = 'mock_access_token';
        $mockSocialiteUser->user = [
            'id' => 87654323,
            'username' => 'ReturningFromPublicPage',
            'avatar_url' => 'https://a.ppy.sh/87654323',
            'country_code' => 'KR',
        ];

        $this->mock(SocialiteFactory::class, function (MockInterface $mock) use ($mockSocialiteUser) {
            $provider = Mockery::mock(Provider::class);
            $provider->shouldReceive('user')->once()->andReturn($mockSocialiteUser);

            $mock->shouldReceive('driver')->with('osu')->andReturn($provider);
        });

        $response = $this
            ->withSession(['login_redirect_url' => route('users.show', $profileUser)])
            ->get('/auth/callback?code=mock_code&state=mock_state');

        $response->assertStatus(302);
        $response->assertRedirect(route('users.show', $profileUser));
        $response->assertSessionMissing('login_redirect_url');
    });

    it('preserves returning user database locale after login', function () {
        User::factory()->create([
            'osu_id' => 87654322,
            'username' => 'LocalizedPlayer',
            'locale' => 'ko',
            'main_mode' => 'osu',
            'main_mode_source' => 'oauth_setup',
        ]);

        $mockSocialiteUser = new SocialiteUser;
        $mockSocialiteUser->id = 87654322;
        $mockSocialiteUser->nickname = 'LocalizedPlayer';
        $mockSocialiteUser->avatar = 'https://a.ppy.sh/87654322';
        $mockSocialiteUser->token = 'mock_access_token';
        $mockSocialiteUser->user = [
            'id' => 87654322,
            'username' => 'LocalizedPlayer',
            'avatar_url' => 'https://a.ppy.sh/87654322',
            'country_code' => 'KR',
        ];

        $this->mock(SocialiteFactory::class, function (MockInterface $mock) use ($mockSocialiteUser) {
            $provider = Mockery::mock(Provider::class);
            $provider->shouldReceive('user')->once()->andReturn($mockSocialiteUser);

            $mock->shouldReceive('driver')->with('osu')->andReturn($provider);
        });

        $response = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/auth/callback?code=mock_code&state=mock_state');

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('locale', 'ko');
        $this->assertDatabaseHas('users', [
            'osu_id' => 87654322,
            'locale' => 'ko',
        ]);
    });

    it('detects russian locale from browser language for new users', function () {
        $mockSocialiteUser = new SocialiteUser;
        $mockSocialiteUser->id = 11223344;
        $mockSocialiteUser->nickname = 'RussianLocalePlayer';
        $mockSocialiteUser->avatar = 'https://a.ppy.sh/11223344';
        $mockSocialiteUser->token = 'mock_access_token';
        $mockSocialiteUser->user = [
            'id' => 11223344,
            'username' => 'RussianLocalePlayer',
            'avatar_url' => 'https://a.ppy.sh/11223344',
            'country_code' => 'RU',
        ];

        $this->mock(SocialiteFactory::class, function (MockInterface $mock) use ($mockSocialiteUser) {
            $provider = Mockery::mock(Provider::class);
            $provider->shouldReceive('user')->once()->andReturn($mockSocialiteUser);

            $mock->shouldReceive('driver')->with('osu')->andReturn($provider);
        });

        $response = $this->withHeaders(['Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8'])
            ->get('/auth/callback?code=mock_code&state=mock_state');

        $response->assertStatus(302);
        $response->assertRedirect(route('setup.show'));
        $response->assertSessionHas('locale', 'ru');
        $this->assertDatabaseHas('users', [
            'osu_id' => 11223344,
            'locale' => 'ru',
        ]);
    });

    it('returns 302 redirect to home with error on OAuth failure', function () {
        // Per OpenAPI: 400 BadRequest scenario (OAuth errors)

        $this->mock(SocialiteFactory::class, function (MockInterface $mock) {
            $provider = Mockery::mock(Provider::class);
            $provider->shouldReceive('user')->andThrow(new Exception('OAuth authentication failed'));

            $mock->shouldReceive('driver')->with('osu')->andReturn($provider);
        });

        $response = $this->get('/auth/callback?code=invalid_code&state=mock_state');

        $response->assertStatus(302);
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error');
    });
});

describe('POST /auth/logout', function () {
    it('redirects to home when there is no public previous page', function () {
        // Per OpenAPI: operationId: logout
        // Response: 200 for authenticated users (Laravel redirects)

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/auth/logout');

        $response->assertStatus(302);
        $response->assertRedirect(route('home'));
        $this->assertGuest();
    });

    it('does not preserve user database locale for guest after logout', function () {
        $user = User::factory()->create(['locale' => 'ko']);

        $response = $this
            ->actingAs($user)
            ->withSession(['locale' => 'ko'])
            ->post('/auth/logout');

        $response->assertRedirect(route('home'));
        $response->assertSessionMissing('locale');
        $this->assertGuest();
    });

    it('preserves language explicitly selected as guest before logout', function () {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'locale' => 'en',
                'guest_locale' => 'ko',
            ])
            ->post('/auth/logout');

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('locale', 'ko');
        $response->assertSessionHas('guest_locale', 'ko');
        $this->assertGuest();
    });

    it('stays on tournaments page and removes account-only filters', function () {
        $user = User::factory()->create();

        $previousUrl = route('tournaments.index', [
            'mode' => 'mania',
            'eligible' => true,
            'eligible_only' => true,
            'reg_open' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->from($previousUrl)
            ->post('/auth/logout');

        $response->assertRedirect(route('tournaments.index', [
            'mode' => 'mania',
            'reg_open' => '1',
        ]));
        $this->assertGuest();
    });

    it('stays on public tournament detail page', function () {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->create(['status' => 'approved']);

        $response = $this
            ->actingAs($user)
            ->from(route('tournaments.show', $tournament))
            ->post('/auth/logout');

        $response->assertRedirect(route('tournaments.show', $tournament));
        $this->assertGuest();
    });

    it('stays on public user profile page', function () {
        $user = User::factory()->create();
        $profileUser = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('users.show', $profileUser))
            ->post('/auth/logout');

        $response->assertRedirect(route('users.show', $profileUser));
        $this->assertGuest();
    });

    it('redirects to home from login-required pages', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('settings.index'))
            ->post('/auth/logout');

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    });

    it('returns redirect for unauthenticated users', function () {
        // Per OpenAPI: 401 Unauthorized for unauthenticated requests
        // Laravel's auth middleware redirects to login for web routes

        $response = $this->post('/auth/logout');

        // Web routes typically redirect to login
        $response->assertStatus(302);
    });
});

describe('GET /auth/me', function () {
    it('returns 200 with User schema for authenticated user', function () {
        // Per OpenAPI: operationId: getCurrentUser
        // Response: 200 with User schema

        $user = User::factory()->create([
            'osu_id' => 11111111,
            'username' => 'AuthenticatedPlayer',
            'country_code' => 'KR',
            'main_mode' => 'mania',
            'role' => 'player',
        ]);

        $this->actingAs($user);

        // Per OpenAPI contract, GET /auth/me should return:
        // {
        //   id: integer,
        //   osu_id: integer,
        //   username: string,
        //   avatar_url: string,
        //   country_code: string,
        //   main_mode: GameMode,
        //   role: UserRole,
        //   setup_complete: boolean,
        //   created_at: date-time
        // }

        $response = $this->getJson('/auth/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'osu_id',
            'username',
            'avatar_url',
            'country_code',
            'main_mode',
            'role',
            'setup_complete',
            'created_at',
        ]);
        $response->assertJson([
            'osu_id' => 11111111,
            'username' => 'AuthenticatedPlayer',
            'main_mode' => 'mania',
            'role' => 'player',
            'setup_complete' => true,
        ]);
    });

    it('returns 401 Unauthorized for unauthenticated request', function () {
        // Per OpenAPI: 401 $ref: '#/components/responses/Unauthorized'
        // Error schema: { message: string, errors?: object }

        $response = $this->getJson('/auth/me');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    });
});

describe('POST /auth/sync', function () {
    it('dispatches user sync on the osu user queue', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/auth/sync');

        $response->assertOk();

        Queue::assertPushed(
            SyncUserFromOsu::class,
            fn (SyncUserFromOsu $job) => $job->queue === QueueNames::OSU_USER
        );
    });
});
