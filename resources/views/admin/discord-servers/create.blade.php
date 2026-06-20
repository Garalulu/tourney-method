@extends('layouts.admin')

@section('title', 'Create Discord Server')

@section('content')
<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Central Alerts</p>
        <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Create Discord Server</h1>
    </div>

    @include('admin.discord-servers.form', [
        'action' => route('admin.discord-servers.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Server',
    ])
</div>
@endsection
