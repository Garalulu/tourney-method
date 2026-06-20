@props([
    'tournament',
])

@php
    $bwsChecked = (bool) old('is_bws', $tournament->is_bws);
@endphp

<div class="md:col-span-2" x-data="{ bwsChecked: @js($bwsChecked) }">
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="flex items-center gap-3 rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm text-slate-200 transition-colors hover:border-pink-500">
            <input type="hidden" name="is_badge" value="0">
            <input type="checkbox" name="is_badge" value="1" @checked(old('is_badge', $tournament->is_badge)) class="h-5 w-5 rounded border-slate-600 bg-slate-900 text-pink-500 focus:ring-pink-500">
            <span class="flex items-center gap-2 font-semibold">
                {{ __('tournaments.corrections.fields.is_badge') }}
                <x-field-tooltip :text="__('tournaments.corrections.tooltips.is_badge')" />
            </span>
        </label>

        <label class="flex items-center gap-3 rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm text-slate-200 transition-colors hover:border-cyan-400">
            <input type="hidden" name="is_bws" value="0">
            <input type="checkbox" name="is_bws" value="1" x-model="bwsChecked" class="h-5 w-5 rounded border-slate-600 bg-slate-900 text-cyan-400 focus:ring-cyan-400">
            <span class="flex items-center gap-2 font-semibold">
                {{ __('tournaments.corrections.fields.is_bws') }}
                <x-field-tooltip :text="__('tournaments.corrections.tooltips.is_bws')" />
            </span>
        </label>
    </div>

    <div x-show="bwsChecked" x-transition class="mt-4 rounded-lg border border-slate-700 bg-slate-950 p-5" style="display: none;">
        <h3 class="flex items-center gap-2 text-lg font-bold text-white">
            {{ __('tournaments.corrections.fields.bws_configuration') }}
            <x-field-tooltip :text="__('tournaments.corrections.tooltips.bws_configuration')" />
        </h3>

        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="space-y-2">
                <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                    {{ __('tournaments.corrections.fields.bws_base_exponent') }}
                    <x-field-tooltip :text="__('tournaments.corrections.tooltips.bws_base_exponent')" />
                </span>
                <input type="number" step="0.0001" min="0.9" max="1.0" name="bws_base_exponent" value="{{ old('bws_base_exponent', $tournament->bws_base_exponent ?? '0.9937') }}" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
            </label>
            <label class="space-y-2">
                <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                    {{ __('tournaments.corrections.fields.bws_badge_power') }}
                    <x-field-tooltip :text="__('tournaments.corrections.tooltips.bws_badge_power')" />
                </span>
                <input type="number" step="0.1" min="1" max="5" name="bws_badge_power" value="{{ old('bws_badge_power', $tournament->bws_badge_power ?? '2.00') }}" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
            </label>
            <label class="space-y-2">
                <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                    {{ __('tournaments.corrections.fields.bws_divisor') }}
                    <x-field-tooltip :text="__('tournaments.corrections.tooltips.bws_divisor')" />
                </span>
                <input type="number" step="0.1" min="0.1" max="10" name="bws_divisor" value="{{ old('bws_divisor', $tournament->bws_divisor ?? '1.0000') }}" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
            </label>
            <label class="space-y-2">
                <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                    {{ __('tournaments.corrections.fields.bws_badge_age_cutoff') }}
                    <x-field-tooltip :text="__('tournaments.corrections.tooltips.bws_badge_age_cutoff')" />
                </span>
                <input type="date" name="bws_badge_age_cutoff" value="{{ old('bws_badge_age_cutoff', $tournament->bws_badge_age_cutoff?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
            </label>
        </div>
    </div>
</div>
