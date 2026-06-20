<?php

use App\Jobs\CacheTournamentBannerJob;
use App\Jobs\ParseForumTopicJob;
use App\Models\Tournament;
use App\Models\User;
use App\Support\QueueNames;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    $this->admin = User::factory()->admin()->create();
    $this->tournament = Tournament::factory()->create([
        'banner_url' => 'https://example.com/old-banner.jpg',
        'modes' => ['osu'],
        'start_round_size' => null,
        'restricted_countries' => null,
    ]);
});

describe('Admin Tournament Create', function () {
    test('create page shows default BWS configuration values', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tournaments.create'));

        $response->assertStatus(200);
        $response->assertSee('value="0.9937"', false);
        $response->assertSee('value="2.00"', false);
        $response->assertSee('value="1.0000"', false);
    });

    test('admin can create pending tournament with two decimal star ratings', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.tournaments.store'), [
                'title' => 'Two Decimal SR Cup',
                'modes' => ['osu'],
                'star_rating_first' => 2.72,
                'star_rating_last' => 5.25,
                'star_rating_qualifier' => 3.14,
            ]);

        $response->assertRedirect();

        $tournament = Tournament::where('title', 'Two Decimal SR Cup')->firstOrFail();

        expect($tournament->status)->toBe('pending_review');
        expect((float) $tournament->getAttribute('star_rating_first'))->toBe(2.72);
        expect((float) $tournament->getAttribute('star_rating_last'))->toBe(5.25);
        expect((float) $tournament->getAttribute('star_rating_qualifier'))->toBe(3.14);
    });

    test('create extracts forum topic id from forum url and queues parse', function () {
        $forumUrl = 'https://osu.ppy.sh/community/forums/topics/1977529?n=1';

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tournaments.store'), [
                'title' => 'Forum Create Cup',
                'modes' => ['osu'],
                'forum_post_url' => $forumUrl,
            ]);

        $response->assertRedirect();

        $tournament = Tournament::where('title', 'Forum Create Cup')->firstOrFail();

        expect($tournament->forum_topic_id)->toBe(1977529);
        expect($tournament->forum_post_url)->toBe('https://osu.ppy.sh/community/forums/topics/1977529');

        Queue::assertPushed(ParseForumTopicJob::class, function (ParseForumTopicJob $job) {
            return $job->topicId === 1977529;
        });
    });

    test('create queues forum parse for newly created duplicate topic tournament only', function () {
        Bus::fake();

        $forumUrl = 'https://osu.ppy.sh/community/forums/topics/1977529';
        $existingTournament = Tournament::factory()->create([
            'forum_topic_id' => 1977529,
            'title' => 'Existing Same Topic Cup',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tournaments.store'), [
                'title' => 'New Same Topic Cup',
                'modes' => ['osu'],
                'forum_post_url' => $forumUrl,
            ]);

        $response->assertRedirect();

        $newTournament = Tournament::where('title', 'New Same Topic Cup')->firstOrFail();

        expect($newTournament->forum_topic_id)->toBe(1977529);
        expect($existingTournament->fresh()->title)->toBe('Existing Same Topic Cup');

        Bus::assertBatched(function (PendingBatch $batch) use ($newTournament) {
            if ($batch->name !== "Stage 1: Parse Tournament ({$newTournament->title})") {
                return false;
            }

            if ($batch->queue() !== QueueNames::OSU_ADMIN_PRIORITY) {
                return false;
            }

            $job = $batch->jobs[0] ?? null;
            if (! $job instanceof ParseForumTopicJob
                || $job->topicId !== 1977529
                || $job->queue !== QueueNames::OSU_ADMIN_PRIORITY
            ) {
                return false;
            }

            $targetTournamentId = new ReflectionProperty($job, 'targetTournamentId');
            $targetTournamentId->setAccessible(true);

            return $targetTournamentId->getValue($job) === $newTournament->id;
        });
    });

    test('create stores wiki url without forum topic id', function () {
        $wikiUrl = 'https://osu.ppy.sh/wiki/en/Tournaments/OWC/2024';

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tournaments.store'), [
                'title' => 'Wiki Create Cup',
                'modes' => ['osu'],
                'forum_post_url' => $wikiUrl,
            ]);

        $response->assertRedirect();

        $tournament = Tournament::where('title', 'Wiki Create Cup')->firstOrFail();

        expect($tournament->forum_topic_id)->toBeNull();
        expect($tournament->forum_post_url)->toBe($wikiUrl);

        Queue::assertNotPushed(ParseForumTopicJob::class);
    });

    test('create field sources exclude virtual mania variants', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.tournaments.store'), [
                'title' => 'Create Mania Cup',
                'modes' => ['osu', 'mania'],
                'mania_variants' => ['mania_4k'],
            ]);

        $response->assertRedirect();

        $tournament = Tournament::where('title', 'Create Mania Cup')->firstOrFail();

        expect($tournament->modes)->toContain(['mode' => 'mania', 'key_count' => 4]);
        expect($tournament->field_sources['modes'])->toBe('manual');
        expect(isset($tournament->field_sources['mania_variants']))->toBeFalse();
    });
});

