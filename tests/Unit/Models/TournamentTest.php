<?php

use App\Models\Tournament;
use App\Models\User;
use App\Models\UserRankHistory;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create([
        'modes' => ['osu', ['mode' => 'mania', 'key_count' => 4]],
        'start_round_size' => 32,
        'restricted_countries' => ['KR', 'JP', 'US'],
        'rank_range_min' => 1000,
        'rank_range_max' => 10000,
    ]);
});

describe('Tournament Model - Start Round Size', function () {
    test('start_round_size accepts powers of 2', function ($value) {
        $tournament = Tournament::factory()->create(['start_round_size' => $value]);

        expect($tournament->start_round_size)->toBe($value);
    })->with([2, 4, 8, 16, 32, 64, 128, 256, 512, 1024]);

    test('start_round_size can be null', function () {
        $tournament = Tournament::factory()->create(['start_round_size' => null]);

        expect($tournament->start_round_size)->toBeNull();
    });
});

describe('Tournament Model - Enhanced Modes', function () {
    test('modes_with_details_returns_enhanced_format', function () {
        expect($this->tournament->modes_with_details)->toHaveCount(2);
        expect($this->tournament->modes_with_details[0])->toBe(['mode' => 'osu', 'key_count' => null]);
        expect($this->tournament->modes_with_details[1])->toBe(['mode' => 'mania', 'key_count' => 4]);
    });

    test('modes_with_details_handles_legacy_string_format', function () {
        $tournament = Tournament::factory()->create(['modes' => ['osu', 'taiko', 'mania']]);

        expect($tournament->modes_with_details)->toHaveCount(3);
        expect($tournament->modes_with_details[0])->toBe(['mode' => 'osu', 'key_count' => null]);
        expect($tournament->modes_with_details[1])->toBe(['mode' => 'taiko', 'key_count' => null]);
        expect($tournament->modes_with_details[2])->toBe(['mode' => 'mania', 'key_count' => null]);
    });

    test('modes_with_details_handles_mixed_format', function () {
        $tournament = Tournament::factory()->create([
            'modes' => ['osu', ['mode' => 'mania', 'key_count' => 7], 'taiko'],
        ]);

        expect($tournament->modes_with_details)->toHaveCount(3);
        expect($tournament->modes_with_details[0])->toBe(['mode' => 'osu', 'key_count' => null]);
        expect($tournament->modes_with_details[1])->toBe(['mode' => 'mania', 'key_count' => 7]);
        expect($tournament->modes_with_details[2])->toBe(['mode' => 'taiko', 'key_count' => null]);
    });

    test('has_mania_variant_detects_key_counts', function () {
        expect($this->tournament->hasManiaVariant(4))->toBeTrue();
        expect($this->tournament->hasManiaVariant(7))->toBeFalse();
        expect($this->tournament->hasManiaVariant())->toBeTrue(); // Any mania
    });

    test('has_mania_variant_returns_false_for_no_mania', function () {
        $tournament = Tournament::factory()->create(['modes' => ['osu', 'taiko']]);

        expect($tournament->hasManiaVariant())->toBeFalse();
        expect($tournament->hasManiaVariant(4))->toBeFalse();
        expect($tournament->hasManiaVariant(7))->toBeFalse();
    });

    test('has_mania_variant_handles_legacy_mania_format', function () {
        $tournament = Tournament::factory()->create(['modes' => ['osu', 'mania']]);

        expect($tournament->hasManiaVariant())->toBeTrue();
        expect($tournament->hasManiaVariant(4))->toBeFalse(); // No specific key count
    });
});

