@foreach($records as $record)
    @php
        $recordSearchText = collect([
            $record->tournament->title,
            $record->team_name,
            $record->teammates->pluck('username')->join(' '),
        ])->filter()->join(' ');
    @endphp
    <div data-participation-record
         data-year="{{ $record->tournament->tournament_end?->year }}"
         data-badged="{{ $record->tournament->is_badge && $record->tournament->badge_status === 'approved' ? '1' : '0' }}"
         data-modes="{{ collect($record->tournament->modes)->map(fn ($mode) => data_get($mode, 'mode', $mode))->filter()->join(' ') }}"
         data-search="{{ e($recordSearchText) }}">
        @include('users.partials.participation-record', [
            'record' => $record,
            'user' => $user,
            'isOwnProfile' => $isOwnProfile,
            'canManageParticipation' => $canManageParticipation,
            'options' => collect($stageOptions[$record->tournament_id] ?? []),
            'tournamentDetails' => $tournamentDetails,
            'participationIndex' => $participationIndices[$record->id] ?? null,
            'displayTeammates' => $displayTeammatesByRecord[$record->id] ?? null,
            'teammateBwsRanks' => $displayTeammateBwsRanksByRecord[$record->id] ?? [],
        ])
    </div>
@endforeach
