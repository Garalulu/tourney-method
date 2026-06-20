<?php

use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\User;
use App\Services\BatchTransactionService;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery as MockeryAlias;
use Mockery\MockInterface;
use ReflectionClass as PHPReflectionClass;

beforeEach(function () {
    // Clear test tournaments
    Tournament::where('import_source', 'forum')->forceDelete();
    Queue::fake();
    Cache::flush();

    // Fake OAuth token for all tests
    Http::fake([
        'osu.ppy.sh/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
    ]);
});

// Helper function to call protected method for testing
$callProtectedMethod = function ($object, string $method, array $args = []) {
    $reflection = new PHPReflectionClass($object);
    $method = $reflection->getMethod($method);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $args);
};

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function parsedForumTopicJobData(array $overrides = []): array
{
    return array_merge([
        'forum_topic_id' => 12345,
        'title' => 'Test Tournament',
        'description' => null,
        'host_osu_id' => null,
        'host_username' => null,
        'modes' => [],
        'registration_start' => null,
        'registration_end' => null,
        'tournament_start' => null,
        'tournament_end' => null,
        'rank_range_min' => null,
        'rank_range_max' => null,
        'is_badge' => false,
        'discord_url' => null,
        'twitch_url' => null,
        'spreadsheet_url' => null,
        'bracket_url' => null,
        'registration_url' => null,
        'tcomm_url' => null,
    ], $overrides);
}

test('job creates new tournament from forum topic', function () {
    $forumTopicId = 12345;
    $postContent = 'Test Tournament. Modes: osu!';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Test Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'created_at' => now()->subDays(2)->toIso8601String(),
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $this->assertDatabaseHas('tournaments', [
        'forum_topic_id' => $forumTopicId,
        'status' => 'pending_review',
        'import_source' => 'forum',
        'title' => 'Test Tournament',
    ]);
});

test('job updates existing tournament on re-parse', function () {
    $forumTopicId = 12345;

    // Create existing tournament
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Old Title',
        'modes' => ['osu'],
        'status' => Tournament::STATUS_PENDING,
        'parse_count' => 1,
    ]);

    $postContent = 'Updated Tournament. Modes: osu!, taiko!';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Updated Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'created_at' => now()->subDays(2)->toIso8601String(),
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify tournament was updated
    $tournament->refresh();
    expect($tournament->title)->toBe('Updated Tournament');
    expect($tournament->modes)->toBe([
        ['mode' => 'osu', 'key_count' => null],
        ['mode' => 'taiko', 'key_count' => null],
    ]);

    // Verify status was preserved
    expect($tournament->status)->toBe(Tournament::STATUS_PENDING);

    // Verify parse count incremented
    expect($tournament->parse_count)->toBe(2);

    // Verify parse history was created
    $this->assertDatabaseHas('tournament_parse_histories', [
        'tournament_id' => $tournament->id,
    ]);
});

test('job creates parse history with changes', function () {
    $forumTopicId = 12345;

    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Old Title',
        'is_badge' => false,
        'status' => Tournament::STATUS_PENDING,
    ]);

    $postContent = 'Updated Tournament with badge. Modes: osu!';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Updated Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $history = TournamentParseHistory::where('tournament_id', $tournament->id)->first();

    expect($history)->not->toBeNull();
    expect($history->changes)->toHaveKey('title');
    expect($history->changes['title']['old'])->toBe('Old Title');
    expect($history->changes['title']['new'])->toBe('Updated Tournament');
});

