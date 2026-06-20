<?php

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use App\Models\YearRecapCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('recap generation uses participation record data', function () {
    $user = User::factory()->withSetup()->create(['username' => 'recapuser']);
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
        ['stage' => 'F', 'score_for' => 5, 'score_against' => 3, 'mp_link' => '123456'],
    ]);

    $response = $this->actingAs($user)
        ->post(route('recap.generate', ['year' => 2025]));

    $response
        ->assertOk()
        ->assertJsonStructure(['image_url', 'generated_at', 'stats']);

    expect($response->json('stats.total_matches'))->toBe(1);
});

test('recap download returns generated image', function () {
    $user = User::factory()->withSetup()->create(['username' => 'downloaduser']);
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

    $this->actingAs($user)->post(route('recap.generate', ['year' => 2025]))->assertOk();

    $cache = YearRecapCache::query()
        ->where('user_id', $user->id)
        ->where('year', 2025)
        ->first();

    expect($cache)->not->toBeNull();
    expect(Storage::exists($cache->image_path))->toBeTrue();

    $this->actingAs($user)
        ->get(route('recap.download', ['year' => 2025]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});