describe('Admin Tournament Update - Start Round Size', function () {
    test('admin can update start_round_size', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'start_round_size' => 32,
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->start_round_size)->toBe(32);
    });

    test('start_round_size_must_be_power_of_2', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'start_round_size' => 15, // Not a power of 2
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'The given data was invalid.',
        ]);
        $this->assertArrayHasKey('start_round_size', $response->json('errors'));
    });

    test('start_round_size_accepts_all_valid_powers_of_2', function ($value) {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'start_round_size' => $value,
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->start_round_size)->toBe($value);
    })->with([2, 4, 8, 16, 32, 64, 128, 256, 512, 1024]);

    test('start_round_size_can_be_null', function () {
        $this->tournament->update(['start_round_size' => 32]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'start_round_size' => null,
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->start_round_size)->toBeNull();
    });

    test('admin can update structured format progression', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'team_formation_style' => 'auction',
                'format_structure' => [
                    'stages' => [
                        [
                            'type' => 'qualifier',
                            'advance_count' => 64,
                        ],
                        [
                            'type' => 'bracket',
                            'start_round_size' => 32,
                            'entry_type' => 'winner_loser_hybrid',
                            'elimination_type' => 'double_elimination',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $fresh = $this->tournament->fresh();
        expect($fresh->team_formation_style)->toBe('auction');
        expect($fresh->format_tags)->toBe(['auction']);
        expect($fresh->start_round_size)->toBe(32);
        expect($fresh->progression_summary)->toBe('Qualifier (Top 64) -> Ro32 Double Elimination (Hybrid)');
        expect($fresh->field_sources['format_structure'])->toBe('manual');
    });

    test('admin progression form renders stage editor without removed raw fields', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tournaments.show', $this->tournament));

        $response->assertStatus(200);
        $response->assertSee('Add Stage');
        $response->assertSee('Delete');
        $response->assertSee('Single Elimination');
        $response->assertSee('Double Elimination');
        $response->assertSee('Qualifier cutoff');
        $response->assertSee('Swiss Round');
        $response->assertSee('Swiss rounds');
        $response->assertSee('Advancing from Swiss');
        $response->assertDontSee('Winner Entries');
        $response->assertDontSee('Loser Entries');
        $response->assertDontSee('BR Win Condition');
        $response->assertDontSee('Final stage');
        $response->assertDontSee('Seed cutoff');
        $response->assertDontSee('Advance / Top Count');
    });
});

