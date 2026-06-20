@props([
    'value',
    'field' => null,
    'muted' => false,
    'admin' => false,
])

@php
    $textClass = $admin
        ? ($muted ? 'text-[var(--admin-muted)]' : 'text-[var(--admin-text)]')
        : ($muted ? 'text-slate-400' : 'text-slate-100');
    $summaryClass = $admin
        ? ($muted ? 'text-[var(--admin-muted)]' : 'text-[var(--osu-cyan)]')
        : ($muted ? 'text-slate-500' : 'text-pink-300');
    $jsonBoxClass = $admin
        ? 'border-[var(--admin-border)] bg-[var(--admin-surface)]'
        : 'border-slate-800 bg-slate-900';
    $formatScalar = function ($item): string {
        if ($item === null || $item === []) {
            return 'None';
        }

        if (is_bool($item)) {
            return $item ? 'Yes' : 'No';
        }

        return (string) $item;
    };

    $summarizeStage = function (array $stage): string {
        $type = (string) ($stage['type'] ?? '');
        $label = match ($type) {
            'qualifier' => 'Qualifier',
            'group_stage' => 'Group Stage',
            'swiss_round' => 'Swiss Round',
            'bracket' => 'Bracket',
            'battle_royale' => 'Battle Royale',
            default => $type !== '' ? str($type)->replace('_', ' ')->headline()->toString() : 'Stage',
        };

        $parts = [];
        foreach ([
            'advance_count' => 'Top',
            'round_count' => 'Rounds',
            'group_count' => 'Groups',
            'teams_per_group' => 'Teams/group',
            'start_round_size' => 'Ro',
            'elimination_type' => 'Elim',
            'entry_type' => 'Entry',
            'lobby_count' => 'Lobbies',
            'players_per_lobby' => 'Players/lobby',
            'advance_per_lobby' => 'Advance/lobby',
            'eliminated_per_map' => 'Eliminated/map',
        ] as $key => $name) {
            if (! filled($stage[$key] ?? null)) {
                continue;
            }

            $value = (string) $stage[$key];
            if ($key === 'entry_type' && $value === 'winner_only') {
                continue;
            }

            $parts[] = $key === 'start_round_size'
                ? $name.$value
                : $name.': '.str($value)->replace('_', ' ')->headline();
        }

        return trim($label.($parts !== [] ? ' ('.implode(', ', $parts).')' : ''));
    };

    $summary = null;
    if (is_array($value)) {
        if ($field === 'format_structure' && is_array(data_get($value, 'stages'))) {
            $summary = collect(data_get($value, 'stages', []))
                ->filter(fn ($stage): bool => is_array($stage))
                ->map($summarizeStage)
                ->implode(' -> ');
        } elseif ($field === 'modes') {
            $summary = collect($value)
                ->map(function ($mode): string {
                    $name = data_get($mode, 'mode', $mode);
                    $keyCount = data_get($mode, 'key_count');

                    return $keyCount ? "{$name} {$keyCount}K" : (string) $name;
                })
                ->implode(', ');
        } elseif ($field === 'restricted_countries') {
            $summary = $value === [] ? 'Open to all countries' : implode(', ', $value);
        } else {
            $summary = collect($value)
                ->map(fn ($item) => is_scalar($item) || $item === null ? $formatScalar($item) : null)
                ->filter()
                ->implode(', ');
        }

        $summary = $summary !== '' ? $summary : 'Structured value';
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }
@endphp

@if(is_array($value))
    <div class="space-y-2 text-sm {{ $textClass }}">
        <p class="font-medium">{{ $summary }}</p>
        <details>
            <summary class="cursor-pointer text-xs font-semibold uppercase {{ $summaryClass }}">Raw JSON</summary>
            <pre class="mt-2 max-h-60 overflow-auto whitespace-pre-wrap break-words rounded border {{ $jsonBoxClass }} p-2 text-xs">{{ $json }}</pre>
        </details>
    </div>
@else
    <pre class="max-h-60 overflow-auto whitespace-pre-wrap break-words text-sm {{ $muted ? $textClass : 'font-medium '.$textClass }}">{{ $formatScalar($value) }}</pre>
@endif
