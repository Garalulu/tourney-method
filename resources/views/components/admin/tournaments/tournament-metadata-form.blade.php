@props([
    'tournament',
    'mode' => 'edit',
])

@php
$isCreate = $mode === 'create';
$formAction = $isCreate
    ? route('admin.tournaments.store')
    : route('admin.tournaments.update', $tournament);
$defaultBwsBaseExponent = '0.9937';
$defaultBwsBadgePower = '2.00';
$defaultBwsDivisor = '1.0000';

// Helper function to normalize modes to simple string format.
$getNormalizedModes = function($tournament) {
    $modes = $tournament->modes ?? [];
    $normalized = [];

    foreach ($modes as $mode) {
        if (is_string($mode)) {
            $normalized[] = $mode;
        } elseif (is_array($mode) && isset($mode['mode'])) {
            $normalized[] = $mode['mode'];
        }
    }

    return $normalized;
};

$getManiaVariants = function($tournament) {
    $variants = [];
    $modes = $tournament->modes ?? [];

    foreach ($modes as $mode) {
        if (is_array($mode) && isset($mode['mode']) && $mode['mode'] === 'mania') {
            $keyCount = $mode['key_count'] ?? null;

            if ($keyCount === 4) {
                $variants[] = 'mania_4k';
            } elseif ($keyCount === 7) {
                $variants[] = 'mania_7k';
            } else {
                $variants[] = 'mania_other';
            }
        } elseif (is_string($mode) && $mode === 'mania') {
            $variants[] = 'mania_other';
        }
    }

    return $variants;
};

$hasManiaMode = function($tournament) {
    $modes = $tournament->modes ?? [];

    foreach ($modes as $mode) {
        if (is_string($mode) && $mode === 'mania') {
            return true;
        }
        if (is_array($mode) && isset($mode['mode']) && $mode['mode'] === 'mania') {
            return true;
        }
    }

    return false;
};

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
if ($formatStages->isEmpty()) {
    $formatStages = collect([
        ['type' => 'qualifier'],
        ['type' => 'group_stage'],
    ]);
}
$initialStages = $formatStages
    ->filter(fn ($stage) => is_array($stage))
    ->values()
    ->all();
$formationStyles = \App\Models\Tournament::teamFormationStyleLabels();
$bracketEliminationStyles = \App\Models\Tournament::bracketEliminationLabels();
$selectedFormationStyle = old('team_formation_style', $tournament->team_formation_style ?? \App\Models\Tournament::TEAM_FORMATION_STANDARD);
$adminDateTimeValue = function (string $field) use ($tournament): string {
    $value = old($field, $tournament->{$field}?->format('Y-m-d\TH:i'));

    return is_string($value) ? $value : '';
};
$adminDateValue = function (string $field) use ($adminDateTimeValue): string {
    $value = $adminDateTimeValue($field);

    return preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches) ? $matches[1] : '';
};
$adminTimeValue = function (string $field) use ($adminDateTimeValue): string {
    $value = $adminDateTimeValue($field);

    if (preg_match('/^\d{4}-\d{2}-\d{2}T(\d{2}:\d{2})/', $value, $matches)) {
        return $matches[1] === '12:00' ? '' : $matches[1];
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} (\d{2}:\d{2})/', $value, $matches)) {
        return $matches[1] === '12:00' ? '' : $matches[1];
    }

    return '';
};
@endphp

