@props([
    'tournament',
])

<div {{ $attributes->merge(['class' => 'space-y-6']) }}>
    <x-admin.tournaments.parse-history-diff :tournament="$tournament" />
    <x-admin.tournaments.forum-content :tournament="$tournament" />
</div>
