<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('Tournament List API', function () {
    beforeEach(function () {
        // Clear cache to ensure clean state for each test
        Cache::flush();

        // Create test tournaments
        $this->activeTournaments = Tournament::factory()->count(5)->create([
            'status' => 'approved',
            'tournament_start' => now()->addDays(7),
            'tournament_end' => now()->addDays(14),
        ]);

        $this->endedTournaments = Tournament::factory()->count(3)->create([
            'status' => 'approved',
            'tournament_start' => now()->subDays(30),
            'tournament_end' => now()->subDays(7),
        ]);

        // Create tournaments with specific modes
        $this->osuTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'tournament_start' => now()->addDays(7),
        ]);

        $this->taikoTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['taiko'],
            'tournament_start' => now()->addDays(7),
        ]);

        // Create badge and non-badge tournaments
        $this->badgeTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'tournament_start' => now()->addDays(7),
        ]);

        $this->nonBadgeTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => false,
            'tournament_start' => now()->addDays(7),
        ]);

        // Create tournament with specific rank range for eligibility testing
        $this->rankRestrictedTournament = Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
            'tournament_start' => now()->addDays(7),
        ]);

        // Create authenticated user with rank data
        $this->user = User::factory()->create([
            'main_mode' => 'osu',
        ]);
    });

    it('returns active tournaments by default', function () {
        $response = $this->getJson('/tournaments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'modes',
                        'is_badge',
                        'rank_range_min',
                        'rank_range_max',
                        'registration_end',
                        'tournament_start',
                        'banner_url',
                        'registration_closes_in',
                    ],
                ],
                // Laravel 11.x API Resource pagination structure
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'links',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        // Should not include ended tournaments
        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();
        foreach ($this->endedTournaments as $ended) {
            expect($tournamentIds)->not->toContain($ended->id);
        }
    });

    it('filters tournaments by status=active', function () {
        $response = $this->getJson('/tournaments?status=active');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        // Should include active tournaments
        expect($tournamentIds)->toContain($this->activeTournaments[0]->id);

        // Should not include ended tournaments
        foreach ($this->endedTournaments as $ended) {
            expect($tournamentIds)->not->toContain($ended->id);
        }
    });

    it('filters tournaments by status=ended', function () {
        $response = $this->getJson('/tournaments?status=ended');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        // Should include ended tournaments
        expect($tournamentIds)->toContain($this->endedTournaments[0]->id);

        // Should not include active tournaments
        foreach ($this->activeTournaments as $active) {
            expect($tournamentIds)->not->toContain($active->id);
        }
    });

    it('filters tournaments by mode', function () {
        $response = $this->getJson('/tournaments?mode=osu');

        $response->assertOk();

        $tournaments = $response->json('data');

        // All returned tournaments should have 'osu' in modes
        foreach ($tournaments as $tournament) {
            expect(collect($tournament['modes'])->pluck('mode'))->toContain('osu');
        }
    });

    it('filters tournaments by is_badge=true', function () {
        $response = $this->getJson('/tournaments?is_badge=true');

        $response->assertOk();

        $tournaments = $response->json('data');

        // All returned tournaments should have is_badge = true
        foreach ($tournaments as $tournament) {
            expect($tournament['is_badge'])->toBeTrue();
        }
    });

    it('filters tournaments by is_badge=false', function () {
        $response = $this->getJson('/tournaments?is_badge=false');

        $response->assertOk();

        $tournaments = $response->json('data');

        // All returned tournaments should have is_badge = false
        foreach ($tournaments as $tournament) {
            expect($tournament['is_badge'])->toBeFalse();
        }
    });

    it('filters ended tournaments by year', function () {
        $currentYear = now()->year;

        // Create tournaments from different years
        // Ended in current year (2026): start in late 2025, end early 2026
        $thisYearTournament = Tournament::factory()->create([
            'status' => 'approved',
            'tournament_start' => now()->startOfYear()->subDays(10),
            'tournament_end' => now()->subDays(2),
        ]);

        $lastYearTournament = Tournament::factory()->create([
            'status' => 'approved',
            'tournament_start' => now()->subYear()->startOfYear()->addDays(10),
            'tournament_end' => now()->subYear()->startOfYear()->addDays(20),
        ]);

        $response = $this->getJson("/tournaments?status=ended&year={$currentYear}");

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($thisYearTournament->id)
            ->and($tournamentIds)->not->toContain($lastYearTournament->id);
    });

    it('searches tournaments by title', function () {
        $searchableTournament = Tournament::factory()->create([
            'status' => 'approved',
            'title' => 'Special osu! World Cup 2024',
            'tournament_start' => now()->addDays(7),
        ]);

        $response = $this->getJson('/tournaments?search=World Cup');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($searchableTournament->id);
    });

    it('filters tournaments by eligible_only for authenticated user', function () {
        // Create user rank history with eligible rank
        $this->user->rankHistory()->create([
            'rank' => 5000,
            'pp' => 5000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        // Should include rank-restricted tournament (1000-10000 range)
        expect($tournamentIds)->toContain($this->rankRestrictedTournament->id);
    });

    it('excludes rank-restricted tournaments when user rank is outside range', function () {
        // Create user rank history with ineligible rank
        $this->user->rankHistory()->create([
            'rank' => 500, // Outside 1000-10000 range
            'pp' => 10000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        // Rank-restricted tournament should NOT be in results
        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($tournamentIds)->not->toContain($this->rankRestrictedTournament->id);
    });

    it('requires authentication for eligible_only filter', function () {
        $response = $this->getJson('/tournaments?eligible_only=true');

        $response->assertUnauthorized();
    });

    it('paginates results with default per_page=20', function () {
        // Create 25 tournaments
        Tournament::factory()->count(25)->create([
            'status' => 'approved',
            'tournament_start' => now()->addDays(7),
        ]);

        $response = $this->getJson('/tournaments');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', function ($total) {
                return $total >= 25;
            });

        expect(count($response->json('data')))->toBeLessThanOrEqual(20);
    });

    it('accepts custom per_page up to maximum 50', function () {
        $response = $this->getJson('/tournaments?per_page=30');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 30);
    });

    it('supports page parameter', function () {
        // Create enough tournaments for multiple pages
        Tournament::factory()->count(30)->create([
            'status' => 'approved',
            'tournament_start' => now()->addDays(7),
        ]);

        $page1 = $this->getJson('/tournaments?per_page=10&page=1');
        $page2 = $this->getJson('/tournaments?per_page=10&page=2');

        $page1->assertOk();
        $page2->assertOk();

        $page1Ids = collect($page1->json('data'))->pluck('id')->toArray();
        $page2Ids = collect($page2->json('data'))->pluck('id')->toArray();

        // Different pages should have different tournaments
        expect($page1Ids)->not->toEqual($page2Ids);
    });

    it('combines multiple filters', function () {
        // Create specific tournament matching all filters
        $targetTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'is_badge' => true,
            'tournament_start' => now()->addDays(7),
            'title' => 'osu! Badge Tournament 2024',
        ]);

        $response = $this->getJson('/tournaments?mode=osu&is_badge=true&search=Badge');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($targetTournament->id);
    });

    it('returns only approved tournaments to guests', function () {
        // Create tournaments with different statuses
        $approved = Tournament::factory()->create([
            'status' => 'approved',
            'tournament_start' => now()->addDays(7),
        ]);

        $pending = Tournament::factory()->create([
            'status' => 'pending_review',
            'tournament_start' => now()->addDays(7),
        ]);

        $rejected = Tournament::factory()->create([
            'status' => 'rejected',
            'tournament_start' => now()->addDays(7),
        ]);

        $response = $this->getJson('/tournaments');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($approved->id)
            ->and($tournamentIds)->not->toContain($pending->id)
            ->and($tournamentIds)->not->toContain($rejected->id);
    });

    it('includes registration_closes_in for tournaments with registration_end', function () {
        $tournamentWithRegistration = Tournament::factory()->create([
            'status' => 'approved',
            'registration_end' => now()->addDays(3)->addHours(5),
            'tournament_start' => now()->addDays(7),
        ]);

        $response = $this->getJson('/tournaments');

        $response->assertOk();

        $tournament = collect($response->json('data'))
            ->firstWhere('id', $tournamentWithRegistration->id);

        expect($tournament)->not->toBeNull()
            ->and($tournament['registration_closes_in'])->not->toBeNull();
    });

    it('includes null registration_closes_in for tournaments without registration_end', function () {
        $tournamentWithoutRegistration = Tournament::factory()->create([
            'status' => 'approved',
            'registration_end' => null,
            'tournament_start' => now()->addDays(7),
        ]);

        $response = $this->getJson('/tournaments');

        $response->assertOk();

        $tournament = collect($response->json('data'))
            ->firstWhere('id', $tournamentWithoutRegistration->id);

        expect($tournament)->not->toBeNull()
            ->and($tournament['registration_closes_in'])->toBeNull();
    });

    describe('Ongoing Filter', function () {
        it('shows only ongoing tournaments when ongoing filter is enabled', function () {
            // Create ongoing tournament (registration closed, tournament active)
            $ongoingTournament = Tournament::factory()->create([
                'status' => 'approved',
                'registration_end' => now()->subDays(1), // Registration closed
                'tournament_start' => now()->subDays(1), // Started 1 day ago
                'tournament_end' => now()->addDays(3), // Ends in 3 days
            ]);

            // Create non-ongoing tournament (registration open)
            $openRegistrationTournament = Tournament::factory()->create([
                'status' => 'approved',
                'registration_end' => now()->addDays(3), // Registration open
                'tournament_start' => now()->addDays(7), // Starts in 7 days
            ]);

            $response = $this->getJson('/tournaments?ongoing=true');

            $response->assertOk();

            $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

            expect($tournamentIds)->toContain($ongoingTournament->id)
                ->and($tournamentIds)->not->toContain($openRegistrationTournament->id);
        });

        it('includes ongoing tournaments in the default active listing', function () {
            // Create ongoing tournament
            $ongoingTournament = Tournament::factory()->create([
                'status' => 'approved',
                'registration_end' => now()->subDays(1),
                'tournament_start' => now()->subDays(1),
                'tournament_end' => now()->addDays(3),
            ]);

            // Create regular tournament
            $regularTournament = Tournament::factory()->create([
                'status' => 'approved',
                'registration_end' => now()->addDays(3),
                'tournament_start' => now()->addDays(7),
            ]);

            $response = $this->getJson('/tournaments');

            $response->assertOk();

            $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

            expect($tournamentIds)->toContain($regularTournament->id)
                ->and($tournamentIds)->toContain($ongoingTournament->id);
        });
    });

    describe('Sorting Priority', function () {
        it('sorts by registration open first, then ongoing badge, then high rank, then date', function () {
            // Create tournaments with different priority characteristics
            $openRegistration = Tournament::factory()->create([
                'status' => 'approved',
                'registration_end' => now()->addDays(5), // Registration open
                'tournament_start' => now()->addDays(10),
                'rank_range_min' => 1000,
                'rank_range_max' => 10000,
                'is_badge' => false,
            ]);

            $ongoingBadge = Tournament::factory()->create([
                'status' => 'approved',
                'registration_end' => now()->subDays(1), // Registration closed
                'tournament_start' => now()->subDays(1), // Tournament active
                'tournament_end' => now()->addDays(3),
                'rank_range_min' => 100,
                'rank_range_max' => 500,
                'is_badge' => true,
            ]);

            $ongoingHighRank = Tournament::factory()->create([
                'status' => 'approved',
                'registration_end' => now()->subDays(1), // Registration closed
                'tournament_start' => now()->subDays(1), // Tournament active
                'tournament_end' => now()->addDays(3),
                'rank_range_min' => 1,
                'rank_range_max' => 100,
                'is_badge' => false,
            ]);

            $regularTournament = Tournament::factory()->create([
                'status' => 'approved',
                'registration_end' => now()->subDays(1), // Registration closed
                'tournament_start' => now()->addDays(5),
                'tournament_end' => now()->addDays(10),
                'rank_range_min' => 1000,
                'rank_range_max' => 10000,
                'is_badge' => false,
            ]);

            $response = $this->getJson('/tournaments');

            $response->assertOk();

            $tournaments = collect($response->json('data'))
                ->whereIn('id', [
                    $openRegistration->id,
                    $ongoingBadge->id,
                    $ongoingHighRank->id,
                    $regularTournament->id,
                ])
                ->values();

            // First tournament should be open registration
            expect($tournaments[0]['id'])->toBe($openRegistration->id);

            // Second tournament should be ongoing badge
            expect($tournaments[1]['id'])->toBe($ongoingBadge->id);

            // Third tournament should be ongoing high rank
            expect($tournaments[2]['id'])->toBe($ongoingHighRank->id);

            // Last tournament should be regular
            expect($tournaments[3]['id'])->toBe($regularTournament->id);
        });
    });

    describe('Default Gamemode Filter', function () {
        it('uses user\'s main_mode as default gamemode filter when authenticated', function () {
            // Create user with osu as main mode
            $user = User::factory()->create(['main_mode' => 'osu']);

            // Create osu tournament
            $osuTournament = Tournament::factory()->create([
                'status' => 'approved',
                'modes' => ['osu'],
                'registration_end' => now()->addDays(3),
            ]);

            // Create taiko tournament
            $taikoTournament = Tournament::factory()->create([
                'status' => 'approved',
                'modes' => ['taiko'],
                'registration_end' => now()->addDays(3),
            ]);

            $response = $this->actingAs($user)->getJson('/tournaments');

            $response->assertOk();

            $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

            expect($tournamentIds)->toContain($osuTournament->id)
                ->and($tournamentIds)->not->toContain($taikoTournament->id);
        });

        it('shows all tournaments when not authenticated', function () {
            // Create osu tournament
            $osuTournament = Tournament::factory()->create([
                'status' => 'approved',
                'modes' => ['osu'],
                'registration_end' => now()->addDays(3),
            ]);

            // Create taiko tournament
            $taikoTournament = Tournament::factory()->create([
                'status' => 'approved',
                'modes' => ['taiko'],
                'registration_end' => now()->addDays(3),
            ]);

            $response = $this->getJson('/tournaments');

            $response->assertOk();

            $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

            expect($tournamentIds)->toContain($osuTournament->id)
                ->and($tournamentIds)->toContain($taikoTournament->id);
        });
    });

    describe('Filter Combinations', function () {
        it('combines ongoing filter with other filters correctly', function () {
            // Create ongoing tournament with osu mode
            $ongoingOsuTournament = Tournament::factory()->create([
                'status' => 'approved',
                'modes' => ['osu'],
                'registration_end' => now()->subDays(1),
                'tournament_start' => now()->subDays(1),
                'tournament_end' => now()->addDays(3),
                'is_badge' => false,
            ]);

            // Create ongoing tournament with taiko mode
            $ongoingTaikoTournament = Tournament::factory()->create([
                'status' => 'approved',
                'modes' => ['taiko'],
                'registration_end' => now()->subDays(1),
                'tournament_start' => now()->subDays(1),
                'tournament_end' => now()->addDays(3),
                'is_badge' => false,
            ]);

            // Create non-ongoing osu tournament
            $nonOngoingOsuTournament = Tournament::factory()->create([
                'status' => 'approved',
                'modes' => ['osu'],
                'registration_end' => now()->addDays(3),
                'tournament_start' => now()->addDays(7),
                'is_badge' => false,
            ]);

            $response = $this->getJson('/tournaments?ongoing=true&mode=osu');

            $response->assertOk();

            $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

            expect($tournamentIds)->toContain($ongoingOsuTournament->id)
                ->and($tournamentIds)->not->toContain($ongoingTaikoTournament->id)
                ->and($tournamentIds)->not->toContain($nonOngoingOsuTournament->id);
        });
    });
});
