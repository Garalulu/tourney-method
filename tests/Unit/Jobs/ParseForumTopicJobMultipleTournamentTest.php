<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParseForumTopicJobMultipleTournamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_job_updates_multiple_tournaments_with_same_topic_id(): void
    {
        $forumTopicId = 1234567;

        // Create two tournaments with the same forum_topic_id
        $tournament1 = Tournament::factory()->create([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Bracket 1: Humans',
            'status' => 'pending_review',
        ]);

        $tournament2 = Tournament::factory()->create([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Bracket 2: Yokais',
            'status' => 'pending_review',
        ]);

        $newTitle = 'Gensokyo Cup 2';
        $postContent = 'Welcome to Gensokyo Cup 2!';

        // Fake osu! API responses
        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'token_type' => 'Bearer',
                'expires_in' => 86400,
            ], 200),
            'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
                'id' => $forumTopicId,
                'title' => $newTitle,
                'user_id' => 98765,
                'posts' => [
                    [
                        'body' => [
                            'raw' => $postContent,
                        ],
                    ],
                ],
            ], 200),
            'osu.ppy.sh/api/v2/users/*' => Http::response([
                'id' => 98765,
                'username' => 'testuser',
            ], 200),
            // Mock banner validation (HEAD request)
            '*' => Http::response('', 200),
        ]);

        // Run the job
        $job = new ParseForumTopicJob($forumTopicId);
        $job->handle(app(OsuApiService::class), app(ForumParser::class));

        // Verify BOTH tournaments were updated
        $tournament1->refresh();
        $tournament2->refresh();

        $this->assertEquals($newTitle, $tournament1->title);
        $this->assertEquals($newTitle, $tournament2->title);

        // Also verify they are still separate records
        $this->assertNotEquals($tournament1->id, $tournament2->id);
    }

    public function test_job_updates_only_target_tournament_when_target_id_is_provided(): void
    {
        $forumTopicId = 1234567;

        $tournament1 = Tournament::factory()->create([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Bracket 1 Original',
            'status' => 'pending_review',
        ]);

        $tournament2 = Tournament::factory()->create([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Bracket 2 Original',
            'status' => 'pending_review',
        ]);

        $postContent = 'Welcome to the updated bracket!';

        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'token_type' => 'Bearer',
                'expires_in' => 86400,
            ], 200),
            'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
                'id' => $forumTopicId,
                'title' => 'Targeted Reparse Title',
                'user_id' => 98765,
                'posts' => [
                    [
                        'body' => [
                            'raw' => $postContent,
                        ],
                    ],
                ],
            ], 200),
            '*' => Http::response('', 200),
        ]);

        $job = new ParseForumTopicJob($forumTopicId, null, true, false, $tournament2->id);
        $job->handle(app(OsuApiService::class), app(ForumParser::class));

        expect($tournament1->fresh()->title)->toBe('Bracket 1 Original');
        expect($tournament2->fresh()->title)->toBe('Targeted Reparse Title');
    }

    public function test_targeted_job_does_not_create_tournament_when_target_is_missing(): void
    {
        $forumTopicId = 1234567;

        Tournament::factory()->create([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Existing Tournament',
            'status' => 'pending_review',
        ]);

        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'token_type' => 'Bearer',
                'expires_in' => 86400,
            ], 200),
            'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
                'id' => $forumTopicId,
                'title' => 'Should Not Create',
                'user_id' => 98765,
                'posts' => [
                    [
                        'body' => [
                            'raw' => 'Welcome to the tournament!',
                        ],
                    ],
                ],
            ], 200),
            '*' => Http::response('', 200),
        ]);

        $job = new ParseForumTopicJob($forumTopicId, null, true, false, 999999);
        $job->handle(app(OsuApiService::class), app(ForumParser::class));

        expect(Tournament::where('forum_topic_id', $forumTopicId)->count())->toBe(1);
        expect(Tournament::where('title', 'Should Not Create')->exists())->toBeFalse();
    }

    public function test_job_respects_modes_protection_for_multiple_tournaments(): void
    {
        $forumTopicId = 1234567;

        // Create two tournaments with same topic but different modes, marked as 'otr' source
        $tournament1 = Tournament::factory()->create([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Osu Bracket',
            'modes' => [['mode' => 'osu', 'key_count' => null]],
            'status' => 'pending_review',
            'field_sources' => ['modes' => 'otr'],
        ]);

        $tournament2 = Tournament::factory()->create([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Mania Bracket',
            'modes' => [['mode' => 'mania', 'key_count' => 4]],
            'status' => 'pending_review',
            'field_sources' => ['modes' => 'otr'],
        ]);

        // Forum topic mentions both modes
        $postContent = 'This tournament has osu! and mania brackets!';

        Http::fake([
            'osu.ppy.sh/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'token_type' => 'Bearer',
                'expires_in' => 86400,
            ], 200),
            'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
                'id' => $forumTopicId,
                'title' => 'Multi-Mode Tourney',
                'user_id' => 98765,
                'posts' => [
                    [
                        'body' => [
                            'raw' => $postContent,
                        ],
                    ],
                ],
            ], 200),
            '*' => Http::response('', 200),
        ]);

        // Run the job
        $job = new ParseForumTopicJob($forumTopicId);
        $job->handle(app(OsuApiService::class), app(ForumParser::class));

        $tournament1->refresh();
        $tournament2->refresh();

        // Titles should be updated
        $this->assertEquals('Multi-Mode Tourney', $tournament1->title);
        $this->assertEquals('Multi-Mode Tourney', $tournament2->title);

        // Modes should be PRESERVED due to 'otr' protection
        $this->assertEquals([['mode' => 'osu', 'key_count' => null]], $tournament1->modes);
        $this->assertEquals([['mode' => 'mania', 'key_count' => 4]], $tournament2->modes);
    }
}
