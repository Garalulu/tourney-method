@props([
    'activeTab',
    'hasFilters' => false,
])

@php
    $copy = [
        'pending' => [
            'title' => 'No pending tournaments',
            'body' => 'New submissions and restored tournaments will appear here for review.',
        ],
        'approved' => [
            'title' => 'No approved tournaments',
            'body' => 'Approved tournaments matching the current filters will appear here.',
        ],
        'rejected' => [
            'title' => 'No rejected tournaments',
            'body' => 'Rejected tournaments matching the current filters will appear here.',
        ],
    ][$activeTab] ?? [
        'title' => 'No tournaments found',
        'body' => 'Try changing the current filters.',
    ];
@endphp

<div class="rounded-lg border border-dashed border-[var(--admin-border)] bg-[var(--admin-surface)] px-6 py-16 text-center">
    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)]">
        <x-icon name="lucide-check" class="h-8 w-8 text-[var(--admin-muted)]" />
    </div>
    <h2 class="text-xl font-bold text-[var(--admin-text)]">{{ $copy['title'] }}</h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-[var(--admin-muted)]">
        {{ $hasFilters ? 'No tournaments match the current search and mode filters.' : $copy['body'] }}
    </p>
</div>
