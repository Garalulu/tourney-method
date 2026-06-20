@props([
    'variant' => 'neutral',
    'size' => 'sm',
])

@php
    $variantClass = match ($variant) {
        'primary' => 'ui-badge-primary',
        'success' => 'ui-badge-success',
        'warning' => 'ui-badge-warning',
        'danger' => 'ui-badge-danger',
        'info' => 'ui-badge-info',
        default => 'ui-badge-neutral',
    };

    $sizeClass = $size === 'xs' ? 'ui-badge-xs' : 'ui-badge';
@endphp

<span {{ $attributes->class([$sizeClass, $variantClass]) }}>
    {{ $slot }}
</span>
