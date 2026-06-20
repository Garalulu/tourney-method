<?php

use App\Services\BwsCalculator;

describe('BwsCalculator', function () {
    beforeEach(function () {
        $this->calculator = new BwsCalculator;
    });

    it('calculates BWS with default formula parameters', function () {
        // Standard formula: rank^(0.9937^(badges^2))
        // More badges = lower BWS rank (better seeding)
        $rank = 10000;
        $badgeCount = 5;

        $bws = $this->calculator->calculate($rank, $badgeCount);

        // Expected: 10000^(0.9937^(5^2)) = 10000^(0.9937^25) = 10000^0.8540 ≈ 2603
        expect($bws)->toBeFloat()
            ->and($bws)->toBeGreaterThan(2500)
            ->and($bws)->toBeLessThan(2700);
    });

    it('calculates BWS with custom base exponent', function () {
        $rank = 10000;
        $badgeCount = 5;
        $baseExponent = 0.95; // Custom base exponent

        $bws = $this->calculator->calculate($rank, $badgeCount, $baseExponent);

        // Expected: 10000^(0.95^(5^2)) = 10000^(0.95^25) = 10000^0.2774 ≈ 12.87
        expect($bws)->toBeFloat()
            ->and($bws)->toBeGreaterThan(10)
            ->and($bws)->toBeLessThan(16);
    });

    it('calculates BWS with custom badge power', function () {
        $rank = 10000;
        $badgeCount = 5;
        $baseExponent = 0.9937;
        $badgePower = 1.5; // Custom badge power (lower than default 2.0)

        $bws = $this->calculator->calculate($rank, $badgeCount, $baseExponent, $badgePower);

        // Expected: 10000^(0.9937^(5^1.5)) = 10000^(0.9937^11.18) = 10000^0.9320 ≈ 5335
        expect($bws)->toBeFloat()
            ->and($bws)->toBeGreaterThan(5200)
            ->and($bws)->toBeLessThan(5500);
    });

    it('calculates BWS with both custom parameters', function () {
        $rank = 10000;
        $badgeCount = 5;
        $baseExponent = 0.99;
        $badgePower = 1.5;

        $bws = $this->calculator->calculate($rank, $badgeCount, $baseExponent, $badgePower);

        // Expected: 10000^(0.99^(5^1.5)) = 10000^(0.99^11.18) = 10000^0.8946 ≈ 3757
        expect($bws)->toBeFloat()
            ->and($bws)->toBeGreaterThan(3600)
            ->and($bws)->toBeLessThan(4000);
    });

    it('returns raw rank when badge count is zero', function () {
        $rank = 10000;
        $badgeCount = 0;

        $bws = $this->calculator->calculate($rank, $badgeCount);

        // With 0 badges, BWS should equal raw rank
        // rank^0.9937 * 0^0.8129 = rank^0.9937 * 0 = 0
        // But logically, 0 badges means no BWS advantage, so BWS should be raw rank
        expect($bws)->toBe((float) $rank);
    });

    it('handles rank of 1 correctly', function () {
        $rank = 1;
        $badgeCount = 5;

        $bws = $this->calculator->calculate($rank, $badgeCount);

        // 1^(0.9937^(5^2)) = 1^(0.9937^25) = 1^0.8547 = 1
        // Any number to any power of 1 is 1
        expect($bws)->toBeFloat()
            ->and($bws)->toBe(1.0);
    });

    it('handles very high rank correctly', function () {
        $rank = 1000000;
        $badgeCount = 10;

        $bws = $this->calculator->calculate($rank, $badgeCount);

        // Should not throw errors with large numbers
        expect($bws)->toBeFloat()
            ->and($bws)->toBeGreaterThan(0)
            ->and($bws)->toBeLessThan($rank * 10); // Sanity check
    });

    it('handles single badge correctly', function () {
        $rank = 10000;
        $badgeCount = 1;

        $bws = $this->calculator->calculate($rank, $badgeCount);

        // 10000^(0.9937^(1^2)) = 10000^0.9937 ≈ 9388
        expect($bws)->toBeFloat()
            ->and($bws)->toBeGreaterThan(9300)
            ->and($bws)->toBeLessThan(9500);
    });

    it('handles many badges correctly', function () {
        $rank = 10000;
        $badgeCount = 50;

        $bws = $this->calculator->calculate($rank, $badgeCount);

        // 10000^(0.9937^(50^2)) = 10000^(0.9937^2500) ≈ 10000^0 ≈ 1
        // BWS should be significantly LOWER than raw rank (badges improve seeding)
        expect($bws)->toBeFloat()
            ->and($bws)->toBeLessThan($rank)
            ->and($bws)->toBeGreaterThan(0);
    });

    it('decreases BWS with more badges', function () {
        $rank = 10000;

        $bws1 = $this->calculator->calculate($rank, 1);
        $bws5 = $this->calculator->calculate($rank, 5);
        $bws10 = $this->calculator->calculate($rank, 10);

        // More badges should result in LOWER BWS (better seeding)
        // Badges indicate tournament experience, improving your seed
        expect($bws10)->toBeLessThan($bws5)
            ->and($bws5)->toBeLessThan($bws1)
            ->and($bws1)->toBeGreaterThan(0);
    });

    it('decreases BWS multiplier with better rank', function () {
        $badgeCount = 5;

        $bwsRank1000 = $this->calculator->calculate(1000, $badgeCount);
        $bwsRank10000 = $this->calculator->calculate(10000, $badgeCount);
        $bwsRank50000 = $this->calculator->calculate(50000, $badgeCount);

        // Better rank (lower number) should have lower BWS value
        expect($bwsRank1000)->toBeLessThan($bwsRank10000)
            ->and($bwsRank10000)->toBeLessThan($bwsRank50000);
    });

    it('returns positive values for all valid inputs', function () {
        $testCases = [
            [1, 0],
            [1, 1],
            [100, 5],
            [1000, 10],
            [10000, 50],
            [100000, 100],
        ];

        foreach ($testCases as [$rank, $badges]) {
            $bws = $this->calculator->calculate($rank, $badges);
            expect($bws)->toBeGreaterThan(0);
        }
    });

    it('handles fractional results correctly', function () {
        $rank = 5555;
        $badgeCount = 7;

        $bws = $this->calculator->calculate($rank, $badgeCount);

        // Should return float with decimal precision
        expect($bws)->toBeFloat()
            ->and($bws)->not->toBe((float) ((int) $bws)); // Has decimal places
    });

    it('is consistent for same inputs', function () {
        $rank = 10000;
        $badgeCount = 5;

        $bws1 = $this->calculator->calculate($rank, $badgeCount);
        $bws2 = $this->calculator->calculate($rank, $badgeCount);
        $bws3 = $this->calculator->calculate($rank, $badgeCount);

        // Should return identical results for same inputs
        expect($bws1)->toBe($bws2)
            ->and($bws2)->toBe($bws3);
    });

    it('handles edge case of very low badge count', function () {
        $rank = 10000;

        $bws0 = $this->calculator->calculate($rank, 0);
        $bws1 = $this->calculator->calculate($rank, 1);

        // 0 badges should return raw rank
        expect($bws0)->toBe((float) $rank);

        // 1 badge should be close to raw rank but slightly different
        expect($bws1)->toBeGreaterThan($rank * 0.9)
            ->and($bws1)->toBeLessThan($rank * 1.1);
    });

    it('uses default parameters matching osu! standard formula', function () {
        // Standard osu! BWS formula: rank^(0.9937^(badges^2))
        $rank = 15000;
        $badgeCount = 8;

        $bws = $this->calculator->calculate($rank, $badgeCount);

        // Manually calculate expected value: rank^(0.9937^(badges^2))
        $exponent = pow(0.9937, pow($badgeCount, 2.0));
        $expectedBws = pow($rank, $exponent);

        // Should match exactly (same calculation)
        expect($bws)->toBe($expectedBws);
    });

    it('allows custom formula to reduce BWS impact', function () {
        $rank = 10000;
        $badgeCount = 10;

        // Standard formula with default badge power 2.0
        $standardBws = $this->calculator->calculate($rank, $badgeCount);

        // Custom formula with lower badge power (less reduction from badges)
        $reducedImpactBws = $this->calculator->calculate($rank, $badgeCount, 0.9937, 1.0);

        // Lower badge power = less badge effect = BWS closer to raw rank (higher)
        expect($reducedImpactBws)->toBeGreaterThan($standardBws);
    });

    it('allows custom formula to increase BWS impact', function () {
        $rank = 10000;
        $badgeCount = 10;

        // Standard formula with default badge power 2.0
        $standardBws = $this->calculator->calculate($rank, $badgeCount);

        // Custom formula with higher badge power (more reduction from badges)
        $increasedImpactBws = $this->calculator->calculate($rank, $badgeCount, 0.9937, 3.0);

        // Higher badge power = more badge effect = lower BWS (better seeding)
        expect($increasedImpactBws)->toBeLessThan($standardBws);
    });

    it('validates base exponent affects result', function () {
        $rank = 10000;
        $badgeCount = 5;

        $bwsExponent095 = $this->calculator->calculate($rank, $badgeCount, 0.95);
        $bwsExponent099 = $this->calculator->calculate($rank, $badgeCount, 0.99);
        $bwsExponent100 = $this->calculator->calculate($rank, $badgeCount, 1.0);

        // Higher exponent should bring BWS closer to raw rank
        expect($bwsExponent100)->toBeGreaterThan($bwsExponent099)
            ->and($bwsExponent099)->toBeGreaterThan($bwsExponent095);
    });

    it('handles typical tournament scenarios', function () {
        // Common tournament scenarios
        // BWS formula: rank^(0.9937^(badges^2))
        // More badges = LOWER BWS rank (appearing more skilled)
        $scenarios = [
            ['rank' => 5000, 'badges' => 3, 'description' => '5k rank with 3 badges'],
            ['rank' => 10000, 'badges' => 5, 'description' => '10k rank with 5 badges'],
            ['rank' => 50000, 'badges' => 10, 'description' => '50k rank with 10 badges'],
            ['rank' => 100000, 'badges' => 20, 'description' => '100k rank with 20 badges'],
        ];

        foreach ($scenarios as $scenario) {
            $bws = $this->calculator->calculate($scenario['rank'], $scenario['badges']);

            // BWS should be positive and LESS than raw rank (badges improve your seeding)
            expect($bws)->toBeFloat()
                ->and($bws)->toBeGreaterThan(0)
                ->and($bws)->toBeLessThan($scenario['rank']);
        }
    });

    it('returns integer rank when no badges and exponent is 1', function () {
        $rank = 10000;
        $badgeCount = 0;
        $baseExponent = 1.0;
        $badgePower = 0.8129;

        $bws = $this->calculator->calculate($rank, $badgeCount, $baseExponent, $badgePower);

        // rank^1.0 * 0^0.8129 = rank * 0 = 0, but should return raw rank
        expect($bws)->toBe((float) $rank);
    });

    it('handles edge case inputs gracefully', function () {
        // Very small rank
        $bws1 = $this->calculator->calculate(1, 5);
        expect($bws1)->toBeFloat()->and($bws1)->toBeGreaterThanOrEqual(1);

        // Zero badges returns raw rank
        $bws2 = $this->calculator->calculate(10000, 0);
        expect($bws2)->toBe(10000.0);
    });
});
