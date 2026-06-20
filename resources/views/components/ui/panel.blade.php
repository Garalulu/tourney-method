@props([
    'as' => 'div',
    'variant' => 'default',
    'padding' => null,
    'overflow' => 'hidden',
])

@php
    $panelClass = match ($variant) {
        'muted' => 'ui-panel-muted',
        'subtle' => 'ui-panel-subtle',
        'modal' => 'ui-panel-modal',
        default => 'ui-panel',
    };

    $paddingClass = match ($padding) {
        'sm' => 'p-4',
        'md' => 'p-6',
        'lg' => 'p-8',
        'none', null => '',
        default => $padding,
    };

    $overflowClass = match ($overflow) {
        'visible' => 'overflow-visible',
        'auto' => 'overflow-auto',
        'none', null => '',
        default => 'overflow-hidden',
    };
@endphp

<{{ $as }} {{ $attributes->class([$panelClass, $paddingClass, $overflowClass]) }}>
    {{ $slot }}
</{{ $as }}>