describe('Tournament Model - Regional Restrictions', function () {
    test('is_region_restricted_returns_true_when_countries_set', function () {
        expect($this->tournament->isRegionRestricted())->toBeTrue();
    });

    test('is_region_restricted_returns_false_when_no_countries', function () {
        $tournament = Tournament::factory()->create(['restricted_countries' => null]);

        expect($tournament->isRegionRestricted())->toBeFalse();
    });

    test('is_region_restricted_returns_false_when_empty_array', function () {
        $tournament = Tournament::factory()->create(['restricted_countries' => []]);

        expect($tournament->isRegionRestricted())->toBeFalse();
    });

    test('is_user_eligible_by_country_checks_restriction', function () {
        expect($this->tournament->isUserEligibleByCountry('KR'))->toBeTrue();
        expect($this->tournament->isUserEligibleByCountry('JP'))->toBeTrue();
        expect($this->tournament->isUserEligibleByCountry('US'))->toBeTrue();
        expect($this->tournament->isUserEligibleByCountry('GB'))->toBeFalse();
        expect($this->tournament->isUserEligibleByCountry('FR'))->toBeFalse();
    });

    test('is_user_eligible_by_country_allows_all_when_no_restriction', function () {
        $tournament = Tournament::factory()->create(['restricted_countries' => null]);

        expect($tournament->isUserEligibleByCountry('KR'))->toBeTrue();
        expect($tournament->isUserEligibleByCountry('US'))->toBeTrue();
        expect($tournament->isUserEligibleByCountry('GB'))->toBeTrue();
        expect($tournament->isUserEligibleByCountry(null))->toBeTrue();
    });

    test('is_user_eligible_by_country_returns_false_for_null_user_country_with_restriction', function () {
        expect($this->tournament->isUserEligibleByCountry(null))->toBeFalse();
    });

    test('is_user_eligible_by_country_handles_case_insensitive', function () {
        expect($this->tournament->isUserEligibleByCountry('kr'))->toBeTrue();
        expect($this->tournament->isUserEligibleByCountry('Kr'))->toBeTrue();
        expect($this->tournament->isUserEligibleByCountry('KR'))->toBeTrue();
    });
});

describe('Tournament Model - Banner Caching', function () {
    test('cached_banner_url_returns_original_when_no_banner', function () {
        $tournament = Tournament::factory()->create(['banner_url' => null]);

        expect($tournament->cached_banner_url)->toBe($tournament->getDefaultBannerUrl());
    });

    test('cached_banner_url_returns_original_when_banner_is_local', function () {
        $tournament = Tournament::factory()->create(['banner_url' => '/images/banner.png']);

        expect($tournament->cached_banner_url)->toBe('/images/banner.png');
    });

    test('cached_banner_url_returns_cached_url_when_cache_exists', function () {
        Storage::fake('public');

        $tournament = Tournament::factory()->create([
            'banner_url' => 'https://example.com/banner.jpg',
            'banner_image_cached_at' => now(),
        ]);

        Storage::disk('public')->put("banners/{$tournament->id}.jpg", 'fake-image-data');

        $expectedUrl = Storage::disk('public')->url("banners/{$tournament->id}.jpg");
        expect($tournament->cached_banner_url)->toBe($expectedUrl);
    });

    test('should_cache_banner_returns_true_for_external_banner', function () {
        $tournament = Tournament::factory()->create([
            'banner_url' => 'https://example.com/banner.jpg',
            'banner_image_cached_at' => null,
        ]);

        expect($tournament->shouldCacheBanner())->toBeTrue();
    });

    test('should_cache_banner_returns_false_for_local_banner', function () {
        $tournament = Tournament::factory()->create([
            'banner_url' => '/images/banner.png',
        ]);

        expect($tournament->shouldCacheBanner())->toBeFalse();
    });

    test('should_cache_banner_returns_false_when_no_banner', function () {
        $tournament = Tournament::factory()->create(['banner_url' => null]);

        expect($tournament->shouldCacheBanner())->toBeFalse();
    });

    test('should_cache_banner_returns_true_when_cache_expired', function () {
        Storage::fake('public');

        $tournament = Tournament::factory()->create([
            'banner_url' => 'https://example.com/banner.jpg',
            'banner_image_cached_at' => now()->subDays(10), // Older than 7 days
        ]);

        expect($tournament->shouldCacheBanner())->toBeTrue();
    });

    test('should_cache_banner_returns_false_when_cache_is_fresh', function () {
        Storage::fake('public');

        $tournament = Tournament::factory()->create([
            'banner_url' => 'https://example.com/banner.jpg',
            'banner_image_cached_at' => now()->subDays(2), // Less than 7 days
        ]);

        expect($tournament->shouldCacheBanner())->toBeFalse();
    });
});