describe('Admin Tournament Update - Regional Restrictions', function () {
    test('review_page_renders_searchable_region_templates', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tournaments.show', $this->tournament));

        $response->assertStatus(200);
        $response->assertSee('Region Templates');
        $response->assertSee('Eastern Europe');
        $response->assertSee('Central Asia');
        $response->assertSee('Russian Speaking');
        $response->assertSee('Search country or code...');
    });

    test('admin can update_restricted_countries', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'restricted_countries' => ['KR', 'JP', 'US'],
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->restricted_countries)->toBe(['KR', 'JP', 'US']);
    });

    test('country_codes_are_normalized_to_uppercase', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'restricted_countries' => ['kr', 'jp', 'us'],
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->restricted_countries)->toBe(['KR', 'JP', 'US']);
    });

    test('country_codes_must_be_valid_iso_3166_alpha2', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'restricted_countries' => ['KR', 'JP', 'INVALID'], // 7 chars
            ]);

        $response->assertStatus(400);
        $errors = $response->json('errors');
        // Check for nested error keys (e.g., "restricted_countries.2")
        $this->assertTrue(collect(array_keys($errors))->filter(fn ($key) => str_starts_with($key, 'restricted_countries'))->isNotEmpty());
    });

    test('country_codes_must_be_exactly_2_characters', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'restricted_countries' => ['KOR', 'JPN'], // 3 chars
            ]);

        $response->assertStatus(400);
        $errors = $response->json('errors');
        $this->assertTrue(collect(array_keys($errors))->filter(fn ($key) => str_starts_with($key, 'restricted_countries'))->isNotEmpty());
    });

    test('restricted_countries_can_be_empty_array', function () {
        $this->tournament->update(['restricted_countries' => ['KR', 'JP']]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'restricted_countries' => [],
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->restricted_countries)->toBe([]);
    });

    test('restricted_countries_clear_from_form_hidden_value_saves_empty_array', function () {
        $this->tournament->update(['restricted_countries' => ['KR', 'JP']]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'restricted_countries' => '',
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->restricted_countries)->toBe([]);
    });

    test('restricted_countries_null_input_clears_to_empty_array', function () {
        $this->tournament->update(['restricted_countries' => ['KR', 'JP']]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'restricted_countries' => null,
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->restricted_countries)->toBe([]);
    });
});

describe('Admin Tournament Update - Modes Checkbox Persistence', function () {
    test('modes_in_enhanced_format_are_preserved_on_update', function () {
        // Start with tournament in enhanced format (as stored in DB)
        $this->tournament->update([
            'modes' => [['mode' => 'osu', 'key_count' => null]],
        ]);

        // Simulate form submission with checkbox checked (sends simple string)
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu'], // Form sends simple string
            ]);

        $response->assertStatus(200);

        // Verify mode is preserved in enhanced format
        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->toHaveCount(1);
        expect($updatedModes[0])->toBe(['mode' => 'osu', 'key_count' => null]);
    });

    test('multiple_modes_are_preserved_on_update', function () {
        $this->tournament->update([
            'modes' => [
                ['mode' => 'osu', 'key_count' => null],
                ['mode' => 'taiko', 'key_count' => null],
                ['mode' => 'catch', 'key_count' => null],
            ],
        ]);

        // Update preserves all modes
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu', 'taiko', 'catch'],
            ]);

        $response->assertStatus(200);

        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->toHaveCount(3);
        expect($updatedModes)->toContain(['mode' => 'osu', 'key_count' => null]);
        expect($updatedModes)->toContain(['mode' => 'taiko', 'key_count' => null]);
        expect($updatedModes)->toContain(['mode' => 'catch', 'key_count' => null]);
    });

    test('can_change_mode_from_osu_to_taiko', function () {
        $this->tournament->update([
            'modes' => [['mode' => 'osu', 'key_count' => null]],
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['taiko'],
            ]);

        $response->assertStatus(200);

        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->toHaveCount(1);
        expect($updatedModes[0])->toBe(['mode' => 'taiko', 'key_count' => null]);
        expect($updatedModes)->not()->toContain(['mode' => 'osu', 'key_count' => null]);
    });

    test('can_add_mode_to_existing_modes', function () {
        $this->tournament->update([
            'modes' => [['mode' => 'osu', 'key_count' => null]],
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu', 'taiko'],
            ]);

        $response->assertStatus(200);

        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->toHaveCount(2);
        expect($updatedModes)->toContain(['mode' => 'osu', 'key_count' => null]);
        expect($updatedModes)->toContain(['mode' => 'taiko', 'key_count' => null]);
    });
});

