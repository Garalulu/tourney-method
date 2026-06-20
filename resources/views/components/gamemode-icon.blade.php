@props([
    'mode',
    'size' => 'sm',
])

@php
    $normalizedMode = $mode === 'fruits' ? 'catch' : (string) $mode;
    $icon = match ($normalizedMode) {
        'osu' => 'std_white.webp',
        'taiko' => 'taiko_white.webp',
        'catch' => 'catch_white.webp',
        'mania', '4k', '7k' => 'mania_white.webp',
        default => null,
    };

    $sizeClass = match ($size) {
        'xs' => 'gamemode-icon',
        'md' => 'gamemode-icon-md',
        default => 'gamemode-icon-sm',
    };
@endphp

@if($icon)
    <img
        src="{{ asset('images/gamemodes/'.$icon) }}"
        alt=""
        class="{{ $sizeClass }}"
        aria-hidden="true"
        loading="lazy"
        decoding="async">
@endif
