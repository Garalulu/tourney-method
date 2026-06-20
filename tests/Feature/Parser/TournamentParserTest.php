<?php

use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Clear test tournaments
    Tournament::where('import_source', 'forum')->forceDelete();
    Queue::fake();
    // Clear cache to prevent cached API responses from interfering with tests
    Cache::flush();
});

test('command fetches 50 recent topics from forum_id=55', function () {
    // Mock the OsuApiService to return test data
    $mockService = Mockery::mock(OsuApiService::class);
    $mockService->shouldReceive('getForumTopics')
        ->once()
        ->with(55, 50, 1)
        ->andReturn([
            'topics' => [
                [
                    'id' => 12345,
                    'title' => 'Test Tournament',
                    'forum_id' => 55,
                    'user_id' => 98765,
                    'created_at' => now()->subDays(2)->toIso8601String(),
                ],
            ],
        ]);
    $this->app->instance(OsuApiService::class, $mockService);

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertExitCode(0);
});

test('new tournament creates pending_review record', function () {
    // Mock the forum topic response with post content
    $forumTopicId = 12345;
    $postContent = 'Test Tournament. Host: @test. Modes: osu!';

    $mockService = Mockery::mock(OsuApiService::class);
    $mockService->shouldReceive('getForumTopics')
        ->once()
        ->with(55, 50, 1)
        ->andReturn([
            'topics' => [
                [
                    'id' => $forumTopicId,
                    'title' => 'Test Tournament',
                    'forum_id' => 55,
                    'user_id' => 98765,
                    'created_at' => now()->subDays(2)->toIso8601String(),
                ],
            ],
        ]);
    // Topic details are fetched when the queued job runs.
    $mockService->shouldReceive('getForumTopic')
        ->once()
        ->with($forumTopicId)
        ->andReturn([
            'topic' => [
                'id' => $forumTopicId,
                'title' => 'Test Tournament',
                'forum_id' => 55,
                'user_id' => 98765,
                'created_at' => now()->subDays(2)->toIso8601String(),
            ],
            'posts' => [
                [
                    'id' => 1,
                    'body' => [
                        'html' => "<div>{$postContent}</div>",
                        'raw' => $postContent,
                    ],
                ],
            ],
        ]);
    $this->app->instance(OsuApiService::class, $mockService);

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertExitCode(0);

    // Process the queued job
    Queue::assertPushed(ParseForumTopicJob::class, 1);
    $job = Queue::pushed(ParseForumTopicJob::class)->first();
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Check that a tournament was created with pending_review status
    $this->assertDatabaseHas('tournaments', [
        'forum_topic_id' => $forumTopicId,
        'status' => 'pending_review',
        'import_source' => 'forum',
    ]);
});

test('existing tournament skips creation (deduplication)', function () {
    $forumTopicId = 12345;

    // Create existing tournament
    Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'status' => 'pending_review',
    ]);

    $mockService = Mockery::mock(OsuApiService::class);
    $mockService->shouldReceive('getForumTopics')
        ->once()
        ->with(55, 50, 1)
        ->andReturn([
            'topics' => [
                [
                    'id' => $forumTopicId,
                    'title' => 'Existing Tournament',
                    'forum_id' => 55,
                    'user_id' => 98765,
                    'created_at' => now()->subDays(2)->toIso8601String(),
                ],
            ],
        ]);
    $mockService->shouldReceive('getForumTopic')
        ->once()
        ->with($forumTopicId)
        ->andReturn([
            'topic' => [
                'id' => $forumTopicId,
                'title' => 'Existing Tournament',
                'forum_id' => 55,
                'user_id' => 98765,
            ],
            'posts' => [],
        ]);
    $this->app->instance(OsuApiService::class, $mockService);

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertExitCode(0);

    // Deduplication occurs inside the queued job.
    Queue::assertPushed(ParseForumTopicJob::class, 1);
    $job = Queue::pushed(ParseForumTopicJob::class)->first();
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify only one tournament exists
    $count = Tournament::where('forum_topic_id', $forumTopicId)->count();
    expect($count)->toBe(1);
});

test('rate limit handling throws exception', function () {
    $mockService = Mockery::mock(OsuApiService::class);
    $mockService->shouldReceive('getForumTopics')
        ->once()
        ->with(55, 50, 1)
        ->andThrow(new Exception('osu! API rate limit exceeded after multiple retries'));
    $this->app->instance(OsuApiService::class, $mockService);

    expect(fn () => Artisan::call('tournaments:parse', ['--confirm' => true, '--no-backup' => true]))
        ->toThrow(Exception::class, 'osu! API rate limit exceeded');
});

