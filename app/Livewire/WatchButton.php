<?php

namespace App\Livewire;

use App\Models\Tournament;
use App\Models\TournamentWatch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WatchButton extends Component
{
    public int $tournamentId;

    public bool $showWatching = true;

    public ?string $watchType = null;

    public bool $loading = false;

    public bool $dropdownOpen = false;

    /**
     * Mount the component with initial state.
     */
    public function mount(int|Tournament $tournament, bool $showWatching = true): void
    {
        $this->tournamentId = is_int($tournament) ? $tournament : $tournament->id;
        $this->showWatching = $showWatching;

        // Load existing watch status
        if (Auth::check()) {
            $watch = TournamentWatch::where('user_id', Auth::id())
                ->where('tournament_id', $this->tournamentId)
                ->first();

            $this->watchType = $watch?->watch_type;
        }
    }

    /**
     * Watch the tournament with the specified type.
     */
    public function watch(string $type): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'));

            return;
        }

        $this->loading = true;

        try {
            TournamentWatch::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'tournament_id' => $this->tournamentId,
                ],
                [
                    'watch_type' => $type,
                ]
            );

            $this->watchType = $type;
            $this->dropdownOpen = false;
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Unwatch the tournament.
     */
    public function unwatch(): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->loading = true;

        try {
            TournamentWatch::where('user_id', Auth::id())
                ->where('tournament_id', $this->tournamentId)
                ->delete();

            $this->watchType = null;
            $this->dropdownOpen = false;
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Toggle the dropdown menu.
     */
    public function toggleDropdown(): void
    {
        $this->dropdownOpen = ! $this->dropdownOpen;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.watch-button');
    }
}
