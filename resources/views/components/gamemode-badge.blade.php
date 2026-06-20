@props([
    'mode',
    'keyCount' => null,
    'label' => null,
    'size' => 'sm',
])

@php
    $normalizedMode = $mode === 'fruits' ? 'catch' : (string) $mode;
    $displayLabel = $label ?? match ($normalizedMode) {
        'osu' => 'osu!',
        'taiko' => 'osu!taiko',
        'catch' => 'osu!catch',
        'mania' => 'osu!mania'.($keyCount ? " {$keyCount}K" : ''),
        '4k', '7k' => 'osu!mania '.strtoupper($normalizedMode),
        default => $normalizedMode,
    };

    $sizeClass = match ($size) {
        'pill' => 'gamemode-badge-pill',
        'compact' => 'gamemode-badge-compact',
        default => '',
    };

    $legacyClass = $size === 'sm' ? 'tournament-card-mode-badge' : '';
    $iconSize = $size === 'compact' ? 'xs' : 'sm';
@endphp

<span {{ $attributes->class(['gamemode-badge', $legacyClass, $sizeClass]) }}>
    <x-gamemode-icon :mode="$normalizedMode" :size="$iconSize" />
    <span>{{ $displayLabel }}</span>
</span>
