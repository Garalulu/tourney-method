@props([
    'tournament' => null,
])

<div data-tournament-podium-list="{{ $tournament->id }}">
    @if($tournament->winners()->where('placement', '<=', 3)->exists())
        @php
            $podiumBackfill = app(\App\Services\ParticipationPodiumBackfillService::class);
            $winnerUserIds = $tournament->winners()
                ->where('placement', '<=', 3)
                ->pluck('user_id')
                ->filter()
                ->all();
            $participationRecordsByUser = \App\Models\TournamentParticipationRecord::query()
                ->where('tournament_id', $tournament->id)
                ->whereIn('user_id', $winnerUserIds)
                ->get()
                ->keyBy('user_id');
        @endphp
        <div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-3">
            @foreach([1, 2, 3] as $placement)
                @php
                    $placementWinners = $tournament->winners()
                        ->where('placement', $placement)
                        ->with('user')
                        ->get();
                    $winnerGroups = $placementWinners
                        ->groupBy(function ($winner) use ($placement, $podiumBackfill): string {
                            $groupId = $podiumBackfill->groupIdForWinner($winner);

                            return filled($groupId) ? (string) $groupId : 'legacy-placement-'.$placement;
                        });
                    $placementLabel = $placement === 1 ? '1st' : ($placement === 2 ? '2nd' : '3rd');
                @endphp
                <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h4 class="font-bold">{{ $placementLabel }} Place</h4>
                        <span class="rounded-md border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1 text-xs font-semibold text-[var(--admin-muted)]">
                            {{ $placementWinners->count() }} {{ \Illuminate\Support\Str::plural('member', $placementWinners->count()) }}
                        </span>
                    </div>

                    <div class="space-y-3" id="podium-placement-{{ $placement }}" data-podium-placement-column="{{ $placement }}">
                        @forelse($winnerGroups as $groupId => $groupWinners)
                            @php
                                $groupKey = $placement.'-'.\Illuminate\Support\Str::slug((string) $groupId);
                                $groupWinnerIds = $groupWinners->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                                $groupTeamName = $groupWinners
                                    ->map(fn ($winner) => $winner->user_id ? $participationRecordsByUser->get($winner->user_id)?->team_name : null)
                                    ->first(fn ($teamName) => filled($teamName));
                            @endphp
                            <article
                                class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3 transition"
                                data-podium-group-card
                                data-podium-placement="{{ $placement }}"
                                data-podium-group-key="{{ $groupKey }}"
                                data-podium-winner-ids='@json($groupWinnerIds)'
                                ondragover="podiumDragOver(event)"
                                ondragleave="podiumDragLeave(event)"
                                ondrop="dropPodiumWinner(event)"
                            >
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Team {{ $loop->iteration }}</span>
                                        <span class="text-xs text-[var(--admin-muted)]">{{ $groupWinners->count() }} players</span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button"
                                                onclick="togglePodiumSelection(this)"
                                                class="rounded px-2 py-1 text-xs font-semibold text-[var(--admin-muted)] transition hover:bg-[var(--admin-surface)] hover:text-[var(--osu-cyan)]">
                                            Select
                                        </button>
                                        <button type="button"
                                                onclick="moveSelectedPodiumWinnersToNewGroup(this)"
                                                data-podium-selection-action
                                                class="hidden rounded px-2 py-1 text-xs font-semibold text-[var(--osu-cyan)] transition hover:bg-[var(--admin-surface)]">
                                            New team
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3 flex gap-2">
                                    <input type="text"
                                           value="{{ $groupTeamName }}"
                                           placeholder="Team name"
                                           data-podium-team-name="{{ $groupKey }}"
                                           class="min-w-0 flex-1 rounded border border-[var(--admin-border)] bg-[var(--admin-surface)] px-3 py-2 text-sm text-[var(--admin-text)]">
                                    <button type="button"
                                            onclick="savePodiumGroupDetails(this)"
                                            class="rounded-md border border-[var(--admin-border)] px-3 py-2 text-xs font-semibold text-[var(--osu-cyan)] transition hover:border-[var(--osu-cyan)] disabled:cursor-not-allowed disabled:opacity-50">
                                        Save
                                    </button>
                                </div>

                                <div class="space-y-2">
                                    @foreach($groupWinners as $winner)
                                        <div
                                            class="flex items-center justify-between gap-2 rounded-md border border-transparent bg-[var(--admin-surface)] p-2 transition hover:border-[var(--admin-border)]"
                                            draggable="true"
                                            data-podium-winner-id="{{ $winner->id }}"
                                            data-podium-placement="{{ $placement }}"
                                            ondragstart="startPodiumDrag(event)"
                                            ondragend="endPodiumDrag(event)"
                                        >
                                            <div class="flex min-w-0 items-center gap-2">
                                                <input type="checkbox"
                                                       value="{{ $winner->id }}"
                                                       data-podium-member-checkbox
                                                       class="hidden rounded border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                                <span class="cursor-grab text-[var(--admin-muted)]" title="Drag to another team in this placement" aria-hidden="true">::</span>
                                                <img src="{{ $winner->user?->avatar_url ?? 'https://a.ppy.sh/' . $winner->osu_id }}" class="h-8 w-8 flex-shrink-0 rounded-md object-cover" alt="">
                                                <span class="truncate text-sm font-medium">{{ $winner->username }}</span>
                                            </div>
                                            <button type="button"
                                                    onclick="removePodiumWinner({{ $winner->id }})"
                                                    class="rounded px-2 py-1 text-lg leading-none text-red-500 hover:bg-red-500/10 hover:text-red-400"
                                                    aria-label="Remove {{ $winner->username }} from podium">
                                                &times;
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @empty
                            <div class="rounded-lg border border-dashed border-[var(--admin-border)] p-4 text-center text-sm text-[var(--admin-muted)]">
                                No {{ $placementLabel }} place winners yet.
                            </div>
                        @endforelse

                        @if($placementWinners->isNotEmpty())
                            <div
                                class="rounded-lg border border-dashed border-[var(--admin-border)] bg-[var(--admin-bg)]/70 p-3 text-center text-sm font-semibold text-[var(--admin-muted)] transition"
                                data-podium-new-group-target
                                data-podium-placement="{{ $placement }}"
                                ondragover="podiumNewGroupDragOver(event)"
                                ondragleave="podiumNewGroupDragLeave(event)"
                                ondrop="dropPodiumWinnerToNewGroup(event)"
                            >
                                Drop here for new team
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
