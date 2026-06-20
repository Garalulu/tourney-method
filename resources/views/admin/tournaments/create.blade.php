@extends('layouts.admin')

@section('title', 'Create Tournament')

@section('content')
<div class="flex h-full" x-data="{
    saving: false,
    saveError: null
}">
    <div class="w-full lg:w-1/2 overflow-y-auto border-r border-[var(--admin-border)] bg-[var(--admin-surface)]">
        <div class="p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2" style="font-family: 'Outfit', sans-serif;">
                    Create New Tournament
                </h1>
                <p class="text-[var(--admin-muted)]">
                    Add tournament metadata and optionally queue a forum parse.
                </p>
            </div>

            <x-admin.tournaments.tournament-metadata-form :tournament="$tournament" mode="create" />
        </div>
    </div>

    <div class="hidden lg:block lg:w-1/2 bg-[var(--admin-bg)] p-8 overflow-y-auto">
        <div class="max-w-md mx-auto space-y-8">
            <div class="bg-[var(--admin-surface)] p-6 rounded-xl border border-[var(--admin-border)]">
                <h3 class="text-xl font-bold mb-4" style="font-family: 'Outfit', sans-serif;">
                    Quick Tips
                </h3>
                <ul class="space-y-4 text-[var(--admin-text)] text-sm">
                    <li>Newly created tournaments start in pending review.</li>
                    <li>Forum topic URLs queue the same full parse pipeline as the re-parse button.</li>
                    <li>Staff and podium winners can be added after the tournament is created.</li>
                    <li>Manual entries are protected from regular automated parser overwrites.</li>
                </ul>
            </div>

            <div class="bg-blue-500/10 p-6 rounded-xl border border-blue-500/20">
                <h3 class="text-lg font-bold text-blue-400 mb-2">Automated Parsing</h3>
                <p class="text-sm text-blue-300">
                    If a forum topic URL is provided, the system saves your manual fields first and then queues parsing for additional metadata and staff.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
