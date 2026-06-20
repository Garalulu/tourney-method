@extends('layouts.app')

@php
    $isCreation = $isCreation ?? false;
@endphp

@section('title')
    {{ $isCreation ? 'Add Tournament' : __('tournaments.corrections.create.title_with_tournament', ['title' => $tournament->title]) }}
@endsection

@section('content')
<div class="min-h-screen overflow-x-hidden bg-slate-950 py-6 sm:py-10">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ $isCreation ? route('tournaments.index') : route('tournaments.show', $tournament) }}" class="text-sm font-semibold text-pink-400 hover:text-pink-300">
                {{ $isCreation ? 'Back to tournaments' : __('tournaments.corrections.actions.back_to_tournament') }}
            </a>
            <h1 class="mt-3 text-3xl font-black text-white">{{ $isCreation ? 'Add Tournament' : __('tournaments.corrections.create.heading', ['title' => $tournament->title]) }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ $isCreation ? 'Submit the complete tournament details for selective admin review. Title and game modes are required.' : __('tournaments.corrections.create.description') }}</p>
        </div>

        @php
            $podiumBackfill = app(\App\Services\ParticipationPodiumBackfillService::class);
            $currentStaffByRole = collect($staffRoles)
                ->mapWithKeys(fn (string $role): array => [
                    $role => $tournament->staff
                        ->filter(fn ($user): bool => $user->pivot && $user->pivot->role === $role && $user->pivot->status === 'approved')
                        ->pluck('username')
                        ->sort(fn (string $a, string $b): int => strcasecmp($a, $b))
                        ->values()
                        ->all(),
                ])
                ->all();
            $podiumUserIds = $tournament->winners
                ->where('placement', '<=', 3)
                ->pluck('user_id')
                ->filter()
                ->values()
                ->all();
            $participationRecordsByUser = \App\Models\TournamentParticipationRecord::query()
                ->where('tournament_id', $tournament->id)
                ->whereIn('user_id', $podiumUserIds)
                ->get()
                ->keyBy('user_id');
            $currentPodiumGroupsByPlacement = collect([1, 2, 3])
                ->mapWithKeys(function (int $placement) use ($tournament, $participationRecordsByUser, $podiumBackfill): array {
                    $groups = $tournament->winners
                        ->filter(fn ($winner): bool => (int) $winner->placement === $placement)
                        ->groupBy(function ($winner) use ($placement, $podiumBackfill): string {
                            $groupId = $podiumBackfill->groupIdForWinner($winner);

                            return filled($groupId) ? (string) $groupId : 'legacy-placement-'.$placement;
                        })
                        ->values()
                        ->map(function ($groupWinners, int $index) use ($placement, $participationRecordsByUser, $podiumBackfill): array {
                            $firstWinner = $groupWinners->first();
                            $groupId = $firstWinner ? $podiumBackfill->groupIdForWinner($firstWinner) : null;
                            $groupKey = filled($groupId) ? (string) $groupId : 'legacy-placement-'.$placement;

                            return [
                                'group_key' => $groupKey,
                                'group_id' => $groupId,
                                'team_name' => $groupWinners
                                    ->map(fn ($winner) => $winner->user_id ? $participationRecordsByUser->get($winner->user_id)?->team_name : null)
                                    ->first(fn ($name): bool => filled($name)),
                                'usernames' => $groupWinners
                                    ->map(fn ($winner): string => (string) $winner->display_username)
                                    ->filter()
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->values()
                        ->all();

                    if ($groups === []) {
                        $groups = [[
                            'group_key' => 'new-placement-'.$placement.'-0',
                            'group_id' => null,
                            'team_name' => null,
                            'usernames' => [],
                        ]];
                    }

                    return [$placement => $groups];
                })
                ->all();
        @endphp

        <form id="tournament-correction-form" method="POST" action="{{ $isCreation ? route('tournaments.add.store') : route('tournaments.corrections.store', $tournament) }}" class="space-y-6" @if(!$isCreation) onsubmit="return window.confirm(@js(__('tournaments.corrections.create.confirm_submit')));" @endif>
            @csrf
            @if($isCreation)
                <input type="hidden" name="creation_confirmed" value="0">
                <input type="hidden" name="selected_tournament_id" value="">
            @endif

            <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/70 p-4 sm:p-5">
                <label class="block space-y-2">
                    <span class="text-sm font-semibold text-slate-300">{{ __('tournaments.corrections.fields.submitter_note') }}</span>
                    <textarea name="submitter_note" rows="4" maxlength="1000" placeholder="{{ __('tournaments.corrections.placeholders.submitter_note') }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">{{ old('submitter_note') }}</textarea>
                    <span class="block text-xs leading-5 text-slate-500">{{ __('tournaments.corrections.help.submitter_note') }}</span>
                </label>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/70 p-4 sm:p-5">
                <h2 class="text-lg font-bold text-white">{{ __('tournaments.corrections.sections.metadata') }}</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="min-w-0 space-y-2 md:col-span-2">
                        <span class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-slate-300">
                            {{ __('tournaments.corrections.fields.banner_url') }}
                            <x-field-tooltip :text="__('tournaments.corrections.tooltips.banner_url')" />
                        </span>
                        <input type="url" name="banner_url" value="{{ old('banner_url', $tournament->banner_url) }}" placeholder="https://example.com/banner.jpg" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>

                    <label class="min-w-0 space-y-2 md:col-span-2">
                        <span class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-slate-300">
                            {{ __('tournaments.corrections.fields.title') }}
                            <x-field-tooltip :text="__('tournaments.corrections.tooltips.title')" />
                        </span>
                        <input name="title" value="{{ old('title', $tournament->title) }}" maxlength="256" @required($isCreation) class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>

                    <x-tournaments.correction-mode-selector :tournament="$tournament" />

                    <div class="grid gap-4 sm:grid-cols-3 md:col-span-2">
                        <label class="space-y-2">
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                                {{ __('tournaments.corrections.fields.vs_size') }}
                                <x-field-tooltip :text="__('tournaments.corrections.tooltips.vs_size')" />
                            </span>
                            <input type="number" name="vs_size" value="{{ old('vs_size', $tournament->vs_size) }}" min="1" max="16" placeholder="e.g., 1" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        </label>
                        <label class="space-y-2">
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                                {{ __('tournaments.corrections.fields.team_size_min') }}
                                <x-field-tooltip :text="__('tournaments.corrections.tooltips.team_size_min')" />
                            </span>
                            <input type="number" name="team_size_min" value="{{ old('team_size_min', $tournament->team_size_min) }}" min="1" max="16" placeholder="e.g., 1" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        </label>
                        <label class="space-y-2">
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                                {{ __('tournaments.corrections.fields.team_size_max') }}
                                <x-field-tooltip :text="__('tournaments.corrections.tooltips.team_size_max')" />
                            </span>
                            <input type="number" name="team_size_max" value="{{ old('team_size_max', $tournament->team_size_max) }}" min="1" max="16" placeholder="e.g., 4" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        </label>
                    </div>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/70 p-4 sm:p-5">
                <h2 class="flex min-w-0 flex-wrap items-center gap-2 text-lg font-bold text-white">
                    {{ __('tournaments.format.form.section_title') }}
                    <x-field-tooltip :text="__('tournaments.format.tooltips.section')" />
                </h2>
                <div class="mt-4">
                    <x-tournaments.correction-format-progression-editor :tournament="$tournament" />
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/70 p-4 sm:p-5">
                <h2 class="text-lg font-bold text-white">{{ __('tournaments.corrections.sections.eligibility_schedule') }}</h2>
                <div class="mt-4 grid min-w-0 gap-4 md:grid-cols-2">
                    <x-tournaments.correction-regional-restrictions :tournament="$tournament" />

                    <label class="space-y-2">
                        <span class="text-sm font-semibold text-slate-300">{{ __('tournaments.corrections.fields.rank_min') }}</span>
                        <input type="number" name="rank_range_min" value="{{ old('rank_range_min', $tournament->rank_range_min) }}" min="1" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        <span class="block text-xs leading-5 text-slate-500">{{ __('tournaments.corrections.help.rank_min') }}</span>
                    </label>
                    <label class="space-y-2">
                        <span class="text-sm font-semibold text-slate-300">{{ __('tournaments.corrections.fields.rank_max') }}</span>
                        <input type="number" name="rank_range_max" value="{{ old('rank_range_max', $tournament->rank_range_max) }}" min="1" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        <span class="block text-xs leading-5 text-slate-500">{{ __('tournaments.corrections.help.rank_max') }}</span>
                    </label>

                    @foreach([
                        'registration_start' => __('tournaments.corrections.fields.registration_start'),
                        'registration_end' => __('tournaments.corrections.fields.registration_end'),
                        'tournament_start' => __('tournaments.corrections.fields.tournament_start'),
                        'tournament_end' => __('tournaments.corrections.fields.tournament_end'),
                    ] as $field => $label)
                        <label class="min-w-0 space-y-2">
                        <span class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-slate-300">
                            {{ $label }}
                            <x-field-tooltip :text="__('tournaments.corrections.tooltips.'.$field)" />
                        </span>
                            <input type="datetime-local" name="{{ $field }}" value="{{ old($field, $tournament->{$field}?->copy()->utc()->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        </label>
                    @endforeach

                    <label class="space-y-2">
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                            {{ __('tournaments.corrections.fields.qualifier_sr') }}
                            <x-field-tooltip :text="__('tournaments.corrections.tooltips.qualifier_sr')" />
                        </span>
                        <input type="number" step="0.01" min="0" max="10" name="star_rating_qualifier" value="{{ old('star_rating_qualifier', $tournament->star_rating_qualifier) }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>
                    <label class="space-y-2">
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                            {{ __('tournaments.corrections.fields.first_round_sr') }}
                            <x-field-tooltip :text="__('tournaments.corrections.tooltips.first_round_sr')" />
                        </span>
                        <input type="number" step="0.01" min="0" max="10" name="star_rating_first" value="{{ old('star_rating_first', $tournament->star_rating_first) }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>
                    <label class="space-y-2">
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                            {{ __('tournaments.corrections.fields.last_round_sr') }}
                            <x-field-tooltip :text="__('tournaments.corrections.tooltips.last_round_sr')" />
                        </span>
                        <input type="number" step="0.01" min="0" max="10" name="star_rating_last" value="{{ old('star_rating_last', $tournament->star_rating_last) }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/70 p-4 sm:p-5">
                <h2 class="text-lg font-bold text-white">{{ __('tournaments.corrections.sections.flags_bws') }}</h2>
                <div class="mt-4">
                    <x-tournaments.correction-bws-config :tournament="$tournament" />
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/70 p-4 sm:p-5">
                <h2 class="text-lg font-bold text-white">{{ __('tournaments.corrections.sections.links_badges') }}</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach([
                        'forum_post_url' => [__('tournaments.corrections.fields.forum_post_url'), 'https://osu.ppy.sh/community/forums/topics/...'],
                        'spreadsheet_url' => [__('tournaments.corrections.fields.spreadsheet_url'), 'https://docs.google.com/spreadsheets/...'],
                        'discord_url' => [__('tournaments.corrections.fields.discord_url'), 'https://discord.gg/...'],
                        'twitch_url' => [__('tournaments.corrections.fields.twitch_url'), 'https://twitch.tv/...'],
                        'registration_url' => [__('tournaments.corrections.fields.registration_url'), 'https://docs.google.com/forms/...'],
                        'bracket_url' => [__('tournaments.corrections.fields.bracket_url'), 'https://challonge.com/...'],
                    ] as $field => [$label, $placeholder])
                        <label class="space-y-2">
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                                {{ $label }}
                                <x-field-tooltip :text="__('tournaments.corrections.tooltips.'.$field)" />
                            </span>
                            <input type="url" name="{{ $field }}" value="{{ old($field, $tournament->{$field}) }}" placeholder="{{ $placeholder }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-sm text-white">
                        </label>
                    @endforeach

                    <label class="space-y-2">
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                            {{ __('tournaments.corrections.fields.badge_status') }}
                            <x-field-tooltip :text="__('tournaments.corrections.tooltips.badge_status')" />
                        </span>
                        <select name="badge_status" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                            <option value="">{{ __('tournaments.corrections.values.no_change_none') }}</option>
                            @foreach(['pending', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected(old('badge_status', $tournament->badge_status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="md:col-span-2">
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                            {{ __('tournaments.corrections.fields.badge_urls') }}
                            <x-field-tooltip :text="__('tournaments.corrections.tooltips.badge_urls')" />
                        </span>
                        <div class="mt-2 grid gap-4 md:grid-cols-3">
                            @foreach([1, 2, 3] as $placement)
                                @php
                                    $existingBadgeUrls = $tournament->badge_urls[$placement] ?? $tournament->badge_urls[(string) $placement] ?? [];
                                    $oldBadgeUrls = old("badge_urls.{$placement}.0");
                                    if ($oldBadgeUrls === null && is_array(old("badge_urls.{$placement}"))) {
                                        $oldBadgeUrls = collect(old("badge_urls.{$placement}"))->implode("\n");
                                    }
                                @endphp
                                <label class="space-y-2">
                                    <span class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                        {{ __('tournaments.corrections.podium.placement_label', ['placement' => $placement]) }}
                                        <x-field-tooltip :text="__('tournaments.corrections.tooltips.badge_urls_placement')" />
                                    </span>
                                    <textarea name="badge_urls[{{ $placement }}][]" rows="4" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-sm text-white">{{ $oldBadgeUrls ?? collect($existingBadgeUrls)->implode("\n") }}</textarea>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/70 p-4 sm:p-5">
                <h2 class="text-lg font-bold text-white">{{ __('tournaments.corrections.sections.staff') }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ __('tournaments.corrections.staff.description') }}</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach($staffRoles as $role)
                        <label class="space-y-2">
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                                {{ \App\Helpers\StaffRoleHelper::getRoleLabel($role) }}
                                <x-field-tooltip :text="__('tournaments.corrections.tooltips.staff_role')" />
                            </span>
                            <textarea name="staff_{{ $role }}" rows="4" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">{{ old("staff_{$role}", implode("\n", $currentStaffByRole[$role] ?? [])) }}</textarea>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/70 p-4 sm:p-5">
                <h2 class="text-lg font-bold text-white">{{ __('tournaments.corrections.sections.podium') }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ __('tournaments.corrections.podium.description') }}</p>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    @foreach([1, 2, 3] as $placement)
                        <div class="space-y-3" data-podium-placement="{{ $placement }}">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="flex items-center gap-2 text-sm font-bold uppercase text-slate-300">
                                    {{ __('tournaments.corrections.podium.placement_heading', ['placement' => $placement]) }}
                                    <x-field-tooltip :text="__('tournaments.corrections.tooltips.podium_placement')" />
                                </h3>
                                <button type="button" data-add-podium-group="{{ $placement }}" class="rounded border border-slate-700 px-2 py-1 text-xs font-semibold text-pink-300 hover:bg-slate-800">
                                    {{ __('tournaments.corrections.podium.add_team') }}
                                </button>
                            </div>
                            <div class="space-y-3" data-podium-group-list="{{ $placement }}">
                                @foreach($currentPodiumGroupsByPlacement[$placement] ?? [] as $groupIndex => $group)
                                    <div class="rounded-lg border border-slate-800 bg-slate-950 p-3" data-podium-group>
                                        <input type="hidden" name="podium_groups[{{ $placement }}][{{ $groupIndex }}][group_key]" value="{{ old("podium_groups.{$placement}.{$groupIndex}.group_key", $group['group_key'] ?? null) }}">
                                        <input type="hidden" name="podium_groups[{{ $placement }}][{{ $groupIndex }}][group_id]" value="{{ old("podium_groups.{$placement}.{$groupIndex}.group_id", $group['group_id'] ?? null) }}">
                                        <div class="mb-3 flex items-center justify-between gap-2">
                                            <span class="text-xs font-semibold uppercase text-slate-500">{{ __('tournaments.corrections.podium.team_number', ['number' => $groupIndex + 1]) }}</span>
                                            <button type="button" data-remove-podium-group class="rounded px-2 py-1 text-xs font-semibold text-red-300 hover:bg-red-500/10">
                                                {{ __('tournaments.corrections.podium.remove_team') }}
                                            </button>
                                        </div>
                                        <label class="space-y-2">
                                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                                                {{ __('tournaments.corrections.podium.team_name') }}
                                                <x-field-tooltip :text="__('tournaments.corrections.tooltips.podium_team_name')" />
                                            </span>
                                            <input name="podium_groups[{{ $placement }}][{{ $groupIndex }}][team_name]" value="{{ old("podium_groups.{$placement}.{$groupIndex}.team_name", $group['team_name'] ?? null) }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                                        </label>
                                        <label class="mt-3 block space-y-2">
                                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                                                {{ __('tournaments.corrections.podium.usernames') }}
                                                <x-field-tooltip :text="__('tournaments.corrections.tooltips.podium_usernames')" />
                                            </span>
                                            <textarea name="podium_groups[{{ $placement }}][{{ $groupIndex }}][usernames]" rows="5" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">{{ old("podium_groups.{$placement}.{$groupIndex}.usernames", implode("\n", $group['usernames'] ?? [])) }}</textarea>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ $isCreation ? route('tournaments.index') : route('tournaments.show', $tournament) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-700 px-4 py-2 font-semibold text-slate-200 hover:bg-slate-800">{{ __('tournaments.corrections.actions.cancel') }}</a>
                <button class="min-h-11 rounded-lg bg-pink-500 px-5 py-2 font-semibold text-white hover:brightness-110">{{ $isCreation ? 'Submit Tournament Request' : __('tournaments.corrections.actions.submit') }}</button>
            </div>
        </form>

        @if($isCreation)
            <div data-creation-confirm-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
                <div class="w-full max-w-xl rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                    <h2 class="text-xl font-bold text-white">Confirm tournament request</h2>
                    <p data-confirm-description class="mt-2 text-sm text-slate-300">Create a separate new tournament request?</p>
                    <div data-duplicate-options class="mt-4 space-y-3"></div>
                    <p data-confirm-error class="mt-3 hidden text-sm text-red-300"></p>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" data-confirm-cancel class="rounded-lg border border-slate-700 px-4 py-2 font-semibold text-slate-200 hover:bg-slate-800">Cancel</button>
                        <button type="button" data-confirm-submit class="rounded-lg bg-pink-500 px-5 py-2 font-semibold text-white hover:brightness-110">Confirm and Submit</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<template id="podium-group-template">
    <div class="rounded-lg border border-slate-800 bg-slate-950 p-3" data-podium-group>
        <input type="hidden" data-name-template="podium_groups[__PLACEMENT__][__INDEX__][group_key]" value="new-placement-__PLACEMENT__-__INDEX__">
        <input type="hidden" data-name-template="podium_groups[__PLACEMENT__][__INDEX__][group_id]" value="">
        <div class="mb-3 flex items-center justify-between gap-2">
            <span class="text-xs font-semibold uppercase text-slate-500" data-team-number>{{ __('tournaments.corrections.podium.team_number', ['number' => '__NUMBER__']) }}</span>
            <button type="button" data-remove-podium-group class="rounded px-2 py-1 text-xs font-semibold text-red-300 hover:bg-red-500/10">
                {{ __('tournaments.corrections.podium.remove_team') }}
            </button>
        </div>
        <label class="space-y-2">
            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                {{ __('tournaments.corrections.podium.team_name') }}
                <x-field-tooltip :text="__('tournaments.corrections.tooltips.podium_team_name')" />
            </span>
            <input data-name-template="podium_groups[__PLACEMENT__][__INDEX__][team_name]" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        </label>
        <label class="mt-3 block space-y-2">
            <span class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                {{ __('tournaments.corrections.podium.usernames') }}
                <x-field-tooltip :text="__('tournaments.corrections.tooltips.podium_usernames')" />
            </span>
            <textarea data-name-template="podium_groups[__PLACEMENT__][__INDEX__][usernames]" rows="5" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"></textarea>
        </label>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const template = document.getElementById('podium-group-template');
        if (!template) return;

        const renumber = (placement) => {
            const list = document.querySelector(`[data-podium-group-list="${placement}"]`);
            if (!list) return;

            list.querySelectorAll('[data-podium-group]').forEach((group, index) => {
                group.querySelectorAll('[name], [data-name-template]').forEach((input) => {
                    const templateName = input.getAttribute('data-name-template') || input.name;
                    input.name = templateName
                        .replaceAll('__PLACEMENT__', placement)
                        .replaceAll('__INDEX__', index);
                    input.setAttribute('data-name-template', templateName);
                });

                const groupKeyInput = group.querySelector(`input[name="podium_groups[${placement}][${index}][group_key]"]`);
                if (groupKeyInput && groupKeyInput.value.includes('__INDEX__')) {
                    groupKeyInput.value = `new-placement-${placement}-${index}`;
                }

                const label = group.querySelector('[data-team-number]');
                if (label) {
                    label.textContent = @js(__('tournaments.corrections.podium.team_number', ['number' => ':number'])).replace(':number', index + 1);
                }
            });
        };

        document.querySelectorAll('[data-add-podium-group]').forEach((button) => {
            button.addEventListener('click', () => {
                const placement = button.dataset.addPodiumGroup;
                const list = document.querySelector(`[data-podium-group-list="${placement}"]`);
                if (!list) return;

                const fragment = template.content.cloneNode(true);
                list.appendChild(fragment);
                renumber(placement);
            });
        });

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-podium-group]');
            if (!button) return;

            const placementContainer = button.closest('[data-podium-placement]');
            const group = button.closest('[data-podium-group]');
            if (!placementContainer || !group) return;

            group.remove();
            renumber(placementContainer.dataset.podiumPlacement);
        });
    });
