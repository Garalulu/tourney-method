<?php

use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Bus::fake();
});

test('admin can manually import tournament by topic id', function () {
    // Arrange: Create admin user
    $admin = User::factory()->admin()->create();

    // Act: Submit manual import request
    $response = $this->actingAs($admin)
        ->post(route('admin.imports.manual'), [
            'topic_id' => 2173714, // Known valid tournament topic
        ]);

    // Assert: Job was dispatched
    Bus::assertDispatched(ParseForumTopicJob::class, function ($job) {
        return $job->topicId === 2173714;
    });

    // Assert: Redirect with success message
    $response->assertRedirect(route('admin.imports.index'));
    $response->assertSessionHas('success');
});

test('admin cannot import tournament with invalid topic id', function () {
    // Arrange
    $admin = User::factory()->admin()->create();

    // Act
    $response = $this->actingAs($admin)
        ->post(route('admin.imports.manual'), [
            'topic_id' => 'invalid', // Not a number
        ]);

    // Assert: Validation error
    $response->assertSessionHasErrors('topic_id');
    Bus::assertNotDispatched(ParseForumTopicJob::class);
});

test('admin cannot import tournament with missing topic id', function () {
    // Arrange
    $admin = User::factory()->admin()->create();

    // Act: Send empty request
    $response = $this->actingAs($admin)
        ->post(route('admin.imports.manual'), []);

    // Assert: Returns error (no topic IDs provided)
    $response->assertSessionHas('error');
    Bus::assertNotDispatched(ParseForumTopicJob::class);
});

test('non_admin_cannot_manual_import_tournament', function () {
    // Arrange: Regular user
    $user = User::factory()->create();

    // Act
    $response = $this->actingAs($user)
        ->post(route('admin.imports.manual'), [
            'topic_id' => 2173714,
        ]);

    // Assert: Forbidden
    $response->assertForbidden();
    Bus::assertNotDispatched(ParseForumTopicJob::class);
});

test('admin_can_manually_import_multiple_tournaments', function () {
    // Arrange
    $admin = User::factory()->admin()->create();

    $topicIds = [2173714, 2174093, 2174424];

    // Act
    $response = $this->actingAs($admin)
        ->post(route('admin.imports.manual'), [
            'topic_ids' => $topicIds,
        ]);

    // Assert: All jobs dispatched
    Bus::assertDispatchedTimes(ParseForumTopicJob::class, 3);

    foreach ($topicIds as $topicId) {
        Bus::assertDispatched(ParseForumTopicJob::class, function ($job) use ($topicId) {
            return $job->topicId === $topicId;
        });
    }

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('manual_import_skips_existing_tournaments', function () {
    // Arrange: Create existing tournament
    $admin = User::factory()->admin()->create();
    Tournament::factory()->create([
        'forum_topic_id' => 2173714,
        'title' => 'Existing Tournament',
    ]);

    // Act: Try to import same topic
    $this->actingAs($admin)
        ->post(route('admin.imports.manual'), [
            'topic_id' => 2173714,
        ]);

    // Assert: Job still dispatched (deduplication happens in job)
    Bus::assertDispatched(ParseForumTopicJob::class);

    // But tournament should not be duplicated
    $this->assertDatabaseHas('tournaments', [
        'forum_topic_id' => 2173714,
        'title' => 'Existing Tournament',
    ]);

    $this->assertDatabaseCount('tournaments', 1); // Still only one
});