test('job merges staff from parsed data', function () {
    $forumTopicId = 12345;

    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Test Tournament',
        'status' => Tournament::STATUS_PENDING,
        'host_osu_id' => null,
        'host_username' => null,
    ]);

    // Create existing staff user
    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    // Attach user1 as existing staff
    $tournament->staff()->attach($user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    $postContent = 'Test Tournament. **Admin:** ![flag](https://a.ppy.sh/456) [User2](https://osu.ppy.sh/users/456)';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Test Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    // Mock ForumParser to return parsed staff
    $mockForumParser = $this->mock(ForumParser::class);
    $mockForumParser->shouldReceive('parseForumTopicData')
        ->with($forumTopicId, MockeryAlias::type('array'), $postContent, true)
        ->once()
        ->andReturn(parsedForumTopicJobData([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Test Tournament',
        ]));

    $mockForumParser->shouldReceive('parseBanner')
        ->with($postContent)
        ->once()
        ->andReturn(null);

    $mockForumParser->shouldReceive('parseStaffFromBBcode')
        ->with($postContent)
        ->once()
        ->andReturn([
            ['osu_id' => 456, 'username' => 'User2', 'role' => 'organizer'],
        ]);

    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $job = new ParseForumTopicJob($forumTopicId, $transactionId);
    $job->handle(app(OsuApiService::class), $mockForumParser);

    // Multi-role model: ParseForumTopicJob stores staff in cache, BatchMergeStaffJob merges
    // Since user1 is not in parsed data, their organizer role is removed
    // user2's admin role is added
    // Note: This test now only verifies caching behavior, not actual merge
    // The actual merge happens in BatchMergeStaffJob as part of 3-stage pipeline

    // Verify staff was cached for batch processing
    $cachedStaff = app(BatchTransactionService::class)->getStaffPayloads($transactionId)[$tournament->id] ?? null;
    expect($cachedStaff)->not->toBeNull();
    expect($cachedStaff)->toHaveCount(1);
    expect($cachedStaff[0]['osu_id'])->toBe(456);
    expect($cachedStaff[0]['role'])->toBe('organizer'); // Admin maps to organizer
});

test('job updates existing staff roles', function () {
    $forumTopicId = 12345;

    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Test Tournament',
        'status' => Tournament::STATUS_PENDING,
        'host_osu_id' => null,
        'host_username' => null,
    ]);

    $user = User::factory()->create(['osu_id' => 123]);

    // Attach user as organizer
    $tournament->staff()->attach($user->id, [
        'role' => 'organizer',
        'status' => 'approved',
    ]);

    // Clear any existing cache
    Cache::forget("staff_batch:{$tournament->id}");

    // Parse with updated role
    $postContent = 'Test Tournament. **Mapper:** ![flag](https://a.ppy.sh/123) [TestUser](https://osu.ppy.sh/users/123)';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Test Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    // Mock ForumParser to return parsed staff
    $mockForumParser = $this->mock(ForumParser::class);
    $mockForumParser->shouldReceive('parseForumTopicData')
        ->with($forumTopicId, MockeryAlias::type('array'), $postContent, true)
        ->once()
        ->andReturn(parsedForumTopicJobData([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Test Tournament',
        ]));

    $mockForumParser->shouldReceive('parseBanner')
        ->with($postContent)
        ->once()
        ->andReturn(null);

    $mockForumParser->shouldReceive('parseStaffFromBBcode')
        ->with($postContent)
        ->once()
        ->andReturn([
            ['osu_id' => 123, 'username' => 'TestUser', 'role' => 'mapper'],
        ]);

    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $job = new ParseForumTopicJob($forumTopicId, $transactionId);
    $job->handle(app(OsuApiService::class), $mockForumParser);

    // Verify staff was cached. Topic author auto-add only happens on initial create.
    $cachedStaff = app(BatchTransactionService::class)->getStaffPayloads($transactionId)[$tournament->id] ?? null;
    expect($cachedStaff)->not->toBeNull();
    expect($cachedStaff)->toHaveCount(1);

    expect($cachedStaff[0]['osu_id'])->toBe(123);
    expect($cachedStaff[0]['role'])->toBe('mapper');
});

test('job does not remove manually added staff', function () {
    $forumTopicId = 12345;

    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Test Tournament',
    ]);

    $user1 = User::factory()->create(['osu_id' => 123]);
    $user2 = User::factory()->create(['osu_id' => 456]);

    // Manually add both staff members
    $tournament->staff()->attach($user1->id, [
        'role' => 'organizer',
        'status' => 'approved',
    ]);
    $tournament->staff()->attach($user2->id, [
        'role' => 'referee',
        'status' => 'approved',
    ]);

    // Parse with only user1
    $postContent = 'Test Tournament. **Admin:** ![flag](https://a.ppy.sh/123) [User1](https://osu.ppy.sh/users/123)';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Test Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify both staff members still exist
    expect($tournament->fresh()->staff)->toHaveCount(2);

    // Verify user2 still has original role
    $user2Pivot = $tournament->fresh()->staff()->where('user_id', $user2->id)->first()->pivot;
    expect($user2Pivot->role)->toBe('referee');
});

