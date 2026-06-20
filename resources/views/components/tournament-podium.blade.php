@props([
    'tournament',
    'podiumSections' => null,
    'currentUserResultTeam' => null,
])

@php
    $podiumSections = $podiumSections ?? app(\App\Services\TournamentResultDisplayService::class)->podiumSections($tournament);
@endphp

@if($podiumSections->isNotEmpty() || $currentUserResultTeam)
<div
    class="rounded-2xl border-2 border-slate-700/50 bg-slate-800/50 p-4 sm:p-8"
    x-data="tournamentResults({
        expandedUrl: @js(route('tournaments.results-expanded', $tournament)),
        userTeamKey: @js($currentUserResultTeam['key'] ?? null),
    })"
>
    <div class="mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="flex items-center gap-3 text-xl font-bold text-white sm:text-2xl">
            <div class="w-1 h-8 bg-gradient-to-b from-pink-500 to-purple-500 rounded-full"></div>
            {{ __('tournaments.podiums.title') }}
        </h2>
    </div>

    <div class="space-y-6 sm:space-y-7">
        @foreach($podiumSections as $section)
            @foreach($section['teams'] as $team)
                @php
                    $podiumLabel = filled($team['team_name'] ?? null)
                        ? $section['label'].' - '.$team['team_name']
                        : $section['label'];
                @endphp
                <section>
                    <div class="mb-3 flex min-w-0 items-center gap-2">
                        <span class="min-w-0 max-w-full truncate text-lg font-bold {{ $loop->parent->first ? 'text-white' : 'text-slate-300' }}" title="{{ $podiumLabel }}">{{ $podiumLabel }}</span>
                        <div class="h-px flex-1 bg-gradient-to-r {{ $loop->parent->first ? 'from-pink-500' : 'from-slate-500' }} to-transparent"></div>
                    </div>
                    <div class="pl-0 sm:pl-10">
                        @include('tournaments.partials.result-team', [
                            'team' => $team,
                            'size' => 'podium',
                            'includePlacementInHeading' => false,
                            'showHeading' => false,
                        ])
                    </div>
                </section>
            @endforeach
        @endforeach

        @if($currentUserResultTeam)
            <section data-current-user-result x-show="!expanded" x-transition>
                <div class="mb-3 flex items-center gap-2">
                    <span class="text-sm font-bold uppercase tracking-wide text-pink-300">{{ $currentUserResultTeam['placement_label'] ?? $currentUserResultTeam['section_label'] ?? __('tournaments.results.unknown_placement') }}</span>
                    <div class="h-px flex-1 bg-gradient-to-r from-pink-500/60 to-transparent"></div>
                </div>
                <div class="pl-0 sm:pl-10">
                    @include('tournaments.partials.result-team', [
                        'team' => array_merge($currentUserResultTeam, ['current_user' => true]),
                        'size' => 'compact',
                        'includePlacementInHeading' => false,
                    ])
                </div>
            </section>
        @endif

        <div x-show="expanded" x-transition class="space-y-7" data-expanded-results></div>

        <div class="border-t border-slate-700/60 pt-5">
            <button
                type="button"
                x-show="!expanded"
                @click="expand"
                :disabled="loading"
                class="inline-flex items-center gap-2 rounded-lg border border-pink-500/50 bg-pink-500/10 px-4 py-2 text-sm font-bold text-pink-200 transition hover:border-pink-400 hover:bg-pink-500/20 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span x-show="!loading">{{ __('tournaments.results.expand_all') }}</span>
                <span x-show="loading">{{ __('tournaments.results.loading') }}</span>
            </button>
            <button
                type="button"
                x-show="expanded"
                @click="collapse"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-600/70 bg-slate-900/50 px-4 py-2 text-sm font-bold text-slate-200 transition hover:border-pink-400/70 hover:text-white"
            >
                {{ __('tournaments.results.collapse') }}
            </button>
            <p x-show="error" x-text="error" class="mt-3 text-sm text-red-300"></p>
        </div>
    </div>
</div>
@endif

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('tournamentResults', (config) => ({
                    expandedUrl: config.expandedUrl,
                    userTeamKey: config.userTeamKey,
                    expanded: false,
                    loading: false,
                    loaded: false,
                    error: null,
                    async expand() {
                        if (this.loaded) {
                            this.expanded = true;
                            this.highlightExpandedUserResult();
                            return;
                        }

                        this.loading = true;
                        this.error = null;

                        try {
                            const response = await fetch(this.expandedUrl, {
                                headers: {
                                    Accept: 'text/html',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (! response.ok) {
                                throw new Error(@js(__('tournaments.results.error')));
                            }

                            const html = await response.text();
                            const container = this.$root.querySelector('[data-expanded-results]');
                            container.innerHTML = html;
                            this.loaded = true;
                            this.expanded = true;
                            this.highlightExpandedUserResult();
                        } catch (error) {
                            this.error = error.message || @js(__('tournaments.results.error'));
                        } finally {
                            this.loading = false;
                        }
                    },
                    collapse() {
                        this.expanded = false;
                    },
                    highlightExpandedUserResult() {
                        if (! this.userTeamKey) {
                            return;
                        }

                        const container = this.$root.querySelector('[data-expanded-results]');
                        const current = container?.querySelector(`[data-result-team-key="${CSS.escape(this.userTeamKey)}"]`);

                        if (current) {
                            current.classList.add('border-pink-400', 'bg-pink-500/10');
                            current.setAttribute('data-current-user-result-expanded', '1');
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
