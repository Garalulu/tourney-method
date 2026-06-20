<?php

use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function tournamentCopyEndedDates(): array
{
    return [
        'registration_start' => now()->subMonths(4),
        'registration_end' => now()->subMonths(3),
        'tournament_start' => now()->subMonths(2),
        'tournament_end' => now()->subMonth(),
    ];
}

/**
 * @return array<string, mixed>
 */
function tournamentCopyActiveDates(): array
{
    return [
        'registration_start' => now()->subMonth(),
        'registration_end' => now()->subWeek(),
        'tournament_start' => now()->subDay(),
        'tournament_end' => now()->addMonth(),
    ];
}

test('admin can copy an ended approved tournament', function () {
    $admin = User::factory()->admin()->create();
    $reviewer = User::factory()->admin()->create();
    $importBatchId = DB::table('otr_import_history')->insertGetId([
        'dump_version' => 'copy-test-v1',
        'dump_url' => 'https://example.com/dump.sql',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    /** @var Tournament $source */
    $source = Tournament::factory()->approved()->create(array_merge(tournamentCopyEndedDates(), [
        'title' => 'Original Cup',
        'description' => 'Original description',
        'forum_topic_id' => 123456,
        'host_osu_id' => 98765,
        'host_username' => 'host_user',
        'modes' => ['osu', ['mode' => 'mania', 'key_count' => 4]],
        'team_size_min' => 2,
        'team_size_max' => 4,
        'vs_size' => 2,
        'rank_range_min' => 1000,
        'rank_range_max' => 50000,
        'is_badge' => true,
        'badge_status' => 'approved',
        'badge_urls' => [1 => ['https://assets.ppy.sh/profile-badges/source.png']],
        'is_bws' => true,
        'bws_base_exponent' => 0.9937,
        'bws_badge_power' => 2.00,
        'bws_divisor' => 1.0000,
        'bws_badge_age_cutoff' => now()->subYear(),
        'star_rating_first' => 3.25,
        'star_rating_last' => 6.75,
        'star_rating_qualifier' => 4.50,
        'format' => 'Swiss',
        'banner_url' => 'https://example.com/banner.jpg',
        'discord_url' => 'https://discord.gg/example',
        'twitch_url' => 'https://twitch.tv/example',
        'spreadsheet_url' => 'https://example.com/sheet',
        'bracket_url' => 'https://example.com/bracket',
        'registration_url' => 'https://example.com/register',
        'tcomm_url' => 'https://tcomm.example/tournaments/1',
        'tcomm_id' => 'tcomm-copy-source',
        'otr_id' => 998877,
        'import_source' => 'otr',
        'import_batch_id' => $importBatchId,
        'otr_deleted_at' => now()->subDay(),
        'field_sources' => [
            'badge_status' => 'manual',
            'title' => 'manual',
            'modes' => 'manual',
        ],
        'start_round_size' => 64,
        'restricted_countries' => ['KR', 'JP'],
        'viewed_at' => now(),
        'reviewed_by' => $reviewer->id,
    ]));

    $staffUser = User::factory()->create();
    TournamentStaff::factory()->approved()->create([
        'tournament_id' => $source->id,
        'user_id' => $staffUser->id,
        'role' => 'organizer',
        'notes' => 'Lead organizer',
        'reviewed_by' => $reviewer->id,
        'source' => 'manual',
    ]);
    TournamentWinner::factory()->create([
        'tournament_id' => $source->id,
        'placement' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.tournaments.copy', $source), [
            'title' => 'Copied Cup',
        ]);

    $response
        ->assertCreated()
        ->assertJson([
            'message' => 'Tournament copied successfully',
        ]);

    /** @var Tournament $copy */
    $copy = Tournament::findOrFail($response->json('tournament_id'));

    expect($copy->title)->toBe('Copied Cup');
    expect($copy->status)->toBe(Tournament::STATUS_APPROVED);
    expect($copy->reviewed_by)->toBe($admin->id);
    expect($copy->reviewed_at)->not()->toBeNull();
    expect($copy->viewed_at)->toBeNull();
    expect($copy->description)->toBe($source->description);
    expect($copy->forum_topic_id)->toBe($source->forum_topic_id);
    expect($copy->host_osu_id)->toBe($source->host_osu_id);
    expect($copy->host_username)->toBe($source->host_username);
    expect($copy->modes)->toBe($source->modes);
    expect($copy->team_size_min)->toBe($source->team_size_min);
    expect($copy->team_size_max)->toBe($source->team_size_max);
    expect($copy->vs_size)->toBe($source->vs_size);
    expect($copy->rank_range_min)->toBe($source->rank_range_min);
    expect($copy->rank_range_max)->toBe($source->rank_range_max);
    expect($copy->is_badge)->toBeTrue();
    expect($copy->getAttribute('badge_status'))->toBeNull();
    expect($copy->getAttribute('badge_urls'))->toBeNull();
    expect($copy->is_bws)->toBeTrue();
    expect((float) $copy->bws_base_exponent)->toBe((float) $source->bws_base_exponent);
    expect((float) $copy->bws_badge_power)->toBe((float) $source->bws_badge_power);
    expect((float) $copy->bws_divisor)->toBe((float) $source->bws_divisor);
    expect((float) $copy->getAttribute('star_rating_first'))->toBe((float) $source->getAttribute('star_rating_first'));
    expect((float) $copy->getAttribute('star_rating_last'))->toBe((float) $source->getAttribute('star_rating_last'));
    expect((float) $copy->getAttribute('star_rating_qualifier'))->toBe((float) $source->getAttribute('star_rating_qualifier'));
    expect($copy->format)->toBe($source->format);
    expect($copy->banner_url)->toBe($source->banner_url);
    expect($copy->discord_url)->toBe($source->discord_url);
    expect($copy->twitch_url)->toBe($source->twitch_url);
    expect($copy->spreadsheet_url)->toBe($source->spreadsheet_url);
    expect($copy->bracket_url)->toBe($source->bracket_url);
    expect($copy->registration_url)->toBe($source->registration_url);
    expect($copy->tcomm_url)->toBe($source->tcomm_url);
    expect($copy->import_source)->toBe($source->import_source);
    expect($copy->field_sources)->toEqual([
        'title' => 'manual',
        'modes' => 'manual',
    ]);
    expect($copy->start_round_size)->toBe($source->start_round_size);
    expect($copy->restricted_countries)->toBe($source->restricted_countries);
    expect($copy->tcomm_id)->toBeNull();
    expect($copy->otr_id)->toBeNull();
    expect($copy->getAttribute('import_batch_id'))->toBeNull();
    expect($copy->getAttribute('otr_deleted_at'))->toBeNull();

    $copiedStaff = TournamentStaff::query()->where('tournament_id', $copy->id)->firstOrFail();
    expect($copiedStaff->user_id)->toBe($staffUser->id);
    expect($copiedStaff->role)->toBe('organizer');
    expect($copiedStaff->notes)->toBe('Lead organizer');
    expect($copiedStaff->status)->toBe('approved');
    expect($copiedStaff->reviewed_by)->toBe($reviewer->id);
    expect($copiedStaff->source)->toBe('manual');

    expect(TournamentWinner::query()->where('tournament_id', $copy->id)->count())->toBe(0);
    expect($response->json('url'))->toBe(route('admin.tournaments.show', $copy));

    expect(AdminAuditLog::query()
        ->where('action', 'tournament.copied')
        ->where('entity_id', $copy->id)
        ->exists())->toBeTrue();
});

test('copy requires an ended approved tournament', function (string $state) {
    $admin = User::factory()->admin()->create();

    $tournament = match ($state) {
        'pending' => Tournament::factory()->pending()->create(tournamentCopyEndedDates()),
        'rejected' => Tournament::factory()->rejected()->create(tournamentCopyEndedDates()),
        'active_approved' => Tournament::factory()->approved()->create(tournamentCopyActiveDates()),
        default => throw new InvalidArgumentException("Unhandled state [{$state}]"),
    };

    $response = $this->actingAs($admin)
        ->postJson(route('admin.tournaments.copy', $tournament), [
            'title' => 'Copied Cup',
        ]);

    $response->assertStatus(400);
    expect(Tournament::query()->where('title', 'Copied Cup')->exists())->toBeFalse();
})->with(['pending', 'rejected', 'active_approved']);

test('copy requires an admin user', function () {
    $tournament = Tournament::factory()->approved()->create(tournamentCopyEndedDates());
    $player = User::factory()->player()->create();

    $this->postJson(route('admin.tournaments.copy', $tournament), [
        'title' => 'Copied Cup',
    ])->assertUnauthorized();

    $this->actingAs($player)
        ->postJson(route('admin.tournaments.copy', $tournament), [
            'title' => 'Copied Cup',
        ])
        ->assertForbidden();
});

test('copy validates title', function () {
    $admin = User::factory()->admin()->create();
    $tournament = Tournament::factory()->approved()->create(tournamentCopyEndedDates());

    $response = $this->actingAs($admin)
        ->postJson(route('admin.tournaments.copy', $tournament), [
            'title' => '',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('title');
});

test('review page shows copy action only for ended approved tournaments', function () {
    $admin = User::factory()->admin()->create();
    $endedApproved = Tournament::factory()->approved()->create(tournamentCopyEndedDates());
    $activeApproved = Tournament::factory()->approved()->create(tournamentCopyActiveDates());
    $pendingEnded = Tournament::factory()->pending()->create(tournamentCopyEndedDates());

    $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $endedApproved))
        ->assertOk()
        ->assertSee('Copy Tournament');

    $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $activeApproved))
        ->assertOk()
        ->assertDontSee('Copy Tournament');

    $this->actingAs($admin)
        ->get(route('admin.tournaments.show', $pendingEnded))
        ->assertOk()
        ->assertDontSee('Copy Tournament');
});