test('job handles missing forum topic gracefully', function () {
    $forumTopicId = 99999;

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response(null, 404),
    ]);

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Should not create tournament
    $this->assertDatabaseMissing('tournaments', [
        'forum_topic_id' => $forumTopicId,
    ]);
});

test('job preserves status and review fields on re-parse', function () {
    $forumTopicId = 12345;
    $admin = User::factory()->create();

    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Old Title',
        'status' => 'approved',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now()->subDay(),
        'rejection_reason' => 'Test rejection',
    ]);

    $postContent = 'Updated Tournament. Modes: osu!';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Updated Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament->refresh();

    expect($tournament->status)->toBe('approved');
    expect($tournament->reviewed_by)->toBe($admin->id);
    expect($tournament->reviewed_at)->not->toBeNull();
    expect($tournament->rejection_reason)->toBe('Test rejection');
});

test('topic author added as organizer when no staff exists', function () use ($callProtectedMethod) {
    $parsedStaff = [];
    $topicData = [
        'user_id' => 12345,
        'title' => 'Test Tournament',
    ];

    $job = new ParseForumTopicJob(1);
    $result = $callProtectedMethod($job, 'ensureTopicAuthorAsOrganizer', [$parsedStaff, $topicData]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toMatchArray([
        'osu_id' => 12345,
        'role' => 'organizer',
        'username' => '',
    ]);
});

test('topic author role updated to organizer when exists with other role', function () use ($callProtectedMethod) {
    $parsedStaff = [
        [
            'osu_id' => 12345,
            'username' => 'TopicAuthor',
            'role' => 'other',
        ],
        [
            'osu_id' => 67890,
            'username' => 'Mapper',
            'role' => 'mapper',
        ],
    ];
    $topicData = [
        'user_id' => 12345,
        'title' => 'Test Tournament',
    ];

    $job = new ParseForumTopicJob(1);
    $result = $callProtectedMethod($job, 'ensureTopicAuthorAsOrganizer', [$parsedStaff, $topicData]);

    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[0]['osu_id'])->toBe(12345);
    expect($result[1]['role'])->toBe('mapper');
});

test('topic author not modified when already organizer', function () use ($callProtectedMethod) {
    $parsedStaff = [
        [
            'osu_id' => 12345,
            'username' => 'Organizer',
            'role' => 'organizer',
        ],
        [
            'osu_id' => 67890,
            'username' => 'Mapper',
            'role' => 'mapper',
        ],
    ];
    $topicData = [
        'user_id' => 12345,
        'title' => 'Test Tournament',
    ];

    $job = new ParseForumTopicJob(1);
    $result = $callProtectedMethod($job, 'ensureTopicAuthorAsOrganizer', [$parsedStaff, $topicData]);

    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[0]['osu_id'])->toBe(12345);
});

test('topic author added as organizer when other roles exist but no organizer', function () use ($callProtectedMethod) {
    $parsedStaff = [
        [
            'osu_id' => 67890,
            'username' => 'Mapper',
            'role' => 'mapper',
        ],
        [
            'osu_id' => 78901,
            'username' => 'Referee',
            'role' => 'referee',
        ],
    ];
    $topicData = [
        'user_id' => 12345,
        'title' => 'Test Tournament',
    ];

    $job = new ParseForumTopicJob(1);
    $result = $callProtectedMethod($job, 'ensureTopicAuthorAsOrganizer', [$parsedStaff, $topicData]);

    expect($result)->toHaveCount(3);
    expect($result[2])->toMatchArray([
        'osu_id' => 12345,
        'role' => 'organizer',
        'username' => '',
    ]);
});

test('topic author role updated from mapper to organizer', function () use ($callProtectedMethod) {
    $parsedStaff = [
        [
            'osu_id' => 12345,
            'username' => 'TopicAuthor',
            'role' => 'mapper',
        ],
    ];
    $topicData = [
        'user_id' => 12345,
        'title' => 'Test Tournament',
    ];

    $job = new ParseForumTopicJob(1);
    $result = $callProtectedMethod($job, 'ensureTopicAuthorAsOrganizer', [$parsedStaff, $topicData]);

    expect($result)->toHaveCount(1);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[0]['osu_id'])->toBe(12345);
});

