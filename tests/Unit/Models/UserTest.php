<?php

use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserRankHistory;

beforeEach(function () {
    $this->user = User::factory()->create([
        'rank_mania_4k' => 7000,
        'rank_mania_7k' => 9000,
        'country_code' => 'KR',
    ]);

    // Create rank history records
    UserRankHistory::create([
        'user_id' => $this->user->id,
        'mode' => 'osu',
        'rank' => 5000,
        'country_rank' => 1000,
        'pp' => 1234.56,
        'recorded_at' => now(),
    ]);

    UserRankHistory::create([
        'user_id' => $this->user->id,
        'mode' => 'taiko',
        'rank' => 8000,
        'country_rank' => 500,
        'pp' => 1000.00,
        'recorded_at' => now(),
    ]);

    UserRankHistory::create([
        'user_id' => $this->user->id,
        'mode' => 'fruits',
        'rank' => 12000,
        'country_rank' => 2000,
        'pp' => 800.00,
        'recorded_at' => now(),
    ]);

    UserRankHistory::create([
        'user_id' => $this->user->id,
        'mode' => 'mania',
        'rank' => 15000,
        'country_rank' => 3000,
        'pp' => 500.00,
        'recorded_at' => now(),
    ]);
});

describe('User Model - Mania Rankings', function () {
    test('avatar_url is derived from osu_id', function () {
        $user = User::factory()->create(['osu_id' => 123456]);

        expect($user->avatar_url)->toBe('https://a.ppy.sh/123456')
            ->and($user->toArray()['avatar_url'])->toBe('https://a.ppy.sh/123456');
    });

    test('get_rank_for_mode_returns_correct_rank', function () {
        expect($this->user->getRankForMode('osu'))->toBe(5000);
        expect($this->user->getRankForMode('taiko'))->toBe(8000);
        expect($this->user->getRankForMode('fruits'))->toBe(12000); // 'catch' mode is stored as 'fruits'
        expect($this->user->getRankForMode('mania'))->toBe(15000);
    });

    test('get_rank_for_mode_returns_mania_specific_rank', function () {
        expect($this->user->getRankForMode('mania', 4))->toBe(7000);
        expect($this->user->getRankForMode('mania', 7))->toBe(9000);
    });

    test('get_rank_for_mode_returns_null_for_unknown_mode', function () {
        expect($this->user->getRankForMode('unknown'))->toBeNull();
    });

    test('get_mania_rank_returns_specific_key_count', function () {
        expect($this->user->getManiaRank(4))->toBe(7000);
        expect($this->user->getManiaRank(7))->toBe(9000);
    });

    test('get_mania_rank_returns_global_rank_when_no_specific_key', function () {
        expect($this->user->getManiaRank())->toBe(15000);
    });

    test('get_mania_rank_returns_null_for_unsupported_key_count', function () {
        expect($this->user->getManiaRank(8))->toBeNull();
        expect($this->user->getManiaRank(9))->toBeNull();
    });

    test('get_mania_rank_handles_null_rankings', function () {
        $user = User::factory()->create([
            'rank_mania_4k' => null,
            'rank_mania_7k' => null,
        ]);

        // No rank history records for mania
        expect($user->getManiaRank())->toBeNull();
        expect($user->getManiaRank(4))->toBeNull();
        expect($user->getManiaRank(7))->toBeNull();
    });
});

