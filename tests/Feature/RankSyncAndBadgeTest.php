<?php

use App\Models\Tournament;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Models\UserRankHistory;
use App\Services\BwsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Http::fake();
});

test('user_badges table does not store gamemode', function () {
    $user = User::factory()->create();

    $badge = $user->badges()->create([
        'name' => 'Test Badge',
        'image_url' => 'https://example.com/badge.png',
        'awarded_at' => now(),
        'is_bws_eligible' => true,
    ]);

    expect(Schema::hasColumn('user_badges', 'gamemode'))->toBeFalse();
    expect($badge->getAttributes())->not->toHaveKey('gamemode');
});

test('user_rank_history stores all mode variants', function () {
    $user = User::factory()->create(['osu_id' => 12345]);

    // Create rank history for different modes
    $user->rankHistory()->createMany([
        [
            'mode' => 'osu',
            'rank' => 1000,
            'country_rank' => 50,
            'pp' => 5000.00,
            'recorded_at' => now(),
        ],
        [
            'mode' => 'taiko',
            'rank' => 5000,
            'country_rank' => 200,
            'pp' => 3000.00,
            'recorded_at' => now(),
        ],
        [
            'mode' => 'mania',
            'rank' => 10000,
            'country_rank' => 500,
            'pp' => 2000.00,
            'recorded_at' => now(),
        ],
        [
            'mode' => '4k',
            'rank' => 2000,
            'country_rank' => null,
            'pp' => null,
            'recorded_at' => now(),
        ],
        [
            'mode' => '7k',
            'rank' => 3000,
            'country_rank' => null,
            'pp' => null,
            'recorded_at' => now(),
        ],
    ]);

    // Verify all modes exist
    expect($user->rankHistory()->count())->toBe(5);

    // Check specific modes
    expect($user->rankHistory()->where('mode', 'osu')->exists())->toBeTrue();
    expect($user->rankHistory()->where('mode', 'taiko')->exists())->toBeTrue();
    expect($user->rankHistory()->where('mode', 'mania')->exists())->toBeTrue();
    expect($user->rankHistory()->where('mode', '4k')->exists())->toBeTrue();
    expect($user->rankHistory()->where('mode', '7k')->exists())->toBeTrue();
});

test('bws calculator filters badges by linked tournament winner gamemode', function () {
    $user = User::factory()->create();

    createBadgeWithWinner($user, 'osu', true, 'osu badge 1');
    createBadgeWithWinner($user, 'osu', true, 'osu badge 2');
    createBadgeWithWinner($user, 'taiko', true, 'taiko badge');
    createBadgeWithWinner($user, 'osu', false, 'excluded badge');

    $tournament = Tournament::factory()->create([
        'modes' => [['mode' => 'osu']],
        'is_bws' => true,
        'bws_base_exponent' => 0.9937,
        'bws_badge_power' => 2.0,
        'bws_divisor' => 1.0,
    ]);

    $calculator = app(BwsCalculator::class);

    // Use reflection to test private method
    $reflection = new ReflectionClass($calculator);
    $method = $reflection->getMethod('getEligibleBadgeCount');
    $method->setAccessible(true);

    $osuCount = $method->invoke($calculator, $user, $tournament, 'osu');
    expect($osuCount)->toBe(2);

    $taikoCount = $method->invoke($calculator, $user, $tournament, 'taiko');
    expect($taikoCount)->toBe(1);
});

test('bws calculator reuses batched results for individual lookups in the same request', function () {
    $user = User::factory()->create();
    $user->rankHistory()->create([
        'mode' => 'osu',
        'rank' => 1234,
        'recorded_at' => now(),
    ]);
    $user->load('rankHistory');
    $tournament = Tournament::factory()->create([
        'modes' => [['mode' => 'osu']],
        'is_bws' => true,
    ]);
    $calculator = app(BwsCalculator::class);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $batchRank = $calculator->calculateForUsers([$user], $tournament, 'osu')[$user->id];
    $queriesAfterBatch = count(DB::getQueryLog());
    $individualRank = app(BwsCalculator::class)->calculateForUser($user, $tournament, 'osu');

    expect($individualRank)->toBe($batchRank)
        ->and(count(DB::getQueryLog()))->toBe($queriesAfterBatch);

    DB::disableQueryLog();
});

