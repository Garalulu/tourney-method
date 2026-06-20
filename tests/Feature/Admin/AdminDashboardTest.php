<?php

use App\Models\AdminAuditLog;
use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use App\Models\ParticipationDeletionRequest;
use App\Models\ParticipationRecordReport;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('admin dashboard renders the four recent activity feeds', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewIs('admin.dashboard')
        ->assertSee('Recently Parsed Tournaments')
        ->assertSee('Recent Audit Activity')
        ->assertSee('Recent Participation Moderation')
        ->assertSee('Recent Corrections')
        ->assertSee(route('admin.tournaments.create'), false)
        ->assertSee(route('admin.imports.index'), false)
        ->assertDontSee('Maintenance Health')
        ->assertDontSee('Quick Actions');
});

test('master sees users navigation but admin does not', function () {
    $admin = User::factory()->admin()->create();
    $master = User::factory()->master()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.users.index'), false);

    $this->actingAs($master)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.users.index'), false)
        ->assertSee('Users');
});

test('dashboard shows recently parsed tournaments and readable audit activity', function () {
    $admin = User::factory()->admin()->create();
    $run = AdminMaintenanceRun::factory()->create([
        'command' => AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE,
        'label' => 'tournaments:parse',
    ]);
    $tournament = Tournament::factory()->create(['title' => 'Fresh Parsed Cup']);

    AdminMaintenanceRunItem::factory()->for($run, 'run')->create([
        'item_type' => AdminMaintenanceRunItem::TYPE_TOURNAMENT,
        'action' => AdminMaintenanceRunItem::ACTION_CREATED,
        'tournament_id' => $tournament->id,
        'tournament_title' => $tournament->title,
    ]);
    AdminAuditLog::factory()->for($admin, 'admin')->create([
        'action' => 'tournament.updated',
        'entity_type' => Tournament::class,
        'entity_id' => $tournament->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Fresh Parsed Cup')
        ->assertSee('Tournament Updated')
        ->assertSee('Fresh Parsed Cup #'.$tournament->id);
});

test('dashboard only combines pending reports podium additions and removal requests', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withSetup()->create(['username' => 'ModeratedPlayer']);
    $reporter = User::factory()->withSetup()->create(['username' => 'CarefulReporter']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Moderation Cup']);
    $record = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $tournament->id,
    ]);
    $podiumTournament = Tournament::factory()->approved()->create(['title' => 'Podium Review Cup']);
    $podiumAdd = TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => $podiumTournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_PENDING,
        'placement' => 2,
        'placement_min' => 2,
    ]);

    ParticipationRecordReport::query()->create([
        'tournament_participation_record_id' => $record->id,
        'reported_by' => $reporter->id,
        'category' => ParticipationRecordReport::CATEGORY_RECORD_CORRECTION,
        'status' => ParticipationRecordReport::STATUS_PENDING,
    ]);
    ParticipationRecordReport::query()->create([
        'tournament_participation_record_id' => $record->id,
        'reported_by' => $reporter->id,
        'category' => ParticipationRecordReport::CATEGORY_REPORT_SPAM,
        'status' => ParticipationRecordReport::STATUS_RESOLVED,
    ]);
    ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $record->id,
        'requested_by' => $user->id,
        'status' => ParticipationDeletionRequest::STATUS_PENDING,
    ]);
    ParticipationDeletionRequest::query()->create([
        'tournament_participation_record_id' => $record->id,
        'requested_by' => $user->id,
        'status' => ParticipationDeletionRequest::STATUS_APPROVED,
    ]);
    TournamentParticipationRecord::query()->create([
        'user_id' => $user->id,
        'tournament_id' => Tournament::factory()->approved()->create(['title' => 'Reviewed Podium Cup'])->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
        'placement' => 1,
        'placement_min' => 1,
    ]);
    $podiumAdd->touch();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('recentParticipationModeration', fn ($entries) => $entries->count() === 3
            && $entries->pluck('type')->sort()->values()->all() === ['Podium add', 'Removal request', 'Report']
            && $entries->pluck('status')->every(fn (string $status): bool => $status === 'pending'))
        ->assertSee('ModeratedPlayer')
        ->assertSee('Moderation Cup')
        ->assertSee('Pending')
        ->assertDontSee('Reviewed Podium Cup')
        ->assertDontSee('Resolved')
        ->assertDontSee('Approved')
        ->assertSee(route('admin.participation-moderation.index'), false);
});

test('dashboard only shows pending corrections and new tournament requests', function () {
    $admin = User::factory()->admin()->create();
    $submitter = User::factory()->withSetup()->create(['username' => 'CorrectionAuthor']);
    $tournament = Tournament::factory()->approved()->create(['title' => 'Correction Cup']);

    TournamentCorrection::factory()->create([
        'tournament_id' => $tournament->id,
        'submitted_by' => $submitter->id,
        'status' => TournamentCorrection::STATUS_PENDING,
    ]);
    TournamentCorrection::factory()->create([
        'tournament_id' => null,
        'submitted_by' => $submitter->id,
        'status' => TournamentCorrection::STATUS_PENDING,
        'kind' => TournamentCorrection::KIND_NEW_TOURNAMENT,
    ]);

    foreach ([
        TournamentCorrection::STATUS_PROCESSING,
        TournamentCorrection::STATUS_APPROVED,
        TournamentCorrection::STATUS_PARTIALLY_APPROVED,
        TournamentCorrection::STATUS_REJECTED,
    ] as $status) {
        TournamentCorrection::factory()->create([
            'tournament_id' => Tournament::factory()->approved()->create([
                'title' => str($status)->headline()->append(' Correction Cup')->toString(),
            ])->id,
            'submitted_by' => $submitter->id,
            'status' => $status,
        ]);
    }

    $response = $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('recentCorrections', fn ($corrections) => $corrections->count() === 2
            && $corrections->pluck('status')->every(
                fn (string $status): bool => $status === TournamentCorrection::STATUS_PENDING
            )
            && $corrections->pluck('kind')->sort()->values()->all() === [
                TournamentCorrection::KIND_CORRECTION,
                TournamentCorrection::KIND_NEW_TOURNAMENT,
            ])
        ->assertSee('Correction Cup')
        ->assertSee('New tournament request')
        ->assertSee('CorrectionAuthor')
        ->assertSee(route('admin.tournament-corrections.index'), false);

    foreach (['Processing', 'Approved', 'Partially Approved', 'Rejected'] as $status) {
        $response->assertDontSee("{$status} Correction Cup");
    }
});

test('pending staff review routes and navigation are removed', function () {
    $admin = User::factory()->admin()->create();

    expect(Route::has('admin.staff.pending'))->toBeFalse()
        ->and(Route::has('admin.staff.approve'))->toBeFalse()
        ->and(Route::has('admin.staff.reject'))->toBeFalse()
        ->and(Route::has('admin.staff.bulk-approve'))->toBeFalse()
        ->and(Route::has('admin.staff.bulk-reject'))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Pending Staff');
});

test('admin layout keeps streamlined navigation and osu avatar', function () {
    $admin = User::factory()->admin()->create([
        'osu_id' => 24680,
        'username' => 'QueueAdmin',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.imports.index'))
        ->assertOk()
        ->assertSee('Tournament Queue')
        ->assertSee('https://a.ppy.sh/24680', false)
        ->assertSee('Player Dashboard')
        ->assertDontSee('Pending Staff');
});
