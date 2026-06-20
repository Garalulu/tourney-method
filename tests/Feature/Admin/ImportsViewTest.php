<?php

use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use App\Models\Tournament;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admin can view maintenance runs with item counts', function () {
    $run = AdminMaintenanceRun::factory()->create([
        'command' => AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE,
        'label' => 'tournaments:parse',
        'summary' => [
            AdminMaintenanceRunItem::ACTION_CREATED => 1,
            AdminMaintenanceRunItem::ACTION_UPDATED => 1,
            'queued' => 2,
        ],
    ]);

    AdminMaintenanceRunItem::factory()->for($run, 'run')->create([
        'action' => AdminMaintenanceRunItem::ACTION_CREATED,
        'tournament_id' => Tournament::factory()->create(['title' => 'Fresh Cup'])->id,
        'tournament_title' => 'Fresh Cup',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.imports.index'));

    $response->assertOk()
        ->assertSee('Maintenance Runs')
        ->assertSee('tournaments:parse')
        ->assertSee('+1 created')
        ->assertSee('1 updated')
        ->assertSee('Fresh Cup');
});

test('imports page renders mobile cards and desktop table', function () {
    AdminMaintenanceRun::factory()->create([
        'command' => AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT,
        'label' => 'sync:tournaments',
        'summary' => [AdminMaintenanceRunItem::ACTION_SYNCED => 3],
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.imports.index'));

    $response->assertOk()
        ->assertSee('lg:hidden', false)
        ->assertSee('lg:block', false)
        ->assertSee('sync:tournaments');
});

test('imports page no longer shows deprecated historical import controls', function () {
    AdminMaintenanceRun::factory()->create();

    $response = $this->actingAs($this->admin)
        ->get(route('admin.imports.index'));

    $response->assertOk()
        ->assertDontSee('Historical data ingestion')
        ->assertDontSee('tcomm API')
        ->assertDontSee('o!TR API')
        ->assertDontSee('import:historical')
        ->assertDontSee('Resume');
});

test('imports page hides user triggered profile sync runs', function () {
    AdminMaintenanceRun::factory()->create([
        'command' => AdminMaintenanceRun::COMMAND_SYNC_USER_PROFILES,
        'label' => 'sync:user-profiles',
        'source' => 'web',
        'options' => ['selected_user_ids' => [123]],
        'summary' => ['synced' => 1],
    ]);

    AdminMaintenanceRun::factory()->create([
        'command' => AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE,
        'label' => 'tournaments:parse',
        'source' => 'cli',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.imports.index'));

    $response->assertOk()
        ->assertSee('tournaments:parse')
        ->assertDontSee('selected_user_ids')
        ->assertDontSee('1 synced');
});

test('non admin cannot view imports page', function () {
    $player = User::factory()->player()->create();

    $this->actingAs($player)
        ->get(route('admin.imports.index'))
        ->assertForbidden();
});
