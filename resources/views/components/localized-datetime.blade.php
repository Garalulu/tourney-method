@props([
    'value',
])

@php
    $utcIso = $value->copy()->utc()->toIso8601String();
    $fallbackDate = $value->format('M j, Y');
    $fallbackDetail = $value->format('M j, Y H:i T');
@endphp

<span
    class="relative inline-flex max-w-full"
    x-data="{ tooltipOpen: false }"
    @mouseenter="tooltipOpen = true"
    @mouseleave="tooltipOpen = false"
    @focusin="tooltipOpen = true"
    @focusout="tooltipOpen = false"
    @click.outside="tooltipOpen = false"
    @keydown.escape.window="tooltipOpen = false"
>
    <time
        datetime="{{ $utcIso }}"
        data-local-datetime
        tabindex="0"
        aria-label="{{ $fallbackDetail }}"
        @click.stop="tooltipOpen = true"
        class="rounded-sm outline-none ring-pink-400/60 focus:ring-2"
    >{{ $fallbackDate }}</time>
    <span
        x-cloak
        x-show="tooltipOpen"
        x-transition.opacity
        data-local-datetime-tooltip
        role="tooltip"
        class="absolute left-1/2 top-full z-50 mt-2 w-max max-w-64 -translate-x-1/2 rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-center text-xs font-semibold text-slate-100 shadow-xl"
    >{{ $fallbackDetail }}</span>
</span>

@once
    @push('scripts')
        <script>
            function localizeTournamentDates(root = document) {
                const compactFormatter = new Intl.DateTimeFormat(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                });
                const detailFormatter = new Intl.DateTimeFormat(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZoneName: 'short',
                });

                root.querySelectorAll('[data-local-datetime]').forEach((time) => {
                    const date = new Date(time.dateTime);

                    if (Number.isNaN(date.getTime())) {
                        return;
                    }

                    const detail = detailFormatter.format(date);
                    time.textContent = compactFormatter.format(date);
                    time.setAttribute('aria-label', detail);

                    const tooltip = time.parentElement?.querySelector('[data-local-datetime-tooltip]');
                    if (tooltip) {
                        tooltip.textContent = detail;
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => localizeTournamentDates());
            } else {
                localizeTournamentDates();
            }

            document.addEventListener('livewire:navigated', () => localizeTournamentDates());
        </script>
    @endpush
@endonce
