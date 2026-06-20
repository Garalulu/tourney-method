<?php

use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Services\BwsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tournament Eligibility Filtering', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'main_mode' => 'osu',
        ]);

        $this->bwsCalculator = app(BwsCalculator::class);
    });

    it('filters tournaments by user rank eligibility', function () {
        // User with rank 5000
        $this->user->rankHistory()->create([
            'rank' => 5000,
            'pp' => 6000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        // Create tournaments with different rank ranges
        $eligibleTournament = Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
            'is_bws' => false,
        ]);

        $tooHighRankTournament = Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => 1,
            'rank_range_max' => 1000,
            'is_bws' => false,
        ]);

        $tooLowRankTournament = Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => 10000,
            'rank_range_max' => 50000,
            'is_bws' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($eligibleTournament->id)
            ->and($tournamentIds)->not->toContain($tooHighRankTournament->id)
            ->and($tournamentIds)->not->toContain($tooLowRankTournament->id);
    });

    it('includes tournaments without rank restrictions', function () {
        $this->user->rankHistory()->create([
            'rank' => 50000,
            'pp' => 3000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $noRestrictionTournament = Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => null,
            'rank_range_max' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($noRestrictionTournament->id);
    });

    it('filters BWS tournaments using calculated BWS rank', function () {
        // User with rank 10000 and 5 eligible badges
        $this->user->rankHistory()->create([
            'rank' => 10000,
            'pp' => 5000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        // Create 5 eligible badges
        foreach (range(1, 5) as $i) {
            $this->user->badges()->create([
                'name' => "Badge {$i}",
                'image_url' => "https://example.com/badge{$i}.png",
                'awarded_at' => now()->subMonths($i),
                'is_bws_eligible' => true,
            ]);
        }

        // BWS calculation: rank^0.9937 * badge_count^0.8129
        // 10000^0.9937 * 5^0.8129 ≈ 9390
        $expectedBws = $this->bwsCalculator->calculate(10000, 5);

        // Tournament with rank range that includes BWS rank
        $bwsTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_bws' => true,
            'bws_base_exponent' => 0.9937,
            'bws_badge_power' => 0.8129,
            'rank_range_min' => 5000,
            'rank_range_max' => 15000,
        ]);

        // Tournament with rank range that excludes BWS rank
        $nonEligibleBwsTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_bws' => true,
            'bws_base_exponent' => 0.9937,
            'bws_badge_power' => 0.8129,
            'rank_range_min' => 1000,
            'rank_range_max' => 5000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        if ($expectedBws >= 5000 && $expectedBws <= 15000) {
            expect($tournamentIds)->toContain($bwsTournament->id);
        }

        if ($expectedBws < 1000 || $expectedBws > 5000) {
            expect($tournamentIds)->not->toContain($nonEligibleBwsTournament->id);
        }
    });

    it('uses only BWS-eligible badges for BWS calculation', function () {
        $this->user->rankHistory()->create([
            'rank' => 10000,
            'pp' => 5000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        // Create 3 eligible badges
        foreach (range(1, 3) as $i) {
            $badgeTournament = Tournament::factory()->create(['status' => 'approved']);

            $this->user->badges()->create([
                'name' => "Eligible Badge {$i}",
                'image_url' => "https://example.com/badge{$i}.png",
                'awarded_at' => now()->subMonths($i),
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

        // Create 2 non-eligible badges (e.g., Mapper, Contributor)
        foreach (range(1, 2) as $i) {
            $badgeTournament = Tournament::factory()->create(['status' => 'approved']);

            $this->user->badges()->create([
                'name' => "Mapper Badge {$i}",
                'image_url' => "https://example.com/mapper{$i}.png",
                'awarded_at' => now()->subMonths($i + 3),
                'is_bws_eligible' => false,
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

        // BWS should only count 3 eligible badges, not 5 total
        $expectedBws = $this->bwsCalculator->calculate(10000, 3, 0.9937, 0.8129);

        $bwsTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_bws' => true,
            'bws_base_exponent' => 0.9937,
            'bws_badge_power' => 0.8129,
            'rank_range_min' => floor($expectedBws) - 500,
            'rank_range_max' => ceil($expectedBws) + 500,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($bwsTournament->id);
    });

    it('handles user with no badges for BWS tournaments', function () {
        $this->user->rankHistory()->create([
            'rank' => 10000,
            'pp' => 5000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        // No badges means BWS rank = raw rank
        $bwsTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_bws' => true,
            'bws_base_exponent' => 0.9937,
            'bws_badge_power' => 0.8129,
            'rank_range_min' => 5000,
            'rank_range_max' => 15000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        // User rank 10000 is within range 5000-15000
        expect($tournamentIds)->toContain($bwsTournament->id);
    });

    it('excludes user with no rank history from all rank-restricted tournaments', function () {
        // User without rank history
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => 1000,
            'rank_range_max' => 50000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->not->toContain($tournament->id);
    });

    it('includes user with no rank history in tournaments without rank restrictions', function () {
        $noRestrictionTournament = Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => null,
            'rank_range_max' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($noRestrictionTournament->id);
    });

    it('filters by mode when user main_mode matches tournament modes', function () {
        $this->user->rankHistory()->create([
            'rank' => 5000,
            'pp' => 6000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $osuTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
        ]);

        $taikoTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['taiko'],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true&mode=osu');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($osuTournament->id)
            ->and($tournamentIds)->not->toContain($taikoTournament->id);
    });

    it('uses most recent rank history for eligibility check', function () {
        // Older rank history (eligible)
        $this->user->rankHistory()->create([
            'rank' => 5000,
            'pp' => 6000,
            'mode' => 'osu',
            'recorded_at' => now()->subDays(2),
        ]);

        // Most recent rank history (ineligible)
        $this->user->rankHistory()->create([
            'rank' => 500,
            'pp' => 12000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        // Should use most recent rank (500), which is ineligible
        expect($tournamentIds)->not->toContain($tournament->id);
    });

    it('combines eligible_only with other filters', function () {
        $this->user->rankHistory()->create([
            'rank' => 5000,
            'pp' => 6000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $badgeEligibleTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'modes' => ['osu'],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
        ]);

        $nonBadgeEligibleTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => false,
            'modes' => ['osu'],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true&is_badge=true&mode=osu');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        expect($tournamentIds)->toContain($badgeEligibleTournament->id)
            ->and($tournamentIds)->not->toContain($nonBadgeEligibleTournament->id);
    });

    it('handles partial rank range (only min)', function () {
        $this->user->rankHistory()->create([
            'rank' => 5000,
            'pp' => 6000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $minOnlyTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'rank_range_min' => 1000,
            'rank_range_max' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        // User rank 5000 >= min 1000, so eligible
        expect($tournamentIds)->toContain($minOnlyTournament->id);
    });

    it('handles partial rank range (only max)', function () {
        $this->user->rankHistory()->create([
            'rank' => 5000,
            'pp' => 6000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        $maxOnlyTournament = Tournament::factory()->create([
            'status' => 'approved',
            'modes' => ['osu'],
            'rank_range_min' => null,
            'rank_range_max' => 10000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();

        // User rank 5000 <= max 10000, so eligible
        expect($tournamentIds)->toContain($maxOnlyTournament->id);
    });

    it('requires authentication for eligible_only filter', function () {
        $response = $this->getJson('/tournaments?eligible_only=true');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);
    });

    it('returns empty list when no tournaments match eligibility', function () {
        $this->user->rankHistory()->create([
            'rank' => 500,
            'pp' => 12000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        // Create only ineligible tournaments
        Tournament::factory()->create([
            'status' => 'approved',
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('handles tournaments with extreme BWS values', function () {
        $this->user->rankHistory()->create([
            'rank' => 100000,
            'pp' => 1000,
            'mode' => 'osu',
            'recorded_at' => now(),
        ]);

        // Create 50 eligible badges (extreme case)
        foreach (range(1, 50) as $i) {
            $this->user->badges()->create([
                'name' => "Badge {$i}",
                'image_url' => "https://example.com/badge{$i}.png",
                'awarded_at' => now()->subMonths($i),
                'is_bws_eligible' => true,
            ]);
        }

        $extremeBwsTournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_bws' => true,
            'bws_base_exponent' => 0.9937,
            'bws_badge_power' => 0.8129,
            'rank_range_min' => 1,
            'rank_range_max' => 200000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/tournaments?eligible_only=true');

        $response->assertOk();

        // Should not cause errors even with extreme values
        $tournamentIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($tournamentIds)->toContain($extremeBwsTournament->id);
    });
});
