@extends('layouts.admin')

@section('title', 'Discord Servers')

@section('content')
<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Central Alerts</p>
            <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Discord Servers</h1>
            <p class="mt-2 max-w-3xl text-sm text-[var(--admin-muted)]">
                Configure Discord webhooks, shared role IDs, and alert rules once per server.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.discord-servers.create') }}" class="w-fit rounded-lg bg-[var(--osu-pink)] px-3 py-2 text-sm font-semibold text-white transition hover:brightness-110">
                New Server
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
        @if($servers->isEmpty())
            <div class="px-4 py-16 text-center">
                <h2 class="text-xl font-bold">No Servers Found</h2>
                <p class="mt-2 text-sm text-[var(--admin-muted)]">Create a server before configuring destinations.</p>
            </div>
        @else
            <div class="divide-y divide-[var(--admin-border)]">
                @foreach($servers as $server)
                    <div class="flex flex-col gap-4 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="font-semibold text-[var(--admin-text)]">{{ $server->server_name }}</div>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-[var(--admin-muted)]">
                                <span>{{ count($server->role_mappings ?? []) }} role mappings</span>
                                <span>{{ $server->destinations_count }} destinations</span>
                                <span>{{ $server->send_unmatched_rank_alerts ? 'Far-rank alerts send without ping' : 'Far-rank alerts skipped' }}</span>
                                <span>
                                    @if(empty($server->countries))
                                        Global alerts only
                                    @else
                                        @foreach($server->countries as $country)
                                            <span title="{{ $country }}">{{ country_flag($country) }} {{ $country }}</span>@if(! $loop->last), @endif
                                        @endforeach
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.discord-servers.edit', $server) }}" class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-xs font-semibold text-[var(--admin-text)] transition hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('admin.discord-servers.destroy', $server) }}" onsubmit="return confirm('Delete this Discord server?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-500/40 px-3 py-2 text-xs font-semibold text-red-300 transition hover:bg-red-500/10">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($servers->hasPages())
        <div class="mt-6">
            {{ $servers->links() }}
        </div>
    @endif
</div>
@endsection
