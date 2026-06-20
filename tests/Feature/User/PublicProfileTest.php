<?php

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET /users/{id} returns 200 with profile data', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'main_mode' => 'osu',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);
    $response->assertViewIs('users.show');
    $response->assertViewHas('user');
});

test('profile shows avatar, username, country, main_mode', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'country_code' => 'US',
        'main_mode' => 'taiko',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertSee('testuser');
    $response->assertSee('taiko');
});

test('unauthenticated users can view profiles', function () {
    $user = User::factory()->create([
        'username' => 'publicuser',
        'main_mode' => 'osu',
    ]);

    auth()->guard('web')->logout();

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);
    $response->assertSee('publicuser');
});

test('staff profile exposes social embed metadata with zero badges', function () {
    $user = User::factory()->create([
        'osu_id' => 123456,
        'username' => 'StaffUser',
        'main_mode' => 'osu',
    ]);
    $tournament = Tournament::factory()->create([
        'status' => 'approved',
        'tournament_end' => now()->subDay(),
    ]);

    TournamentStaff::factory()->approved()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('<title>Tourney Method - StaffUser - player info</title>', false)
        ->assertSee('<meta name="description" content="Staff (osu!) - 0 badges">', false)
        ->assertSee('<meta property="og:site_name" content="Tourney Method">', false)
        ->assertSee('<meta property="og:title" content="StaffUser - player info">', false)
        ->assertSee('<meta property="og:description" content="Staff (osu!) - 0 badges">', false)
        ->assertSee('<meta property="og:image" content="https://a.ppy.sh/123456">', false)
        ->assertSee('<meta property="og:type" content="profile">', false)
        ->assertSee('<meta property="og:url" content="'.route('users.show', $user).'">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta name="twitter:title" content="StaffUser - player info">', false)
        ->assertSee('<meta name="twitter:description" content="Staff (osu!) - 0 badges">', false)
        ->assertSee('<meta name="twitter:image" content="https://a.ppy.sh/123456">', false)
        ->assertSee('<link rel="canonical" href="'.route('users.show', $user).'">', false);
});

test('player profile counts only tournament badges in social embed metadata', function () {
    $user = User::factory()->create([
        'username' => 'PlayerUser',
        'main_mode' => 'taiko',
    ]);
    $tournaments = Tournament::factory()->count(2)->create([
        'status' => 'approved',
        'tournament_end' => now()->subDay(),
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournaments->first()->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    foreach ($tournaments as $tournament) {
        UserBadge::factory()->create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
        ]);
    }
    UserBadge::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => null,
    ]);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('<meta name="description" content="Player (osu!taiko) - 2 badges">', false)
        ->assertSee('<meta property="og:description" content="Player (osu!taiko) - 2 badges">', false);
});

test('staff player profile omits a missing gamemode without dangling punctuation', function () {
    $user = User::factory()->create([
        'username' => 'HybridUser',
        'main_mode' => null,
    ]);
    $tournament = Tournament::factory()->create([
        'status' => 'approved',
        'tournament_end' => now()->subDay(),
    ]);

    TournamentStaff::factory()->approved()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('<meta name="description" content="Staff/Player - 0 badges">', false)
        ->assertDontSee('Staff/Player ()', false);
});

test('viewing non-existent user returns 404', function () {
    $response = $this->get(route('users.show', 999999));

    $response->assertStatus(404);
});

test('profile shows correct tab content', function () {
    $user = User::factory()->create([
        'username' => 'tabuser',
        'main_mode' => 'osu',
    ]);

    $badge = UserBadge::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Badge',
    ]);

    $tournament = Tournament::factory()->create([
        'title' => 'Test Tournament',
        'status' => 'approved',
    ]);

    TournamentStaff::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    $response = $this->get(route('users.show', $user->id));

    $response->assertStatus(200);
    $response->assertViewHas('user');
    $response->assertViewHas('staffRoles');
    $response->assertViewHas('matchStats');
});

test('mania profile shows key ranks by best placement before global rank', function () {
    $user = User::factory()->create([
        'main_mode' => 'mania',
    ]);

    $user->rankHistory()->createMany([
        ['mode' => 'mania', 'rank' => 12345, 'pp' => 5000, 'recorded_at' => now()],
        ['mode' => '4k', 'rank' => 4567, 'pp' => 4000, 'recorded_at' => now()],
        ['mode' => '7k', 'rank' => 1234, 'pp' => 4500, 'recorded_at' => now()],
    ]);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSeeInOrder([
            '7K: #1,234',
            '4K: #4,567',
            'Global Rank: #12,345',
        ]);
});

test('mania profile hides key ranks that are zero', function () {
    $user = User::factory()->create([
        'main_mode' => 'mania',
    ]);

    $user->rankHistory()->createMany([
        ['mode' => 'mania', 'rank' => 12345, 'pp' => 5000, 'recorded_at' => now()],
        ['mode' => '4k', 'rank' => 0, 'pp' => 0, 'recorded_at' => now()],
        ['mode' => '7k', 'rank' => 1234, 'pp' => 4500, 'recorded_at' => now()],
    ]);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('7K: #1,234')
        ->assertDontSee('4K: #0')
        ->assertSeeInOrder([
            '7K: #1,234',
            'Global Rank: #12,345',
        ]);
});
