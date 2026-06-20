@isset($matchStats)
<!-- Match Statistics Tab -->
<div class="space-y-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Matches -->
        <div class="bg-gradient-to-br from-pink-500/10 to-pink-500/5 rounded-xl p-6 border border-pink-500/20">
            <div class="flex items-center justify-between mb-2">
                <span class="text-pink-400 text-sm font-medium">Total Matches</span>
                <x-icon name="lucide-circle-check" class="w-5 h-5 text-pink-400/50" />
            </div>
            <div class="text-3xl font-bold text-white font-display">{{ $matchStats['total_matches'] ?? 0 }}</div>
            <p class="text-slate-500 text-xs mt-1">Approved matches only</p>
        </div>

        <!-- Total Games -->
        <div class="bg-gradient-to-br from-purple-500/10 to-purple-500/5 rounded-xl p-6 border border-purple-500/20">
            <div class="flex items-center justify-between mb-2">
                <span class="text-purple-400 text-sm font-medium">Games Played</span>
                <x-icon name="lucide-play-circle" class="w-5 h-5 text-purple-400/50" />
            </div>
            <div class="text-3xl font-bold text-white font-display">{{ $matchStats['total_games'] ?? 0 }}</div>
            <p class="text-slate-500 text-xs mt-1">Individual game count</p>
        </div>

        <!-- Win Rate -->
        <div class="bg-gradient-to-br from-green-500/10 to-green-500/5 rounded-xl p-6 border border-green-500/20">
            <div class="flex items-center justify-between mb-2">
                <span class="text-green-400 text-sm font-medium">Win Rate</span>
                <x-icon name="lucide-trending-up" class="w-5 h-5 text-green-400/50" />
            </div>
            <div class="text-3xl font-bold text-white font-display">{{ number_format($matchStats['win_rate'] ?? 0, 1) }}%</div>
            <p class="text-slate-500 text-xs mt-1">{{ $matchStats['games_won'] ?? 0 }} / {{ $matchStats['total_games'] ?? 0 }} games won</p>
        </div>
    </div>

    @if(($matchStats['total_matches'] ?? 0) === 0)
    <!-- No Data Notice -->
    <div class="bg-slate-800/30 backdrop-blur-sm rounded-xl border border-slate-700/50 p-8 text-center">
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-900/50 flex items-center justify-center
                    border border-slate-700/50">
            <x-icon name="lucide-file-text" class="w-6 h-6 text-slate-500" />
        </div>
        <h3 class="text-lg font-medium text-slate-400 mb-1 font-display">No Match Data Yet</h3>
        <p class="text-slate-500 text-sm max-w-md mx-auto">
            Match statistics will appear here once approved matches are recorded for this player.
        </p>
    </div>
    @else
    <!-- Frequent Players Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Frequent Teammates -->
        <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 p-6">
            <h3 class="text-white font-semibold mb-2 font-display flex items-center gap-2">
                <x-icon name="lucide-users" class="w-5 h-5 text-pink-400" />
                Frequent Teammates
            </h3>
            <p class="text-slate-500 text-sm mb-4">Players you've teamed up with 5+ times</p>
            @if(empty($matchStats['frequent_teammates']))
                <p class="text-slate-600 text-sm italic">No frequent teammates yet</p>
            @else
                <div class="space-y-2">
                    @foreach($matchStats['frequent_teammates'] as $teammate)
                    <a href="{{ route('users.show', $teammate['user_id']) }}" class="flex items-center gap-3 p-3 bg-slate-900/30 rounded-lg hover:bg-slate-900/50 transition-colors">
                        <div class="w-10 h-10 rounded-full bg-slate-700 overflow-hidden border border-slate-600">
                            @if($teammate['avatar_url'])
                                <img src="{{ $teammate['avatar_url'] }}" alt="{{ $teammate['username'] }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-pink-500/20 to-purple-500/20">
                                    <span class="text-sm font-bold text-pink-400">{{ strtoupper(substr($teammate['username'], 0, 1)) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <div class="text-white font-medium">{{ $teammate['username'] }}</div>
                            <div class="text-slate-500 text-xs">{{ $teammate['match_count'] }} matches together</div>
                        </div>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Frequent Opponents -->
        <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 p-6">
            <h3 class="text-white font-semibold mb-2 font-display flex items-center gap-2">
                <x-icon name="lucide-users" class="w-5 h-5 text-purple-400" />
                Frequent Opponents
            </h3>
            <p class="text-slate-500 text-sm mb-4">Players you've faced 5+ times</p>
            @if(empty($matchStats['frequent_opponents']))
                <p class="text-slate-600 text-sm italic">No frequent opponents yet</p>
            @else
                <div class="space-y-2">
                    @foreach($matchStats['frequent_opponents'] as $opponent)
                    <a href="{{ route('users.show', $opponent['user_id']) }}" class="flex items-center gap-3 p-3 bg-slate-900/30 rounded-lg hover:bg-slate-900/50 transition-colors">
                        <div class="w-10 h-10 rounded-full bg-slate-700 overflow-hidden border border-slate-600">
                            @if($opponent['avatar_url'])
                                <img src="{{ $opponent['avatar_url'] }}" alt="{{ $opponent['username'] }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-500/20 to-pink-500/20">
                                    <span class="text-sm font-bold text-purple-400">{{ strtoupper(substr($opponent['username'], 0, 1)) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <div class="text-white font-medium">{{ $opponent['username'] }}</div>
                            <div class="text-slate-500 text-xs">{{ $opponent['match_count'] }} matches faced</div>
                        </div>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
@else
<!-- Match Statistics Tab (No Data) -->
<div class="space-y-6">
    <div class="bg-slate-800/30 backdrop-blur-sm rounded-xl border border-slate-700/50 p-8 text-center">
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-900/50 flex items-center justify-center
                    border border-slate-700/50">
            <x-icon name="lucide-clock" class="w-6 h-6 text-slate-500" />
        </div>
        <h3 class="text-lg font-medium text-slate-400 mb-1 font-display">Match Statistics Loading...</h3>
        <p class="text-slate-500 text-sm max-w-md mx-auto">
            Statistics are being calculated. Please refresh the page.
        </p>
    </div>
</div>
@endisset
