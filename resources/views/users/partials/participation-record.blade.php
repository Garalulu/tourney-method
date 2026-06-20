@php
    $canManageParticipation = $canManageParticipation ?? $isOwnProfile;
    $viewerIsModerator = auth()->user()?->isAdmin() ?? false;
    $stageValue = data_get($record->metadata, 'stage_value');
    $placement = $record->placement_override ?? $record->placement_min ?? $record->placement;
    $placementLabel = $record->placementRangeLabel();
    if ($record->placement_min && $record->placement_max && $record->placement_min === $record->placement_max) {
        $placementLabel = match($record->placement_min) {
            1 => '1ST',
            2 => '2ND',
            3 => '3RD',
            default => strtoupper("#{$record->placement_min}"),
        };
    }
    if (! $placementLabel && in_array($record->selection_outcome, ['dnq', 'dnp'], true)) {
        $placementLabel = $record->selection_outcome_label;
    }
    $placementColor = match($placementLabel) {
        '1ST' => 'bg-yellow-500/20 text-yellow-400',
        '2ND' => 'bg-slate-400/20 text-slate-300',
        '3RD' => 'bg-orange-700/20 text-orange-400',
        'DNQ', 'DNP' => 'bg-purple-500/15 text-purple-200 ring-1 ring-purple-400/25',
        default => 'bg-pink-500/10 text-pink-200',
    };
    $stageOptionsService = app(\App\Services\ParticipationStageOptionsService::class);
    $stageOptions = collect($options ?? []);
    $stageOption = $stageOptions->firstWhere('value', $stageValue);
    if (! $stageOption && preg_match('/^swiss:(\d+)$/', (string) $stageValue, $matches)) {
        $stageOption = $stageOptions->first(fn ($option) => str_starts_with((string) data_get($option, 'value'), "swiss:{$matches[1]}:"));
    }
    $recordStageValue = (string) data_get($stageOption, 'value', $stageValue);
    $qualifierCutoff = $stageOptionsService->qualifierCutoff($record->tournament);
    $resultSummary = data_get($record->metadata, 'hide_finished_bracket') || in_array($record->selection_outcome, ['dnq', 'dnp'], true) ? null : $record->result_summary;
    $displayTeammates = collect($displayTeammates ?? $record->teammates);
    $teammateBwsRanks = collect($teammateBwsRanks ?? []);
    $sortedTeammates = $displayTeammates
        ->sortBy(fn ($teammate) => $teammateBwsRanks->get($teammate->id) ?? PHP_INT_MAX)
        ->values();
    $pendingDeletionRequest = $record->deletionRequests
        ->first(fn ($request) => $request->status === \App\Models\ParticipationDeletionRequest::STATUS_PENDING);
    $hasPendingDeletionRequest = $pendingDeletionRequest !== null;
    $viewerParticipationLocked = ! $viewerIsModerator && (auth()->user()?->hasParticipationInputLock() ?? false);
    $participationLocked = ! $viewerIsModerator && $isOwnProfile && (bool) $user->participationInputLock;
    $canReportParticipation = auth()->check() && auth()->id() !== $record->user_id && ! $viewerParticipationLocked;
    $canDeleteDirectly = $viewerIsModerator || (! $record->isPodiumBacked() && ! $record->isAdminApprovedPodium() && $sortedTeammates->isEmpty());
    $canRequestDeletion = ! $viewerIsModerator && ! $record->isPodiumBacked() && ! $record->isAdminApprovedPodium() && $sortedTeammates->isNotEmpty();
    $canToggleVisibility = $isOwnProfile && ! $hasPendingDeletionRequest && ! $record->isPodiumBacked() && ! $record->isAdminApprovedPodium();
    $isHidden = $record->profile_hidden_at !== null;
    $recordStateClasses = $hasPendingDeletionRequest
        ? 'border-l-rose-400/70 bg-rose-950/20 hover:bg-rose-950/30'
        : ($isHidden
            ? 'border-l-cyan-400/40 bg-slate-900/45 hover:bg-slate-800/40'
            : 'border-l-transparent hover:bg-slate-700/30');
    $regionLabel = $record->tournament->isRegionRestricted()
        ? __('users.participation.labels.regional')
        : __('users.participation.labels.global');
    $formatLabel = $record->tournament->team_size_display ?: ($record->tournament->format ?: null);
    $metadataText = collect([
        $record->tournament->rank_range.($record->tournament->is_bws ? ' BWS' : ''),
        $formatLabel,
        $regionLabel,
    ])->filter()->join(' · ');
    $recordPayload = [
        'id' => $record->id,
        'stage_value' => $recordStageValue,
        'placement' => $record->placement,
        'placement_min' => $record->placement_min,
        'placement_max' => $record->placement_max,
        'placement_override' => $record->placement_override,
        'placement_range_editable' => (bool) data_get($stageOption, 'range_placement', false),
        'podium_locked' => $record->source === \App\Models\TournamentParticipationRecord::SOURCE_SYSTEM || data_get($record->metadata, 'autofilled_from') === 'tournament_winners',
        'podium_member_editable' => $viewerIsModerator && ($record->source === \App\Models\TournamentParticipationRecord::SOURCE_SYSTEM || data_get($record->metadata, 'autofilled_from') === 'tournament_winners'),
        'seed' => $record->seed,
        'team_name' => $record->team_name,
        'memo' => $record->memo,
        'matches' => $record->matches ?? [],
        'teammates' => $sortedTeammates->map(fn ($teammate) => [
            'id' => $teammate->id,
            'osu_id' => $teammate->osu_id,
            'username' => $teammate->username,
            'avatar_url' => $teammate->avatar_url,
            'country_code' => $teammate->country_code,
            'profile_url' => route('users.show', $teammate),
            'bws_rank' => $teammateBwsRanks->get($teammate->id),
        ])->values()->all(),
    ];
    $tournamentPayload = $tournamentDetails[$record->tournament_id] ?? [
        'id' => $record->tournament->id,
        'title' => $record->tournament->title,
        'year' => $record->tournament->tournament_end?->year,
        'modes' => [],
        'is_badge' => (bool) $record->tournament->is_badge,
        'profile_url' => route('tournaments.show', $record->tournament),
        'forum_post_url' => $record->tournament->forum_post_url,
        'spreadsheet_url' => $record->tournament->spreadsheet_url,
        'bracket_url' => $record->tournament->bracket_url,
        'stage_options' => $options->all(),
        'match_stage_options' => $stageOptionsService->matchStageLabelsFor($record->tournament),
        'has_qualifier' => false,
        'qualifier_cutoff' => null,
        'is_team_tournament' => false,
    ];
