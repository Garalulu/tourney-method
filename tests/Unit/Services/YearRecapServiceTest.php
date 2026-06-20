<?php

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\YearRecapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('calculateYearStats uses participation record matches', function () {
    $user = User::factory()->create(['username' => 'yearuser']);
    $teammate = User::factory()->create(['username' => 'teammate']);
    $tournament = Tournament::factory()->approved()->create([
        'tournament_end' => now()->setDate(2025, 3, 15),
    ]);

    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);
    $record->teammates()->attach($teammate);
    $record->syncParticipationMatches([
        ['stage' => 'SF', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '123456'],
        ['stage' => 'F', 'score_for' => 4, 'score_against' => 6, 'mp_link' => '234567'],
    ]);

    UserBadge::factory()->create([
        'user_id' => $user->id,
        'name' => '2025 Badge',
        'awarded_at' => now()->setDate(2025, 5, 1),
    ]);

    $stats = app(YearRecapService::class)->calculateYearStats($user, 2025);

    expect($stats['year'])->toBe(2025)
        ->and($stats['total_matches'])->toBe(2)
        ->and($stats['total_games'])->toBe(2)
        ->and($stats['games_won'])->toBe(1)
        ->and($stats['win_rate'])->toBe(50.0)
        ->and($stats['badges_earned'])->toBe(1)
        ->and($stats['frequent_opponents'])->toBe([]);
});

test('recap image can be generated from participation data', function () {
    $user = User::factory()->create(['username' => 'imggenuser']);
    $tournament = Tournament::factory()->approved()->create([
        'tournament_end' => now()->setDate(2025, 1, 15),
    ]);

    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
        'source' => TournamentParticipationRecord::SOURCE_MANUAL,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);
    $record->syncParticipationMatches([
        ['stage' => 'F', 'score_for' => 6, 'score_against' => 4, 'mp_link' => '987654'],
    ]);

    $service = app(YearRecapService::class);

    expect($service->hasDataForYear($user, 2025))->toBeTrue();

    $result = $service->getOrCreateRecap($user, 2025);

    expect($result)->toHaveKeys(['image_url', 'generated_at', 'stats']);
});
