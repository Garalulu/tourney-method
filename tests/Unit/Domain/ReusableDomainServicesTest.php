<?php

use App\Domain\Participation\MatchWinCalculator;
use App\Domain\Tournaments\Staff\StaffRoleOrder;
use App\Integrations\Otr\LegacyModDecoder;
use App\Models\MatchGame;
use App\Models\MatchScore;
use App\Models\User;

test('legacy mod decoder handles compound and ordinary flags', function () {
    $decoder = new LegacyModDecoder;

    expect($decoder->decode(8 | 16))->toBe(['HD', 'HR'])
        ->and($decoder->decode(64 | 512))->toBe(['NC'])
        ->and($decoder->decode(32 | 16384))->toBe(['PF']);
});

test('staff role order exposes one reusable priority and sql definition', function () {
    expect(StaffRoleOrder::priority('organizer'))->toBe(1)
        ->and(StaffRoleOrder::priority('unknown'))->toBe(999)
        ->and(StaffRoleOrder::sql())->toContain("WHEN tournament_staff.role = 'organizer' THEN 1");
});

test('match win calculator counts individual and team wins without counting ties', function () {
    $user = User::factory()->create();
    $opponent = User::factory()->create();
    $calculator = new MatchWinCalculator;

    $individualWin = MatchGame::factory()->create(['team_type' => 'head_to_head']);
    $userIndividualScore = MatchScore::factory()->create([
        'match_game_id' => $individualWin->id,
        'user_id' => $user->id,
        'team' => null,
        'score' => 200,
    ]);
    MatchScore::factory()->create([
        'match_game_id' => $individualWin->id,
        'user_id' => $opponent->id,
        'team' => null,
        'score' => 100,
    ]);

    $teamWin = MatchGame::factory()->create(['team_type' => 'team_vs']);
    $userTeamScore = MatchScore::factory()->create([
        'match_game_id' => $teamWin->id,
        'user_id' => $user->id,
        'team' => 'red',
        'score' => 120,
    ]);
    MatchScore::factory()->create([
        'match_game_id' => $teamWin->id,
        'user_id' => $opponent->id,
        'team' => 'red',
        'score' => 100,
    ]);
    MatchScore::factory()->create([
        'match_game_id' => $teamWin->id,
        'team' => 'blue',
        'score' => 200,
    ]);

    $tie = MatchGame::factory()->create(['team_type' => 'head_to_head']);
    $userTieScore = MatchScore::factory()->create([
        'match_game_id' => $tie->id,
        'user_id' => $user->id,
        'team' => null,
        'score' => 100,
    ]);
    MatchScore::factory()->create([
        'match_game_id' => $tie->id,
        'user_id' => $opponent->id,
        'team' => null,
        'score' => 100,
    ]);

    $scores = MatchScore::query()
        ->whereIn('id', [$userIndividualScore->id, $userTeamScore->id, $userTieScore->id])
        ->get();

    expect($calculator->count($scores))->toBe(2);
});
