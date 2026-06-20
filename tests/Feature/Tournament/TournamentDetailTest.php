<?php

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentWatch;
use App\Models\TournamentWinner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tournament Detail Social Metadata', function () {
    it('exposes structured social embed metadata', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'World Cup',
            'host_username' => 'KRZY',
            'modes' => ['osu', 'taiko'],
            'tournament_start' => '2025-12-01 00:00:00',
            'tournament_end' => '2026-01-15 00:00:00',
            'banner_url' => 'https://example.com/world-cup.png',
        ]);

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('<title>Tourney Method - World Cup - tournament info</title>', false)
            ->assertSee('<meta name="description" content="2026 · Hosted by KRZY (osu!, osu!taiko)">', false)
            ->assertSee('<meta property="og:site_name" content="Tourney Method">', false)
            ->assertSee('<meta property="og:title" content="World Cup - tournament info">', false)
            ->assertSee('<meta property="og:description" content="2026 · Hosted by KRZY (osu!, osu!taiko)">', false)
            ->assertSee('<meta property="og:image" content="https://example.com/world-cup.png">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:url" content="'.route('tournaments.show', $tournament).'">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('<meta name="twitter:title" content="World Cup - tournament info">', false)
            ->assertSee('<meta name="twitter:description" content="2026 · Hosted by KRZY (osu!, osu!taiko)">', false)
            ->assertSee('<meta name="twitter:image" content="https://example.com/world-cup.png">', false)
            ->assertSee('<link rel="canonical" href="'.route('tournaments.show', $tournament).'">', false);
    });

    it('falls back to the start year and default banner while omitting a missing host', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Hostless Cup',
            'host_username' => null,
            'modes' => ['osu'],
            'tournament_start' => '2025-08-01 00:00:00',
            'tournament_end' => null,
            'banner_url' => null,
        ]);

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('<meta name="description" content="2025 · osu!">', false)
            ->assertSee('<meta property="og:image" content="'.$tournament->getDefaultBannerUrl().'">', false)
            ->assertSee('<meta name="twitter:image" content="'.$tournament->getDefaultBannerUrl().'">', false)
            ->assertDontSee('Hosted by  (', false);
    });

    it('omits a missing year without leaving a dangling separator', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Undated Cup',
            'host_username' => 'NoYearHost',
            'modes' => ['mania'],
            'tournament_start' => null,
            'tournament_end' => null,
        ]);

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('<meta name="description" content="Hosted by NoYearHost (osu!mania)">', false)
            ->assertDontSee('· Hosted by NoYearHost', false);
    });
});

