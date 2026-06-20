@props([
    'tournament',
])

@php
    $formatStructure = old('format_structure', $tournament->format_structure ?? ['stages' => []]);
    $formatStages = collect($formatStructure['stages'] ?? [])->values();
    if ($formatStages->isEmpty() && $tournament->start_round_size) {
        $formatStages = collect([[
            'type' => 'bracket',
            'start_round_size' => $tournament->start_round_size,
            'entry_type' => 'winner_only',
            'elimination_type' => 'double_elimination',
        ]]);
    }
    $initialStages = $formatStages
        ->filter(fn ($stage) => is_array($stage))
        ->values()
        ->all();
    $formationStyles = \App\Models\Tournament::teamFormationStyleLabels();
    $bracketEliminationStyles = \App\Models\Tournament::bracketEliminationLabels();
    $selectedFormationStyle = old('team_formation_style', $tournament->team_formation_style ?? \App\Models\Tournament::TEAM_FORMATION_STANDARD);
@endphp

<div class="space-y-4">
    <div>
        <label for="team_formation_style" class="block text-sm font-semibold text-slate-300">
            <span class="flex items-center gap-2">
                {{ __('tournaments.format.form.team_formation_style') }}
                <x-field-tooltip :text="__('tournaments.format.tooltips.team_formation_style')" />
            </span>
        </label>
        <select name="team_formation_style" id="team_formation_style" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            @foreach($formationStyles as $value => $label)
                <option value="{{ $value }}" @selected($selectedFormationStyle === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <input type="hidden" name="format" value="{{ old('format', $tournament->format) }}">

    <div class="space-y-3" x-data="correctionFormatProgressionEditor(@js($initialStages))">
        <input type="hidden" name="format_structure_present" value="1">

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-slate-200">
                {{ __('tournaments.format.form.ordered_stages') }}
                <x-field-tooltip :text="__('tournaments.format.tooltips.ordered_stages')" />
            </div>
            <button type="button" class="min-h-11 w-full rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200 transition-colors hover:border-pink-500 hover:text-pink-300 focus:outline-none focus:ring-2 focus:ring-pink-500/40 sm:min-h-0 sm:w-auto" @click="addStage()">
                {{ __('tournaments.format.form.add_stage') }}
            </button>
        </div>

        <template x-for="(stage, index) in stages" :key="stage.key">
            <div
                class="rounded-lg border border-slate-700 bg-slate-950 p-4 transition"
                :class="{ 'opacity-60 ring-2 ring-cyan-400': draggingIndex === index }"
                @dragover.prevent
                @drop="dropStage(index, $event)"
                @dragend="endDrag()"
                data-format-stage-card
            >
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex min-w-0 w-full flex-col gap-3 sm:max-w-xs sm:flex-row sm:items-end">
                        <button
                            type="button"
                            class="hidden h-10 w-10 shrink-0 cursor-grab items-center justify-center rounded-lg border border-slate-700 text-slate-400 hover:border-cyan-400 hover:text-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/40 active:cursor-grabbing sm:inline-flex"
                            title="Drag to reorder stage"
                            aria-label="Drag to reorder stage"
                            data-stage-drag-handle
                            draggable="true"
                            @dragstart.stop="startDrag(index, $event)"
                            @dragend="endDrag()"
                        >
                            <x-icon name="lucide-grip-vertical" class="h-5 w-5" />
                        </button>

                        <div class="min-w-0 w-full">
                            <label class="mb-1 flex min-w-0 flex-wrap items-center gap-2 text-xs font-semibold text-slate-500">
                                <span x-text="'{{ __('tournaments.format.form.stage') }} ' + (index + 1)"></span>
                                <x-field-tooltip :text="__('tournaments.format.tooltips.stage_type')" />
                            </label>
                            <select :name="'format_structure[stages][' + index + '][type]'" x-model="stage.type" @change="stages[index] = defaultStage(stage.type)" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                                <option value="">{{ __('tournaments.format.form.select_stage_type') }}</option>
                                <option value="qualifier">{{ __('tournaments.format.stage.qualifier') }}</option>
                                <option value="group_stage">{{ __('tournaments.format.stage.group_stage') }}</option>
                                <option value="swiss_round">{{ __('tournaments.format.stage.swiss_round') }}</option>
                                <option value="bracket">{{ __('tournaments.format.stage.bracket') }}</option>
                                <option value="battle_royale">{{ __('tournaments.format.stage.battle_royale') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 sm:block">
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-700 text-slate-200 transition hover:border-cyan-400 hover:text-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/40 disabled:cursor-not-allowed disabled:opacity-40 sm:hidden"
                            :disabled="index === 0"
                            :aria-label="'Move {{ __('tournaments.format.form.stage') }} ' + (index + 1) + ' up'"
                            @click="moveStage(index, -1)"
                        >
                            <x-icon name="lucide-chevron-up" class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-700 text-slate-200 transition hover:border-cyan-400 hover:text-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-400/40 disabled:cursor-not-allowed disabled:opacity-40 sm:hidden"
                            :disabled="index === stages.length - 1"
                            :aria-label="'Move {{ __('tournaments.format.form.stage') }} ' + (index + 1) + ' down'"
                            @click="moveStage(index, 1)"
                        >
                            <x-icon name="lucide-chevron-down" class="h-5 w-5" />
                        </button>
                        <button type="button" class="min-h-11 rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-red-300 transition-colors hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-400/40 sm:min-h-0" @click="removeStage(index)">
                            {{ __('tournaments.format.form.delete_stage') }}
                        </button>
                    </div>
                </div>

                <div x-show="stage.type === 'qualifier'" x-transition class="mt-4">
                    <label class="mb-1 flex items-center gap-2 text-xs font-semibold text-slate-500">
                        {{ __('tournaments.format.form.qualifier_top') }}
                        <x-field-tooltip :text="__('tournaments.format.tooltips.qualifier_top')" />
                    </label>
                    <input type="number" :name="'format_structure[stages][' + index + '][advance_count]'" x-model="stage.advance_count" :disabled="stage.type !== 'qualifier'" min="1" max="4096" placeholder="64" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                </div>

                <div x-show="stage.type === 'group_stage'" x-transition class="mt-4 grid gap-3 md:grid-cols-3">
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.groups') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.groups')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][group_count]'" x-model="stage.group_count" :disabled="stage.type !== 'group_stage'" min="1" max="512" placeholder="8" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.teams_per_group') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.teams_per_group')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][teams_per_group]'" x-model="stage.teams_per_group" :disabled="stage.type !== 'group_stage'" min="1" max="512" placeholder="4" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.group_top') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.group_top')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][advance_count]'" x-model="stage.advance_count" :disabled="stage.type !== 'group_stage'" min="1" max="4096" placeholder="32" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                </div>

                <div x-show="stage.type === 'swiss_round'" x-transition class="mt-4 grid gap-3 md:grid-cols-2">
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.swiss_rounds') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.swiss_rounds')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][round_count]'" x-model="stage.round_count" :disabled="stage.type !== 'swiss_round'" min="1" max="64" placeholder="5" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.swiss_top') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.swiss_top')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][advance_count]'" x-model="stage.advance_count" :disabled="stage.type !== 'swiss_round'" min="1" max="4096" placeholder="16" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                </div>

                <div x-show="stage.type === 'bracket'" x-transition class="mt-4 grid gap-3 md:grid-cols-3">
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.elimination') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.elimination')" />
                        </span>
                        <select :name="'format_structure[stages][' + index + '][elimination_type]'" x-model="stage.elimination_type" @change="if (stage.elimination_type === 'single_elimination') stage.entry_type = 'winner_only'" :disabled="stage.type !== 'bracket'" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                            @foreach($bracketEliminationStyles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.bracket_round') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.bracket_round')" />
                        </span>
                        <select :name="'format_structure[stages][' + index + '][start_round_size]'" x-model="stage.start_round_size" :disabled="stage.type !== 'bracket'" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                            <option value="">{{ __('tournaments.format.form.select_round') }}</option>
                            @foreach([4, 8, 16, 32, 64, 128, 256, 512, 1024] as $roundSize)
                                <option value="{{ $roundSize }}">Ro{{ $roundSize }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label x-show="stage.elimination_type !== 'single_elimination'" x-transition class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.bracket_entry') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.bracket_entry')" />
                        </span>
                        <select :name="'format_structure[stages][' + index + '][entry_type]'" x-model="stage.entry_type" :disabled="stage.type !== 'bracket' || stage.elimination_type === 'single_elimination'" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                            <option value="winner_only">{{ __('tournaments.format.form.winner_only') }}</option>
                            <option value="winner_loser_hybrid">{{ __('tournaments.format.form.winner_loser_hybrid') }}</option>
                        </select>
                    </label>
                    <input type="hidden" :name="'format_structure[stages][' + index + '][entry_type]'" value="winner_only" :disabled="stage.type !== 'bracket' || stage.elimination_type !== 'single_elimination'">
                </div>

                <div x-show="stage.type === 'battle_royale'" x-transition class="mt-4 grid gap-3 md:grid-cols-4">
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.lobbies') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.lobbies')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][lobby_count]'" x-model="stage.lobby_count" :disabled="stage.type !== 'battle_royale'" min="1" max="512" placeholder="4" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.players_per_lobby') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.players_per_lobby')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][players_per_lobby]'" x-model="stage.players_per_lobby" :disabled="stage.type !== 'battle_royale'" min="1" max="512" placeholder="16" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.advance_per_lobby') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.advance_per_lobby')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][advance_per_lobby]'" x-model="stage.advance_per_lobby" :disabled="stage.type !== 'battle_royale'" min="1" max="512" placeholder="4" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                    <label class="space-y-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            {{ __('tournaments.format.form.eliminated_per_map') }}
                            <x-field-tooltip :text="__('tournaments.format.tooltips.eliminated_per_map')" />
                        </span>
                        <input type="number" :name="'format_structure[stages][' + index + '][eliminated_per_map]'" x-model="stage.eliminated_per_map" :disabled="stage.type !== 'battle_royale'" min="1" max="512" placeholder="1" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                    </label>
                </div>
            </div>
        </template>

        <p class="sr-only" aria-live="polite" x-text="moveAnnouncement"></p>
    </div>
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('correctionFormatProgressionEditor', (initialStages = []) => ({
                stages: [],
                nextKey: 1,
                draggingIndex: null,
                moveAnnouncement: '',

                init() {
                    this.stages = Array.isArray(initialStages)
                        ? initialStages.map((stage) => this.hydrateStage(stage))
                        : [];
                },

                defaultStage(type = '') {
                    const stage = {
                        key: this.nextKey++,
                        type,
                    };

                    if (type === 'qualifier') {
                        return { ...stage, advance_count: '' };
                    }

                    if (type === 'group_stage') {
                        return { ...stage, group_count: '', teams_per_group: '', advance_count: '' };
                    }

                    if (type === 'swiss_round') {
                        return { ...stage, round_count: '', advance_count: '' };
                    }

                    if (type === 'bracket') {
                        return {
                            ...stage,
                            elimination_type: 'double_elimination',
                            start_round_size: '',
                            entry_type: 'winner_only',
                        };
                    }

                    if (type === 'battle_royale') {
                        return {
                            ...stage,
                            lobby_count: '',
                            players_per_lobby: '',
                            advance_per_lobby: '',
                            eliminated_per_map: '',
                        };
                    }

                    return stage;
                },

                hydrateStage(stage) {
                    const type = stage?.type || '';
                    const hydrated = {
                        ...this.defaultStage(type),
                        ...stage,
                    };

                    hydrated.key = this.nextKey++;

                    if (hydrated.elimination_type === 'single_elimination') {
                        hydrated.entry_type = 'winner_only';
                    }

                    return hydrated;
                },

                addStage() {
                    this.stages.push(this.defaultStage(''));
                },

                removeStage(index) {
                    this.stages.splice(index, 1);
                },

                moveStage(index, offset) {
                    const targetIndex = index + offset;

                    if (targetIndex < 0 || targetIndex >= this.stages.length) {
                        return;
                    }

                    const moved = this.stages.splice(index, 1)[0];
                    this.stages.splice(targetIndex, 0, moved);
                    this.moveAnnouncement = `{{ __('tournaments.format.form.stage') }} ${targetIndex + 1}`;
                },

                startDrag(index, event) {
                    if (!event.target.closest('[data-stage-drag-handle]')) {
                        event.preventDefault();
                        this.endDrag();
                        return;
                    }

                    this.draggingIndex = index;
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(index));
                },

                dropStage(index, event) {
                    const fromIndex = this.draggingIndex ?? Number(event.dataTransfer.getData('text/plain'));

                    if (!Number.isInteger(fromIndex) || fromIndex === index) {
                        this.draggingIndex = null;
                        return;
                    }

                    const moved = this.stages.splice(fromIndex, 1)[0];
                    this.stages.splice(index, 0, moved);
                    this.endDrag();
                },

                endDrag() {
                    this.draggingIndex = null;
                },
            }));
        });
    </script>
@endonce