test('no changes when organizer already exists and is not topic author', function () use ($callProtectedMethod) {
    $parsedStaff = [
        [
            'osu_id' => 99999,
            'username' => 'Organizer',
            'role' => 'organizer',
        ],
        [
            'osu_id' => 12345,
            'username' => 'TopicAuthor',
            'role' => 'other',
        ],
    ];
    $topicData = [
        'user_id' => 12345,
        'title' => 'Test Tournament',
    ];

    $job = new ParseForumTopicJob(1);
    $result = $callProtectedMethod($job, 'ensureTopicAuthorAsOrganizer', [$parsedStaff, $topicData]);

    // Should not update topic author since another organizer already exists
    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[1]['role'])->toBe('other');
});

test('returns empty array when no topic data user id', function () use ($callProtectedMethod) {
    $parsedStaff = [];
    $topicData = [
        'title' => 'Test Tournament',
        // No user_id key
    ];

    $job = new ParseForumTopicJob(1);
    $result = $callProtectedMethod($job, 'ensureTopicAuthorAsOrganizer', [$parsedStaff, $topicData]);

    expect($result)->toBeEmpty();
});

test('job detects conflicts on re-parse with manual fields', function () {
    $forumTopicId = 12345;

    // Create tournament with manual title and parsed modes
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Manual Title',
        'description' => 'Manual Description',
        'modes' => ['osu'],
        'status' => Tournament::STATUS_PENDING,
        'field_sources' => [
            'title' => 'manual',
            'description' => 'manual',
            'modes' => 'parsed',
        ],
        'parse_count' => 1,
    ]);

    $postContent = 'Updated Tournament. Modes: osu!, taiko!';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Updated Title',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify manual fields were preserved
    $tournament->refresh();
    expect($tournament->title)->toBe('Manual Title');
    expect($tournament->description)->toBe('Manual Description');

    // Verify parsed fields were updated
    expect($tournament->modes)->toBe([
        ['mode' => 'osu', 'key_count' => null],
        ['mode' => 'taiko', 'key_count' => null],
    ]);

    // Verify parse history was created with conflicts
    $history = TournamentParseHistory::where('tournament_id', $tournament->id)
        ->orderBy('created_at', 'desc')
        ->first();

    expect($history)->not->toBeNull();
    expect($history->conflicts)->not->toBeEmpty();
    expect($history->conflicts)->toHaveKey('title');
    expect($history->conflicts)->toHaveKey('description');
    expect($history->conflicts['title']['current'])->toBe('Manual Title');
    expect($history->conflicts['title']['parsed'])->toBe('Updated Title');
    expect($history->conflicts['title']['source'])->toBe('manual');
});

test('job preserves manual fields on re-parse', function () {
    $forumTopicId = 12345;

    // Create tournament with all manual fields
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Manual Title',
        'description' => 'Manual Description',
        'registration_start' => now()->addDays(1),
        'registration_end' => now()->addDays(7),
        'field_sources' => [
            'title' => 'manual',
            'description' => 'manual',
            'registration_start' => 'manual',
            'registration_end' => 'manual',
        ],
        'parse_count' => 1,
    ]);

    $postContent = 'Updated Tournament with different dates';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Parsed Title',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify all manual fields were preserved
    $tournament->refresh();
    expect($tournament->title)->toBe('Manual Title');
    expect($tournament->description)->toBe('Manual Description');
    expect($tournament->registration_start->format('Y-m-d'))->toBe(now()->addDays(1)->format('Y-m-d'));
    expect($tournament->registration_end->format('Y-m-d'))->toBe(now()->addDays(7)->format('Y-m-d'));

    // Verify field_sources still marked as manual
    expect($tournament->field_sources['title'])->toBe('manual');
    expect($tournament->field_sources['description'])->toBe('manual');
    expect($tournament->field_sources['registration_start'])->toBe('manual');
    expect($tournament->field_sources['registration_end'])->toBe('manual');
});

test('job preserves existing host_username during re-parse', function () {
    $forumTopicId = 12345;

    // Create tournament with existing host_username
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'host_osu_id' => 98765,
        'host_username' => 'existing_host',
        'title' => 'Old Title',
        'status' => Tournament::STATUS_PENDING,
    ]);

    $postContent = 'Updated Tournament. Modes: osu!';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Updated Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify existing host_username was preserved
    $tournament->refresh();
    expect($tournament->host_username)->toBe('existing_host');

    // Verify other fields were updated
    expect($tournament->title)->toBe('Updated Tournament');
});

