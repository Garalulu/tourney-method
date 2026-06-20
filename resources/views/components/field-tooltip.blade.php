@props([
    'text' => null,
])

@php
    $tooltipText = $text ?? trim((string) $slot);
    $tooltipId = 'field-tooltip-'.\Illuminate\Support\Str::uuid();
@endphp

<span
    class="inline-flex shrink-0 align-middle"
    x-data="{ show: false }"
    @click.outside="show = false"
    @keydown.escape.window="show = false"
>
    <button
        type="button"
        x-ref="trigger"
        aria-label="{{ __('Show help: :text', ['text' => strip_tags($tooltipText)]) }}"
        aria-describedby="{{ $tooltipId }}"
        :aria-expanded="show.toString()"
        @mouseenter="show = true"
        @mouseleave="show = false"
        @focusin="show = true"
        @focusout="show = false"
        @pointerdown.stop
        @click.stop.prevent="show = true"
        class="inline-flex h-7 w-7 touch-manipulation items-center justify-center rounded-full border border-slate-600 bg-slate-950/80 text-xs font-bold leading-none text-slate-300 transition hover:border-pink-400 hover:text-pink-200 focus:border-pink-400 focus:text-pink-200 focus:outline-none focus:ring-2 focus:ring-pink-500/30 sm:h-5 sm:w-5 sm:cursor-help"
    >i</button>
    <template x-teleport="body">
        <span
            id="{{ $tooltipId }}"
            role="tooltip"
            x-show="show"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-none fixed z-[100] w-72 max-w-[calc(100vw-2rem)] rounded-lg border border-slate-700 bg-slate-950 p-3 text-left text-xs font-normal leading-5 text-slate-300 shadow-xl"
            x-anchor.bottom-start="$refs.trigger || document.body"
            style="display: none;"
        >
            @if($text !== null)
                {{ $text }}
            @else
                {!! $slot !!}
            @endif
        </span>
    </template>
</span>
