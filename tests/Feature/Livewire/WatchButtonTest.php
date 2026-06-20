<?php

declare(strict_types=1);

use App\Livewire\WatchButton;
use App\Models\Tournament;
use App\Models\TournamentWatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'main_mode' => 'osu',
    ]);

    $this->user->rankHistory()->create([
        'mode' => 'osu',
        'rank' => 5000,
        'pp' => 6000,
        'recorded_at' => now(),
    ]);

    $this->tournament = Tournament::factory()->create([
        'status' => 'approved',
        'modes' => ['osu'],
    ]);
});

describe('WatchButton Livewire Component', function () {
    it('renders successfully', function () {
        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->assertStatus(200);
    });

    it('loads null watch type when user is not watching', function () {
        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->assertSet('watchType', null);
    });

    it('loads existing watch type when user is watching', function () {
        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->assertSet('watchType', 'watching');
    });

    it('can watch a tournament with watching type', function () {
        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->call('watch', 'watching')
            ->assertSet('watchType', 'watching');

        $this->assertDatabaseHas('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);
    });

    it('can watch a tournament with stream type', function () {
        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->call('watch', 'stream')
            ->assertSet('watchType', 'stream');

        $this->assertDatabaseHas('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'stream',
        ]);
    });

    it('updates watch type when changing from watching to stream', function () {
        // Create initial watch
        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->assertSet('watchType', 'watching')
            ->call('watch', 'stream')
            ->assertSet('watchType', 'stream');

        // Should only have one record
        expect(TournamentWatch::where('user_id', $this->user->id)
            ->where('tournament_id', $this->tournament->id)
            ->count()
        )->toBe(1);

        $this->assertDatabaseHas('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'stream',
        ]);
    });

    it('can unwatch a tournament', function () {
        // Create initial watch
        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->assertSet('watchType', 'watching')
            ->call('unwatch')
            ->assertSet('watchType', null);

        $this->assertDatabaseMissing('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
        ]);
    });

    it('sets loading state during watch operations', function () {
        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->call('watch', 'watching')
            ->assertSet('loading', false); // Loading should be false after completion
    });

    it('sets loading state during unwatch operations', function () {
        TournamentWatch::create([
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
            'watch_type' => 'watching',
        ]);

        Livewire::actingAs($this->user)
            ->test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->call('unwatch')
            ->assertSet('loading', false); // Loading should be false after completion
    });

    it('redirects to login when unauthenticated user tries to watch', function () {
        Livewire::test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->call('watch', 'watching')
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('tournament_watches', [
            'user_id' => $this->user->id,
            'tournament_id' => $this->tournament->id,
        ]);
    });

    it('does nothing when unauthenticated user tries to unwatch', function () {
        Livewire::test(WatchButton::class, ['tournament' => $this->tournament->id])
            ->call('unwatch')
            ->assertSet('watchType', null)
            ->assertNoRedirect();
    });
});
