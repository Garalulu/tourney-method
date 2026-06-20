@props([
    'tournament',
])

@php
    $geoscheme = app(\App\Services\UnitedNationsGeoschemeService::class);
    $countryOptions = $geoscheme->countryOptions(app()->getLocale());
    $regionTemplates = $geoscheme->templates();
    $selectedCountries = old('restricted_countries', $tournament->restricted_countries ?? []);
@endphp

<div class="min-w-0 md:col-span-2">
    <label class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-slate-300">
        <span>{{ __('tournaments.corrections.fields.regional_restrictions') }}</span>
        <span class="min-w-0 font-normal text-slate-500">{{ __('tournaments.corrections.fields.regional_restrictions_empty') }}</span>
        <x-field-tooltip :text="__('tournaments.corrections.tooltips.regional_restrictions')" />
    </label>

    <div
        x-data="correctionRegionalRestrictions({
            selected: @js($selectedCountries),
            countries: @js($countryOptions),
            templates: @js($regionTemplates)
        })"
        class="mt-2 min-w-0 space-y-3"
    >
        <div class="min-w-0 rounded-lg border border-slate-700 bg-slate-950 p-3">
            <div class="flex min-h-8 flex-wrap gap-2">
                <template x-for="country in selectedDetails" :key="country.code">
                    <span class="inline-flex min-w-0 max-w-full items-center gap-1 rounded-md bg-pink-500 px-2.5 py-1 text-sm font-medium text-white">
                        <span class="shrink-0" x-text="country.flag"></span>
                        <span class="shrink-0 font-semibold" x-text="country.code"></span>
                        <span class="min-w-0 truncate" x-text="country.name"></span>
                        <button type="button" class="shrink-0 rounded p-0.5 hover:bg-white/20" :aria-label="`Remove ${country.name}`" @click="removeCountry(country.code)">
                            <x-icon name="lucide-x" class="h-3.5 w-3.5" />
                        </button>
                    </span>
                </template>

                <span x-show="selected.length === 0" class="text-sm text-slate-500">
                    {{ __('tournaments.corrections.values.no_regional_restrictions') }}
                </span>
            </div>

            <div class="mt-3 flex min-w-0 flex-col gap-2 sm:flex-row">
                <div class="relative min-w-0 flex-1">
                    <input type="search" x-model="countryQuery" placeholder="{{ __('tournaments.corrections.placeholders.search_country') }}" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 pr-9 text-sm text-white focus:border-transparent focus:outline-none focus:ring-2 focus:ring-pink-500">
                    <x-icon name="lucide-search" class="absolute right-3 top-2.5 h-4 w-4 text-slate-500" />

                    <div x-show="countryQuery.length > 0" @click.outside="countryQuery = ''" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-700 bg-slate-900 shadow-xl" style="display: none;">
                        <template x-for="country in filteredCountries" :key="country.code">
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-50" :disabled="selected.includes(country.code)" @click="addCountry(country.code)">
                                <span x-text="country.flag"></span>
                                <span class="w-8 font-semibold text-cyan-300" x-text="country.code"></span>
                                <span class="min-w-0 flex-1 truncate" x-text="country.name"></span>
                            </button>
                        </template>
                        <div x-show="filteredCountries.length === 0" class="px-3 py-3 text-sm text-slate-500">
                            {{ __('tournaments.corrections.values.no_matching_countries') }}
                        </div>
                    </div>
                </div>

                <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800" @click="clearCountries()">
                    {{ __('tournaments.corrections.actions.clear') }}
                </button>
            </div>
        </div>

        <div class="min-w-0 rounded-lg border border-slate-700 bg-slate-950 p-3">
            <div class="mb-2 flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-slate-200">
                        {{ __('tournaments.corrections.fields.region_templates') }}
                        <x-field-tooltip :text="__('tournaments.corrections.tooltips.region_templates')" />
                    </p>
                    <p class="text-xs text-slate-500">{{ __('tournaments.corrections.help.region_templates') }}</p>
                </div>
                <input type="search" x-model="templateQuery" placeholder="{{ __('tournaments.corrections.placeholders.search_template') }}" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-transparent focus:outline-none focus:ring-2 focus:ring-cyan-400 sm:w-64">
            </div>

            <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                <template x-for="template in filteredTemplates" :key="template.code">
                    <div class="flex min-w-0 flex-col gap-2 rounded-lg border border-slate-700 bg-slate-900 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-white" x-text="template.name"></span>
                                <span class="rounded border border-slate-700 px-2 py-0.5 text-xs capitalize text-slate-400" x-text="template.type"></span>
                                <span class="text-xs text-slate-500" x-text="@js(__('tournaments.corrections.values.country_count', ['count' => ':count'])).replace(':count', template.count)"></span>
                            </div>
                            <p class="mt-1 truncate text-xs text-slate-500" x-text="template.countries.slice(0, 14).join(', ') + (template.countries.length > 14 ? '...' : '')"></p>
                        </div>
                        <div class="grid shrink-0 grid-cols-2 gap-2 sm:flex">
                            <button type="button" class="min-h-11 rounded bg-cyan-400 px-3 py-1.5 text-xs font-semibold text-slate-950 hover:brightness-110 sm:min-h-0" @click="applyTemplate(template.countries, false)">
                                {{ __('tournaments.corrections.actions.add') }}
                            </button>
                            <button type="button" class="min-h-11 rounded bg-pink-500 px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110 sm:min-h-0" @click="applyTemplate(template.countries, true)">
                                {{ __('tournaments.corrections.actions.replace') }}
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="filteredTemplates.length === 0" class="px-3 py-3 text-sm text-slate-500">
                    {{ __('tournaments.corrections.values.no_matching_templates') }}
                </div>
            </div>
        </div>

        <template x-if="selected.length === 0">
            <input type="hidden" name="restricted_countries" value="">
        </template>

        <template x-for="country in selected" :key="country">
            <input type="hidden" name="restricted_countries[]" :value="country">
        </template>
    </div>