describe('Tournament Model - Rank Display Formatting', function () {
    test('rank displays as #min-max when both values exist', function () {
        $tournament = Tournament::factory()->create([
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
        ]);

        expect($tournament->getRankRangeAttribute())->toBe('#1,000-#10,000');
    });

    test('rank displays as #min+ when only rank_min exists', function () {
        $tournament = Tournament::factory()->create([
            'rank_range_min' => 5000,
            'rank_range_max' => null,
        ]);

        expect($tournament->getRankRangeAttribute())->toBe('#5,000+');
    });

    test('rank displays as Open Rank when both are null', function () {
        $tournament = Tournament::factory()->create([
            'rank_range_min' => null,
            'rank_range_max' => null,
        ]);

        expect($tournament->getRankRangeAttribute())->toBe('Open Rank');
    });

    test('rank displays as #1-#max when only rank_max exists', function () {
        $tournament = Tournament::factory()->create([
            'rank_range_min' => null,
            'rank_range_max' => 25000,
        ]);

        expect($tournament->getRankRangeAttribute())->toBe('#1-#25,000');
    });

    test('uses proper number formatting with commas', function () {
        $tournament = Tournament::factory()->create([
            'rank_range_min' => 1000000,
            'rank_range_max' => 9999999,
        ]);

        expect($tournament->getRankRangeAttribute())->toBe('#1,000,000-#9,999,999');
    });
});

describe('Tournament Model - Ongoing Status', function () {
    test('is_ongoing returns true when registration closed AND tournament within date range', function () {
        // Tournament currently running (registration ended, tournament is active)
        $tournament = Tournament::factory()->create([
            'registration_end' => now()->subDays(1), // Registration closed 1 day ago
            'tournament_start' => now()->subDays(1), // Started 1 day ago
            'tournament_end' => now()->addDays(3), // Ends in 3 days
        ]);

        expect($tournament->is_ongoing)->toBeTrue();
    });

    test('is_ongoing returns false when registration is open', function () {
        // Tournament with open registration
        $tournament = Tournament::factory()->create([
            'registration_end' => now()->addDays(3), // Registration open
            'tournament_start' => now()->addDays(7), // Starts in 7 days
        ]);

        expect($tournament->is_ongoing)->toBeFalse();
    });

    test('is_ongoing returns false when tournament hasn\'t started', function () {
        // Tournament hasn't started yet (registration closed, but event hasn't started)
        $tournament = Tournament::factory()->create([
            'registration_end' => now()->subDays(1), // Registration closed
            'tournament_start' => now()->addDays(3), // Starts in 3 days
            'tournament_end' => now()->addDays(10), // Ends in 10 days
        ]);

        expect($tournament->is_ongoing)->toBeFalse();
    });

    test('is_ongoing returns false when tournament has ended', function () {
        // Tournament has ended (registration closed, event finished)
        $tournament = Tournament::factory()->create([
            'registration_end' => now()->subDays(10), // Registration closed
            'tournament_start' => now()->subDays(5), // Started 5 days ago
            'tournament_end' => now()->subDays(1), // Ended 1 day ago
        ]);

        expect($tournament->is_ongoing)->toBeFalse();
    });

    test('ongoing_status returns correct status strings', function () {
        // Test all possible ongoing status combinations
        $data = [
            // Open registration - should not be ongoing
            ['reg_start' => now()->subDays(2), 'reg_end' => now()->addDays(1), 'tourn_start' => now()->addDays(3), 'tourn_end' => now()->addDays(10), 'expected' => 'upcoming'],

            // Registration closed, hasn't started
            ['reg_start' => now()->subDays(5), 'reg_end' => now()->subDays(1), 'tourn_start' => now()->addDays(2), 'tourn_end' => now()->addDays(5), 'expected' => 'upcoming'],

            // Registration closed, ongoing
            ['reg_start' => now()->subDays(5), 'reg_end' => now()->subDays(1), 'tourn_start' => now()->subDays(1), 'tourn_end' => now()->addDays(2), 'expected' => 'ongoing'],

            // Registration closed, ended
            ['reg_start' => now()->subDays(10), 'reg_end' => now()->subDays(5), 'tourn_start' => now()->subDays(4), 'tourn_end' => now()->subDays(1), 'expected' => 'ended'],
        ];

        foreach ($data as $case) {
            $tournament = Tournament::factory()->create([
                'registration_start' => $case['reg_start'],
                'registration_end' => $case['reg_end'],
                'tournament_start' => $case['tourn_start'],
                'tournament_end' => $case['tourn_end'],
            ]);

            expect($tournament->ongoing_status)->toBe($case['expected']);
        }
    });
});

