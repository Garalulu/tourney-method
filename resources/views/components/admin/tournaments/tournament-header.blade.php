@props([
    'tournament' => null,
])

<!-- Header -->
<div class="mb-8">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
            <a href="/admin/tournaments/pending"
               class="p-2 hover:bg-[var(--admin-bg)] rounded-lg transition-colors">
                <x-icon name="lucide-arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold" style="font-family: 'Outfit', sans-serif;">
                    Review & Edit
                </h1>
                <p class="text-sm text-[var(--admin-muted)]">
                    Modify fields before approval
                </p>
            </div>
        </div>

        @if($tournament->forum_topic_id)
        <form method="POST" action="/admin/tournaments/{{ $tournament->id }}/reparse" class="inline" data-reparse-form>
            @csrf
            <button type="submit"
                    data-reparse-button
                    class="inline-flex items-center px-4 py-2 rounded-lg bg-gradient-to-r from-[var(--osu-cyan)] to-[var(--osu-pink)] text-white font-semibold text-sm hover:brightness-110 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                <x-icon name="lucide-refresh-cw" class="w-4 h-4 mr-2 animate-spin-slow" />
                Re-parse Tournament
            </button>
        </form>
        @endif
    </div>

    <div id="reparse-progress" class="hidden mb-4"></div>

    <!-- Status Badge -->
    @if($tournament->status === 'pending_review')
        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider bg-yellow-500 bg-opacity-20 text-yellow-400 border border-yellow-500 border-opacity-30">
            <x-icon name="lucide-clock" class="w-4 h-4 mr-2 animate-pulse" />
            Pending Review
        </span>
    @elseif($tournament->status === 'approved')
        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider bg-green-500 bg-opacity-20 text-green-400 border border-green-500 border-opacity-30">
            <x-icon name="lucide-circle-check" class="w-4 h-4 mr-2" />
            Approved
        </span>
    @elseif($tournament->status === 'rejected')
        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider bg-red-500 bg-opacity-20 text-red-400 border border-red-500 border-opacity-30">
            <x-icon name="lucide-circle-x" class="w-4 h-4 mr-2" />
            Rejected
        </span>
        @if($tournament->rejection_reason)
            <div class="mt-2 text-sm text-red-400">
                <span class="font-semibold">Reason:</span> {{ $tournament->rejection_reason }}
            </div>
        @endif
    @endif
</div>
