<?php

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('shared public pages use compact social metadata without duplicating the site name', function (
    string $routeName,
    string $title,
    string $description
) {
    $url = route($routeName);

    $this->get($url)
        ->assertOk()
        ->assertSee("<title>Tourney Method - {$title}</title>", false)
        ->assertSee("<meta name=\"description\" content=\"{$description}\">", false)
        ->assertSee('<meta property="og:site_name" content="Tourney Method">', false)
        ->assertSee("<meta property=\"og:title\" content=\"{$title}\">", false)
        ->assertSee("<meta property=\"og:description\" content=\"{$description}\">", false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee("<meta property=\"og:url\" content=\"{$url}\">", false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee("<meta name=\"twitter:title\" content=\"{$title}\">", false)
        ->assertSee("<meta name=\"twitter:description\" content=\"{$description}\">", false)
        ->assertSee("<link rel=\"canonical\" href=\"{$url}\">", false)
        ->assertDontSee("content=\"Tourney Method - {$title}\"", false)
        ->assertDontSee('Tourney Method - Tourney Method', false);
})->with([
    'homepage' => [
        'home',
        'Welcome',
        'Discover and manage osu! tournaments with advanced BWS calculations and streamlined organization.',
    ],
    'tournament index' => [
        'tournaments.index',
        'Tournaments',
        'Discover and manage osu! tournaments with advanced BWS calculations and streamlined organization.',
    ],
    'contribution page' => [
        'contribute',
        'Contribute',
        'Help improve Tourney Method through translations, tournament corrections, participation history, and support.',
    ],
]);

test('homepage renders updated workflow copy and changelog keys', function () {
    $changelogVersion = config('changelog.version');

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Discover osu! tournaments, track participation, and improve tournament data')
        ->assertSee('How to use Tourney Method')
        ->assertSee('personal participation records')
        ->assertSee('Correct Tournament')
        ->assertSee('eligible registration opens or closes within 24 hours')
        ->assertSee(config('changelog.title'))
        ->assertSee(config('changelog.items')[0])
        ->assertSee("tourney-method-changelog-seen:{$changelogVersion}")
        ->assertSee("tourney-method-changelog-last-shown:{$changelogVersion}");
});

test('homepage has browse tournaments link for guests and authenticated users', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('tournaments.index'));

    $user = User::factory()->withSetup()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('tournaments.index'));
});

test('homepage active player count includes recent approved participation and podium users only once', function () {
    $participatingUser = User::factory()->withSetup()->create();
    $podiumUser = User::factory()->withSetup()->create();
    $duplicateUser = User::factory()->withSetup()->create();
    $oldUser = User::factory()->withSetup()->create();
    $pendingUser = User::factory()->withSetup()->create();

    $recentTournament = Tournament::factory()->approved()->create(['tournament_end' => now()->subMonths(2)]);
    $oldTournament = Tournament::factory()->approved()->create(['tournament_end' => now()->subMonths(13)]);
    $pendingTournament = Tournament::factory()->pending()->create(['tournament_end' => now()->subMonths(2)]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $participatingUser->id,
        'tournament_id' => $recentTournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $duplicateUser->id,
        'tournament_id' => $recentTournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    TournamentWinner::query()->create([
        'user_id' => $podiumUser->id,
        'tournament_id' => $recentTournament->id,
        'placement' => 1,
    ]);

    TournamentWinner::query()->create([
        'user_id' => $duplicateUser->id,
        'tournament_id' => $recentTournament->id,
        'placement' => 2,
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $oldUser->id,
        'tournament_id' => $oldTournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    TournamentParticipationRecord::query()->create([
        'user_id' => $pendingUser->id,
        'tournament_id' => $pendingTournament->id,
        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['3', __('home.stats.active_players')]);
});

test('homepage active staff count includes approved recent staff only', function () {
    $activeStaff = User::factory()->withSetup()->create();
    $oldStaff = User::factory()->withSetup()->create();
    $pendingStaff = User::factory()->withSetup()->create();
    $unapprovedStaff = User::factory()->withSetup()->create();

    $recentTournament = Tournament::factory()->approved()->create(['tournament_end' => now()->subMonths(2)]);
    $oldTournament = Tournament::factory()->approved()->create(['tournament_end' => now()->subMonths(13)]);
    $pendingTournament = Tournament::factory()->pending()->create(['tournament_end' => now()->subMonths(2)]);

    TournamentStaff::factory()->create([
        'user_id' => $activeStaff->id,
        'tournament_id' => $recentTournament->id,
        'status' => 'approved',
    ]);
    TournamentStaff::factory()->create([
        'user_id' => $oldStaff->id,
        'tournament_id' => $oldTournament->id,
        'status' => 'approved',
    ]);
    TournamentStaff::factory()->create([
        'user_id' => $pendingStaff->id,
        'tournament_id' => $pendingTournament->id,
        'status' => 'approved',
    ]);
    TournamentStaff::factory()->create([
        'user_id' => $unapprovedStaff->id,
        'tournament_id' => $recentTournament->id,
        'status' => 'pending',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['1', __('home.stats.active_staff')]);
});

test('footer links to contribution page and contribution page explains translation and correction', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder([__('common.footer.donate'), __('common.footer.contribute')])
        ->assertSee(route('contribute'));

    $this->get(route('contribute'))
        ->assertOk()
        ->assertSee('https://crowdin.com/project/tourney-method')
        ->assertSee('Correct Tournament')
        ->assertSee('Open Crowdin');
});