describe('Tournament Model - Registration Status Filters', function () {
    test('regOpen filter logic excludes tournaments where registration has not started', function () {
        // Tournament with registration starting in 3 days (should NOT be included)
        $futureTournament = Tournament::factory()->create([
            'registration_start' => now()->addDays(3),
            'registration_end' => now()->addDays(30),
            'status' => 'approved',
        ]);

        // Tournament with registration currently open (should be included)
        $openTournament = Tournament::factory()->create([
            'registration_start' => now()->subDays(3),
            'registration_end' => now()->addDays(30),
            'status' => 'approved',
        ]);

        // Tournament with no registration start date (should be included)
        $noStartTournament = Tournament::factory()->create([
            'registration_start' => null,
            'registration_end' => now()->addDays(30),
            'status' => 'approved',
        ]);

        // Tournament with registration ended (should NOT be included)
        $endedTournament = Tournament::factory()->create([
            'registration_start' => now()->subDays(30),
            'registration_end' => now()->subDays(3),
            'status' => 'approved',
        ]);

        // Apply the same logic as in TournamentList.php (FIXED implementation)
        $results = Tournament::query();

        // Fixed (CORRECT) implementation - checks both registration_start AND registration_end
        $results->where(function ($q) {
            $q->whereNull('registration_end')
                ->orWhere('registration_end', '>=', now());
        })->where(function ($q) {
            $q->whereNull('registration_start')
                ->orWhere('registration_start', '<=', now());
        });

        $results = $results->get();

        // The fixed implementation correctly excludes future tournaments
        // Compare IDs instead of objects because beforeEach creates an additional tournament
        expect($results->pluck('id')->toArray())->not->toContain($futureTournament->id);  // Should NOT be included (not started yet)
        expect($results->pluck('id')->toArray())->toContain($openTournament->id);          // Should be included
        expect($results->pluck('id')->toArray())->toContain($noStartTournament->id);      // Should be included
        expect($results->pluck('id')->toArray())->not->toContain($endedTournament->id);   // Should NOT be included
    });
});

describe('Tournament Model - Team Size Display', function () {
    test('team_size_display returns vs format when only vs_size is set', function () {
        $tournament = Tournament::factory()->create([
            'vs_size' => 2,
            'team_size_min' => null,
            'team_size_max' => null,
        ]);

        expect($tournament->team_size_display)->toBe('2v2');
    });

    test('team_size_display returns team size range when only team_size is set', function () {
        $tournament = Tournament::factory()->create([
            'vs_size' => null,
            'team_size_min' => 3,
            'team_size_max' => 6,
        ]);

        expect($tournament->team_size_display)->toBe('TS3-6');
    });

    test('team_size_display returns combined format when both are set', function () {
        $tournament = Tournament::factory()->create([
            'vs_size' => 2,
            'team_size_min' => 3,
            'team_size_max' => 6,
        ]);

        expect($tournament->team_size_display)->toBe('2v2 / TS3-6');
    });

    test('team_size_display returns single team size when min equals max', function () {
        $tournament = Tournament::factory()->create([
            'vs_size' => null,
            'team_size_min' => 4,
            'team_size_max' => 4,
        ]);

        expect($tournament->team_size_display)->toBe('TS4');
    });

    test('team_size_display returns empty string when neither is set', function () {
        $tournament = Tournament::factory()->create([
            'vs_size' => null,
            'team_size_min' => null,
            'team_size_max' => null,
        ]);

        expect($tournament->team_size_display)->toBe('');
    });
});