test('job sets host_username to null for new tournaments', function () {
    $forumTopicId = 12345;
    $postContent = 'New Tournament. Modes: osu!';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'New Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify host_osu_id is set
    $this->assertDatabaseHas('tournaments', [
        'forum_topic_id' => $forumTopicId,
        'host_osu_id' => 98765,
        // host_username should be null initially (will be resolved by BatchFetchUsersJob)
    ]);
});

test('job preserves null host_username when BatchFetchUsersJob has not run', function () {
    $forumTopicId = 12345;

    // Create tournament with null host_username but valid host_osu_id
    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'host_osu_id' => 98765,
        'host_username' => null, // Not yet resolved by BatchFetchUsersJob
        'title' => 'Old Title',
    ]);

    $postContent = 'Updated Tournament. Modes: osu!';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Updated Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [
                [
                    'body' => [
                        'raw' => $postContent,
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

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    // Verify host_username remains null (will be resolved later)
    $tournament->refresh();
    expect($tournament->host_username)->toBeNull();
    expect($tournament->host_osu_id)->toBe(98765);
});

test('job extracts banner_url from [img] tag when creating tournament', function () {
    $forumTopicId = 12345;
    $bannerUrl = 'https://example.com/tournament-banner.png';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Test Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'created_at' => now()->subDays(2)->toIso8601String(),
            'posts' => [
                [
                    'body' => [
                        'raw' => "Test Tournament. Modes: osu!\n\n[img]{$bannerUrl}[/img]",
                        'html' => '<p>Test Tournament</p>',
                    ],
                ],
            ],
        ], 200),
        $bannerUrl => Http::response('', 200, ['Content-Type' => 'image/png']),
    ]);

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', $forumTopicId)->first();
    expect($tournament)->not->toBeNull();
    expect($tournament->banner_url)->toBe($bannerUrl);
});

test('job extracts banner_url from [imagemap] tag when creating tournament', function () {
    $forumTopicId = 12345;
    $bannerUrl = 'https://content.example.com/banner.webp';

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Test Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'created_at' => now()->subDays(2)->toIso8601String(),
            'posts' => [
                [
                    'body' => [
                        'raw' => "Test Tournament. Modes: osu!\n\n[imagemap]\n{$bannerUrl}\n100 100 200 200 https://example.com JOIN\n[/imagemap]",
                        'html' => '<p>Test Tournament</p>',
                    ],
                ],
            ],
        ], 200),
        $bannerUrl => Http::response('', 200, ['Content-Type' => 'image/webp']),
    ]);

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', $forumTopicId)->first();
    expect($tournament)->not->toBeNull();
    expect($tournament->banner_url)->toBe($bannerUrl);
});

test('job sets banner_url to null when no images found', function () {
    $forumTopicId = 12345;

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Test Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'created_at' => now()->subDays(2)->toIso8601String(),
            'posts' => [
                [
                    'body' => [
                        'raw' => 'Test Tournament. Modes: osu! (no banner images)',
                        'html' => '<p>Test Tournament</p>',
                    ],
                ],
            ],
        ], 200),
    ]);

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament = Tournament::where('forum_topic_id', $forumTopicId)->first();
    expect($tournament)->not->toBeNull();
    expect($tournament->banner_url)->toBeNull();
});

test('job preserves existing banner_url during re-parse', function () {
    $forumTopicId = 12345;
    $existingBannerUrl = 'https://existing.com/banner.png';

    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'host_osu_id' => 98765,
        'banner_url' => $existingBannerUrl,
        'title' => 'Old Title',
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Updated Title',
            'forum_id' => 55,
            'user_id' => 98765,
            'created_at' => now()->subDays(2)->toIso8601String(),
            'posts' => [
                [
                    'body' => [
                        'raw' => 'Updated Tournament info',
                        'html' => '<p>Updated Tournament info</p>',
                    ],
                ],
            ],
        ], 200),
    ]);

    $job = new ParseForumTopicJob($forumTopicId);
    $job->handle(app(OsuApiService::class), app(ForumParser::class));

    $tournament->refresh();
    expect($tournament->banner_url)->toBe($existingBannerUrl);
});