describe('Admin Tournament Update - Mania Variants', function () {
    test('admin can_update_mania_variants', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu', 'mania'],
                'mania_variants' => ['mania_4k', 'mania_7k'],
            ]);

        $response->assertStatus(200);

        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->toContain(['mode' => 'mania', 'key_count' => 4]);
        expect($updatedModes)->toContain(['mode' => 'mania', 'key_count' => 7]);
    });

    test('mania_variants_converts_to_enhanced_format', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu'],
                'mania_variants' => ['mania_4k'],
            ]);

        $response->assertStatus(200);

        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->toContain(['mode' => 'mania', 'key_count' => 4]);
    });

    test('mania_variants_allows_multiple_variants', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu'],
                'mania_variants' => ['mania_4k', 'mania_7k', 'mania_other'],
            ]);

        $response->assertStatus(200);

        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->toContain(['mode' => 'mania', 'key_count' => 4]);
        expect($updatedModes)->toContain(['mode' => 'mania', 'key_count' => 7]);
        expect($updatedModes)->toContain(['mode' => 'mania', 'key_count' => null]); // mania_other
    });

    test('mania_variants_replaces_generic_mania_in_modes', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu', 'mania'], // Generic mania should be replaced
                'mania_variants' => ['mania_4k'],
            ]);

        $response->assertStatus(200);

        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->not()->toContain('mania'); // Generic mania removed
        expect($updatedModes)->toContain(['mode' => 'mania', 'key_count' => 4]);
    });

    test('mania_variants_must_be_valid', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu'],
                'mania_variants' => ['invalid_variant'],
            ]);

        $response->assertStatus(400);
        $errors = $response->json('errors');
        $this->assertTrue(collect(array_keys($errors))->filter(fn ($key) => str_starts_with($key, 'mania_variants'))->isNotEmpty());
    });

    test('can_remove_all_mania_variants', function () {
        $this->tournament->update([
            'modes' => [['mode' => 'mania', 'key_count' => 4]],
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu'],
                'mania_variants' => [],
            ]);

        $response->assertStatus(200);

        $updatedModes = $this->tournament->fresh()->modes;
        expect($updatedModes)->not()->toContain('mania');
    });
});

describe('Admin Tournament Update - Banner URL', function () {
    test('banner_url_change_triggers_cache_job', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'banner_url' => 'https://new-example.com/banner.jpg',
            ]);

        $response->assertStatus(200);

        Queue::assertPushed(CacheTournamentBannerJob::class, function ($job) {
            return $job->tournament->id === $this->tournament->id;
        });
    });

    test('banner_url_same_value_does_not_trigger_cache_job', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'banner_url' => $this->tournament->banner_url,
            ]);

        $response->assertStatus(200);

        Queue::assertNotPushed(CacheTournamentBannerJob::class);
    });

    test('banner_url_to_null_does_not_trigger_cache_job', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'banner_url' => null,
            ]);

        $response->assertStatus(200);

        Queue::assertNotPushed(CacheTournamentBannerJob::class);
    });
});

describe('Admin Tournament Update - Combined Fields', function () {
    test('can_update_all_new_fields_simultaneously', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'start_round_size' => 64,
                'restricted_countries' => ['KR', 'JP'],
                'modes' => ['osu'],
                'mania_variants' => ['mania_4k'],
            ]);

        $response->assertStatus(200);

        $tournament = $this->tournament->fresh();
        expect($tournament->start_round_size)->toBe(64);
        expect($tournament->restricted_countries)->toBe(['KR', 'JP']);
        expect($tournament->modes)->toContain(['mode' => 'mania', 'key_count' => 4]);
    });
});