describe('User Model - Eligibility Checking', function () {
    beforeEach(function () {
        $this->tournament = Tournament::factory()->create([
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
            'restricted_countries' => ['KR', 'JP', 'US'],
        ]);
    });

    test('is_eligible_for_tournament_returns_true_when_all_conditions_met', function () {
        expect($this->user->isEligibleForTournament($this->tournament))->toBeTrue();
    });

    test('is_eligible_for_tournament_returns_false_when_rank_too_low', function () {
        $user = User::factory()->create([
            'country_code' => 'KR',
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 500,
            'country_rank' => 100,
            'pp' => 100.00,
            'recorded_at' => now(),
        ]);

        expect($user->isEligibleForTournament($this->tournament))->toBeFalse();
    });

    test('is_eligible_for_tournament_returns_false_when_rank_too_high', function () {
        $user = User::factory()->create([
            'country_code' => 'KR',
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 15000,
            'country_rank' => 5000,
            'pp' => 100.00,
            'recorded_at' => now(),
        ]);

        expect($user->isEligibleForTournament($this->tournament))->toBeFalse();
    });

    test('is_eligible_for_tournament_returns_false_when_wrong_country', function () {
        $user = User::factory()->create([
            'country_code' => 'GB', // Not in restricted_countries
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 5000,
            'country_rank' => 1000,
            'pp' => 1234.56,
            'recorded_at' => now(),
        ]);

        expect($user->isEligibleForTournament($this->tournament))->toBeFalse();
    });

    test('is_eligible_for_tournament_returns_true_when_no_country_restriction', function () {
        $tournament = Tournament::factory()->create([
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
            'restricted_countries' => null, // No restriction
        ]);

        $user = User::factory()->create([
            'country_code' => 'GB',
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 5000,
            'country_rank' => 1000,
            'pp' => 1234.56,
            'recorded_at' => now(),
        ]);

        expect($user->isEligibleForTournament($tournament))->toBeTrue();
    });

    test('is_eligible_for_tournament_uses_mania_specific_rank', function () {
        $tournament = Tournament::factory()->create([
            'modes' => [['mode' => 'mania', 'key_count' => 4]],
            'rank_range_min' => 5000,
            'rank_range_max' => 10000,
            'restricted_countries' => ['KR', 'JP', 'US'],
        ]);

        // User has good 4K rank but poor global mania rank
        $user = User::factory()->create([
            'rank_mania_4k' => 7000, // Good 4K rank (within range)
            'country_code' => 'KR',
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'mania',
            'rank' => 100000,
            'country_rank' => 50000,
            'pp' => 100.00,
            'recorded_at' => now(),
        ]);

        expect($user->isEligibleForTournament($tournament))->toBeTrue();
    });

    test('is_eligible_for_tournament_returns_false_for_wrong_mania_variant', function () {
        $tournament = Tournament::factory()->create([
            'modes' => [['mode' => 'mania', 'key_count' => 7]], // Needs 7K
            'rank_range_min' => 5000,
            'rank_range_max' => 10000,
            'restricted_countries' => ['KR', 'JP', 'US'],
        ]);

        // User has 4K rank but no 7K rank
        $user = User::factory()->create([
            'rank_mania_4k' => 7000,
            'rank_mania_7k' => null, // No 7K rank
            'country_code' => 'KR',
        ]);

        expect($user->isEligibleForTournament($tournament))->toBeFalse();
    });

    test('is_eligible_for_tournament_handles_multiple_modes', function () {
        $tournament = Tournament::factory()->create([
            'modes' => [
                ['mode' => 'osu', 'key_count' => null],
                ['mode' => 'mania', 'key_count' => 4],
            ],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
            'restricted_countries' => ['KR', 'JP', 'US'],
        ]);

        $user = User::factory()->create([
            'rank_mania_4k' => 7000,
            'country_code' => 'KR',
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 5000,
            'country_rank' => 1000,
            'pp' => 1234.56,
            'recorded_at' => now(),
        ]);

        // User is eligible for at least one mode
        expect($user->isEligibleForTournament($tournament))->toBeTrue();
    });

    test('is_eligible_for_tournament_handles_null_rank_requirements', function () {
        $tournament = Tournament::factory()->create([
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'rank_range_min' => null, // No minimum
            'rank_range_max' => 10000,
            'restricted_countries' => ['KR'],
        ]);

        $user = User::factory()->create([
            'country_code' => 'KR',
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 100,
            'country_rank' => 50,
            'pp' => 50.00,
            'recorded_at' => now(),
        ]);

        expect($user->isEligibleForTournament($tournament))->toBeTrue();
    });

    test('is_eligible_for_tournament_returns_false_when_no_ranking_for_mode', function () {
        $tournament = Tournament::factory()->create([
            'modes' => [['mode' => 'mania', 'key_count' => 7]],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
            'restricted_countries' => ['KR'],
        ]);

        $user = User::factory()->create([
            'rank_mania_7k' => null, // No 7K ranking
            'country_code' => 'KR',
        ]);

        expect($user->isEligibleForTournament($tournament))->toBeFalse();
    });

    test('is_eligible_for_tournament_allows_null_country_with_no_restriction', function () {
        $tournament = Tournament::factory()->create([
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'rank_range_min' => 1000,
            'rank_range_max' => 10000,
            'restricted_countries' => null, // No restriction
        ]);

        $user = User::factory()->create([
            'country_code' => null, // No country set
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 5000,
            'country_rank' => 1000,
            'pp' => 1234.56,
            'recorded_at' => now(),
        ]);

        expect($user->isEligibleForTournament($tournament))->toBeTrue();
    });

    test('is_eligible_for_tournament_handles_legacy_mode_format', function () {
        $tournament = Tournament::factory()->create([
            'modes' => ['osu', 'mania'], // Legacy string format
            'rank_range_min' => 1000,
            'rank_range_max' => 100000,
            'restricted_countries' => ['KR'],
        ]);

        $user = User::factory()->create([
            'country_code' => 'KR',
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'osu',
            'rank' => 5000,
            'country_rank' => 1000,
            'pp' => 1234.56,
            'recorded_at' => now(),
        ]);
        UserRankHistory::create([
            'user_id' => $user->id,
            'mode' => 'mania',
            'rank' => 15000,
            'country_rank' => 3000,
            'pp' => 500.00,
            'recorded_at' => now(),
        ]);

        expect($user->isEligibleForTournament($tournament))->toBeTrue();
    });
});

