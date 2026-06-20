@extends('layouts.admin')

@section('title', 'Review Match: ' . $match->name)

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        {{-- Back Button --}}
        <div class="mb-6">
            <a
                href="{{ route('admin.matches.pending') }}"
                class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 font-medium transition-colors"
            >
                <x-icon name="lucide-arrow-left" class="w-5 h-5" />
                Back to Queue
            </a>
        </div>

        {{-- Match Header --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mb-6">
            <div class="flex items-start justify-between mb-6">
                <div class="flex-1">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center shadow-lg shadow-pink-500/30">
                            <x-icon name="lucide-play-circle" class="w-7 h-7 text-white" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 mb-1">{{ $match->name }}</h1>
                            <p class="text-sm text-gray-500 font-mono">Match ID: #{{ $match->osu_match_id }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                        {{-- Submitter --}}
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-gray-50">
                            <img
                                src="{{ $match->submitter->avatar_url }}"
                                alt="{{ $match->submitter->username }}"
                                class="w-12 h-12 rounded-lg ring-2 ring-white shadow-md"
                            >
                            <div>
                                <div class="text-xs text-gray-500 uppercase font-semibold tracking-wide">{{ __('admin.matches.submitted_by') }}</div>
                                <span class="font-semibold text-gray-900">
                                    {{ $match->submitter->username }}
                                </span>
                            </div>
                        </div>

                        {{-- Date Range --}}
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-gray-50">
                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-md">
                                <x-icon name="lucide-calendar" class="w-6 h-6 text-white" />
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase font-semibold tracking-wide">Match Date</div>
                                <div class="font-semibold text-gray-900">
                                    {{ $match->start_time ? $match->start_time->format('M d, Y') : 'Unknown' }}
                                </div>
                            </div>
                        </div>

                        {{-- Status --}}
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-gray-50">
                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center shadow-md">
                                <x-icon name="lucide-clock" class="w-6 h-6 text-white" />
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase font-semibold tracking-wide">Status</div>
                                <span class="inline-flex items-center gap-2 font-bold text-yellow-600">
                                    <span class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></span>
                                    Pending Review
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tournament Association --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mb-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                    <x-icon name="lucide-calendar" class="w-5 h-5 text-purple-600" />
                </div>
                <h2 class="text-xl font-bold text-gray-900">Tournament Association</h2>
                @if ($match->tournament)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                        <x-icon name="lucide-zap" class="w-3.5 h-3.5" />
                        Auto-detected
                    </span>
                @endif
            </div>

            <form action="{{ route('admin.matches.update', $match->id) }}" method="POST" class="flex items-end gap-4">
                @csrf
                @method('PATCH')

                <div class="flex-1">
                    <label for="tournament_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        Select Tournament
                    </label>
                    <select
                        id="tournament_id"
                        name="tournament_id"
                        class="w-full px-4 py-3 text-base rounded-xl border-2 border-gray-200 bg-white focus:border-purple-400 focus:ring-4 focus:ring-purple-500/10 transition-all duration-300"
                    >
                        <option value="">No tournament</option>
                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}" {{ $match->tournament_id == $tournament->id ? 'selected' : '' }}>
                                {{ $tournament->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button
                    type="submit"
                    class="px-6 py-3 rounded-xl bg-purple-600 text-white font-semibold hover:bg-purple-700 transition-colors shadow-lg shadow-purple-500/30"
                >
                    <x-icon name="lucide-check" class="w-5 h-5 inline mr-2" />
                    Save
                </button>
            </form>
        </div>

        {{-- Games Section --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mb-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-lg bg-pink-100 flex items-center justify-center">
                    <x-icon name="lucide-clipboard-list" class="w-5 h-5 text-pink-600" />
                </div>
                <h2 class="text-xl font-bold text-gray-900">Match Games <span class="text-gray-400 font-normal">({{ $match->games->count() }})</span></h2>
            </div>

            <div class="space-y-3">
                @foreach ($match->games as $index => $game)
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button
                            type="button"
                            onclick="toggleGame({{ $index }})"
                            class="w-full px-6 py-4 flex items-center justify-between bg-gray-50 hover:bg-gray-100 transition-colors"
                        >
                            <div class="flex items-center gap-4">
                                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-pink-500 to-purple-600 text-white font-bold text-sm shadow-md">
                                    {{ $index + 1 }}
                                </span>
                                <div class="text-left">
                                    <div class="font-semibold text-gray-900">
                                        {{ $game->beatmap_title ?? 'Unknown Beatmap' }}
                                    </div>
                                    <div class="text-sm text-gray-500 flex items-center gap-3 mt-1">
                                        <span>{{ $game->beatmap_version }}</span>
                                        @if ($game->mods && count($game->mods) > 0)
                                            <span class="flex items-center gap-1">
                                                <x-icon name="lucide-zap" class="w-3.5 h-3.5" />
                                                {{ implode(', ', $game->mods) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <x-icon name="lucide-chevron-down" class="w-5 h-5 text-gray-400 transition-transform game-chevron-{{ $index }}" />
                        </button>

                        <div id="game-{{ $index }}" class="hidden">
                            <div class="p-6 bg-white">
                                {{-- Game Metadata --}}
                                <div class="grid grid-cols-3 gap-4 mb-6 p-4 rounded-lg bg-gray-50">
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase font-semibold mb-1">Mode</div>
                                        <div class="font-semibold text-gray-900">{{ ucfirst($game->mode) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase font-semibold mb-1">Scoring</div>
                                        <div class="font-semibold text-gray-900">{{ ucfirst($game->scoring_type ?? 'Score') }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase font-semibold mb-1">Team Type</div>
                                        <div class="font-semibold text-gray-900">{{ ucfirst(str_replace('-', ' ', $game->team_type ?? 'head-to-head')) }}</div>
                                    </div>
                                </div>

                                {{-- Scores Table --}}
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Player</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Team</th>
                                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Score</th>
                                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Accuracy</th>
                                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Combo</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($game->scores->sortByDesc('score') as $score)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-2">
                                                            @if ($score->user)
                                                                <img src="{{ $score->user->avatar_url }}" class="w-6 h-6 rounded" alt="{{ $score->username }}">
                                                            @endif
                                                            <span class="font-medium text-gray-900">{{ $score->username }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        @if ($score->team)
                                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold {{ $score->team === 'blue' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                                                                {{ ucfirst($score->team) }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400 text-xs">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-gray-900">
                                                        {{ number_format($score->score) }}
                                                    </td>
                                                    <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">
                                                        {{ $score->accuracy ? number_format($score->accuracy * 100, 2) . '%' : '—' }}
                                                    </td>
                                                    <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">
                                                        {{ $score->max_combo ?? '—' }}x
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        @if ($score->passed)
                                                            <span class="inline-flex items-center gap-1 text-emerald-600">
                                                                <x-icon name="lucide-circle-check" class="w-4 h-4" />
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 text-red-600">
                                                                <x-icon name="lucide-circle-x" class="w-4 h-4" />
                                                            </span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">Review Actions</h3>
                    <p class="text-sm text-gray-500">Approve or reject this match submission</p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        onclick="openRejectModal()"
                        class="px-6 py-3 rounded-xl bg-red-500 text-white font-semibold hover:bg-red-600 transition-all duration-300 shadow-lg shadow-red-500/30 hover:shadow-xl hover:shadow-red-500/40"
                    >
                        <x-icon name="lucide-x" class="w-5 h-5 inline mr-2" />
                        Reject Match
                    </button>

                    <form action="{{ route('admin.matches.approve', $match->id) }}" method="POST" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold hover:from-emerald-600 hover:to-teal-600 transition-all duration-300 shadow-xl shadow-emerald-500/30 hover:shadow-2xl hover:shadow-emerald-500/40"
                            onclick="return confirm('Approve this match? It will be visible on the submitter\'s profile.')"
                        >
                            <x-icon name="lucide-check" class="w-5 h-5 inline mr-2" />
                            Approve Match
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Rejection Modal --}}
<div id="rejectModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        {{-- Backdrop --}}
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75 backdrop-blur-sm" onclick="closeRejectModal()"></div>

        {{-- Modal --}}
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="{{ route('admin.matches.reject', $match->id) }}" method="POST">
                @csrf

                <div class="bg-white px-6 pt-6 pb-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                            <x-icon name="lucide-triangle-alert" class="w-6 h-6 text-red-600" />
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Reject Match</h3>
                            <p class="text-sm text-gray-500 mb-4">
                                Please provide a reason for rejecting this match. The submitter will see this message.
                            </p>

                            <div class="mt-4">
                                <label for="reason" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Rejection Reason
                                </label>
                                <textarea
                                    id="reason"
                                    name="reason"
                                    rows="4"
                                    required
                                    class="w-full px-4 py-3 text-base rounded-xl border-2 border-gray-200 focus:border-red-400 focus:ring-4 focus:ring-red-500/10 transition-all duration-300 resize-none"
                                    placeholder="e.g., Match does not belong to a tournament, Invalid match data, etc."
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onclick="closeRejectModal()"
                        class="px-5 py-2.5 rounded-lg bg-white border-2 border-gray-200 text-gray-700 font-semibold hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2.5 rounded-lg bg-red-600 text-white font-semibold hover:bg-red-700 transition-colors shadow-lg shadow-red-500/30"
                    >
                        Reject Match
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleGame(index) {
        const content = document.getElementById(`game-${index}`);
        const chevron = document.querySelector(`.game-chevron-${index}`);

        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        } else {
            content.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }

    function openRejectModal() {
        document.getElementById('rejectModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
        document.getElementById('reason').value = '';
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeRejectModal();
        }
    });
</script>
@endsection