describe('Admin Tournament Update - Reparse Protection', function () {
    test('admin_update_marks_field_as_manual', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'title' => 'Admin Edited Title',
                'registration_start' => '2026-02-01',
                'registration_end' => '2026-03-01',
            ]);

        $response->assertStatus(200);

        $tournament = $this->tournament->fresh();

        // Verify values were updated
        expect($tournament->title)->toBe('Admin Edited Title');
        expect($tournament->registration_start->format('Y-m-d H:i:s'))->toBe('2026-02-01 12:00:00');
        expect($tournament->registration_end->format('Y-m-d H:i:s'))->toBe('2026-03-01 12:00:00');

        // Verify field_sources marks these as 'manual'
        expect($tournament->field_sources['title'])->toBe('manual');
        expect($tournament->field_sources['registration_start'])->toBe('manual');
        expect($tournament->field_sources['registration_end'])->toBe('manual');
    });

    test('admin_update_preserves_explicit_date_times_from_split_form_fields', function () {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'registration_start' => '2026-02-01T18:30',
                'registration_end' => '2026-03-01T21:45',
                'tournament_start' => '2026-03-07T13:00',
                'tournament_end' => '2026-03-21T16:15',
            ]);

        $response->assertStatus(200);

        $tournament = $this->tournament->fresh();
        expect($tournament->registration_start->format('Y-m-d H:i:s'))->toBe('2026-02-01 18:30:00');
        expect($tournament->registration_end->format('Y-m-d H:i:s'))->toBe('2026-03-01 21:45:00');
        expect($tournament->tournament_start->format('Y-m-d H:i:s'))->toBe('2026-03-07 13:00:00');
        expect($tournament->tournament_end->format('Y-m-d H:i:s'))->toBe('2026-03-21 16:15:00');
    });

    test('reparse_respects_admin_edits_marked_as_manual', function () {
        // Admin edits fields
        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'title' => 'Admin Title',
                'rank_range_min' => 5000,
                'rank_range_max' => 100000,
            ]);

        $this->tournament->refresh();

        // Verify admin edits
        expect($this->tournament->title)->toBe('Admin Title');
        expect($this->tournament->field_sources['title'])->toBe('manual');

        // Simulate re-parse with conflicting data
        $parsedData = [
            'title' => 'Parsed Title', // Should NOT update (manual)
            'rank_range_min' => 1000, // Should NOT update (manual)
            'rank_range_max' => 50000, // Should NOT update (manual)
            'forum_topic_id' => $this->tournament->forum_topic_id,
            'forum_post_url' => $this->tournament->forum_post_url,
            'modes' => $this->tournament->modes,
        ];

        $this->tournament->updateFromParsedData($parsedData);

        // Verify manual fields are preserved
        expect($this->tournament->fresh()->title)->toBe('Admin Title');
        expect($this->tournament->fresh()->rank_range_min)->toBe(5000);
        expect($this->tournament->fresh()->rank_range_max)->toBe(100000);

        // Verify field_sources still marks them as manual
        expect($this->tournament->fresh()->field_sources['title'])->toBe('manual');
        expect($this->tournament->fresh()->field_sources['rank_range_min'])->toBe('manual');
        expect($this->tournament->fresh()->field_sources['rank_range_max'])->toBe('manual');
    });

    test('multiple_admin_updates_accumulate_manual_fields', function () {
        // First admin update
        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'title' => 'First Edit',
            ]);

        // Second admin update
        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'rank_range_min' => 1000,
            ]);

        $this->tournament->refresh();

        // Both fields should be marked as manual
        expect($this->tournament->field_sources['title'])->toBe('manual');
        expect($this->tournament->field_sources['rank_range_min'])->toBe('manual');
        expect($this->tournament->title)->toBe('First Edit');
        expect($this->tournament->rank_range_min)->toBe(1000);
    });

    test('virtual_fields_are_not_included_in_field_sources', function () {
        // Update with mania_variants (virtual field)
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'modes' => ['osu'],
                'mania_variants' => ['mania_4k'],
            ]);

        $response->assertStatus(200);

        $tournament = $this->tournament->fresh();

        // modes should be in field_sources (actual column)
        expect($tournament->field_sources['modes'])->toBe('manual');

        // mania_variants should NOT be in field_sources (virtual field)
        expect(isset($tournament->field_sources['mania_variants']))->toBeFalse();
    });
});

describe('Admin Banner Refresh Endpoint', function () {
    test('review_page_renders_csrf_safe_banner_refresh_handler', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tournaments.show', $this->tournament));

        $response->assertStatus(200);
        $response->assertSee('onclick="refreshBanner('.$this->tournament->id.', this)"', false);
        $response->assertSee("'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content", false);
        $response->assertSee('Your session token expired. Refresh the page and try again.', false);
    });

    test('admin_can_refresh_banner_cache', function () {
        Http::fake([
            'example.com/*' => Http::response(file_get_contents(base_path('tests/fixtures/test-banner.jpg')), 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tournaments.refresh-banner', $this->tournament));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Banner cache refresh queued successfully',
        ]);

        Queue::assertPushed(CacheTournamentBannerJob::class);
    });

    test('banner_refresh_returns_error_when_no_banner_url', function () {
        $this->tournament->update(['banner_url' => null]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tournaments.refresh-banner', $this->tournament));

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Tournament does not have a banner URL',
        ]);

        Queue::assertNotPushed(CacheTournamentBannerJob::class);
    });

    test('non_admin_cannot_refresh_banner', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('admin.tournaments.refresh-banner', $this->tournament));

        $response->assertStatus(403);
    });

    test('guest_cannot_refresh_banner', function () {
        $response = $this->post(route('admin.tournaments.refresh-banner', $this->tournament));

        $response->assertStatus(401); // Unauthenticated users get 401, not 403
    });
});