@endphp

<div class="group relative border-l-4 px-5 py-4 transition {{ $recordStateClasses }}" x-data="{ menuOpen: false }" :class="menuOpen ? 'z-40' : 'z-0'" @click.outside="menuOpen = false">
    @if($hasPendingDeletionRequest)
        <div class="pointer-events-none absolute inset-0 opacity-[0.16] [background-image:repeating-linear-gradient(135deg,rgba(251,113,133,0.45)_0,rgba(251,113,133,0.45)_1px,transparent_1px,transparent_10px)]"></div>
    @elseif($isHidden)
        <div class="pointer-events-none absolute inset-0 opacity-[0.14] [background-image:radial-gradient(circle_at_1px_1px,rgba(34,211,238,0.45)_1px,transparent_0)] [background-size:12px_12px]"></div>
    @endif
    <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex min-w-0 flex-nowrap items-center gap-2">
                @if($participationIndex)
                    <span class="shrink-0 rounded-md border border-slate-700/70 bg-slate-900/60 px-2 py-0.5 text-xs font-semibold text-slate-400">#{{ $participationIndex }}</span>
                @endif
                <a href="{{ route('tournaments.show', $record->tournament) }}" class="min-w-0 truncate font-tournament font-medium text-white transition hover:text-pink-400">
                    {{ $record->tournament->title }}
                </a>
                @if($isHidden)
                    <span class="shrink-0 rounded-md border border-cyan-400/30 bg-cyan-500/10 px-2 py-0.5 text-xs font-semibold text-cyan-200">{{ __('users.participation.labels.hidden_public') }}</span>
                @endif
            </div>
            @if($hasPendingDeletionRequest)
                <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-rose-400/40 bg-rose-500/10 px-3 py-2 text-sm text-rose-100">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-rose-500/15 text-rose-200">
                        <x-icon name="lucide-circle-alert" class="h-4 w-4" />
                    </span>
                    <span class="font-semibold">{{ __('users.participation.labels.deletion_pending') }}</span>
                    <span class="text-rose-200/80">{{ __('users.participation.labels.deletion_review') }}</span>
                    @if($pendingDeletionRequest->created_at)
                        <span class="text-xs text-rose-200/60">{{ $pendingDeletionRequest->created_at->format('M d, Y H:i') }}</span>
                    @endif
                </div>
            @elseif($isHidden)
                <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-cyan-400/25 bg-cyan-500/10 px-3 py-2 text-sm text-cyan-100">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-cyan-500/15 text-cyan-200">
                        <x-icon name="lucide-eye-off" class="h-4 w-4" />
                    </span>
                    <span class="text-cyan-200/75">{{ __('users.participation.labels.hidden_help') }}</span>
                </div>
            @endif
            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-400">
                @if($record->tournament->tournament_end)
                    <span>{{ $record->tournament->tournament_end->format('M Y') }}</span>
                @endif
                @foreach($record->tournament->modes_with_details as $modeDetail)
                    <x-gamemode-badge :mode="$modeDetail['mode']" :key-count="$modeDetail['key_count'] ?? null" size="compact" />
                @endforeach
                @if($record->tournament->is_badge && $record->tournament->badge_status === 'approved')
                    <span class="ui-badge-xs ui-badge-neutral">{{ __('users.history.badged') }}</span>
                @endif
            </div>
            <div class="mt-1 text-xs text-gray-500">{{ $metadataText }}</div>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                @if($placementLabel)
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase leading-none {{ $placementColor }}">{{ $placementLabel }}</span>
                @endif
                @if($resultSummary)
                    <span class="text-slate-300">{{ $resultSummary }}</span>
                @endif
                @if($record->seed)
                    <span class="text-slate-400">{{ __('users.participation.fields.seed') }} {{ $record->seed }}@if($qualifierCutoff)/{{ $qualifierCutoff }}@endif</span>
                @endif
            </div>
            @if($record->team_name || $sortedTeammates->isNotEmpty())
                <div class="mt-2 text-sm text-slate-400">
                    @if($record->team_name)
                        <span class="text-slate-400">{{ __('users.participation.fields.team_name') }}: <span class="text-slate-300">{{ $record->team_name }}</span></span>
                    @endif
                    @if($sortedTeammates->isNotEmpty())
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                            @foreach($sortedTeammates as $teammate)
                                <a href="{{ route('users.show', $teammate) }}" class="group/teammate inline-flex min-w-0 items-center gap-2 rounded-lg border border-slate-700/50 bg-slate-900/50 px-2 py-1 text-xs text-slate-300 transition hover:border-pink-500/50 hover:text-white">
                                    <span class="relative shrink-0">
                                        <img src="{{ $teammate->avatar_url ?? 'https://a.ppy.sh/'.$teammate->osu_id }}" alt="{{ $teammate->username }}" class="h-6 w-6 rounded-md object-cover">
                                        @if($teammate->country_code)
                                            <img src="https://flagcdn.com/w40/{{ strtolower($teammate->country_code) }}.png" alt="{{ $teammate->country_code }}" class="absolute -bottom-1 -right-1 h-3 w-5 rounded-sm border border-slate-900 object-cover" title="{{ $teammate->country_code }}">
                                        @endif
                                    </span>
                                    <span class="min-w-0 truncate font-medium group-hover/teammate:text-pink-300">{{ $teammate->username }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            @if($record->memo)
                <p class="mt-2 max-w-3xl whitespace-pre-line text-sm text-slate-400">{{ $record->memo }}</p>
            @endif
            @if(! empty($record->matches))
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($record->matches as $match)
                        @php
                            $stage = $match['stage'] ?? __('users.participation.fields.match_stage');
                            $scoreFor = $match['score_for'] ?? null;
                            $scoreAgainst = $match['score_against'] ?? null;
                            $isForfeit = ! empty($match['is_forfeit']);
                            $hasScores = $scoreFor !== null && $scoreAgainst !== null;
                            $isWin = $hasScores && (int) $scoreFor > (int) $scoreAgainst;
                            $isLoss = $hasScores && (int) $scoreFor < (int) $scoreAgainst;
                            $resultText = $isForfeit
                                ? "{$stage} FF"
                                : ($hasScores ? "{$stage} {$scoreFor} - {$scoreAgainst}" : trim($stage.' '.($match['result'] ?? '')));
                            $resultClass = $isWin
                                ? 'border-green-400/40 bg-green-500/10 text-green-300 hover:text-green-200'
                                : ($isLoss ? 'border-red-400/40 bg-red-500/10 text-red-300 hover:text-red-200' : 'border-slate-700/50 bg-slate-900/50 text-slate-300 hover:text-white');
                        @endphp
                        @if(! empty($match['mp_link']))
                            <a href="{{ $match['mp_link'] }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-semibold tabular-nums transition {{ $resultClass }}">
                                {{ $resultText }}
                            </a>
                        @else
                            <span class="inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-semibold tabular-nums {{ $resultClass }}">
                                {{ $resultText }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
        @if($canManageParticipation || $canReportParticipation)
            <div class="relative self-start">
                <button type="button"
                        @click="menuOpen = ! menuOpen"
                        class="rounded-lg border border-slate-700 p-2 text-slate-300 opacity-100 transition hover:border-pink-500/50 hover:text-pink-300 sm:opacity-0 sm:group-hover:opacity-100"
                        aria-label="{{ __('users.participation.actions.more') }}">
                    <x-icon name="lucide-ellipsis-vertical" class="h-5 w-5" />
                </button>
                <div x-show="menuOpen" x-transition class="absolute left-0 z-20 mt-2 w-56 overflow-hidden rounded-lg border border-slate-700 bg-slate-900 shadow-xl sm:left-auto sm:right-0" style="display: none;">
                    @if($canManageParticipation && $participationLocked)
                        <div class="px-4 py-2.5 text-sm text-yellow-200">{{ __('users.participation.input_locked') }}</div>
                    @elseif($canManageParticipation)
                        <button type="button"
                                @click="menuOpen = false; openEdit(@js($recordPayload), @js($tournamentPayload))"
                                class="block w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-200 hover:bg-slate-800">
                            {{ __('users.participation.actions.edit') }}
                        </button>
                    @endif
                    @if($canToggleVisibility)
                        <form method="POST" action="{{ route('users.participation.visibility', [$user, $record]) }}" @submit.prevent="submitParticipationAction($event)">
                            @csrf
                            @method('PATCH')
                            <button class="block w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-200 hover:bg-slate-800">
                                {{ __($isHidden ? 'users.participation.actions.show' : 'users.participation.actions.hide') }}
                            </button>
                        </form>
                    @endif
                    @if($canReportParticipation)
                        <button type="button" @click="menuOpen = false; openReportDialog(@js(route('users.participation.report', [$user, $record])))" class="block w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-200 hover:bg-slate-800">
                            {{ __('users.participation.actions.report') }}
                        </button>
                    @endif
                    @if($pendingDeletionRequest && ! $viewerIsModerator)
                        <form method="POST" action="{{ route('users.participation.deletion-request.cancel', [$user, $record]) }}" @submit.prevent="submitParticipationAction($event)">
                            @csrf
                            @method('DELETE')
                            <button class="block w-full px-4 py-2.5 text-left text-sm font-semibold text-yellow-200 hover:bg-yellow-500/10">
                                {{ __('users.participation.actions.cancel_delete_request') }}
                            </button>
                        </form>
                    @elseif($participationLocked)
                        <div class="px-4 py-2.5 text-sm text-slate-500">{{ __('users.participation.labels.delete_locked') }}</div>
                    @elseif($canDeleteDirectly)
                        <button type="button" @click="menuOpen = false; openDeleteConfirmDialog(@js(route('users.participation.destroy', [$user, $record])))" class="block w-full px-4 py-2.5 text-left text-sm font-semibold text-red-300 hover:bg-red-500/10">
                            {{ __('users.participation.actions.delete') }}
                        </button>
                    @elseif($canRequestDeletion)
                        <button type="button" @click="menuOpen = false; openDeletionRequestDialog(@js(route('users.participation.deletion-request', [$user, $record])))" class="block w-full px-4 py-2.5 text-left text-sm font-semibold text-red-300 hover:bg-red-500/10">
                            {{ __('users.participation.actions.request_delete') }}
                        </button>
                    @else
                        <div class="px-4 py-2.5 text-sm text-slate-500">{{ __('users.participation.labels.delete_locked') }}</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