describe('Tournament Model - Star Rating Display', function () {
    test('star_rating_display returns formatted range with qualifier', function () {
        $tournament = Tournament::factory()->create([
            'star_rating_min' => 4.0,
            'star_rating_max' => 5.5,
            'star_rating_qualifier' => 4.75,  // Numeric threshold, not text
        ]);

        expect($tournament->star_rating_display)->toBe('*4~*5.5 (QL *4.75)');
    });

    test('star_rating_display returns formatted range without qualifier', function () {
        $tournament = Tournament::factory()->create([
            'star_rating_min' => 3.0,
            'star_rating_max' => 4.5,
            'star_rating_qualifier' => null,
        ]);

        expect($tournament->star_rating_display)->toBe('*3~*4.5');
    });

    test('star_rating_display returns null when min is missing', function () {
        $tournament = Tournament::factory()->create([
            'star_rating_min' => null,
            'star_rating_max' => 5.0,
        ]);

        expect($tournament->star_rating_display)->toBeNull();
    });

    test('star_rating_display returns null when max is missing', function () {
        $tournament = Tournament::factory()->create([
            'star_rating_min' => 4.0,
            'star_rating_max' => null,
        ]);

        expect($tournament->star_rating_display)->toBeNull();
    });
});

describe('Tournament Model - Registration Status', function () {
    test('registration_closed_but_not_started returns true when reg closed and tournament not started', function () {
        $tournament = Tournament::factory()->create([
            'registration_end' => now()->subDays(3),
            'tournament_start' => now()->addDays(7),
        ]);

        expect($tournament->registration_closed_but_not_started)->toBeTrue();
    });

    test('registration_closed_but_not_started returns false when registration is open', function () {
        $tournament = Tournament::factory()->create([
            'registration_end' => now()->addDays(7),
            'tournament_start' => now()->addDays(14),
        ]);

        expect($tournament->registration_closed_but_not_started)->toBeFalse();
    });

    test('registration_closed_but_not_started returns false when tournament has started', function () {
        $tournament = Tournament::factory()->create([
            'registration_end' => now()->subDays(7),
            'tournament_start' => now()->subDays(3),
        ]);

        expect($tournament->registration_closed_but_not_started)->toBeFalse();
    });
});

