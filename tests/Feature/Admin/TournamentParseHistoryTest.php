<?php

use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\User;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Cache::flush();
});

test('admin can view tournament parse history', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();
    TournamentParseHistory::factory()->count(3)->create([
        'tournament_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.parse-history', $tournament));

    $response->assertStatus(200);
    $response->assertViewIs('admin.tournaments.parse-history');
    $response->assertViewHas('tournament');
    $response->assertViewHas('histories');
});

test('admin cannot view parse history without admin role', function () {
    $user = User::factory()->create(['role' => 'player']);
    $tournament = Tournament::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('admin.tournaments.parse-history', $tournament));

    $response->assertStatus(403);
});

test('guest cannot view parse history', function () {
    $tournament = Tournament::factory()->create();

    $response = $this->get(route('admin.tournaments.parse-history', $tournament));

    // Unauthenticated guests get 401, not 302
    $response->assertStatus(401);
});

test('admin can reparse tournament with forum topic', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => 12345,
        'parse_count' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.tournaments.reparse', $tournament));

    // The controller returns a redirect response
    $response->assertStatus(302);

    // Verify job was dispatched
    Queue::assertPushed(ParseForumTopicJob::class, function ($job) use ($tournament) {
        return $job->topicId === $tournament->forum_topic_id;
    });

    // Verify audit log was created
    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'tournament.reparsed',
        'entity_id' => $tournament->id,
        'admin_id' => $admin->id,
    ]);
});

test('admin cannot reparse tournament without forum topic', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => null,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.tournaments.reparse', $tournament));

    // The controller returns a redirect with error message
    $response->assertStatus(302);
    $response->assertSessionHas('error');

    // Verify job was not dispatched
    Queue::assertNotPushed(ParseForumTopicJob::class);
});

test('admin can view individual parse history entry', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();
    $history = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'changes' => [
            'title' => ['old' => 'Old Title', 'new' => 'New Title'],
        ],
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.parse-history.show', [$tournament, $history]));

    $response->assertStatus(200);
    $response->assertViewIs('admin.tournaments.parse-history-show');
    $response->assertViewHas('tournament');
    $response->assertViewHas('history');
});

test('admin can view compacted parse history entry', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();
    $history = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'compacted_at' => now(),
        'parsed_data' => [
            'compacted' => true,
            'original_sha256' => str_repeat('a', 64),
            'original_bytes' => 1234,
            'top_level_keys' => ['title', 'description'],
        ],
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.parse-history.show', [$tournament, $history]));

    $response->assertStatus(200);
    $response->assertSee('Compacted Parsed Data Summary');
    $response->assertSee('Full raw parsed data was compacted');
});

test('admin cannot view parse history from different tournament', function () {
    $admin = User::factory()->admin()->create();
    $tournament1 = Tournament::factory()->create();
    $tournament2 = Tournament::factory()->create();
    $history = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament1->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.parse-history.show', [$tournament2, $history]));

    $response->assertStatus(404);
});

test('admin can delete parse history entry', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();
    $history = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.tournaments.parse-history.delete', [$tournament, $history]));

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'Parse history deleted successfully',
    ]);

    // Verify history was deleted
    $this->assertDatabaseMissing('tournament_parse_histories', [
        'id' => $history->id,
    ]);

    // Verify audit log was created
    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'tournament.parse_history_deleted',
        'entity_id' => $tournament->id,
        'admin_id' => $admin->id,
    ]);
});

test('admin cannot delete parse history from different tournament', function () {
    $admin = User::factory()->admin()->create();
    $tournament1 = Tournament::factory()->create();
    $tournament2 = Tournament::factory()->create();
    $history = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament1->id,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.tournaments.parse-history.delete', [$tournament2, $history]));

    $response->assertStatus(404);

    // Verify history was not deleted
    $this->assertDatabaseHas('tournament_parse_histories', [
        'id' => $history->id,
    ]);
});

test('parse history is ordered by created_at desc', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();

    $history1 = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'created_at' => now()->subDays(3),
    ]);

    $history2 = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'created_at' => now()->subDays(2),
    ]);

    $history3 = TournamentParseHistory::factory()->create([
        'tournament_id' => $tournament->id,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.parse-history', $tournament));

    $response->assertStatus(200);

    $histories = $response->viewData('histories');
    expect($histories->first()->id)->toBe($history3->id);
    expect($histories->last()->id)->toBe($history1->id);
});

test('parse history paginates results', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create();

    // Create 25 history entries
    TournamentParseHistory::factory()->count(25)->create([
        'tournament_id' => $tournament->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.tournaments.parse-history', $tournament));

    $response->assertStatus(200);

    $histories = $response->viewData('histories');
    expect($histories)->toHaveCount(20); // Default pagination
});

test('reparse increments tournament parse count', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => 12345,
    ]);

    // Mock the OsuApiService - MUST include oauth/token mock
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => 12345,
            'title' => 'Updated Title',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => 'Updated tournament. Modes: osu!',
                    ],
                ],
            ],
        ], 200),
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/users/*' => Http::response([
            'id' => 98765,
            'username' => 'testuser',
        ], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.reparse', $tournament));

    // Process the job synchronously for testing
    Queue::assertPushed(ParseForumTopicJob::class, function ($job) {
        // This test just verifies the job was dispatched
        // The actual parse count increment happens in the job handler
        return true;
    });

    // Manually process the job to verify the increment happens
    $job = new ParseForumTopicJob(12345, null, true, false, $tournament->id);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament->refresh();
    expect($tournament->parse_count)->toEqual(1); // Incremented from 0 to 1
    expect($tournament->last_parsed_at)->not->toBeNull();
});

test('reparse preserves tournament status', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => 12345,
        'status' => 'approved',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now()->subDay(),
    ]);

    // Mock the OsuApiService - MUST include oauth/token mock
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => 12345,
            'title' => 'Updated Title',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => 'Updated tournament. Modes: osu!',
                    ],
                ],
            ],
        ], 200),
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/users/*' => Http::response([
            'id' => 98765,
            'username' => 'testuser',
        ], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tournaments.reparse', $tournament));

    $job = new ParseForumTopicJob(12345, null, true, false, $tournament->id);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament->refresh();
    expect($tournament->status)->toBe('approved');
    expect($tournament->reviewed_by)->toBe($admin->id);
});
