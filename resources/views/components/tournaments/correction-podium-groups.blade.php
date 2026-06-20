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
            <div class="space-y-3">
                @foreach($group['changes'] as $key => $change)
                    @php
                        $action = data_get($change, 'apply.action');
                        $oldValue = $change['old'] ?? null;
                        $newValue = $change['new'] ?? null;
                        $old = is_array($oldValue) ? $oldValue : [];
                        $new = is_array($newValue) ? $newValue : [];
                        $oldNames = collect($old['usernames'] ?? (filled($oldValue) && ! is_array($oldValue) ? [$oldValue] : []))->filter()->values();
                        $newNames = collect($new['usernames'] ?? (filled($newValue) && ! is_array($newValue) ? [$newValue] : []))->filter()->values();
                        $addedNames = $newNames->diff($oldNames)->values();
                        $removedNames = $oldNames->diff($newNames)->values();
                        $currentNames = $addedNames->isEmpty() && $removedNames->isEmpty() ? $newNames : collect();
                        $oldTeamName = $old['team_name'] ?? null;
                        $newTeamName = $new['team_name'] ?? null;
                    @endphp
                    <div class="rounded bg-slate-900 p-3">
                        @if($oldTeamName !== $newTeamName)
                            <div class="rounded border border-slate-700 bg-slate-950 p-2">
                                <div class="text-xs font-semibold uppercase text-slate-500">Team name</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                    <span class="text-slate-400">{{ filled($oldTeamName) ? $oldTeamName : 'None' }}</span>
                                    <span class="text-slate-600">→</span>
                                    <span class="font-semibold text-slate-100">{{ filled($newTeamName) ? $newTeamName : 'None' }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="{{ $oldTeamName !== $newTeamName ? 'mt-3 ' : '' }}grid gap-3 md:grid-cols-2">
                            @foreach([
                                'Added' => $addedNames,
                                'Removed' => $action === 'group_remove' ? $oldNames : $removedNames,
                                'Members' => $currentNames,
                            ] as $heading => $names)
                                @if($names->isNotEmpty())
                                    <div>
                                        <div class="mb-2 text-xs font-semibold uppercase text-slate-500">{{ $heading }}</div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($names as $name)
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
        </div>
    @endforeach
</div>
