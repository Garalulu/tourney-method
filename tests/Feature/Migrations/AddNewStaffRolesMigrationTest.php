<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Start a clean transaction for each test
    DB::beginTransaction();
});

afterEach(function () {
    // Rollback changes after each test
    DB::rollBack();
});

test('new staff roles migration adds 4 additional roles to enum constraint', function () {
    // Create required foreign key records
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    // Run the migration
    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_02_03_150000_add_new_staff_roles_to_tournament_staff_table.php',
    ])->assertExitCode(0);

    // Verify the constraint exists by attempting to insert all 10 role types
    $validRoles = [
        'organizer',
        'mapper',
        'mappooler',      // NEW
        'referee',
        'streamer',
        'commentator',
        'playtester',     // NEW
        'gfx',            // NEW
        'sheeter',        // NEW
        'other',
    ];

    foreach ($validRoles as $role) {
        DB::table('tournament_staff')->insert([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify the insert succeeded
        $this->assertDatabaseHas('tournament_staff', [
            'role' => $role,
        ]);
    }
});

test('migration rejects invalid role values', function () {
    // Create required foreign key records
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    // Run the migration
    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_02_03_150000_add_new_staff_roles_to_tournament_staff_table.php',
    ])->assertExitCode(0);

    // Attempt to insert an invalid role (should fail database constraint)
    $exception = null;

    try {
        DB::table('tournament_staff')->insert([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'role' => 'invalid_role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException $e) {
        $exception = $e;
    }

    $this->assertNotNull($exception);
    $this->assertStringContainsString('tournament_staff_role_check', $exception->getMessage());
});

test('migration can be rolled back', function () {
    // Create required foreign key records
    $tournament = Tournament::factory()->create();
    $user = User::factory()->create();

    // Run the migration
    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_02_03_150000_add_new_staff_roles_to_tournament_staff_table.php',
    ])->assertExitCode(0);

    // Verify new roles work
    DB::table('tournament_staff')->insert([
        'tournament_id' => $tournament->id,
        'user_id' => $user->id,
        'role' => 'mappooler',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->assertDatabaseHas('tournament_staff', [
        'role' => 'mappooler',
    ]);

    // Rollback the migration
    $this->artisan('migrate:rollback', [
        '--step' => 1,
    ])->assertExitCode(0);

    // Clean up test data before re-running migration
    DB::table('tournament_staff')->where('tournament_id', $tournament->id)->delete();

    // Re-run migration to restore 10-role constraint for subsequent tests
    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_02_03_150000_add_new_staff_roles_to_tournament_staff_table.php',
    ])->assertExitCode(0);
});
