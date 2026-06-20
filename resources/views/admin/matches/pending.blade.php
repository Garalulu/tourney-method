@extends('layouts.admin')

@section('title', 'Match Review Queue')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Match Review Queue</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Review and approve user-submitted multiplayer matches
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    {{-- Stats Badge --}}
                    <div class="px-4 py-2 rounded-lg bg-gradient-to-r from-pink-500 to-purple-600 text-white font-semibold shadow-lg shadow-pink-500/30">
                        <span class="text-2xl font-bold">{{ $matches->total() }}</span>
                        <span class="text-sm opacity-90 ml-1">pending</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        @if ($matches->isEmpty())
            {{-- Empty State --}}
            <div class="rounded-2xl bg-white border-2 border-dashed border-gray-300 p-12">
                <div class="text-center">
                    <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center mb-4">
                        <x-icon name="lucide-check" class="w-10 h-10 text-gray-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">All caught up!</h3>
                    <p class="text-gray-500 max-w-sm mx-auto">
                        There are no pending matches to review at the moment.
                    </p>
                </div>
            </div>
        @else
            {{-- Matches Table --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Match
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Submitter
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Submitted
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Tournament
                                </th>
                                <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($matches as $match)
                                <tr class="hover:bg-gray-50 transition-colors duration-150 group">
                                    {{-- Match Name & ID --}}
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center shadow-md">
                                                <x-icon name="lucide-play-circle" class="w-5 h-5 text-white" />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="font-semibold text-gray-900 truncate group-hover:text-pink-600 transition-colors">
                                                    {{ $match->name }}
                                                </div>
                                                <div class="text-xs text-gray-500 font-mono mt-0.5">
                                                    #{{ $match->osu_match_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Submitter --}}
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <img
                                                src="{{ $match->submitter->avatar_url }}"
                                                alt="{{ $match->submitter->username }}"
                                                class="w-8 h-8 rounded-lg ring-2 ring-gray-100"
                                            >
                                            <span class="font-medium text-gray-900">
                                                {{ $match->submitter->username }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Submitted Date --}}
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <div class="flex items-center gap-1.5">
                                            <x-icon name="lucide-clock" class="w-4 h-4 text-gray-400" />
                                            <span>{{ $match->created_at->diffForHumans() }}</span>
                                        </div>
                                    </td>

                                    {{-- Tournament --}}
                                    <td class="px-6 py-4">
                                        @if ($match->tournament)
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-purple-50 text-purple-700 text-xs font-tournament font-semibold">
                                                <x-icon name="lucide-calendar" class="w-3.5 h-3.5" />
                                                {{ Str::limit($match->tournament->title, 25) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 italic">Not linked</span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a
                                                href="{{ route('admin.matches.show', $match->id) }}"
                                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 text-gray-700 font-medium text-sm hover:bg-gray-200 transition-colors duration-150"
                                            >
                                                <x-icon name="lucide-eye" class="w-4 h-4" />
                                                Review
                                            </a>

                                            <form action="{{ route('admin.matches.approve', $match->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-500 text-white font-medium text-sm hover:bg-emerald-600 transition-colors duration-150 shadow-sm"
                                                    onclick="return confirm('Approve this match?')"
                                                >
                                                    <x-icon name="lucide-check" class="w-4 h-4" />
                                                    Quick Approve
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            @if ($matches->hasPages())
                <div class="mt-6">
                    {{ $matches->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
