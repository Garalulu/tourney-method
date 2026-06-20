<section
    x-data="{
        nextOffset: @js($contributionGroupsNextOffset ?? null),
        hasMore: @js($contributionGroupsHasMore ?? false),
        loading: false,
        async loadMore() {
            if (!this.hasMore || this.loading || this.nextOffset === null) return;

            this.loading = true;
            try {
                const url = new URL(@js($contributionGroupsUrl), window.location.origin);
                url.searchParams.set('offset', String(this.nextOffset));
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) throw new Error('Unable to load contributions');

                const data = await response.json();
                this.$refs.list.insertAdjacentHTML('beforeend', data.html || '');
                this.nextOffset = data.next_offset;
                this.hasMore = Boolean(data.has_more);
            } finally {
                this.loading = false;
            }
        },
    }"
    class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-6">
    <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <h2 class="text-xl font-bold text-white font-display">{{ __('users.contributions.title') }}</h2>
        <p class="text-sm font-semibold text-slate-300">
            @php($displayContributionCount = $contributionCount ?? $contributionGroups->count())
            {{ trans_choice('users.contributions.count', $displayContributionCount, ['count' => number_format($displayContributionCount)]) }}
        </p>
    </div>

    <div x-ref="list" class="space-y-3">
        @if($contributionGroups->isNotEmpty())
            @include('users.partials.contribution-rows', ['contributionGroups' => $contributionGroups])
        @else
            <div class="rounded-lg border border-dashed border-slate-700 p-8 text-center text-sm text-slate-400">
                {{ __('users.contributions.empty') }}
            </div>
        @endif
    </div>

    @if($contributionGroupsHasMore ?? false)
        <div x-show="hasMore" class="mt-5 flex justify-center">
            <button type="button"
                    @click="loadMore()"
                    :disabled="loading"
                    class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                <span x-show="!loading">{{ __('users.contributions.load_more') }}</span>
                <span x-show="loading">{{ __('users.contributions.loading') }}</span>
            </button>
        </div>
    @endif
</section>