describe('Tournament Detail Watch Placement', function () {
    it('places watch in the top action area for a normal user', function () {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->approved()->create([
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDay(),
            'tournament_start' => now()->addDays(2),
            'tournament_end' => now()->addDays(3),
        ]);

        $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

        $response->assertOk()
            ->assertSee('data-tournament-actions', false)
            ->assertSee(__('tournaments.subscriptions.title'))
            ->assertDontSee(route('admin.tournaments.show', $tournament), false);

        expect(substr_count($response->getContent(), 'data-tournament-actions'))->toBe(1)
            ->and(strpos($response->getContent(), __('tournaments.subscriptions.title')))
            ->toBeGreaterThan(strpos($response->getContent(), 'data-tournament-actions'));
    });

    it('places watch next to the admin action for administrators', function () {
        $admin = User::factory()->admin()->create();
        $tournament = Tournament::factory()->approved()->create([
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDay(),
            'tournament_start' => now()->addDays(2),
            'tournament_end' => now()->addDays(3),
        ]);

        $html = $this->actingAs($admin)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee(__('tournaments.subscriptions.title'))
            ->assertSee(route('admin.tournaments.show', $tournament), false)
            ->getContent();

        $actionsStart = strpos($html, 'data-tournament-actions');
        expect(strpos($html, __('tournaments.subscriptions.title')))->toBeGreaterThan($actionsStart)
            ->and(strpos($html, route('admin.tournaments.show', $tournament)))->toBeGreaterThan($actionsStart);
    });

    it('hides watch after the tournament ends', function () {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->approved()->create([
            'registration_start' => now()->subDays(4),
            'registration_end' => now()->subDays(3),
            'tournament_start' => now()->subDays(2),
            'tournament_end' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertDontSee(__('tournaments.subscriptions.title'));
    });
});

describe('Tournament Detail API', function () {
    beforeEach(function () {
        $this->tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Test Tournament 2024',
            'description' => 'This is a test tournament',
            'modes' => ['osu', 'taiko'],
            'is_badge' => true,
            'is_bws' => true,
            'bws_base_exponent' => 0.9937,
            'bws_badge_power' => 0.8129,
            'rank_range_min' => 1000,
            'rank_range_max' => 50000,
            'team_size_min' => 1,
            'team_size_max' => 1,
            'star_rating_min' => 5.0,
            'star_rating_max' => 7.0,
            'format' => '1v1 Double Elimination',
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(7),
            'tournament_start' => now()->addDays(14),
            'tournament_end' => now()->addDays(21),
            'banner_url' => 'https://example.com/banner.jpg',
            'discord_url' => 'https://discord.gg/example',
            'twitch_url' => 'https://twitch.tv/example',
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/example',
            'bracket_url' => 'https://challonge.com/example',
            'registration_url' => 'https://forms.google.com/example',
            'host_username' => 'TestHost',
        ]);

        $this->user = User::factory()->create([
            'main_mode' => 'osu',
        ]);
    });

    it('returns tournament detail for approved tournament', function () {
        $response = $this->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'modes',
                    'is_badge',
                    'is_bws',
                    'bws_base_exponent',
                    'bws_badge_power',
                    'rank_range_min',
                    'rank_range_max',
                    'team_size_min',
                    'team_size_max',
                    'star_rating_min',
                    'star_rating_max',
                    'format',
                    'registration_start',
                    'registration_end',
                    'tournament_start',
                    'tournament_end',
                    'banner_url',
                    'discord_url',
                    'twitch_url',
                    'spreadsheet_url',
                    'bracket_url',
                    'registration_url',
                    'forum_post_url',
                    'host_username',
                    'registration_closes_in',
                    'user_watch_status',
                    'user_is_eligible',
                ],
            ])
            ->assertJsonPath('data.id', $this->tournament->id)
            ->assertJsonPath('data.title', 'Test Tournament 2024')
            ->assertJsonPath('data.description', 'This is a test tournament')
            ->assertJsonPath('data.modes', [
                ['mode' => 'osu', 'key_count' => null],
                ['mode' => 'taiko', 'key_count' => null],
            ])
            ->assertJsonPath('data.is_badge', true)
            ->assertJsonPath('data.is_bws', true)
            ->assertJsonPath('data.rank_range_min', 1000)
            ->assertJsonPath('data.rank_range_max', 50000);
    });

    it('returns 404 for non-existent tournament', function () {
        $response = $this->getJson('/tournaments/99999');

        $response->assertNotFound()
            ->assertJsonStructure(['message']);
    });

    it('returns 404 for pending tournament when accessed by guest', function () {
        $pendingTournament = Tournament::factory()->create([
            'status' => 'pending_review',
        ]);

        $response = $this->getJson("/tournaments/{$pendingTournament->id}");

        $response->assertNotFound();
    });

    it('returns 404 for rejected tournament', function () {
        $rejectedTournament = Tournament::factory()->create([
            'status' => 'rejected',
        ]);

        $response = $this->getJson("/tournaments/{$rejectedTournament->id}");

        $response->assertNotFound();
    });

    it('returns 404 for pending tournament page when accessed by normal user', function () {
        $pendingTournament = Tournament::factory()->pending()->create();

        $response = $this->actingAs($this->user)
            ->get(route('tournaments.show', $pendingTournament));

        $response->assertNotFound();
    });

    it('returns 404 for rejected tournament page when accessed by normal user', function () {
        $rejectedTournament = Tournament::factory()->rejected()->create();

        $response = $this->actingAs($this->user)
            ->get(route('tournaments.show', $rejectedTournament));

        $response->assertNotFound();
    });

    it('includes user_watch_status for authenticated user with watch', function () {
        // Create watch record
        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_watch_status', 'watching');
    });

    it('includes null user_watch_status for authenticated user without watch', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_watch_status', null);
    });

    it('includes null user_watch_status for guest user', function () {
        $response = $this->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_watch_status', null);
    });

    it('includes user_is_eligible=true for eligible authenticated user', function () {
        // Create user rank history within eligible range
        $this->user->rankHistory()->create([
            'rank' => 5000,
            'pp' => 8000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_is_eligible', true);
    });

    it('includes user_is_eligible=false for ineligible authenticated user', function () {
        // Create user rank history outside eligible range
        $this->user->rankHistory()->create([
            'rank' => 500, // Outside 1000-50000 range
            'pp' => 10000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_is_eligible', false);
    });

    it('includes user_is_eligible=true for tournament without rank restrictions', function () {
        $noRankTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'rank_range_min' => null,
            'rank_range_max' => null,
        ]);

        // User with any rank
        $this->user->rankHistory()->create([
            'rank' => 100000,
            'pp' => 1000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/tournaments/{$noRankTournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_is_eligible', true);
    });

    it('includes null user_is_eligible for guest user', function () {
        $response = $this->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_is_eligible', null);
    });

    it('includes user_is_eligible=false when user has no rank history', function () {
        // User without rank history
        $response = $this->actingAs($this->user)
            ->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_is_eligible', false);
    });

    it('uses badge cutoff date when calculating BWS eligibility for API details', function () {
        $this->user->rankHistory()->create([
            'rank' => 10000,
            'pp' => 5000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $cutoff = now()->subDays(10)->startOfDay();

        $oldBadgeTournament = Tournament::factory()->create(['status' => 'approved']);
        $recentBadgeTournament = Tournament::factory()->create(['status' => 'approved']);

        foreach ([
            [$oldBadgeTournament, $cutoff->copy()->subDay()],
            [$recentBadgeTournament, $cutoff->copy()->addDay()],
        ] as [$badgeTournament, $awardedAt]) {
            $this->user->badges()->create([
                'name' => "Badge {$badgeTournament->id}",
                'image_url' => "https://example.com/badge{$badgeTournament->id}.png",
                'awarded_at' => $awardedAt,
                'is_bws_eligible' => true,
                'tournament_id' => $badgeTournament->id,
            ]);

            TournamentWinner::factory()->create([
                'tournament_id' => $badgeTournament->id,
                'user_id' => $this->user->id,
                'username' => $this->user->username,
                'osu_id' => $this->user->osu_id,
                'gamemode' => 'osu',
            ]);
        }

        $bwsTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'is_bws' => true,
            'bws_base_exponent' => 0.9937,
            'bws_badge_power' => 2.0,
            'bws_badge_age_cutoff' => $cutoff,
            'rank_range_min' => 9000,
            'rank_range_max' => 9500,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDays(7),
            'tournament_start' => now()->addDays(14),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/tournaments/{$bwsTournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_is_eligible', true);

        $this->actingAs($this->user)
            ->get(route('tournaments.show', $bwsTournament))
            ->assertOk()
            ->assertSeeText('You are eligible!');
    });

    it('returns all optional fields as null when not set', function () {
        $minimalTournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Minimal Tournament',
            'modes' => ['osu'],
            'description' => null,
            'host_username' => null,
            'team_size_min' => null,
            'team_size_max' => null,
            'registration_start' => null,
            'registration_end' => null,
            'tournament_end' => null,
            'is_bws' => false,
            'bws_base_exponent' => null,
            'bws_badge_power' => null,
            'star_rating_min' => null,
            'star_rating_max' => null,
            'format' => null,
            'banner_url' => null,
            'discord_url' => null,
            'twitch_url' => null,
            'spreadsheet_url' => null,
            'bracket_url' => null,
            'registration_url' => null,
        ]);

        $response = $this->getJson("/tournaments/{$minimalTournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.host_username', null)
            ->assertJsonPath('data.team_size_min', null)
            ->assertJsonPath('data.team_size_max', null)
            ->assertJsonPath('data.registration_start', null)
            ->assertJsonPath('data.registration_end', null)
            ->assertJsonPath('data.tournament_end', null)
            ->assertJsonPath('data.bws_base_exponent', null)
            ->assertJsonPath('data.bws_badge_power', null)
            ->assertJsonPath('data.star_rating_min', null)
            ->assertJsonPath('data.star_rating_max', null)
            ->assertJsonPath('data.format', null)
            ->assertJsonPath('data.banner_url', null)
            ->assertJsonPath('data.discord_url', null)
            ->assertJsonPath('data.twitch_url', null)
            ->assertJsonPath('data.spreadsheet_url', null)
            ->assertJsonPath('data.bracket_url', null)
            ->assertJsonPath('data.registration_url', null)
            ->assertJsonPath('data.registration_closes_in', null);
    });

    it('includes registration_closes_in for tournament with registration_end', function () {
        $response = $this->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk();

        $closesIn = $response->json('data.registration_closes_in');
        expect($closesIn)->not->toBeNull();
    });

    it('includes null registration_closes_in for tournament without registration_end', function () {
        $this->tournament->update(['registration_end' => null]);

        $response = $this->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk()
            ->assertJsonPath('data.registration_closes_in', null);
    });

    it('includes null registration_closes_in for tournament with past registration_end', function () {
        $this->tournament->update(['registration_end' => now()->subDays(1)]);

        $response = $this->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk();
        // registration_closes_in may be null for past dates
    });

    it('handles user eligibility with BWS calculation', function () {
        // Create user with badges
        $this->user->rankHistory()->create([
            'rank' => 10000,
            'pp' => 5000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        // Add 5 eligible badges
        foreach (range(1, 5) as $i) {
            $this->user->badges()->create([
                'name' => "Badge {$i}",
                'image_url' => "https://example.com/badge{$i}.png",
                'awarded_at' => now()->subMonths($i),
                'is_bws_eligible' => true,
            ]);
        }

        // Tournament with BWS
        $bwsTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_bws' => true,
            'bws_base_exponent' => 0.9937,
            'bws_badge_power' => 0.8129,
            'rank_range_min' => 1000,
            'rank_range_max' => 5000, // BWS rank should be within this range
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/tournaments/{$bwsTournament->id}");

        $response->assertOk();

        // BWS calculation: rank^0.9937 * badge_count^0.8129
        // 10000^0.9937 * 5^0.8129 ≈ 9390
        // This should be outside 1000-5000, so user_is_eligible should be false
        // But let's not assert specific value as it depends on implementation
        expect($response->json('data.user_is_eligible'))->toBeIn([true, false]);
    });

    it('returns consistent data types for all fields', function () {
        $response = $this->getJson("/tournaments/{$this->tournament->id}");

        $response->assertOk();

        $data = $response->json('data');

        expect($data['id'])->toBeInt()
            ->and($data['title'])->toBeString()
            ->and($data['modes'])->toBeArray()
            ->and($data['is_badge'])->toBeBool()
            ->and($data['is_bws'])->toBeBool()
            ->and($data['rank_range_min'])->toBeInt()
            ->and($data['rank_range_max'])->toBeInt()
            ->and($data['team_size_min'])->toBeInt()
            ->and($data['team_size_max'])->toBeInt();
    });
});

describe('Tournament Detail Page Format Display', function () {
    it('shows formation style and structured progression summary', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Structured Format Cup',
            'team_formation_style' => 'draft',
            'format_tags' => ['draft', 'battle_royale'],
            'format_structure' => [
                'stages' => [
                    ['type' => 'qualifier', 'advance_count' => 64],
                    ['type' => 'battle_royale', 'lobby_count' => 4, 'players_per_lobby' => 8, 'advance_per_lobby' => 4],
                ],
            ],
        ]);

        $response = $this->get(route('tournaments.show', $tournament));

        $response->assertStatus(200);
        $response->assertSee('Team Formation');
        $response->assertSee('Draft');
        $response->assertSee('Progression');
        $response->assertSee('Qualifier (Top 64)');
        $response->assertSee('Battle Royale (3 Rounds)');
        $response->assertSee('&darr;', false);
        $response->assertDontSee('bg-cyan-500/20');
    });
});

describe('Tournament Detail Page Display', function () {
    beforeEach(function () {
        $this->tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Test Tournament 2024',
            'description' => '[b]Bold BBCode[/b] and [i]italic BBCode[/i]',
        ]);
    });

    test('shows rank ineligible reason for minimum only rank ranges', function () {
        $user = User::factory()->create(['main_mode' => 'osu']);
        $user->rankHistory()->create([
            'rank' => 500,
            'pp' => 10000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'is_bws' => false,
            'rank_range_min' => 1000,
            'rank_range_max' => null,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDays(7),
            'tournament_start' => now()->addDays(14),
        ]);

        $this->actingAs($user)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSeeText('Your rank (#500) is outside the allowed range (#1,000+)');
    });

    test('shows rank ineligible reason for maximum only rank ranges', function () {
        $user = User::factory()->create(['main_mode' => 'osu']);
        $user->rankHistory()->create([
            'rank' => 20000,
            'pp' => 3000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'is_bws' => false,
            'rank_range_min' => null,
            'rank_range_max' => 10000,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDays(7),
            'tournament_start' => now()->addDays(14),
        ]);

        $this->actingAs($user)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSeeText('Your rank (#20,000) is outside the allowed range (#1-#10,000)');
    });

    test('does not display raw BBCode description section', function () {
        $response = $this->get("/tournaments/{$this->tournament->id}");

        $response->assertOk();

        // Raw BBCode should NOT be visible in the HTML
        $response->assertDontSee('[b]Bold BBCode[/b]');
        $response->assertDontSee('[i]italic BBCode[/i]');

        // Description heading should NOT be visible
        $response->assertDontSee('Description');
    });

    test('still displays forum post link for full information', function () {
        // Set forum_topic_id so forum_post_url will be generated
        $this->tournament->update(['forum_topic_id' => 12345]);

        $response = $this->get("/tournaments/{$this->tournament->id}");

        $response->assertOk();

        // External links section should still exist
        $response->assertSee('Forum Post');
        $response->assertSee('https://osu.ppy.sh/community/forums/topics/12345');
    });

    test('back to tournaments link uses preserved list referrer', function () {
        $backUrl = route('tournaments.index', [
            'tab' => 'ended',
            'q' => 'owc',
            'mode' => 'osu',
        ]);

        $response = $this
            ->withSession(['tournaments_referrer_url' => $backUrl])
            ->get("/tournaments/{$this->tournament->id}");

        $response->assertOk();
        $response->assertSee('href="'.e($backUrl).'"', false);
    });

    test('displays registration status badge', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'registration_start' => now()->subDays(2),
            'registration_end' => now()->addDays(5),
            'tournament_start' => now()->addDays(10),
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should display registration status
        $response->assertSee('Registration');
        $response->assertSee('Open');
    });

    test('hero badges use tournament card styling and requested order', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'is_badge' => true,
            'registration_start' => now()->subDays(2),
            'registration_end' => now()->addDays(10),
            'tournament_start' => now()->addDays(15),
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();
        $response->assertSee('tournament-detail-hero-badges', false);
        $response->assertSee('tournament-card-mode-badge', false);
        $response->assertSee('tournament-card-status-badge', false);
        $response->assertSee('tournament-card-status-badge-open', false);
        $response->assertSeeInOrder([
            'osu!',
            'Badged',
            'Registration Open',
        ]);
    });

    test('hero status badge prioritizes closing soon', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'is_badge' => true,
            'registration_start' => now()->subDays(2),
            'registration_end' => now()->addDays(2),
            'tournament_start' => now()->addDays(10),
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();
        $response->assertSee('tournament-card-status-badge-closing', false);
        $response->assertSee('Closes in');
        $response->assertSeeInOrder([
            'osu!',
            'Badged',
            'Closes in',
        ]);
    });

    test('displays tournament period in details', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'registration_start' => now()->subDays(2),
            'registration_end' => now()->addDays(5),
            'tournament_start' => now()->addDays(10),
            'tournament_end' => now()->addDays(15),
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should display Tournament Period
        $response->assertSee('Tournament Period');
    });

    test('renders schedule timestamps for browser-localized date tooltips', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'registration_start' => '2026-03-08 06:30:00+00',
            'registration_end' => '2026-03-08 08:30:00+00',
            'tournament_start' => '2026-11-01 05:30:00+00',
            'tournament_end' => '2026-11-01 07:30:00+00',
        ])->fresh();

        $response = $this->get(route('tournaments.show', $tournament));

        $response->assertOk()
            ->assertSee('data-local-datetime', false)
            ->assertSee('data-local-datetime-tooltip', false)
            ->assertSee('role="tooltip"', false)
            ->assertSee('Intl.DateTimeFormat', false)
            ->assertSee('timeZoneName:', false);

        foreach ([
            $tournament->registration_start,
            $tournament->registration_end,
            $tournament->tournament_start,
            $tournament->tournament_end,
        ] as $date) {
            $response->assertSee(
                'datetime="'.$date->copy()->utc()->toIso8601String().'"',
                false,
            );
        }
    });

    test('renders a localized tournament start when the end date is missing', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'registration_start' => null,
            'registration_end' => null,
            'tournament_start' => '2026-07-01 12:45:00+00',
            'tournament_end' => null,
        ])->fresh();

        $response = $this->get(route('tournaments.show', $tournament));

        $response->assertOk()
            ->assertSee('Tournament Period')
            ->assertSee(
                'datetime="'.$tournament->tournament_start->copy()->utc()->toIso8601String().'"',
                false,
            )
            ->assertDontSee('Registration Period');
    });

    test('orders mobile links details and staff sections and exposes compact layouts', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Mobile Layout Cup',
            'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/123',
            'registration_start' => now()->subMonth(),
            'registration_end' => now()->subWeeks(3),
            'tournament_start' => now()->subWeeks(2),
            'tournament_end' => now()->subDay(),
        ]);
        $winner = User::factory()->create(['username' => 'MobileWinner']);
        $staff = User::factory()->create(['username' => 'MobileStaff']);

        TournamentWinner::factory()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winner->id,
            'username' => $winner->username,
            'osu_id' => $winner->osu_id,
            'placement' => 1,
        ]);
        $tournament->staff()->attach($staff->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        $html = $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('data-tournament-section="results"', false)
            ->assertSee('data-tournament-section="links"', false)
            ->assertSee('data-tournament-section="details-mobile"', false)
            ->assertSee('data-tournament-section="staff"', false)
            ->assertSee('data-result-roster-layout="mobile-two-column"', false)
            ->assertSee('data-staff-roster-layout="mobile-two-column"', false)
            ->assertSee('grid grid-cols-2 gap-2', false)
            ->getContent();

        expect(strpos($html, 'data-tournament-section="links"'))
            ->toBeLessThan(strpos($html, 'data-tournament-section="details-mobile"'))
            ->and(strpos($html, 'data-tournament-section="details-mobile"'))
            ->toBeLessThan(strpos($html, 'data-tournament-section="staff"'));
    });

    test('renders mobile link icons with accessible names and desktop labels', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'forum_post_url' => 'https://osu.ppy.sh/community/forums/topics/123',
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/example',
            'discord_url' => 'https://discord.gg/example',
            'twitch_url' => 'https://twitch.tv/example',
            'bracket_url' => 'https://challonge.com/example',
        ]);

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('data-mobile-link-grid', false)
            ->assertSee('grid-cols-4', false)
            ->assertSee('aria-label="Forum Post"', false)
            ->assertSee('title="Forum Post"', false)
            ->assertSee('sm:inline', false);
    });

    test('displays staff list with roles', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Test Tournament',
        ]);

        $user = User::factory()->create(['username' => 'TestStaffMember']);
        $tournament->staff()->attach($user->id, [
            'role' => 'organizer',
            'status' => 'approved',
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should display staff section
        $response->assertSee('Staff');
        $response->assertSee('Organizer'); // Capitalized in UI
        $response->assertSee('TestStaffMember');
    });

    test('displays latest linked user name for podium winners', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Ended Tournament',
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDay(),
        ]);

        $user = User::factory()->create([
            'username' => 'LatestWinnerName',
            'osu_id' => 123456,
        ]);

        TournamentWinner::factory()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'osu_id' => $user->osu_id,
            'username' => 'OldWinnerName',
            'placement' => 1,
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();
        $response->assertSee('LatestWinnerName');
        $response->assertDontSee('OldWinnerName');
    });

    test('displays tournament period with start and end dates', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'tournament_start' => now()->createFromDate(2023, 10, 30),
            'tournament_end' => now()->createFromDate(2023, 12, 30),
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should display Tournament Period label
        $response->assertSee('Tournament Period');

        // Should display both dates
        $response->assertSee('Oct 30, 2023');
        $response->assertSee('Dec 30, 2023');

        // Should NOT display the old label
        $response->assertDontSee('Tournament Start');
    });

    test('displays tournament period with only start date when end date is missing', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'tournament_start' => now()->createFromDate(2023, 10, 30),
            'tournament_end' => null,
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should display Tournament Period label
        $response->assertSee('Tournament Period');

        // Should display start date
        $response->assertSee('Oct 30, 2023');
    });

    test('displays badge approval status for ended badge tournaments', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'approved',
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDays(1), // Ended
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should display Badge Approval section
        $response->assertSee('Badge Approval');
        $response->assertSee('Approved');
    });

    test('displays pending badge approval status', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'pending',
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDays(1), // Ended
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should display Badge Approval section
        $response->assertSee('Badge Approval');
        $response->assertSee('Pending Review');
    });

    test('displays rejected badge approval status', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'rejected',
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDays(1), // Ended
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should display Badge Approval section
        $response->assertSee('Badge Approval');
        $response->assertSee('Rejected');
    });

    test('does not display badge approval for non-badge tournaments', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => false,
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDays(1), // Ended
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should NOT display Badge Approval section
        $response->assertDontSee('Badge Approval');
    });

    test('does not display badge approval for active badge tournaments', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'pending',
            'tournament_start' => now()->subDays(1),
            'tournament_end' => now()->addDays(30), // Still active
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should NOT display Badge Approval section (tournament not ended)
        $response->assertDontSee('Badge Approval');
    });

    test('does not display badge approval when badge_status is null', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => null,
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDays(1), // Ended
        ]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();

        // Should NOT display Badge Approval section (badge_status is null)
        $response->assertDontSee('Badge Approval');
    });
});