</script>
@if($isCreation)
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('tournament-correction-form');
        const modal = document.querySelector('[data-creation-confirm-modal]');
        const options = modal?.querySelector('[data-duplicate-options]');
        const description = modal?.querySelector('[data-confirm-description]');
        const error = modal?.querySelector('[data-confirm-error]');
        const confirmed = form?.querySelector('[name="creation_confirmed"]');
        const selected = form?.querySelector('[name="selected_tournament_id"]');
        let submitting = false;

        if (!form || !modal || !options || !description || !error || !confirmed || !selected) return;

        const showModal = () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };
        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };
        const radioOption = (value, label, checked = false) => {
            const wrapper = document.createElement('label');
            wrapper.className = 'flex gap-3 rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm text-slate-200';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'duplicate_confirmation_choice';
            radio.value = value;
            radio.checked = checked;
            radio.className = 'mt-0.5';
            const content = document.createElement('span');
            content.append(label);
            wrapper.append(radio, content);
            return wrapper;
        };

        form.addEventListener('submit', async (event) => {
            if (submitting) return;
            event.preventDefault();
            error.classList.add('hidden');
            options.textContent = '';

            try {
                const response = await fetch(@js(route('tournaments.add.check-duplicates')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    },
                    body: new FormData(form),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data?.message || 'Unable to check for duplicates.');

                if (data.pending) {
                    description.textContent = `This topic already belongs to pending tournament “${data.pending.title}”. The request will be submitted as its correction.`;
                    options.append(radioOption(String(data.pending.id), 'Submit as correction to the pending tournament', true));
                } else if (data.approved.length > 0) {
                    description.textContent = 'Approved tournaments already use this topic. Select one to correct, or continue with a separate tournament request.';
                    data.approved.forEach((tournament, index) => {
                        const label = document.createElement('span');
                        label.append('Correct ');
                        const link = document.createElement('a');
                        link.href = tournament.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.className = 'font-semibold text-cyan-300 hover:underline';
                        link.textContent = tournament.title;
                        label.append(link);
                        options.append(radioOption(String(tournament.id), label, index === 0));
                    });
                    options.append(radioOption('', 'Continue with a separate new tournament request'));
                } else {
                    description.textContent = 'Create a separate new tournament request for admin review?';
                    options.append(radioOption('', 'Create new tournament request', true));
                }

                showModal();
            } catch (exception) {
                error.textContent = exception.message;
                error.classList.remove('hidden');
                showModal();
            }
        });

        modal.querySelectorAll('[data-confirm-cancel]').forEach((button) => button.addEventListener('click', closeModal));
        modal.querySelector('[data-confirm-submit]').addEventListener('click', () => {
            const choice = options.querySelector('input[name="duplicate_confirmation_choice"]:checked');
            if (!choice) {
                error.textContent = 'Choose how to submit this request.';
                error.classList.remove('hidden');
                return;
            }
            selected.value = choice.value;
            confirmed.value = '1';
            submitting = true;
            form.submit();
        });
    });
</script>
@endif
@endpush