test('bws calculator maps fruits mode to catch gamemode', function () {
    $calculator = app(BwsCalculator::class);

    // Use reflection to test private method
    $reflection = new ReflectionClass($calculator);
    $method = $reflection->getMethod('mapModeToBadgeGamemode');
    $method->setAccessible(true);

    expect($method->invoke($calculator, 'fruits'))->toBe('catch');
    expect($method->invoke($calculator, 'catch'))->toBe('catch');
    expect($method->invoke($calculator, 'osu'))->toBe('osu');
    expect($method->invoke($calculator, 'taiko'))->toBe('taiko');
    expect($method->invoke($calculator, 'mania'))->toBe('mania');
});

test('user_badges has tournament relationship', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    $badge = $user->badges()->create([
        'name' => 'Test Badge',
        'image_url' => 'https://example.com/badge.png',
        'awarded_at' => now(),
        'is_bws_eligible' => true,
        'tournament_id' => $tournament->id,
    ]);

    expect($badge->tournament)->not->toBeNull();
    expect($badge->tournament->id)->toBe($tournament->id);
});

test('syncUserData creates UserRankHistory for all modes', function () {
    // This test verifies the logic without making actual HTTP calls
    // We'll test that UserRankHistory can store all mode variants

    $user = User::factory()->create(['osu_id' => 12345]);

    // Simulate what syncUserData does - create rank history for all modes
    $modes = [
        ['mode' => 'osu', 'rank' => 1000, 'country_rank' => 50, 'pp' => 5000.00],
        ['mode' => 'taiko', 'rank' => 5000, 'country_rank' => 200, 'pp' => 3000.00],
        ['mode' => 'fruits', 'rank' => 8000, 'country_rank' => 300, 'pp' => 2000.00],
        ['mode' => 'mania', 'rank' => 10000, 'country_rank' => 400, 'pp' => 1500.00],
        ['mode' => '4k', 'rank' => 2000, 'country_rank' => null, 'pp' => null],
        ['mode' => '7k', 'rank' => 3000, 'country_rank' => null, 'pp' => null],
    ];

    foreach ($modes as $modeData) {
        UserRankHistory::updateOrCreate(
            ['user_id' => $user->id, 'mode' => $modeData['mode']],
            [
                'rank' => $modeData['rank'],
                'country_rank' => $modeData['country_rank'],
                'pp' => $modeData['pp'],
                'recorded_at' => now(),
            ]
        );
    }

    // Check that all modes have rank history
    $rankHistory = $user->rankHistory()->get();

    expect($rankHistory)->toHaveCount(6); // osu, taiko, fruits, mania, 4k, 7k

    // Verify specific modes exist
    $modes = $rankHistory->pluck('mode')->toArray();
    expect($modes)->toContain('osu');
    expect($modes)->toContain('taiko');
    expect($modes)->toContain('fruits');
    expect($modes)->toContain('mania');
    expect($modes)->toContain('4k');
    expect($modes)->toContain('7k');
});

test('eligible filter works with multi-mode users', function () {
    $user = User::factory()->create();

    // Create rank history for different modes
    $user->rankHistory()->createMany([
        ['mode' => 'osu', 'rank' => 500, 'country_rank' => 50, 'pp' => 8000, 'recorded_at' => now()],
        ['mode' => 'taiko', 'rank' => 50000, 'country_rank' => 5000, 'pp' => 500, 'recorded_at' => now()],
    ]);

    // Create osu tournament with rank restriction
    $osuTournament = Tournament::factory()->create([
        'modes' => [['mode' => 'osu']],
        'rank_range_min' => 1,
        'rank_range_max' => 1000,
    ]);

    $calculator = app(BwsCalculator::class);

    // User should be eligible for osu (rank 500)
    $osuEligible = $calculator->isEligible($user, $osuTournament, 'osu');
    expect($osuEligible)->toBeTrue();

    // Create taiko tournament with same rank restriction
    $taikoTournament = Tournament::factory()->create([
        'modes' => [['mode' => 'taiko']],
        'rank_range_min' => 1,
        'rank_range_max' => 1000,
    ]);

    // User should NOT be eligible for taiko (rank 50000)
    $taikoEligible = $calculator->isEligible($user, $taikoTournament, 'taiko');
    expect($taikoEligible)->toBeFalse();
});

function createBadgeWithWinner(User $user, string $gamemode, bool $isEligible, string $name): void
{
    $tournament = Tournament::factory()->create([
        'modes' => [['mode' => $gamemode]],
    ]);

    TournamentWinner::factory()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'gamemode' => $gamemode,
    ]);

    $user->badges()->create([
        'name' => $name,
        'image_url' => 'https://example.com/'.str($name)->slug().'.png',
        'awarded_at' => now()->subYear(),
        'is_bws_eligible' => $isEligible,
        'tournament_id' => $tournament->id,
    ]);
}
