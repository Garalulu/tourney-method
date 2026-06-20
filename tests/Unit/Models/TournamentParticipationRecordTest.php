<?php

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('participation record labels selection outcomes for future history cases', function (string $outcome, string $label) {
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => User::factory()->create()->id,
        'tournament_id' => Tournament::factory()->create()->id,
        'selection_outcome' => $outcome,
    ]);

    expect($record->selection_outcome_label)->toBe($label);
})->with([
    'registered' => [TournamentParticipationRecord::SELECTION_REGISTERED, 'Registered'],
    'seed cut' => [TournamentParticipationRecord::SELECTION_SEED_CUT, 'Cut by seed'],
    'not picked' => [TournamentParticipationRecord::SELECTION_NOT_PICKED, 'Not picked'],
    'world cup tryout failed' => [TournamentParticipationRecord::SELECTION_TRYOUT_FAILED, 'Tryout failed'],
    'suiji random miss' => [TournamentParticipationRecord::SELECTION_RANDOM_POOL_MISSED, 'Random pool missed'],
    'auction unsold' => [TournamentParticipationRecord::SELECTION_AUCTION_UNSOLD, 'Auction unsold'],
    'disqualified' => [TournamentParticipationRecord::SELECTION_DISQUALIFIED, 'Disqualified'],
]);

test('participation record summarizes battle royale elimination', function () {
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => User::factory()->create()->id,
        'tournament_id' => Tournament::factory()->create()->id,
        'selection_outcome' => TournamentParticipationRecord::SELECTION_REGISTERED,
        'final_result' => TournamentParticipationRecord::RESULT_ELIMINATED,
        'stage_type' => Tournament::STAGE_BATTLE_ROYALE,
        'stage_name' => 'Battle Royale Stage 2',
        'round_label' => 'Map 5',
    ]);

    expect($record->result_summary)->toBe('Eliminated - Battle Royale Stage 2 - Map 5');
});

test('participation record summarizes hybrid bracket losers path elimination', function () {
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => User::factory()->create()->id,
        'tournament_id' => Tournament::factory()->create()->id,
        'selection_outcome' => TournamentParticipationRecord::SELECTION_REGISTERED,
        'final_result' => TournamentParticipationRecord::RESULT_ELIMINATED,
        'stage_type' => Tournament::STAGE_BRACKET,
        'stage_name' => 'Hybrid Bracket',
        'round_label' => 'Final LB 2',
        'bracket_path' => TournamentParticipationRecord::BRACKET_LOSERS,
    ]);

    expect($record->result_summary)->toBe('Eliminated - Hybrid Bracket - Final LB 2 - Losers');
});
