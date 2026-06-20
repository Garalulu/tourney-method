@props([
    'tournament' => null,
    'name' => 'restricted_countries',
    'selected' => null,
    'label' => 'Regional Restrictions',
    'help' => '(leave empty for open tournament)',
    'emptyText' => 'No restrictions - open to all countries',
])

@php
    $geoscheme = app(\App\Services\UnitedNationsGeoschemeService::class);
    $countryOptions = $geoscheme->countryOptions(app()->getLocale());
    $regionTemplates = $geoscheme->templates();
    $selectedCountries = old($name, $selected ?? $tournament?->restricted_countries ?? []);
@endphp

<div>
    <label class="block text-sm font-semibold mb-2">
        {{ $label }} <span class="text-xs text-[var(--admin-muted)] font-normal">{{ $help }}</span>
    </label>

    <div
        x-data="regionalRestrictions({
            selected: @js($selectedCountries),
            countries: @js($countryOptions),
            templates: @js($regionTemplates)
        })"
        class="space-y-3"
    >
        <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
            <div class="flex flex-wrap gap-2 min-h-8">
                <template x-for="country in selectedDetails" :key="country.code">
                    <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-[var(--osu-pink)] px-2.5 py-1 text-sm font-medium text-white">
                        <span x-text="country.flag"></span>
                        <span class="font-semibold" x-text="country.code"></span>
                        <span class="truncate" x-text="country.name"></span>
                        <button
                            type="button"
                            class="rounded p-0.5 hover:bg-white/20"
                            :aria-label="`Remove ${country.name}`"
                            @click="removeCountry(country.code)"
                        >
                            <x-icon name="lucide-x" class="h-3.5 w-3.5" />
                        </button>
                    </span>
                </template>

                <span x-show="selected.length === 0" class="text-sm text-[var(--admin-muted)]">
                    {{ $emptyText }}
                </span>
            </div>

            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <div class="relative flex-1">
                    <input
                        type="search"
                        x-model="countryQuery"
                        placeholder="Search country or code..."
                        class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] px-3 py-2 pr-9 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                    >
                    <x-icon name="lucide-search" class="absolute right-3 top-2.5 h-4 w-4 text-[var(--admin-muted)]" />

                    <div
                        x-show="countryQuery.length > 0"
                        @click.outside="countryQuery = ''"
                        class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] shadow-xl"
                        style="display: none;"
                    >
                        <template x-for="country in filteredCountries" :key="country.code">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-[var(--admin-bg)] disabled:opacity-50"
                                :disabled="selected.includes(country.code)"
                                @click="addCountry(country.code)"
                            >
                                <span x-text="country.flag"></span>
                                <span class="w-8 font-semibold text-[var(--osu-cyan)]" x-text="country.code"></span>
                                <span class="min-w-0 flex-1 truncate" x-text="country.name"></span>
                            </button>
                        </template>
                        <div x-show="filteredCountries.length === 0" class="px-3 py-3 text-sm text-[var(--admin-muted)]">
                            No matching countries
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm font-medium text-[var(--admin-text)] transition hover:bg-[var(--admin-surface)]"
                    @click="clearCountries()"
                >
                    Clear
                </button>
            </div>
        </div>

        <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
            <div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold">Region Templates</p>
                    <p class="text-xs text-[var(--admin-muted)]">UN geoscheme continents, intermediary regions, and subregions</p>
                </div>
                <input
                    type="search"
                    x-model="templateQuery"
                    placeholder="Search template..."
                    class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)] sm:w-64"
                >
            </div>

            <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                <template x-for="template in filteredTemplates" :key="template.code">
                    <div class="flex flex-col gap-2 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-[var(--admin-text)]" x-text="template.name"></span>
                                <span class="rounded border border-[var(--admin-border)] px-2 py-0.5 text-xs capitalize text-[var(--admin-muted)]" x-text="template.type"></span>
                                <span class="text-xs text-[var(--admin-muted)]" x-text="`${template.count} countries`"></span>
                            </div>
                            <p class="mt-1 truncate text-xs text-[var(--admin-muted)]" x-text="template.countries.slice(0, 14).join(', ') + (template.countries.length > 14 ? '...' : '')"></p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button
                                type="button"
                                class="rounded bg-[var(--osu-cyan)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-bg)] hover:brightness-110"
                                @click="applyTemplate(template.countries, false)"
                            >
                                Add
                            </button>
                            <button
                                type="button"
                                class="rounded bg-[var(--osu-pink)] px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110"
                                @click="applyTemplate(template.countries, true)"
                            >
                                Replace
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="filteredTemplates.length === 0" class="px-3 py-3 text-sm text-[var(--admin-muted)]">
                    No matching templates
                </div>
            </div>
        </div>

        <template x-if="selected.length === 0">
            <input type="hidden" name="{{ $name }}" value="">
        </template>

        <template x-for="country in selected" :key="country">
            <input type="hidden" name="{{ $name }}[]" :value="country">
        </template>

        @error($name)
            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
        @enderror
        @error($name.'.*')
            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<script>
    window.regionalRestrictions = window.regionalRestrictions || function regionalRestrictions(config) {
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
                    .filter(country => {
                        return country.code.toLowerCase().includes(query)
                            || country.name.toLowerCase().includes(query)
                            || country.english_name.toLowerCase().includes(query);
                    })
                    .slice(0, 30);
            },

            get filteredTemplates() {
                const query = this.templateQuery.trim().toLowerCase();
                if (!query) {
                    return this.templates;
                }

                return this.templates.filter(template => {
                    return template.name.toLowerCase().includes(query)
                        || template.type.toLowerCase().includes(query)
                        || template.countries.join(' ').toLowerCase().includes(query);
                });
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
