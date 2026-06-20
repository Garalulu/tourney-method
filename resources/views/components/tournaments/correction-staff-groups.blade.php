@props([
    'groups',
    'accepted' => [],
    'failures' => [],
    'status',
])

<div class="space-y-3">
    @foreach($groups as $group)
        <div class="rounded border border-slate-800 bg-slate-950 p-3 text-sm">
            <div class="mb-3 font-semibold text-white">{{ $group['label'] }}</div>
            <div class="grid gap-3 md:grid-cols-2">
                @php($addedStaff = collect($group['changes'])->filter(fn ($change) => data_get($change, 'apply.action') === 'add'))
                @php($removedStaff = collect($group['changes'])->filter(fn ($change) => data_get($change, 'apply.action') === 'remove'))
                @foreach(['Added' => $addedStaff, 'Removed' => $removedStaff] as $heading => $staffChanges)
                    @if($staffChanges->isNotEmpty())
                        <div class="rounded bg-slate-900 p-2">
                            <div class="mb-2 text-xs font-semibold uppercase text-slate-500">{{ $heading }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($staffChanges as $key => $change)
                                    @php($name = data_get($change, 'apply.username') ?? $change['new'] ?? $change['old'] ?? 'Unknown user')
                                    @php($failed = collect($failures)->contains(fn ($failure) => ($failure['key'] ?? null) === $key && strcasecmp((string) ($failure['username'] ?? ''), (string) $name) === 0))
                                    <span class="inline-flex items-center gap-2 rounded border border-slate-700 bg-slate-950 px-2 py-1 text-slate-100">
                                        {{ $name }}
                                        @if($failed)
                                            <span class="rounded bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-300">Failed</span>
                                        @elseif(in_array($key, $accepted ?: [], true))
                                            <span class="rounded bg-green-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-green-300">Accepted</span>
                                        @elseif($status !== \App\Models\TournamentCorrection::STATUS_PENDING)
                                            <span class="rounded bg-red-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-300">Rejected</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</div>