test('command logs progress and error counts', function () {
    $mockService = Mockery::mock(OsuApiService::class);
    $mockService->shouldReceive('getForumTopics')
        ->once()
        ->with(55, 50, 1)
        ->andReturn([
            'topics' => [
                [
                    'id' => 12345,
                    'title' => 'Valid Tournament',
                    'forum_id' => 55,
                    'user_id' => 98765,
                    'created_at' => now()->subDays(2)->toIso8601String(),
                ],
                [
                    'id' => 12346,
                    'title' => 'Invalid Tournament',
                    'forum_id' => 55,
                    'user_id' => 98766,
                    'created_at' => now()->subDays(2)->toIso8601String(),
                ],
            ],
        ]);
    $this->app->instance(OsuApiService::class, $mockService);

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('topics')
        ->expectsOutputToContain('Summary'); // Command outputs "Parsing Summary"
});

test('fetches most recent 50 topics', function () {
    $mockService = Mockery::mock(OsuApiService::class);
    $mockService->shouldReceive('getForumTopics')
        ->once()
        ->with(55, 50, 1)
        ->andReturn([
            'topics' => [
                [
                    'id' => 12345,
                    'title' => 'Recent Tournament',
                    'forum_id' => 55,
                    'user_id' => 98765,
                    'created_at' => now()->subDays(3)->toIso8601String(),
                ],
            ],
        ]);
    $this->app->instance(OsuApiService::class, $mockService);

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertExitCode(0);

    // The tournament should have been queued
    Queue::assertPushed(ParseForumTopicJob::class, 1);
});

test('error handling for individual topic parse failures', function () {
    $forumTopicId = 12345;

    $mockService = Mockery::mock(OsuApiService::class);
    $mockService->shouldReceive('getForumTopics')
        ->once()
        ->with(55, 50, 1)
        ->andReturn([
            'topics' => [
                [
                    'id' => $forumTopicId,
                    'title' => 'Problematic Tournament',
                    'forum_id' => 55,
                    'user_id' => 98765,
                    'created_at' => now()->subDays(2)->toIso8601String(),
                ],
            ],
        ]);
    // Return null to simulate API failure (called once when job runs)
    $mockService->shouldReceive('getForumTopic')
        ->once()
        ->with($forumTopicId)
        ->andReturn(null);
    $this->app->instance(OsuApiService::class, $mockService);

    $this->artisan('tournaments:parse', ['--confirm' => true, '--no-backup' => true])
        ->assertExitCode(0);

    // Process the queued job
    Queue::assertPushed(ParseForumTopicJob::class, 1);
    $job = Queue::pushed(ParseForumTopicJob::class)->first();
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Tournament should not be created due to API failure
    $this->assertDatabaseMissing('tournaments', [
        'forum_topic_id' => 12345,
    ]);
});

test('parseModes only detects mania when o!m in title with osu! in content', function () {
    $parser = app(ForumParser::class);

    $content = '[title]o!m Tournament[/title]
    In order to be eligible for this tournament:
    You must be within the osu!mania 4-key rank range of #20,000
    1st Place: 6 months of osu! Supporter!
    This map must be an osu! mania 4K map';

    $result = $parser->parseModes($content);
    expect($result['modes'])->toEqual([['mode' => 'mania', 'key_count' => 4]]);
});

test('parseModes detects both modes when both explicitly stated without abbreviations', function () {
    $parser = app(ForumParser::class);

    $content = 'This tournament supports both osu and mania modes';

    $result = $parser->parseModes($content);
    expect($result['modes'])->toEqual([
        ['mode' => 'osu', 'key_count' => null],
        ['mode' => 'mania', 'key_count' => null],
    ]);
});

test('parseModes handles taiko abbreviation correctly', function () {
    $parser = app(ForumParser::class);

    $content = '[title]o!t Tournament[/title]
    Prizes: osu! Supporter for winners
    Rules: All maps must be taiko maps';

    $result = $parser->parseModes($content);
    expect($result['modes'])->toEqual([['mode' => 'taiko', 'key_count' => null]]);
});

test('parseModes handles o!t with osu!taiko compound word', function () {
    $parser = app(ForumParser::class);

    $content = '[title]o!t Tournament[/title]
    Welcome to osu!taiko tournament
    Prizes: osu! Supporter';

    $result = $parser->parseModes($content);
    expect($result['modes'])->toEqual([['mode' => 'taiko', 'key_count' => null]]);
});
