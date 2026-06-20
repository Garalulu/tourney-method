<?php

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

uses()->group('commands');

beforeEach(function () {
    // Clear config
    Config::set('user-cleanup.whitelist', []);
    Config::set('user-cleanup.protection_rules.roles', ['admin', 'master']);
    Config::set('user-cleanup.protection_rules.oauth_setup_protected', true);
});

test('it protects admin users from deletion', function () {
    $admin = User::factory()->create([
        'username' => 'admin_user',
        'role' => 'admin',
        'main_mode_source' => 'auto_detected',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $admin->id,
        'deleted_at' => null,
    ]);
});

test('it protects master users from deletion', function () {
    $master = User::factory()->create([
        'username' => 'master_user',
        'role' => 'master',
        'main_mode_source' => 'auto_detected',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $master->id,
        'deleted_at' => null,
    ]);
});

test('it protects oauth users from deletion', function () {
    $oauthUser = User::factory()->create([
        'username' => 'oauth_user',
        'role' => 'player',
        'main_mode_source' => 'oauth_setup',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $oauthUser->id,
        'deleted_at' => null,
    ]);
});

test('it always protects oauth setup users from deletion even when config protection is disabled', function () {
    Config::set('user-cleanup.protection_rules.oauth_setup_protected', false);

    $oauthUser = User::factory()->create([
        'username' => 'configured_oauth_user',
        'role' => 'player',
        'main_mode' => 'mania',
        'main_mode_source' => 'oauth_setup',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $oauthUser->id,
        'deleted_at' => null,
    ]);
});

test('it protects whitelisted users from deletion', function () {
    Config::set('user-cleanup.whitelist', ['whitelisted_user']);

    $whitelisted = User::factory()->create([
        'username' => 'whitelisted_user',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $whitelisted->id,
        'deleted_at' => null,
    ]);
});

test('it deletes orphaned users with no tournament participation', function () {
    $orphan = User::factory()->create([
        'username' => 'orphan_user',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('1 deleted, 0 skipped');

    assertSoftDeleted('users', [
        'id' => $orphan->id,
    ]);
});

test('it protects users with tournament winner placements', function () {
    $winner = User::factory()->create([
        'username' => 'winner_user',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    $tournament = Tournament::factory()->create();
    DB::table('tournament_winners')->insert([
        'tournament_id' => $tournament->id,
        'user_id' => $winner->id,
        'placement' => 1,
        'username' => $winner->username,
        'osu_id' => $winner->osu_id,
        'gamemode' => 'mania',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $winner->id,
        'deleted_at' => null,
    ]);
});

test('it protects users with participation records even without podium placements', function () {
    $participant = User::factory()->create([
        'username' => 'manual_participant',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);
    $tournament = Tournament::factory()->create();
    TournamentParticipationRecord::query()->create([
        'user_id' => $participant->id,
        'tournament_id' => $tournament->id,
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $participant->id,
        'deleted_at' => null,
    ]);
});

test('it protects users with tournament staff roles', function () {
    $staff = User::factory()->create([
        'username' => 'staff_user',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    $tournament = Tournament::factory()->create();
    $tournament->staff()->attach($staff->id, [
        'role' => 'mappooler',
        'status' => 'approved',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $staff->id,
        'deleted_at' => null,
    ]);
});

test('it protects users who hosted tournaments', function () {
    $host = User::factory()->create([
        'username' => 'host_user',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    Tournament::factory()->create([
        'host_username' => $host->username,
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $host->id,
        'deleted_at' => null,
    ]);
});

test('dry run mode does not delete users', function () {
    $orphan = User::factory()->create([
        'username' => 'orphan_user',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('DRY RUN MODE')
        ->expectsOutputToContain('Dry run complete - no users were deleted');

    assertDatabaseHas('users', [
        'id' => $orphan->id,
        'deleted_at' => null,
    ]);
});

test('it asks for confirmation without force flag', function () {
    User::factory()->create([
        'username' => 'orphan_user',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    $this->artisan('cleanup:orphaned-users')
        ->assertSuccessful()
        ->expectsConfirmation('Delete these 1 orphaned users?', false);
});

test('it respects min age option', function () {
    $oldOrphan = User::factory()->create([
        'username' => 'old_orphan',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
        'created_at' => now()->subDays(40),
    ]);

    $recentOrphan = User::factory()->create([
        'username' => 'recent_orphan',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('cleanup:orphaned-users', [
        '--force' => true,
        '--min-age' => 30,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('1 deleted');

    assertSoftDeleted('users', ['id' => $oldOrphan->id]);
    assertDatabaseHas('users', [
        'id' => $recentOrphan->id,
        'deleted_at' => null,
    ]);
});

test('it handles multiple orphaned users in batches', function () {
    $orphans = User::factory()->count(5)->create([
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    $protected = User::factory()->create([
        'role' => 'admin',
        'main_mode_source' => 'auto_detected',
    ]);

    $this->artisan('cleanup:orphaned-users', [
        '--force' => true,
        '--batch-size' => 2,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('5 deleted, 0 skipped');

    foreach ($orphans as $orphan) {
        assertSoftDeleted('users', ['id' => $orphan->id]);
    }

    assertDatabaseHas('users', [
        'id' => $protected->id,
        'deleted_at' => null,
    ]);
});

test('it shows no users found when none match criteria', function () {
    User::factory()->create([
        'role' => 'admin',
        'main_mode_source' => 'oauth_setup',
    ]);

    $this->artisan('cleanup:orphaned-users', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');
});

test('it handles custom whitelist file', function () {
    $whitelistFile = storage_path('test_whitelist.json');
    file_put_contents($whitelistFile, json_encode(['custom_whitelisted']));

    $whitelisted = User::factory()->create([
        'username' => 'custom_whitelisted',
        'role' => 'player',
        'main_mode_source' => 'auto_detected',
    ]);

    $this->artisan('cleanup:orphaned-users', [
        '--force' => true,
        '--whitelist' => $whitelistFile,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned users found');

    assertDatabaseHas('users', [
        'id' => $whitelisted->id,
        'deleted_at' => null,
    ]);

    // Cleanup
    unlink($whitelistFile);
});