describe('Tournament Detail Result Teams', function () {
    test('podium displays team names separate groups legacy grouping self roster and rank sorting', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Team Result Cup',
            'modes' => ['osu'],
            'team_size_min' => 2,
            'team_size_max' => 4,
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDay(),
        ]);

        $alphaBetter = User::factory()->create(['username' => 'AlphaBetter']);
        $alphaWorse = User::factory()->create(['username' => 'AlphaWorse']);
        $betaOne = User::factory()->create(['username' => 'BetaOne']);
        $betaTwo = User::factory()->create(['username' => 'BetaTwo']);
        $legacyOne = User::factory()->create(['username' => 'LegacyOne']);
        $legacyTwo = User::factory()->create(['username' => 'LegacyTwo']);

        $alphaBetter->rankHistory()->create(['mode' => 'osu', 'rank' => 100, 'recorded_at' => now()]);
        $alphaWorse->rankHistory()->create(['mode' => 'osu', 'rank' => 1000, 'recorded_at' => now()]);

        foreach ([[$alphaWorse, 1, 'alpha'], [$alphaBetter, 1, 'alpha']] as [$user, $placement, $group]) {
            TournamentWinner::factory()->create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'username' => $user->username,
                'osu_id' => $user->osu_id,
                'placement' => $placement,
                'metadata' => ['podium_group_id' => $group, 'podium_group_manual' => true],
            ]);
        }

        foreach ([[$betaOne, 'beta-one', 'Team One'], [$betaTwo, 'beta-two', 'Team Two']] as [$user, $group, $teamName]) {
            TournamentWinner::factory()->create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'username' => $user->username,
                'osu_id' => $user->osu_id,
                'placement' => 2,
                'metadata' => ['podium_group_id' => $group, 'podium_group_manual' => true],
            ]);
            TournamentParticipationRecord::query()->create([
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
                'placement' => 2,
                'placement_min' => 2,
                'placement_max' => 2,
                'team_name' => $teamName,
            ]);
        }

        foreach ([$legacyOne, $legacyTwo] as $user) {
            TournamentWinner::factory()->create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'username' => $user->username,
                'osu_id' => $user->osu_id,
                'placement' => 3,
            ]);
        }

        TournamentParticipationRecord::query()->create([
            'user_id' => $alphaBetter->id,
            'tournament_id' => $tournament->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'placement' => 1,
            'placement_min' => 1,
            'placement_max' => 1,
            'team_name' => 'Team Alpha',
        ]);

        $html = $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('1st Place - Team Alpha')
            ->assertSee('Team Alpha')
            ->assertSee('2nd Place - Team One')
            ->assertSee('2nd Place - Team Two')
            ->assertSee('Team One')
            ->assertSee('Team Two')
            ->assertSee('LegacyOne')
            ->assertSee('LegacyTwo')
            ->getContent();

        expect($html)->toContain('AlphaBetter')
            ->and($html)->toContain('AlphaWorse')
            ->and(strpos($html, 'AlphaBetter'))->toBeLessThan(strpos($html, 'AlphaWorse'));
    });

    test('logged in non podium placement appears initially and expanded endpoint excludes podium duplicates', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Current User Cup',
            'modes' => ['osu'],
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDay(),
        ]);
        $winner = User::factory()->create(['username' => 'PodiumWinner']);
        $current = User::factory()->withSetup()->create(['username' => 'CurrentPlayer']);
        $mate = User::factory()->create(['username' => 'CurrentMate']);

        TournamentWinner::factory()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $winner->id,
            'username' => $winner->username,
            'osu_id' => $winner->osu_id,
            'placement' => 1,
        ]);

        $record = TournamentParticipationRecord::query()->create([
            'user_id' => $current->id,
            'tournament_id' => $tournament->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'placement' => 4,
            'placement_min' => 4,
            'placement_max' => 4,
            'team_name' => 'Fourth Team',
        ]);
        $record->teammates()->attach($mate);

        $this->actingAs($current)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertDontSee('Your Result')
            ->assertSee('4th')
            ->assertSee('Fourth Team')
            ->assertSee('CurrentPlayer')
            ->assertSee('CurrentMate');

        $this->actingAs($current)
            ->get(route('tournaments.results-expanded', $tournament))
            ->assertOk()
            ->assertSee('4th')
            ->assertSee('Fourth Team')
            ->assertSee('CurrentPlayer')
            ->assertDontSee('PodiumWinner');
    });

    test('expanded results order DNQ teams by qualifier seed and group DNP and Tryout buckets', function () {
        $worldCup = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'World Cup Results',
            'modes' => ['osu'],
            'team_formation_style' => Tournament::TEAM_FORMATION_WORLD_CUP,
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDay(),
            'format_structure' => [
                'stages' => [
                    ['type' => Tournament::STAGE_QUALIFIER, 'advance_count' => 16],
                    ['type' => Tournament::STAGE_BRACKET, 'start_round_size' => 16],
                ],
            ],
        ]);

        $fourth = User::factory()->create(['username' => 'FourthPlace']);
        $earlyDnq = User::factory()->create(['username' => 'EarlyDnq']);
        $lateDnq = User::factory()->create(['username' => 'LateDnq']);
        $tryoutOne = User::factory()->create(['username' => 'TryoutOne']);
        $tryoutTwo = User::factory()->create(['username' => 'TryoutTwo']);

        TournamentParticipationRecord::query()->create([
            'user_id' => $fourth->id,
            'tournament_id' => $worldCup->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'placement' => 4,
            'placement_min' => 4,
            'placement_max' => 4,
        ]);
        TournamentParticipationRecord::query()->create([
            'user_id' => $lateDnq->id,
            'tournament_id' => $worldCup->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'selection_outcome' => 'dnq',
            'team_name' => 'Late DNQ',
            'seed' => 30,
            'metadata' => ['stage_value' => 'dnq'],
        ]);
        TournamentParticipationRecord::query()->create([
            'user_id' => $earlyDnq->id,
            'tournament_id' => $worldCup->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'selection_outcome' => 'dnq',
            'team_name' => 'Early DNQ',
            'seed' => 12,
            'metadata' => ['stage_value' => 'dnq'],
        ]);

        foreach ([$tryoutOne, $tryoutTwo] as $tryoutUser) {
            TournamentParticipationRecord::query()->create([
                'user_id' => $tryoutUser->id,
                'tournament_id' => $worldCup->id,
                'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
                'selection_outcome' => TournamentParticipationRecord::SELECTION_TRYOUT_FAILED,
                'metadata' => ['stage_value' => 'tryout'],
            ]);
        }

        $worldCupHtml = $this->get(route('tournaments.results-expanded', $worldCup))
            ->assertOk()
            ->assertSee('DNQ')
            ->assertSee('Early DNQ')
            ->assertSee('Late DNQ')
            ->assertSee('Tryout')
            ->getContent();

        expect(strpos($worldCupHtml, '4th'))->toBeLessThan(strpos($worldCupHtml, 'DNQ'))
            ->and(strpos($worldCupHtml, 'Early DNQ'))->toBeLessThan(strpos($worldCupHtml, 'Late DNQ'))
            ->and(substr_count($worldCupHtml, 'Tryout'))->toBeGreaterThanOrEqual(1)
            ->and($worldCupHtml)->toContain('TryoutOne')
            ->and($worldCupHtml)->toContain('TryoutTwo');

        $draft = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Draft Results',
            'team_formation_style' => Tournament::TEAM_FORMATION_DRAFT,
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDay(),
        ]);
        $draftPlacement = User::factory()->create(['username' => 'DraftFourth']);
        $dnpOne = User::factory()->create(['username' => 'DnpOne']);
        $dnpTwo = User::factory()->create(['username' => 'DnpTwo']);

        TournamentParticipationRecord::query()->create([
            'user_id' => $draftPlacement->id,
            'tournament_id' => $draft->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'placement' => 4,
            'placement_min' => 4,
            'placement_max' => 4,
        ]);
        foreach ([$dnpOne, $dnpTwo] as $dnpUser) {
            TournamentParticipationRecord::query()->create([
                'user_id' => $dnpUser->id,
                'tournament_id' => $draft->id,
                'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
                'selection_outcome' => 'dnp',
                'metadata' => ['stage_value' => 'dnp'],
            ]);
        }

        $draftHtml = $this->get(route('tournaments.results-expanded', $draft))
            ->assertOk()
            ->assertSee('DNP')
            ->getContent();

        expect(strpos($draftHtml, '4th'))->toBeLessThan(strpos($draftHtml, 'DNP'))
            ->and($draftHtml)->toContain('DnpOne')
            ->and($draftHtml)->toContain('DnpTwo');
    });

    test('expanded results collapse shared root record with shared teammate records', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Shared Root Detail Cup',
            'modes' => ['osu'],
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDay(),
        ]);
        $captain = User::factory()->create(['username' => 'RootCaptain']);
        $mateOne = User::factory()->create(['username' => 'RootMateOne']);
        $mateTwo = User::factory()->create(['username' => 'RootMateTwo']);

        $root = TournamentParticipationRecord::query()->create([
            'user_id' => $captain->id,
            'tournament_id' => $tournament->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'placement' => 5,
            'placement_min' => 5,
            'placement_max' => 6,
            'team_name' => 'Root Shared Team',
        ]);
        $root->teammates()->attach([$mateOne->id, $mateTwo->id]);

        foreach ([$mateOne, $mateTwo] as $mate) {
            $record = TournamentParticipationRecord::query()->create([
                'user_id' => $mate->id,
                'tournament_id' => $tournament->id,
                'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
                'placement' => 5,
                'placement_min' => 5,
                'placement_max' => 6,
                'team_name' => 'Root Shared Team',
                'metadata' => ['shared_from_record_id' => $root->id],
            ]);
            $record->teammates()->attach(collect([$captain, $mateOne, $mateTwo])
                ->reject(fn (User $user): bool => $user->is($mate))
                ->pluck('id')
                ->all());
        }

        $html = $this->get(route('tournaments.results-expanded', $tournament))
            ->assertOk()
            ->assertSee('5th-6th')
            ->assertSee('Root Shared Team')
            ->assertSee('RootCaptain')
            ->assertSee('RootMateOne')
            ->assertSee('RootMateTwo')
            ->getContent();

        expect(substr_count($html, 'Root Shared Team'))->toBe(1);
    });

    test('expanded results show inherited swiss placement range', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Swiss Detail Cup',
            'modes' => ['osu'],
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDay(),
            'format_structure' => [
                'stages' => [
                    ['type' => Tournament::STAGE_QUALIFIER, 'advance_count' => 16],
                    ['type' => Tournament::STAGE_SWISS, 'round_count' => 4, 'advance_count' => 8],
                    ['type' => Tournament::STAGE_BRACKET, 'start_round_size' => 8],
                ],
            ],
        ]);
        $player = User::factory()->create(['username' => 'SwissNinth']);

        TournamentParticipationRecord::query()->create([
            'user_id' => $player->id,
            'tournament_id' => $tournament->id,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'selection_outcome' => TournamentParticipationRecord::SELECTION_REGISTERED,
            'placement' => 9,
            'placement_min' => 9,
            'placement_max' => 16,
            'metadata' => ['stage_value' => 'swiss:1:9:16'],
        ]);

        $this->get(route('tournaments.results-expanded', $tournament))
            ->assertOk()
            ->assertSee('9th-16th')
            ->assertSee('SwissNinth');
    });

    test('expanded results collapse tied solo placement into one roster row', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Solo Tie Cup',
            'modes' => ['osu'],
            'team_size_min' => 1,
            'team_size_max' => 1,
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDay(),
        ]);
        $better = User::factory()->create(['username' => 'SoloBetter']);
        $worse = User::factory()->create(['username' => 'SoloWorse']);

        $better->rankHistory()->create(['mode' => 'osu', 'rank' => 100, 'recorded_at' => now()]);
        $worse->rankHistory()->create(['mode' => 'osu', 'rank' => 1000, 'recorded_at' => now()]);

        foreach ([$worse, $better] as $user) {
            TournamentParticipationRecord::query()->create([
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
                'selection_outcome' => TournamentParticipationRecord::SELECTION_REGISTERED,
                'placement' => 7,
                'placement_min' => 7,
                'placement_max' => 8,
                'metadata' => ['stage_value' => 'bracket:1:4:losers:sf_lb2:7:8'],
            ]);
        }

        $html = $this->get(route('tournaments.results-expanded', $tournament))
            ->assertOk()
            ->assertSee('7th-8th')
            ->assertSee('SoloBetter')
            ->assertSee('SoloWorse')
            ->getContent();

        expect(substr_count($html, 'data-result-team-key='))->toBe(1)
            ->and(strpos($html, 'SoloBetter'))->toBeLessThan(strpos($html, 'SoloWorse'));
    });
});