test('daily parse skips approved tournament staff payloads even when forum post changed', function () {
    $forumTopicId = 543210;
    $postContent = 'Changed Tournament. Modes: osu!';
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $staffUser = User::factory()->create(['osu_id' => 111]);

    $tournament = Tournament::factory()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Approved Tournament',
        'status' => Tournament::STATUS_APPROVED,
        'parse_count' => 7,
        'forum_post_sha256' => hash('sha256', 'old post'),
    ]);
    $tournament->staff()->attach($staffUser->id, [
        'role' => 'organizer',
        'status' => 'approved',
        'source' => 'parsed',
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Changed Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [['body' => ['raw' => $postContent]]],
        ], 200),
    ]);

    /** @var ForumParser&MockInterface $parser */
    $parser = MockeryAlias::mock(ForumParser::class);
    $parser->shouldReceive('parseForumTopicData')
        ->once()
        ->with($forumTopicId, MockeryAlias::type('array'), $postContent, true)
        ->andReturn(parsedForumTopicJobData([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Changed Tournament',
            'description' => $postContent,
            'modes' => ['osu'],
        ]));
    $parser->shouldReceive('parseBanner')->never();
    $parser->shouldReceive('parseStaffFromBBcode')->never();

    (new ParseForumTopicJob($forumTopicId, $transactionId))->handle(app(OsuApiService::class), $parser);

    $tournament->refresh();

    expect($tournament->title)->toBe('Approved Tournament');
    expect($tournament->parse_count)->toBe(7);
    expect($tournament->staff()->count())->toBe(1);
    expect(TournamentParseHistory::where('tournament_id', $tournament->id)->count())->toBe(0);
    expect(app(BatchTransactionService::class)->getStaffPayloads($transactionId))->toBeEmpty();
});

test('daily parse skips pending tournament staff when forum post is unchanged', function () {
    $forumTopicId = 543211;
    $postContent = 'Same Tournament. Modes: osu!';
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();

    $tournament = Tournament::factory()->pending()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Same Tournament',
        'parse_count' => 2,
        'forum_post_sha256' => hash('sha256', $postContent),
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Same Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [['body' => ['raw' => $postContent]]],
        ], 200),
    ]);

    /** @var ForumParser&MockInterface $parser */
    $parser = MockeryAlias::mock(ForumParser::class);
    $parser->shouldReceive('parseForumTopicData')
        ->once()
        ->andReturn(parsedForumTopicJobData([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Same Tournament',
            'description' => $postContent,
            'modes' => ['osu'],
        ]));
    $parser->shouldReceive('parseBanner')->never();
    $parser->shouldReceive('parseStaffFromBBcode')->never();

    (new ParseForumTopicJob($forumTopicId, $transactionId))->handle(app(OsuApiService::class), $parser);

    expect($tournament->fresh()->parse_count)->toBe(2);
    expect(TournamentParseHistory::where('tournament_id', $tournament->id)->count())->toBe(0);
    expect(app(BatchTransactionService::class)->getStaffPayloads($transactionId))->toBeEmpty();
});

test('daily parse updates pending tournament staff when forum post changed', function () {
    $forumTopicId = 543212;
    $postContent = 'Changed Pending Tournament. Modes: osu!';
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $user = User::factory()->create(['osu_id' => 222, 'username' => 'MapperUser']);

    $tournament = Tournament::factory()->pending()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Old Pending Tournament',
        'host_osu_id' => null,
        'host_username' => null,
        'forum_post_sha256' => hash('sha256', 'old post'),
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Changed Pending Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [['body' => ['raw' => $postContent]]],
        ], 200),
    ]);

    /** @var ForumParser&MockInterface $parser */
    $parser = MockeryAlias::mock(ForumParser::class);
    $parser->shouldReceive('parseForumTopicData')
        ->once()
        ->andReturn(parsedForumTopicJobData([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Changed Pending Tournament',
            'description' => $postContent,
            'modes' => ['osu'],
        ]));
    $parser->shouldReceive('parseBanner')->once()->andReturn(null);
    $parser->shouldReceive('parseStaffFromBBcode')
        ->once()
        ->with($postContent)
        ->andReturn([
            ['osu_id' => $user->osu_id, 'username' => $user->username, 'role' => 'mapper'],
        ]);

    (new ParseForumTopicJob($forumTopicId, $transactionId))->handle(app(OsuApiService::class), $parser);

    $tournament->refresh();
    $payloads = app(BatchTransactionService::class)->getStaffPayloads($transactionId);

    expect($tournament->title)->toBe('Changed Pending Tournament');
    expect($tournament->forum_post_sha256)->toBe(hash('sha256', $postContent));
    expect($payloads[$tournament->id])->toHaveCount(1);
    expect($payloads[$tournament->id][0]['osu_id'])->toBe($user->osu_id);
});