<form method="POST" action="{{ $formAction }}" class="space-y-6" onsubmit="normalizeAdminTournamentDateFields(this)" @if($isCreate) id="createForm" @endif>
    @csrf
    @unless($isCreate)
        @method('PATCH')
    @endunless

                {{-- Banner URL Input --}}
                <div class="mb-6">
                    <label for="banner_url" class="block text-sm font-semibold mb-2">
                        Banner URL <span class="text-xs text-[var(--admin-muted)] font-normal">(tournament banner image)</span>
                    </label>
                    <input type="url"
                           name="banner_url"
                           id="banner_url"
                           value="{{ old('banner_url', $tournament->banner_url) }}"
                           placeholder="https://example.com/banner.jpg"
                           class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                    @error('banner_url')
                        <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-semibold mb-2">
                        Tournament Title <span class="text-[var(--danger)]">*</span>
                    </label>
                    <input type="text"
                           name="title"
                           id="title"
                           value="{{ old('title', $tournament->title) }}"
                           required
                           maxlength="256"
                           class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                    @error('title')
                        <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Game Modes -->
                <div>
                    <label class="block text-sm font-semibold mb-3">
                        Game Modes <span class="text-[var(--danger)]">*</span>
                    </label>

                    <div class="space-y-3">
                        <!-- Standard Modes -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            @foreach(['osu', 'taiko', 'catch'] as $mode)
                            <label class="flex items-center space-x-3 p-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg cursor-pointer hover:border-[var(--osu-pink)] transition-colors">
                                <input type="checkbox"
                                       name="modes[]"
                                       value="{{ $mode }}"
                                       @if(in_array($mode, old('modes', $getNormalizedModes($tournament)))) checked @endif
                                       class="w-5 h-5 text-[var(--osu-pink)] bg-[var(--admin-surface)] border-[var(--admin-border)] rounded focus:ring-[var(--osu-pink)]">
                                <span class="font-medium capitalize">{{ $mode }}</span>
                            </label>
                            @endforeach
                        </div>

                        <!-- Mania with Sub-modes -->
                        <div class="border border-[var(--admin-border)] rounded-lg p-4" x-data="{
                            maniaChecked: @if($hasManiaMode($tournament) || collect(old('modes', $getNormalizedModes($tournament)))->contains('mania')) true @else false @endif
                        }">
                            <label class="flex items-center space-x-3 mb-3">
                                <input type="checkbox"
                                       id="mania_checkbox"
                                       name="modes[]"
                                       value="mania"
                                       @if($hasManiaMode($tournament)) checked @endif
                                       class="w-5 h-5 text-[var(--osu-pink)] bg-[var(--admin-surface)] border-[var(--admin-border)] rounded focus:ring-[var(--osu-pink)]"
                                       x-model="maniaChecked">
                                <span class="font-semibold">Mania</span>
                            </label>

                            <div x-show="maniaChecked" x-transition class="ml-6 mt-3 space-y-2">
                                <label class="flex items-center space-x-3 p-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg cursor-pointer hover:border-[var(--osu-pink)] transition-colors">
                                    <input type="checkbox"
                                           name="mania_variants[]"
                                           value="mania_4k"
                                           @if(in_array('mania_4k', old('mania_variants', $getManiaVariants($tournament)))) checked @endif
                                           class="w-5 h-5 text-[var(--osu-pink)] bg-[var(--admin-surface)] border-[var(--admin-border)] rounded focus:ring-[var(--osu-pink)]">
                                    <span class="font-medium">4K</span>
                                </label>

                                <label class="flex items-center space-x-3 p-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg cursor-pointer hover:border-[var(--osu-pink)] transition-colors">
                                    <input type="checkbox"
                                           name="mania_variants[]"
                                           value="mania_7k"
                                           @if(in_array('mania_7k', old('mania_variants', $getManiaVariants($tournament)))) checked @endif
                                           class="w-5 h-5 text-[var(--osu-pink)] bg-[var(--admin-surface)] border-[var(--admin-border)] rounded focus:ring-[var(--osu-pink)]">
                                    <span class="font-medium">7K</span>
                                </label>

                                <label class="flex items-center space-x-3 p-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg cursor-pointer hover:border-[var(--osu-pink)] transition-colors">
                                    <input type="checkbox"
                                           name="mania_variants[]"
                                           value="mania_other"
                                           @if(in_array('mania_other', old('mania_variants', $getManiaVariants($tournament)))) checked @endif
                                           class="w-5 h-5 text-[var(--osu-pink)] bg-[var(--admin-surface)] border-[var(--admin-border)] rounded focus:ring-[var(--osu-pink)]">
                                    <span class="font-medium">Other (specify in description)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    @error('modes')
                        <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                    @error('mania_variants')
                        <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <!-- VS Size & Team Size -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="vs_size" class="block text-sm font-semibold mb-2">
                            VS Size <span class="text-xs text-[var(--admin-muted)] font-normal">(e.g., 1 for 1v1)</span>
                        </label>
                        <input type="number"
                               name="vs_size"
                               id="vs_size"
                               value="{{ old('vs_size', $tournament->vs_size) }}"
                               min="1"
                               max="16"
                               placeholder="e.g., 1"
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        @error('vs_size')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="team_size_min" class="block text-sm font-semibold mb-2">
                            Team Size Min
                        </label>
                        <input type="number"
                               name="team_size_min"
                               id="team_size_min"
                               value="{{ old('team_size_min', $tournament->team_size_min) }}"
                               min="1"
                               max="16"
                               placeholder="e.g., 1"
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        @error('team_size_min')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="team_size_max" class="block text-sm font-semibold mb-2">
                            Team Size Max
                        </label>
                        <input type="number"
                               name="team_size_max"
                               id="team_size_max"
                               value="{{ old('team_size_max', $tournament->team_size_max) }}"
                               min="1"
                               max="16"
                               placeholder="e.g., 4"
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        @error('team_size_max')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Format & Progression -->
                <div class="space-y-4 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)]/40 p-5">
                    <div>
                        <h3 class="text-lg font-bold text-[var(--admin-text)]" style="font-family: 'Outfit', sans-serif;">
                            {{ __('tournaments.format.form.section_title') }}
                        </h3>
                    </div>

                    <div>
                        <label for="team_formation_style" class="block text-sm font-semibold mb-2">
                            {{ __('tournaments.format.form.team_formation_style') }}
                        </label>
                        <select name="team_formation_style"
                                id="team_formation_style"
                                class="w-full px-4 py-3 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                            @foreach($formationStyles as $value => $label)
                                <option value="{{ $value }}" {{ $selectedFormationStyle === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('team_formation_style')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <input type="hidden" name="format" value="{{ old('format', $tournament->format) }}">

                    <div class="space-y-3" x-data="formatProgressionEditor(@js($initialStages))">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-semibold text-[var(--admin-text)]">{{ __('tournaments.format.form.ordered_stages') }}</div>
                            <button type="button"
                                    class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold text-[var(--admin-text)] transition-colors hover:border-[var(--osu-pink)] hover:text-[var(--osu-pink)]"
                                    @click="addStage()">
                                {{ __('tournaments.format.form.add_stage') }}
                            </button>
                        </div>

                        <template x-for="(stage, index) in stages" :key="stage.key">
                            <div
                                class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 transition"
                                :class="{ 'opacity-60 ring-2 ring-[var(--osu-cyan)]': draggingIndex === index }"
                                @dragover.prevent
                                @drop="dropStage(index, $event)"
                                @dragend="endDrag()"
                                data-format-stage-card
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                    <div class="flex w-full flex-col gap-3 sm:max-w-xs sm:flex-row sm:items-end">
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[var(--admin-border)] text-[var(--admin-muted)] cursor-grab active:cursor-grabbing hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]"
                                            title="Drag to reorder stage"
                                            aria-label="Drag to reorder stage"
                                            data-stage-drag-handle
                                            draggable="true"
                                            @dragstart.stop="startDrag(index, $event)"
                                            @dragend="endDrag()"
                                        >
                                            <x-icon name="lucide-grip-vertical" class="h-5 w-5" />
                                        </button>

                                        <div class="w-full">
                                            <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]" x-text="'{{ __('tournaments.format.form.stage') }} ' + (index + 1)"></label>
                                        <select :name="'format_structure[stages][' + index + '][type]'"
                                                x-model="stage.type"
                                                @change="stages[index] = defaultStage(stage.type)"
                                                class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                            <option value="">{{ __('tournaments.format.form.select_stage_type') }}</option>
                                            <option value="qualifier">{{ __('tournaments.format.stage.qualifier') }}</option>
                                            <option value="group_stage">{{ __('tournaments.format.stage.group_stage') }}</option>
                                            <option value="swiss_round">{{ __('tournaments.format.stage.swiss_round') }}</option>
                                            <option value="bracket">{{ __('tournaments.format.stage.bracket') }}</option>
                                            <option value="battle_royale">{{ __('tournaments.format.stage.battle_royale') }}</option>
                                        </select>
                                        </div>
                                    </div>

                                    <button type="button"
                                            class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold text-[var(--danger)] transition-colors hover:border-[var(--danger)]"
                                            @click="removeStage(index)">
                                        {{ __('tournaments.format.form.delete_stage') }}
                                    </button>
                                </div>

                                <div x-show="stage.type === 'qualifier'" x-transition class="mt-4">
                                    <div>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.qualifier_top') }}</label>
                                        <input type="number"
                                               :name="'format_structure[stages][' + index + '][advance_count]'"
                                               x-model="stage.advance_count"
                                               :disabled="stage.type !== 'qualifier'"
                                               min="1"
                                               max="4096"
                                               placeholder="64"
                                               class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                    </div>
                                </div>

                                <div x-show="stage.type === 'group_stage'" x-transition class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.groups') }}</label>
                                        <input type="number"
                                               :name="'format_structure[stages][' + index + '][group_count]'"
                                               x-model="stage.group_count"
                                               :disabled="stage.type !== 'group_stage'"
                                               min="1"
                                               max="512"
                                               placeholder="8"
                                               class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.teams_per_group') }}</label>
                                        <input type="number"
                                               :name="'format_structure[stages][' + index + '][teams_per_group]'"
                                               x-model="stage.teams_per_group"
                                               :disabled="stage.type !== 'group_stage'"
                                               min="1"
                                               max="512"
                                               placeholder="4"
                                               class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.group_top') }}</label>
                                        <input type="number"
                                               :name="'format_structure[stages][' + index + '][advance_count]'"
                                               x-model="stage.advance_count"
                                               :disabled="stage.type !== 'group_stage'"
                                               min="1"
                                               max="4096"
                                               placeholder="32"
                                               class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                    </div>
                                </div>

                                <div x-show="stage.type === 'swiss_round'" x-transition class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.swiss_rounds') }}</label>
                                        <input type="number"
                                               :name="'format_structure[stages][' + index + '][round_count]'"
                                               x-model="stage.round_count"
                                               :disabled="stage.type !== 'swiss_round'"
                                               min="1"
                                               max="64"
                                               placeholder="5"
                                               class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.swiss_top') }}</label>
                                        <input type="number"
                                               :name="'format_structure[stages][' + index + '][advance_count]'"
                                               x-model="stage.advance_count"
                                               :disabled="stage.type !== 'swiss_round'"
                                               min="1"
                                               max="4096"
                                               placeholder="16"
                                               class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                    </div>
                                </div>

                                <div x-show="stage.type === 'bracket'" x-transition class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.elimination') }}</label>
                                        <select :name="'format_structure[stages][' + index + '][elimination_type]'"
                                                x-model="stage.elimination_type"
                                                @change="if (stage.elimination_type === 'single_elimination') stage.entry_type = 'winner_only'"
                                                :disabled="stage.type !== 'bracket'"
                                                class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                            @foreach($bracketEliminationStyles as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.bracket_round') }}</label>
                                        <select :name="'format_structure[stages][' + index + '][start_round_size]'"
                                                x-model="stage.start_round_size"
                                                :disabled="stage.type !== 'bracket'"
                                                class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                            <option value="">{{ __('tournaments.format.form.select_round') }}</option>
                                            @foreach([4, 8, 16, 32, 64, 128, 256, 512, 1024] as $roundSize)
                                                <option value="{{ $roundSize }}">Ro{{ $roundSize }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div x-show="stage.elimination_type !== 'single_elimination'" x-transition>
                                        <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.bracket_entry') }}</label>
                                        <select :name="'format_structure[stages][' + index + '][entry_type]'"
                                                x-model="stage.entry_type"
                                                :disabled="stage.type !== 'bracket' || stage.elimination_type === 'single_elimination'"
                                                class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                            <option value="winner_only">{{ __('tournaments.format.form.winner_only') }}</option>
                                            <option value="winner_loser_hybrid">{{ __('tournaments.format.form.winner_loser_hybrid') }}</option>
                                        </select>
                                    </div>
                                    <input type="hidden"
                                           :name="'format_structure[stages][' + index + '][entry_type]'"
                                           value="winner_only"
                                           :disabled="stage.type !== 'bracket' || stage.elimination_type !== 'single_elimination'">
                                </div>

                                <div x-show="stage.type === 'battle_royale'" x-transition class="mt-4 space-y-3">
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                        <div>
                                            <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.lobbies') }}</label>
                                            <input type="number"
                                                   :name="'format_structure[stages][' + index + '][lobby_count]'"
                                                   x-model="stage.lobby_count"
                                                   :disabled="stage.type !== 'battle_royale'"
                                                   min="1"
                                                   max="512"
                                                   placeholder="4"
                                                   class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.players_per_lobby') }}</label>
                                            <input type="number"
                                                   :name="'format_structure[stages][' + index + '][players_per_lobby]'"
                                                   x-model="stage.players_per_lobby"
                                                   :disabled="stage.type !== 'battle_royale'"
                                                   min="1"
                                                   max="512"
                                                   placeholder="16"
                                                   class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.advance_per_lobby') }}</label>
                                            <input type="number"
                                                   :name="'format_structure[stages][' + index + '][advance_per_lobby]'"
                                                   x-model="stage.advance_per_lobby"
                                                   :disabled="stage.type !== 'battle_royale'"
                                                   min="1"
                                                   max="512"
                                                   placeholder="4"
                                                   class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold mb-1 text-[var(--admin-muted)]">{{ __('tournaments.format.form.eliminated_per_map') }}</label>
                                            <input type="number"
                                                   :name="'format_structure[stages][' + index + '][eliminated_per_map]'"
                                                   x-model="stage.eliminated_per_map"
                                                   :disabled="stage.type !== 'battle_royale'"
                                                   min="1"
                                                   max="512"
                                                   placeholder="1"
                                                   class="w-full px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)]">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </template>

                        @error('format_structure')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @once
                    <script>
                        window.normalizeAdminTournamentDateFields = function(form) {
                            form.querySelectorAll('[data-admin-datetime-field]').forEach((field) => {
                                const hidden = field.querySelector('input[type="hidden"]');
                                const dateInput = field.querySelector('[data-admin-datetime-date]');
                                const timeInput = field.querySelector('[data-admin-datetime-time]');

                                if (!hidden || !dateInput || !timeInput) {
                                    return;
                                }

                                hidden.value = dateInput.value
                                    ? (timeInput.value ? `${dateInput.value}T${timeInput.value}` : dateInput.value)
                                    : '';
                            });
                        };

                        document.addEventListener('input', (event) => {
                            const field = event.target.closest?.('[data-admin-datetime-field]');
                            const form = field?.closest('form');

                            if (form) {
                                window.normalizeAdminTournamentDateFields(form);
                            }
                        });

                        document.addEventListener('alpine:init', () => {
                            Alpine.data('formatProgressionEditor', (initialStages = []) => ({
                                stages: [],
                                nextKey: 1,
                                draggingIndex: null,

                                init() {
                                    const sourceStages = Array.isArray(initialStages) && initialStages.length > 0
                                        ? initialStages
                                        : [{ type: 'qualifier' }, { type: 'group_stage' }];

                                    this.stages = sourceStages.map((stage) => this.hydrateStage(stage));
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

                <x-admin.tournaments.regional-restrictions :tournament="$tournament" />

                <!-- Rank Range -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="rank_range_min" class="block text-sm font-semibold mb-2">
                            Rank Min
                        </label>
                        <input type="number"
                               name="rank_range_min"
                               id="rank_range_min"
                               value="{{ old('rank_range_min', $tournament->rank_range_min) }}"
                               min="1"
                               placeholder="e.g., 1"
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        @error('rank_range_min')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="rank_range_max" class="block text-sm font-semibold mb-2">
                            Rank Max
                        </label>
                        <input type="number"
                               name="rank_range_max"
                               id="rank_range_max"
                               value="{{ old('rank_range_max', $tournament->rank_range_max) }}"
                               min="1"
                               placeholder="e.g., 10000"
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        @error('rank_range_max')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Star Ratings -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="star_rating_first" class="block text-sm font-semibold mb-2">
                            First Round SR <span class="text-xs text-[var(--admin-muted)] font-normal">(optional)</span>
                        </label>
                        <input type="number"
                               name="star_rating_first"
                               id="star_rating_first"
                               value="{{ old('star_rating_first', $tournament->star_rating_first) }}"
                               step="0.01"
                               min="0"
                               max="10"
                               placeholder="e.g., 3.5"
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        @error('star_rating_first')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="star_rating_last" class="block text-sm font-semibold mb-2">
                            Last Round SR <span class="text-xs text-[var(--admin-muted)] font-normal">(optional)</span>
                        </label>
                        <input type="number"
                               name="star_rating_last"
                               id="star_rating_last"
                               value="{{ old('star_rating_last', $tournament->star_rating_last) }}"
                               step="0.01"
                               min="0"
                               max="10"
                               placeholder="e.g., 6.0"
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        @error('star_rating_last')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="star_rating_qualifier" class="block text-sm font-semibold mb-2">
                            Qualifier SR <span class="text-xs text-[var(--admin-muted)] font-normal">(optional)</span>
                        </label>
                        <input type="number"
                               name="star_rating_qualifier"
                               id="star_rating_qualifier"
                               value="{{ old('star_rating_qualifier', $tournament->star_rating_qualifier) }}"
                               step="0.01"
                               min="0"
                               max="10"
                               placeholder="e.g., 4.0"
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        @error('star_rating_qualifier')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="registration_start_date" class="block text-sm font-semibold mb-2">
                            Registration Start
                        </label>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]" data-admin-datetime-field="registration_start">
                            <input type="hidden" name="registration_start" value="{{ $adminDateTimeValue('registration_start') }}">
                            <input type="date"
                                   id="registration_start_date"
                                   value="{{ $adminDateValue('registration_start') }}"
                                   data-admin-datetime-date
                                   class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                            <input type="time"
                                   aria-label="Registration Start time"
                                   value="{{ $adminTimeValue('registration_start') }}"
                                   data-admin-datetime-time
                                   class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        </div>
                    </div>
                    <div>
                        <label for="registration_end_date" class="block text-sm font-semibold mb-2">
                            Registration End
                        </label>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]" data-admin-datetime-field="registration_end">
                            <input type="hidden" name="registration_end" value="{{ $adminDateTimeValue('registration_end') }}">
                            <input type="date"
                                   id="registration_end_date"
                                   value="{{ $adminDateValue('registration_end') }}"
                                   data-admin-datetime-date
                                   class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                            <input type="time"
                                   aria-label="Registration End time"
                                   value="{{ $adminTimeValue('registration_end') }}"
                                   data-admin-datetime-time
                                   class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="tournament_start_date" class="block text-sm font-semibold mb-2">
                            Tournament Start
                        </label>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]" data-admin-datetime-field="tournament_start">
                            <input type="hidden" name="tournament_start" value="{{ $adminDateTimeValue('tournament_start') }}">
                            <input type="date"
                                   id="tournament_start_date"
                                   value="{{ $adminDateValue('tournament_start') }}"
                                   data-admin-datetime-date
                                   class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                            <input type="time"
                                   aria-label="Tournament Start time"
                                   value="{{ $adminTimeValue('tournament_start') }}"
                                   data-admin-datetime-time
                                   class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        </div>
                    </div>
                    <div>
                        <label for="tournament_end_date" class="block text-sm font-semibold mb-2">
                            Tournament End
                        </label>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]" data-admin-datetime-field="tournament_end">
                            <input type="hidden" name="tournament_end" value="{{ $adminDateTimeValue('tournament_end') }}">
                            <input type="date"
                                   id="tournament_end_date"
                                   value="{{ $adminDateValue('tournament_end') }}"
                                   data-admin-datetime-date
                                   class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                            <input type="time"
                                   aria-label="Tournament End time"
                                   value="{{ $adminTimeValue('tournament_end') }}"
                                   data-admin-datetime-time
                                   class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                        </div>
                    </div>
                </div>

    {{ $slot }}
                <!-- Flags & BWS Configuration -->
                <div x-data="{
                    bwsChecked: @js(old('is_bws', $tournament->is_bws))
                }">
                    <!-- Flags -->
                    <div class="space-y-3 mb-4">
                        <label class="flex items-center space-x-3 p-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg cursor-pointer hover:border-[var(--osu-pink)] transition-colors">
                            <input type="hidden" name="is_badge" value="0">
                            <input type="checkbox"
                                   name="is_badge"
                                   value="1"
                                   {{ old('is_badge', $tournament->is_badge) ? 'checked' : '' }}
                                   @change="$dispatch('admin-badge-toggle', { checked: $event.target.checked })"
                                   class="w-5 h-5 text-[var(--osu-pink)] bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded focus:ring-[var(--osu-pink)]">
                            <div>
                                <span class="font-semibold">Badge Tournament</span>
                                <p class="text-xs text-[var(--admin-muted)]">Grants profile badge to participants</p>
                            </div>
                        </label>

                        <label class="flex items-center space-x-3 p-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg cursor-pointer hover:border-[var(--osu-cyan)] transition-colors">
                            <input type="hidden" name="is_bws" value="0">
                            <input type="checkbox"
                                   name="is_bws"
                                   value="1"
                                   x-model="bwsChecked"
                                   class="w-5 h-5 text-[var(--osu-cyan)] bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded focus:ring-[var(--osu-cyan)]">
                            <div>
                                <span class="font-semibold">BWS Ranking</span>
                                <p class="text-xs text-[var(--admin-muted)]">Uses Badge Weighted Seeding</p>
                            </div>
                        </label>
                    </div>

                    <!-- BWS Configuration -->
                    <div x-show="bwsChecked" x-transition class="bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)] p-6">
                        <h3 class="text-lg font-bold mb-4" style="font-family: 'Outfit', sans-serif;">
                            ⚖️ BWS Configuration
                        </h3>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <label for="bws_base_exponent" class="block text-sm font-semibold mb-2">
                                    Base Exponent (x)
                                    <span class="text-xs text-[var(--admin-muted)] font-normal">default: 0.9937</span>
                                </label>
                                <input type="number"
                                       step="0.0001"
                                       min="0.9"
                                       max="1.0"
                                       name="bws_base_exponent"
                                       id="bws_base_exponent"
                                       value="{{ old('bws_base_exponent', $tournament->bws_base_exponent ?? $defaultBwsBaseExponent) }}"
                                       class="w-full px-4 py-3 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)] focus:border-transparent text-[var(--admin-text)]">
                            </div>

                            <div>
                                <label for="bws_badge_power" class="block text-sm font-semibold mb-2">
                                    Badge Power (y)
                                    <span class="text-xs text-[var(--admin-muted)] font-normal">default: 2.0</span>
                                </label>
                                <input type="number"
                                       step="0.1"
                                       min="1"
                                       max="5"
                                       name="bws_badge_power"
                                       id="bws_badge_power"
                                       value="{{ old('bws_badge_power', $tournament->bws_badge_power ?? $defaultBwsBadgePower) }}"
                                       class="w-full px-4 py-3 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)] focus:border-transparent text-[var(--admin-text)]">
                            </div>

                            <div>
                                <label for="bws_divisor" class="block text-sm font-semibold mb-2">
                                    Divisor (z)
                                    <span class="text-xs text-[var(--admin-muted)] font-normal">default: 1.0</span>
                                </label>
                                <input type="number"
                                       step="0.1"
                                       min="0.1"
                                       max="10"
                                       name="bws_divisor"
                                       id="bws_divisor"
                                       value="{{ old('bws_divisor', $tournament->bws_divisor ?? $defaultBwsDivisor) }}"
                                       class="w-full px-4 py-3 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)] focus:border-transparent text-[var(--admin-text)]">
                            </div>

                            <div>
                                <label for="bws_badge_age_cutoff" class="block text-sm font-semibold mb-2">
                                    Badge Cutoff Date
                                    <span class="text-xs text-[var(--admin-muted)] font-normal">badges after this date count</span>
                                </label>
                                <input type="date"
                                       name="bws_badge_age_cutoff"
                                       id="bws_badge_age_cutoff"
                                       value="{{ old('bws_badge_age_cutoff', $tournament->bws_badge_age_cutoff?->format('Y-m-d')) }}"
                                       class="w-full px-4 py-3 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)] focus:border-transparent text-[var(--admin-text)]">
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-[var(--admin-muted)]">
                            💡 Formula: <code class="px-2 py-1 bg-[var(--admin-surface)] rounded">rank^(base^(badges^power) / divisor)</code>
                        </p>
                    </div>
                </div>

                <!-- URLs -->
                <div class="space-y-4">
                    <div>
                        <label for="forum_post_url" class="block text-sm font-semibold mb-2">
                            Forum Post URL
                        </label>
                        <input type="url"
                               name="forum_post_url"
                               id="forum_post_url"
                               value="{{ old('forum_post_url', $tournament->forum_post_url) }}"
                               placeholder="https://osu.ppy.sh/community/forums/topics/..."
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)] font-mono text-sm">
                    </div>

                    <div>
                        <label for="spreadsheet_url" class="block text-sm font-semibold mb-2">
                            Spreadsheet URL
                        </label>
                        <input type="url"
                               name="spreadsheet_url"
                               id="spreadsheet_url"
                               value="{{ old('spreadsheet_url', $tournament->spreadsheet_url) }}"
                               placeholder="https://docs.google.com/spreadsheets/..."
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)] font-mono text-sm">
                    </div>

                    <div>
                        <label for="discord_url" class="block text-sm font-semibold mb-2">
                            Discord URL
                        </label>
                        <input type="url"
                               name="discord_url"
                               id="discord_url"
                               value="{{ old('discord_url', $tournament->discord_url) }}"
                               placeholder="https://discord.gg/..."
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)] font-mono text-sm">
                    </div>

                    <div>
                        <label for="twitch_url" class="block text-sm font-semibold mb-2">
                            Twitch URL
                        </label>
                        <input type="url"
                               name="twitch_url"
                               id="twitch_url"
                               value="{{ old('twitch_url', $tournament->twitch_url) }}"
                               placeholder="https://twitch.tv/..."
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)] font-mono text-sm">
                    </div>

                    <div>
                        <label for="registration_url" class="block text-sm font-semibold mb-2">
                            Registration URL <span class="text-xs text-[var(--admin-muted)] font-normal">(player registration form)</span>
                        </label>
                        <input type="url"
                               name="registration_url"
                               id="registration_url"
                               value="{{ old('registration_url', $tournament->registration_url) }}"
                               placeholder="https://docs.google.com/forms/..."
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)] font-mono text-sm">
                    </div>

                    <div>
                        <label for="bracket_url" class="block text-sm font-semibold mb-2">
                            Bracket URL <span class="text-xs text-[var(--admin-muted)] font-normal">(challonge, bracket, etc.)</span>
                        </label>
                        <input type="url"
                               name="bracket_url"
                               id="bracket_url"
                               value="{{ old('bracket_url', $tournament->bracket_url) }}"
                               placeholder="https://challonge.com/..."
                               class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)] font-mono text-sm">
                    </div>
                </div>


    @if($isCreate)
        <div class="flex items-center space-x-4 pt-6">
            <a href="{{ route('admin.tournaments.pending') }}"
               class="flex-1 px-6 py-3 rounded-lg border-2 border-[var(--admin-border)] text-[var(--admin-text)] font-semibold text-center hover:bg-[var(--admin-bg)] transition-all">
                Cancel
            </a>
            <button type="submit"
                    @click="saving = true"
                    class="flex-[2] px-6 py-3 rounded-lg bg-[var(--osu-pink)] text-white font-semibold hover:brightness-110 transition-all shadow-lg flex items-center justify-center space-x-2">
                <x-icon name="lucide-loader-circle" x-show="saving" class="animate-spin h-5 w-5 mr-2" />
                <span x-text="saving ? 'Creating...' : '🚀 Create Tournament'"></span>
            </button>
        </div>
    @else
        <!-- Save Draft Button -->
        <button type="submit"
                :disabled="saving"
                @click.prevent="
                    saving = true;
                    saveSuccess = false;
                    saveError = null;
                    const form = $el.closest('form');
                    normalizeAdminTournamentDateFields(form);
                    const formData = new FormData(form);

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (response.ok) {
                            return response.json().then(data => {
                                if (data.message === 'Tournament updated successfully') {
                                    saveSuccess = true;
                                    setTimeout(() => { saveSuccess = false; }, 3000);
                                } else {
                                    saveError = data.message || data.error || 'Failed to save draft';
                                }
                                saving = false;
                            });
                        } else if (response.status === 400 || response.status === 422) {
                            return response.json().then(data => {
                                const errorMessages = data.errors
                                    ? Object.values(data.errors).flat().join(', ')
                                    : data.message || 'Validation failed';
                                saveError = errorMessages;
                                saving = false;
                            });
                        } else {
                            response.json().then(data => {
                                saveError = data.message || data.error || `HTTP ${response.status} error`;
                                saving = false;
                            }).catch(() => {
                                saveError = `HTTP ${response.status} error`;
                                saving = false;
                            });
                        }
                    })
                    .catch(err => {
                        saveError = 'Network error. Please try again.';
                        saving = false;
                    });
                "
                class="w-full px-6 py-3 rounded-lg border-2 border-[var(--admin-border)] text-[var(--admin-text)] font-semibold hover:bg-[var(--admin-bg)] transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2">
            <x-icon name="lucide-loader-circle" x-show="saving" class="animate-spin h-4 w-4" />
            <x-icon name="lucide-circle-check" x-show="saveSuccess && !saving" class="h-4 w-4 text-green-500" />
            <span x-text="saving ? 'Saving...' : (saveSuccess ? 'Saved!' : '💾 Save Draft')"></span>
        </button>

        <div x-show="saveSuccess"
             x-transition
             class="mt-3 p-3 bg-green-500/10 border border-green-500/30 rounded-lg">
            <div class="flex items-center space-x-2">
                <x-icon name="lucide-circle-check" class="w-5 h-5 text-green-500 flex-shrink-0" />
                <span class="text-sm font-medium text-green-600">Draft saved successfully</span>
            </div>
        </div>

        <div x-show="saveError"
             x-transition
             class="mt-3 p-3 bg-red-500/10 border border-red-500/30 rounded-lg">
            <div class="flex items-start justify-between space-x-2">
                <div class="flex items-start space-x-2 flex-1">
                    <x-icon name="lucide-circle-x" class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-red-500">{{ __('admin.review.save_failed') }}</p>
                        <p class="text-sm text-red-400 mt-1" x-text="saveError"></p>
                    </div>
                </div>
                <button type="button" @click="saveError = null" class="text-red-500 hover:text-red-400">
                    <x-icon name="lucide-circle-x" class="w-5 h-5" />
                </button>
            </div>
        </div>
    @endif
</form>