describe('User Model - Essential Tournament User Selection', function () {
    test('essential winners include unsynced podium users from ended non rejected badge tournaments even with rank history', function () {
        $tournament = Tournament::factory()->create([
            'status' => 'approved',
            'is_badge' => true,
            'badge_status' => 'approved',
            'tournament_end' => '2025-08-01',
        ]);

        $firstPlace = User::factory()->create();
        $secondPlace = User::factory()->create();
        $thirdPlace = User::factory()->create();
        $hasRank = User::factory()->create();
        $fourthPlace = User::factory()->create();
        $alreadySynced = User::factory()->create();

        UserRankHistory::create([
            'user_id' => $hasRank->id,
            'mode' => 'osu',
            'rank' => 1234,
            'recorded_at' => now(),
        ]);

        foreach ([
            [$firstPlace, 1, null, null],
            [$secondPlace, 2, null, null],
            [$thirdPlace, 3, null, null],
            [$hasRank, 1, null, null],
            [$fourthPlace, 4, null, null],
            [$alreadySynced, 1, 'Synced Cup Winner', 'https://osu.ppy.sh/community/forums/topics/123'],
        ] as [$user, $placement, $badgeDescription, $badgeUrl]) {
            TournamentWinner::factory()->create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'osu_id' => $user->osu_id,
                'username' => $user->username,
                'placement' => $placement,
                'badge_description' => $badgeDescription,
                'badge_url' => $badgeUrl,
            ]);
        }

        $users = User::getTournamentUsers('winners', 2025, 2025, true);

        expect($users->pluck('id')->sort()->values()->all())->toBe([
            $firstPlace->id,
            $secondPlace->id,
            $thirdPlace->id,
            $hasRank->id,
        ]);
    });

    test('essential winners include null approved and pending badge statuses only for ended tournaments', function () {
        $badgeStatuses = [null, 'approved', 'pending', 'rejected'];
        $usersByStatus = [];

        foreach ($badgeStatuses as $badgeStatus) {
            $user = User::factory()->create();
            $usersByStatus[$badgeStatus ?? 'null'] = $user;

            $tournament = Tournament::factory()->create([
                'is_badge' => true,
                'badge_status' => $badgeStatus,
                'tournament_end' => '2025-08-01',
            ]);

            TournamentWinner::factory()->create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'osu_id' => $user->osu_id,
                'username' => $user->username,
                'placement' => 1,
                'badge_description' => null,
                'badge_url' => null,
            ]);
        }

        $futureUser = User::factory()->create();
        $futureTournament = Tournament::factory()->create([
            'is_badge' => true,
            'badge_status' => 'approved',
            'tournament_end' => now()->addMonth(),
        ]);

        TournamentWinner::factory()->create([
            'tournament_id' => $futureTournament->id,
            'user_id' => $futureUser->id,
            'osu_id' => $futureUser->osu_id,
            'username' => $futureUser->username,
            'placement' => 1,
            'badge_description' => null,
            'badge_url' => null,
        ]);

        $users = User::getTournamentUsers('winners', 2025, 2026, true);

        expect($users->pluck('id')->sort()->values()->all())->toBe([
            $usersByStatus['null']->id,
            $usersByStatus['approved']->id,
            $usersByStatus['pending']->id,
        ]);
    });

    test('essential staff only includes staff users without country code', function () {
        $tournament = Tournament::factory()->create([
            'tournament_end' => '2025-08-01',
        ]);

        $missingCountry = User::factory()->create(['country_code' => null]);
        $hasCountry = User::factory()->create(['country_code' => 'KR']);

        TournamentStaff::factory()->approved()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $missingCountry->id,
        ]);
        TournamentStaff::factory()->approved()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $hasCountry->id,
        ]);

        $users = User::getTournamentUsers('staff', 2025, 2025, true);

        expect($users->pluck('id')->all())->toBe([$missingCountry->id]);
    });

    test('essential hosts only includes hosted tournament users without country code', function () {
        $missingCountry = User::factory()->create([
            'username' => 'MissingCountryHost',
            'country_code' => null,
        ]);
        $hasCountry = User::factory()->create([
            'username' => 'KnownCountryHost',
            'country_code' => 'KR',
        ]);

        Tournament::factory()->create([
            'host_username' => $missingCountry->username,
            'tournament_end' => '2025-08-01',
        ]);
        Tournament::factory()->create([
            'host_username' => $hasCountry->username,
            'tournament_end' => '2025-08-01',
        ]);

        $users = User::getTournamentUsers('hosts', 2025, 2025, true);

        expect($users->pluck('id')->all())->toBe([$missingCountry->id]);
    });
});