test('new tournament stores forum post hash and staff payload', function () {
    $forumTopicId = 543213;
    $postContent = 'New Tournament. Modes: osu!';
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'New Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [['body' => ['raw' => $postContent]]],
        ], 200),
    ]);

    /** @var ForumParser&MockInterface $parser */
    $parser = MockeryAlias::mock(ForumParser::class);
    $parser->shouldReceive('parseForumTopicData')
        ->once()
        ->andReturn(parsedForumTopicJobData([
            'forum_topic_id' => $forumTopicId,
            'title' => 'New Tournament',
            'description' => $postContent,
            'modes' => ['osu'],
        ]));
    $parser->shouldReceive('parseBanner')->once()->andReturn(null);
    $parser->shouldReceive('parseStaffFromBBcode')
        ->once()
        ->andReturn([
            ['osu_id' => 333, 'username' => 'OrganizerUser', 'role' => 'organizer'],
        ]);

    (new ParseForumTopicJob($forumTopicId, $transactionId))->handle(app(OsuApiService::class), $parser);

    $tournament = Tournament::where('forum_topic_id', $forumTopicId)->firstOrFail();
    $payloads = app(BatchTransactionService::class)->getStaffPayloads($transactionId);

    expect($tournament->forum_post_sha256)->toBe(hash('sha256', $postContent));
    expect($payloads[$tournament->id])->toHaveCount(1);
});

test('forced reparse updates approved tournament staff despite status and hash gates', function () {
    $forumTopicId = 543214;
    $postContent = 'Forced Tournament. Modes: osu!';
    $transactionId = app(BatchTransactionService::class)->generateTransactionId();
    $user = User::factory()->create(['osu_id' => 444, 'username' => 'ForcedMapper']);

    $tournament = Tournament::factory()->approved()->create([
        'forum_topic_id' => $forumTopicId,
        'title' => 'Approved Old Title',
        'host_osu_id' => null,
        'host_username' => null,
        'forum_post_sha256' => hash('sha256', $postContent),
    ]);

    Http::fake([
        'osu.ppy.sh/api/v2/forums/topics/*' => Http::response([
            'id' => $forumTopicId,
            'title' => 'Forced Tournament',
            'forum_id' => 55,
            'user_id' => 98765,
            'posts' => [['body' => ['raw' => $postContent]]],
        ], 200),
    ]);

    /** @var ForumParser&MockInterface $parser */
    $parser = MockeryAlias::mock(ForumParser::class);
    $parser->shouldReceive('parseForumTopicData')
        ->once()
        ->with($forumTopicId, MockeryAlias::type('array'), $postContent, false)
        ->andReturn(parsedForumTopicJobData([
            'forum_topic_id' => $forumTopicId,
            'title' => 'Forced Tournament',
            'description' => $postContent,
            'modes' => ['osu'],
        ]));
    $parser->shouldReceive('parseBanner')->once()->andReturn(null);
    $parser->shouldReceive('parseStaffFromBBcode')
        ->once()
        ->andReturn([
            ['osu_id' => $user->osu_id, 'username' => $user->username, 'role' => 'mapper'],
        ]);

    (new ParseForumTopicJob($forumTopicId, $transactionId, forceReparse: true))
        ->handle(app(OsuApiService::class), $parser);

    $tournament->refresh();
    $payloads = app(BatchTransactionService::class)->getStaffPayloads($transactionId);

    expect($tournament->title)->toBe('Forced Tournament');
    expect($tournament->forum_post_sha256)->toBe(hash('sha256', $postContent));
    expect($payloads[$tournament->id])->toHaveCount(1);
    expect($payloads[$tournament->id][0]['osu_id'])->toBe($user->osu_id);
});