describe('Admin Tournament Update - Forum Post URL', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->tournament = Tournament::factory()->create([
            'forum_topic_id' => null,
        ]);
    });

    test('admin can add forum_post_url which extracts forum_topic_id', function () {
        $forumUrl = 'https://osu.ppy.sh/community/forums/topics/1977529';

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'forum_post_url' => $forumUrl,
            ]);

        $response->assertStatus(200);
        $this->tournament->refresh();
        expect($this->tournament->forum_topic_id)->toBe(1977529);
        expect($this->tournament->forum_post_url)->toBe($forumUrl);
    });

    test('forum_post_url_with_query_params_extracts_topic_id', function () {
        $forumUrl = 'https://osu.ppy.sh/community/forums/topics/1977529?n=1';

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'forum_post_url' => $forumUrl,
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->forum_topic_id)->toBe(1977529);
    });

    test('empty_forum_post_url_does_not_update_forum_topic_id', function () {
        $this->tournament->update(['forum_topic_id' => 12345]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'forum_post_url' => '',
            ]);

        $response->assertStatus(200);
        expect($this->tournament->fresh()->forum_topic_id)->toBe(12345);
    });

    test('non_osu_forum_url_does_not_extract_topic_id', function () {
        $nonOsuUrl = 'https://example.com/not-a-forum-url';

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'forum_post_url' => $nonOsuUrl,
            ]);

        $response->assertStatus(200); // Valid URL format passes validation
        expect($this->tournament->fresh()->forum_topic_id)->toBeNull(); // But no topic ID extracted
    });

    test('wiki_url_can_be_saved_for_official_tournaments', function () {
        $wikiUrl = 'https://osu.ppy.sh/wiki/en/Tournaments/OWC/2024';

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'forum_post_url' => $wikiUrl,
            ]);

        $response->assertStatus(200);
        $this->tournament->refresh();
        expect($this->tournament->forum_post_url)->toBe($wikiUrl);
        expect($this->tournament->forum_topic_id)->toBeNull(); // Wiki URL clears topic ID
    });

    test('forum_url_clears_wiki_url', function () {
        // First set a wiki URL
        $this->tournament->update([
            'forum_post_url' => 'https://osu.ppy.sh/wiki/en/Tournaments/OWC/2024',
            'forum_topic_id' => null,
        ]);

        $this->tournament->refresh();
        expect($this->tournament->forum_post_url)->toBe('https://osu.ppy.sh/wiki/en/Tournaments/OWC/2024');

        // Then update with forum URL (use unique topic ID to avoid constraint)
        $forumUrl = 'https://osu.ppy.sh/community/forums/topics/'.(999900 + $this->tournament->id);
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'forum_post_url' => $forumUrl,
            ]);

        $response->assertStatus(200);
        $this->tournament->refresh();
        expect($this->tournament->forum_topic_id)->toBe(999900 + $this->tournament->id);
        // When forum_topic_id is set, forum_post_url should be computed, not stored
        expect($this->tournament->forum_post_url)->toBe($forumUrl);
    });

    test('wiki_url_is_protected_from_reparse', function () {
        $wikiUrl = 'https://osu.ppy.sh/wiki/en/Tournaments/OWC/2024';

        $this->actingAs($this->admin)
            ->patch(route('admin.tournaments.update', $this->tournament), [
                'forum_post_url' => $wikiUrl,
            ]);

        $this->tournament->refresh();
        expect($this->tournament->field_sources['forum_post_url'] ?? null)->toBe('manual');
    });
});
