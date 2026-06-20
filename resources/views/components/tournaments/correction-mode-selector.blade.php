@props([
    'tournament',
])

@php
    $normalizedModes = collect($tournament->modes)
        ->map(fn ($item) => data_get($item, 'mode', $item))
        ->filter()
        ->values()
        ->all();
    $maniaVariants = collect($tournament->modes)
        ->filter(fn ($item): bool => data_get($item, 'mode', $item) === 'mania')
        ->map(fn ($item): string => match (data_get($item, 'key_count')) {
            4 => 'mania_4k',
            7 => 'mania_7k',
            default => 'mania_other',
        })
        ->unique()
        ->values()
        ->all();
    $oldModes = old('modes', $normalizedModes);
    $oldManiaVariants = old('mania_variants', $maniaVariants);
@endphp

<div class="space-y-3 md:col-span-2" x-data="{ maniaChecked: @js(in_array('mania', $oldModes, true) || ! empty($oldManiaVariants)) }">
    <label class="flex items-center gap-2 text-sm font-semibold text-slate-300">
        {{ __('tournaments.corrections.fields.game_modes') }}
        <x-field-tooltip :text="__('tournaments.corrections.tooltips.game_modes')" />
    </label>

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach(['osu' => 'osu!', 'taiko' => 'osu!taiko', 'catch' => 'osu!catch'] as $mode => $label)
            <label class="flex items-center gap-3 rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm text-slate-200 transition-colors hover:border-pink-500">
                <input type="checkbox" name="modes[]" value="{{ $mode }}" @checked(in_array($mode, $oldModes, true)) class="h-5 w-5 rounded border-slate-600 bg-slate-900 text-pink-500 focus:ring-pink-500">
                <span class="font-medium">{{ $label }}</span>
            </label>
        @endforeach
    </div>

    <div class="rounded-lg border border-slate-700 bg-slate-950 p-4">
        <label class="flex items-center gap-3">
            <input type="checkbox" name="modes[]" value="mania" x-model="maniaChecked" @checked(in_array('mania', $oldModes, true)) class="h-5 w-5 rounded border-slate-600 bg-slate-900 text-pink-500 focus:ring-pink-500">
            <span class="flex items-center gap-2 font-semibold text-slate-200">
                osu!mania
                <x-field-tooltip :text="__('tournaments.corrections.tooltips.mania_variants')" />
            </span>
        </label>

        <div x-show="maniaChecked" x-transition class="ml-8 mt-3 grid gap-2 sm:grid-cols-3" style="display: none;">
            @foreach(['mania_4k' => '4K', 'mania_7k' => '7K', 'mania_other' => 'Other'] as $variant => $label)
                <label class="flex items-center gap-3 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200 transition-colors hover:border-pink-500">
                    <input type="checkbox" name="mania_variants[]" value="{{ $variant }}" @checked(in_array($variant, $oldManiaVariants, true)) class="h-5 w-5 rounded border-slate-600 bg-slate-950 text-pink-500 focus:ring-pink-500">
                    <span class="font-medium">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>
