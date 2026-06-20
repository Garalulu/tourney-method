@extends('layouts.admin')

@section('title', 'Edit Discord Server')

@section('content')
<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Central Alerts</p>
        <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Edit Discord Server</h1>
        <p class="mt-2 text-sm text-[var(--admin-muted)]">{{ $server->server_name }}</p>
    </div>

    @include('admin.discord-servers.form', [
        'action' => route('admin.discord-servers.update', $server),
        'method' => 'PATCH',
        'submitLabel' => 'Save Server',
    ])
</div>
@endsection