describe('Tournament Model - Eligibility Filters', function () {
    test('scopeEligibleFor includes open rank tournaments (NULL + NULL)', function () {
        $openRankTournament = Tournament::factory()->create([
            'rank_range_min' => null,
            'rank_range_max' => null,
            'is_bws' => false,
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'status' => 'approved',
        ]);

        $user = User::factory()->create(['main_mode' => 'osu']);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 50000,
            'country_rank' => 1000,
            'pp' => 1234.56,
            'recorded_at' => now(),
        ]);

        $results = Tournament::eligibleFor($user)->get();

        // Compare IDs instead of objects because beforeEach creates an additional tournament
        expect($results->pluck('id')->toArray())->toContain($openRankTournament->id);
    });

    test('scopeEligibleFor excludes users above upper bound (NULL + max)', function () {
        $upperBoundTournament = Tournament::factory()->create([
            'rank_range_min' => null,
            'rank_range_max' => 10000,
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'status' => 'approved',
        ]);

        // User with rank above max
        $highRankUser = User::factory()->create(['main_mode' => 'osu']);
        UserRankHistory::create([
            'user_id' => $highRankUser->id,
            'mode' => 'osu',
            'rank' => 20000,
            'country_rank' => 2000,
            'pp' => 500.00,
            'recorded_at' => now(),
        ]);

        $results = Tournament::eligibleFor($highRankUser)->get();

        expect($results)->not->toContain($upperBoundTournament);
    });

    test('scopeEligibleFor excludes users below lower bound (min + NULL)', function () {
        $lowerBoundTournament = Tournament::factory()->create([
            'rank_range_min' => 100000,
            'rank_range_max' => null,
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'status' => 'approved',
        ]);

        // User with rank below min
        $lowRankUser = User::factory()->create(['main_mode' => 'osu']);
        UserRankHistory::create([
            'user_id' => $lowRankUser->id,
            'mode' => 'osu',
            'rank' => 50000,
            'country_rank' => 1000,
            'pp' => 1234.56,
            'recorded_at' => now(),
        ]);

        $results = Tournament::eligibleFor($lowRankUser)->get();

        expect($results)->not->toContain($lowerBoundTournament);
    });

    test('scopeEligibleFor includes users within normal range (min + max)', function () {
        $normalRangeTournament = Tournament::factory()->create([
            'rank_range_min' => 10000,
            'rank_range_max' => 50000,
            'is_bws' => false,
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'status' => 'approved',
        ]);

        $eligibleUser = User::factory()->create(['main_mode' => 'osu']);
        UserRankHistory::create([
            'user_id' => $eligibleUser->id,
            'mode' => 'osu',
            'rank' => 25000,
            'country_rank' => 500,
            'pp' => 1234.56,
            'recorded_at' => now(),
        ]);

        $results = Tournament::eligibleFor($eligibleUser)->get();

        // Compare IDs instead of objects because beforeEach creates an additional tournament
        expect($results->pluck('id')->toArray())->toContain($normalRangeTournament->id);
    });

    test('isEligibleForTournament handles null rank requirements correctly', function () {
        // Test null rank_range_min
        $nullMinTournament = Tournament::factory()->create([
            'rank_range_min' => null,
            'rank_range_max' => 10000,
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'status' => 'approved',
        ]);

        // User with rank below 10000 should be eligible (no minimum)
        $lowUser = User::factory()->create(['main_mode' => 'osu']);
        UserRankHistory::create([
            'user_id' => $lowUser->id,
            'mode' => 'osu',
            'rank' => 5000,
            'country_rank' => 100,
            'pp' => 1000.00,
            'recorded_at' => now(),
        ]);

        expect($lowUser->isEligibleForTournament($nullMinTournament))->toBeTrue();

        // User with rank above 10000 should not be eligible
        $highUser = User::factory()->create(['main_mode' => 'osu']);
        UserRankHistory::create([
            'user_id' => $highUser->id,
            'mode' => 'osu',
            'rank' => 20000,
            'country_rank' => 2000,
            'pp' => 500.00,
            'recorded_at' => now(),
        ]);

        expect($highUser->isEligibleForTournament($nullMinTournament))->toBeFalse();

        // Test null rank_range_max
        $nullMaxTournament = Tournament::factory()->create([
            'rank_range_min' => 10000,
            'rank_range_max' => null,
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'status' => 'approved',
        ]);

        // User with rank above 10000 should be eligible (no maximum)
        $highUser2 = User::factory()->create(['main_mode' => 'osu']);
        UserRankHistory::create([
            'user_id' => $highUser2->id,
            'mode' => 'osu',
            'rank' => 50000,
            'country_rank' => 5000,
            'pp' => 500.00,
            'recorded_at' => now(),
        ]);

        expect($highUser2->isEligibleForTournament($nullMaxTournament))->toBeTrue();

        // User with rank below 10000 should not be eligible
        $lowUser2 = User::factory()->create(['main_mode' => 'osu']);
        UserRankHistory::create([
            'user_id' => $lowUser2->id,
            'mode' => 'osu',
            'rank' => 5000,
            'country_rank' => 100,
            'pp' => 1000.00,
            'recorded_at' => now(),
        ]);

        expect($lowUser2->isEligibleForTournament($nullMaxTournament))->toBeFalse();
    });
});
