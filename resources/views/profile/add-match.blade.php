@extends('layouts.app')

@section('title', 'Add Match')

@section('content')
<div class="min-h-screen relative overflow-hidden bg-gradient-to-br from-pink-50 via-purple-50 to-indigo-50">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 -right-10 w-64 h-64 bg-pink-300/20 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-40 -left-10 w-80 h-80 bg-purple-300/20 rounded-full blur-3xl animate-floatReverse"></div>
        <div class="absolute top-1/2 left-1/2 w-96 h-96 bg-indigo-300/10 rounded-full blur-3xl animate-pulse"></div>

        {{-- Geometric Pattern Overlay --}}
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: repeating-linear-gradient(45deg, #ff66aa 0, #ff66aa 1px, transparent 0, transparent 50%); background-size: 20px 20px;"></div>
    </div>

    {{-- Main Content --}}
    <div class="relative z-10 mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        {{-- Header Section --}}
        <div class="mb-12 text-center animate-fadeInDown">
            <div class="inline-flex items-center justify-center gap-3 mb-6">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-500 to-purple-600 shadow-lg shadow-pink-500/30 flex items-center justify-center transform rotate-12 hover:rotate-0 transition-transform duration-300">
                    <x-icon name="lucide-zap" class="w-7 h-7 text-white" />
                </div>
            </div>

            <h1 class="text-4xl sm:text-5xl font-black mb-4 bg-gradient-to-r from-pink-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent leading-tight tracking-tight">
                Add Match to Your History
            </h1>

            <p class="text-lg text-gray-600 max-w-2xl mx-auto leading-relaxed">
                Import your tournament matches by pasting the multiplayer link from osu!
            </p>

            {{-- Decorative dots --}}
            <div class="flex items-center justify-center gap-2 mt-6">
                <div class="w-2 h-2 rounded-full bg-pink-400 animate-pulse"></div>
                <div class="w-2 h-2 rounded-full bg-purple-400 animate-pulse" style="animation-delay: 0.2s;"></div>
                <div class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse" style="animation-delay: 0.4s;"></div>
            </div>
        </div>

        {{-- Submission Form Card --}}
        <div class="mb-12 animate-fadeInUp" style="animation-delay: 0.1s;">
            <div class="relative rounded-3xl bg-white/80 backdrop-blur-xl shadow-2xl shadow-pink-500/10 border border-white/50 p-8 sm:p-10 overflow-hidden">
                {{-- Decorative corner elements --}}
                <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-pink-500/10 to-transparent rounded-bl-full"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-gradient-to-tr from-purple-500/10 to-transparent rounded-tr-full"></div>

                <div class="relative z-10">
                    <livewire:match-submission />
                </div>
            </div>
        </div>

        {{-- Recent Submissions Section --}}
        <div class="mb-12 animate-fadeInUp" style="animation-delay: 0.2s;">
            <div class="flex items-center gap-3 mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Recent Submissions</h2>
                <div class="h-px flex-1 bg-gradient-to-r from-gray-300 to-transparent"></div>
            </div>

            @php
                $recentMatches = \App\Models\Match::where('submitted_by', auth()->id())
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get();
            @endphp

            @if ($recentMatches->isEmpty())
                <div class="rounded-2xl bg-white/60 backdrop-blur-sm border-2 border-dashed border-gray-300 p-12 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 flex items-center justify-center">
                        <x-icon name="lucide-inbox" class="w-8 h-8 text-gray-400" />
                    </div>
                    <p class="text-gray-500 font-medium">No matches submitted yet</p>
                    <p class="text-sm text-gray-400 mt-1">Your submissions will appear here</p>
                </div>
            @else
                <div class="grid gap-4">
                    @foreach ($recentMatches as $index => $match)
                        <div class="group rounded-2xl bg-white/80 backdrop-blur-sm border border-gray-200 p-5 hover:shadow-lg hover:shadow-pink-500/10 hover:border-pink-300 transition-all duration-300 animate-fadeInUp" style="animation-delay: {{ 0.3 + ($index * 0.1) }}s;">
                            <div class="flex items-start justify-between gap-4">
                                {{-- Match Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center flex-shrink-0 shadow-md">
                                            <x-icon name="lucide-play-circle" class="w-5 h-5 text-white" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-bold text-gray-900 truncate group-hover:text-pink-600 transition-colors">
                                                {{ $match->name }}
                                            </h3>
                                            <p class="text-sm text-gray-500 flex items-center gap-1.5 mt-0.5">
                                                <x-icon name="lucide-clock" class="w-4 h-4" />
                                                {{ $match->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>

                                    @if ($match->tournament)
                                        <div class="flex items-center gap-2 mt-3 pl-13">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-purple-50 text-purple-700 text-xs font-tournament font-semibold">
                                                <x-icon name="lucide-calendar" class="w-3.5 h-3.5" />
                                                {{ Str::limit($match->tournament->title, 30) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Status Badge --}}
                                <div class="flex-shrink-0">
                                    @if ($match->status === 'pending')
                                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-yellow-400 to-orange-400 text-white text-sm font-bold shadow-md shadow-yellow-500/30">
                                            <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                                            Pending
                                        </span>
                                    @elseif ($match->status === 'approved')
                                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-400 to-teal-400 text-white text-sm font-bold shadow-md shadow-emerald-500/30">
                                            <x-icon name="lucide-circle-check" class="w-4 h-4" />
                                            Approved
                                        </span>
                                    @elseif ($match->status === 'rejected')
                                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-red-400 to-rose-400 text-white text-sm font-bold shadow-md shadow-red-500/30">
                                            <x-icon name="lucide-circle-x" class="w-4 h-4" />
                                            Rejected
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Help Section --}}
        <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
            <div class="relative rounded-3xl bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 p-8 sm:p-10 shadow-2xl shadow-indigo-500/30 overflow-hidden">
                {{-- Decorative patterns --}}
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-white rounded-full blur-3xl"></div>
                </div>

                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                            <x-icon name="lucide-info" class="w-6 h-6 text-white" />
                        </div>
                        <h3 class="text-xl font-bold text-white">How to find your match link</h3>
                    </div>

                    <ol class="space-y-4">
                        @foreach ([
                            'Go to your osu! match history',
                            'Click on the match you want to add',
                            'Copy the URL from your browser',
                            'Paste it into the form above'
                        ] as $index => $step)
                            <li class="flex items-start gap-4 group">
                                <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center font-bold text-white group-hover:bg-white/30 transition-colors">
                                    {{ $index + 1 }}
                                </div>
                                <p class="flex-1 text-white/95 font-medium pt-1 leading-relaxed">{{ $step }}</p>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-6 pt-6 border-t border-white/20">
                        <p class="text-white/80 text-sm flex items-center gap-2">
                            <x-icon name="lucide-info" class="w-5 h-5" />
                            <span>Match URL format: <code class="px-2 py-1 rounded bg-white/20 backdrop-blur-sm font-mono text-xs">https://osu.ppy.sh/community/matches/123456</code></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes float {
        0%, 100% {
            transform: translateY(0) rotate(0deg);
        }
        50% {
            transform: translateY(-20px) rotate(5deg);
        }
    }

    @keyframes floatReverse {
        0%, 100% {
            transform: translateY(0) rotate(0deg);
        }
        50% {
            transform: translateY(20px) rotate(-5deg);
        }
    }

    .animate-fadeInDown {
        animation: fadeInDown 0.6s ease-out;
    }

    .animate-fadeInUp {
        animation: fadeInUp 0.6s ease-out both;
    }

    .animate-float {
        animation: float 8s ease-in-out infinite;
    }

    .animate-floatReverse {
        animation: floatReverse 10s ease-in-out infinite;
    }
</style>
@endsection