</div>

@once
    <script>
        window.correctionRegionalRestrictions = window.correctionRegionalRestrictions || function correctionRegionalRestrictions(config) {
            return {
                selected: Array.isArray(config.selected) ? config.selected.filter(Boolean).map(code => String(code).toUpperCase()) : [],
                countries: config.countries || [],
                templates: config.templates || [],
                countryQuery: '',
                templateQuery: '',

                get selectedDetails() {
                    return this.selected
                        .map(code => this.countries.find(country => country.code === code) || { code, name: code, english_name: code, flag: '' })
                        .sort((a, b) => a.name.localeCompare(b.name));
                },

                get filteredCountries() {
                    const query = this.countryQuery.trim().toLowerCase();
                    if (!query) {
                        return [];
                    }

                    return this.countries
                        .filter(country => country.code.toLowerCase().includes(query)
                            || country.name.toLowerCase().includes(query)
                            || country.english_name.toLowerCase().includes(query))
                        .slice(0, 30);
                },

                get filteredTemplates() {
                    const query = this.templateQuery.trim().toLowerCase();
                    if (!query) {
                        return this.templates;
                    }

                    return this.templates.filter(template => template.name.toLowerCase().includes(query)
                        || template.type.toLowerCase().includes(query)
                        || template.countries.join(' ').toLowerCase().includes(query));
                },

                addCountry(code) {
                    const normalized = String(code).toUpperCase();
                    if (!this.selected.includes(normalized)) {
                        this.selected.push(normalized);
                        this.selected.sort();
                    }
                    this.countryQuery = '';
                },

                removeCountry(code) {
                    this.selected = this.selected.filter(country => country !== code);
                },

                clearCountries() {
                    this.selected = [];
                },

                applyTemplate(countries, replace) {
                    const normalized = countries.map(code => String(code).toUpperCase());
                    this.selected = replace
                        ? Array.from(new Set(normalized)).sort()
                        : Array.from(new Set([...this.selected, ...normalized])).sort();
                },
            };
        };
    </script>
@endonce
