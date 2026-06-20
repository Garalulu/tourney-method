@php
    $roleMappings = old('role_mappings', $server->role_mappings ?? []);
    $roleMappings = is_array($roleMappings) ? array_values($roleMappings) : [];
    $selectedCountries = old('countries', $server->countries ?? []);
    $destinations = old('destinations');
    if ($destinations === null) {
        $destinations = $server->exists
            ? $server->destinations->map(fn ($destination) => [
                'id' => $destination->id,
                'channel_name' => $destination->channel_name,
                'webhook_url' => $destination->webhook_url,
                'mode' => $destination->mode,
                'is_badge' => $destination->is_badge,
                'is_active' => $destination->is_active,
            ])->values()->all()
            : [];
    }
    $destinations = is_array($destinations) ? array_values($destinations) : [];
    $sendUnmatchedRankAlerts = old('send_unmatched_rank_alerts', $server->send_unmatched_rank_alerts ?? true);
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="max-w-6xl rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 sm:p-6"
    x-data="{ roleMappings: @js($roleMappings), destinations: @js($destinations) }"
>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">
            <div class="font-semibold">Please fix the highlighted fields.</div>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <label class="block space-y-1">
        <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Server Name</span>
        <input
            type="text"
            name="server_name"
            value="{{ old('server_name', $server->server_name) }}"
            class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
            required
        >
    </label>

    <label class="mt-5 flex items-start gap-3 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-3">
        <input type="hidden" name="send_unmatched_rank_alerts" value="0">
        <input
            type="checkbox"
            name="send_unmatched_rank_alerts"
            value="1"
            class="mt-0.5 rounded border-[var(--admin-border)] bg-[var(--admin-surface)] text-[var(--osu-pink)] focus:ring-[var(--osu-pink)]"
            @checked($sendUnmatchedRankAlerts)
        >
        <span>
            <span class="block text-sm font-semibold">Send far-rank alerts without ping</span>
            <span class="mt-1 block text-xs text-[var(--admin-muted)]">When a tournament starts past the next digit boundary after this server's largest role threshold, keep the Discord post but omit role mentions.</span>
        </span>
    </label>

    <div class="mt-6 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)]">
        <div class="flex items-center justify-between gap-3 border-b border-[var(--admin-border)] px-4 py-3">
            <div>
                <h2 class="font-semibold">Role Mappings</h2>
                <p class="mt-1 text-xs text-[var(--admin-muted)]">These role IDs are shared by every destination attached to this server.</p>
            </div>
            <button
                type="button"
                class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-xs font-semibold text-[var(--admin-text)] transition hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]"
                @click="roleMappings.push({ threshold: '', role_id: '' })"
            >
                Add Row
            </button>
        </div>

        <div class="space-y-3 p-4">
            <template x-if="roleMappings.length === 0">
                <div class="rounded-lg border border-dashed border-[var(--admin-border)] px-4 py-6 text-center text-sm text-[var(--admin-muted)]">
                    No role mappings configured.
                </div>
            </template>

            <template x-for="(mapping, index) in roleMappings" :key="index">
                <div class="grid gap-3 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-3 md:grid-cols-[1fr_2fr_auto]">
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Threshold</span>
                        <input
                            type="number"
                            min="1"
                            :name="`role_mappings[${index}][threshold]`"
                            x-model="mapping.threshold"
                            class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                        >
                    </label>

                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Role ID</span>
                        <input
                            type="text"
                            inputmode="numeric"
                            :name="`role_mappings[${index}][role_id]`"
                            x-model="mapping.role_id"
                            class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                        >
                    </label>

                    <div class="flex items-end">
                        <button
                            type="button"
                            class="w-full rounded-lg border border-red-500/40 px-3 py-2 text-xs font-semibold text-red-300 transition hover:bg-red-500/10"
                            @click="roleMappings.splice(index, 1)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="mt-6">
        <x-admin.tournaments.regional-restrictions
            name="countries"
            :selected="$selectedCountries"
            label="Alert Countries"
            help="(global alerts always post; flagged alerts require a matching country)"
            empty-text="Global alerts only"
        />
    </div>

    <div class="mt-6 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)]">
        <div class="flex items-center justify-between gap-3 border-b border-[var(--admin-border)] px-4 py-3">
            <div>
                <h2 class="font-semibold">Destinations</h2>
                <p class="mt-1 text-xs text-[var(--admin-muted)]">Each row posts matching tournament alerts to one Discord webhook.</p>
            </div>
            <button
                type="button"
                class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-xs font-semibold text-[var(--admin-text)] transition hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]"
                @click="destinations.push({ id: null, channel_name: '', webhook_url: '', mode: 'osu', is_badge: true, is_active: true })"
            >
                Add Row
            </button>
        </div>

        <div class="space-y-3 p-4">
            <template x-if="destinations.length === 0">
                <div class="rounded-lg border border-dashed border-[var(--admin-border)] px-4 py-6 text-center text-sm text-[var(--admin-muted)]">
                    No destinations configured.
                </div>
            </template>

            <template x-for="(destination, index) in destinations" :key="destination.id || index">
                <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-3">
                    <input type="hidden" :name="`destinations[${index}][id]`" x-model="destination.id">

                    <div class="grid gap-3 lg:grid-cols-[1.2fr_2fr_0.8fr]">
                        <label class="space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Destination Name</span>
                            <input
                                type="text"
                                :name="`destinations[${index}][channel_name]`"
                                x-model="destination.channel_name"
                                class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                            >
                        </label>

                        <label class="space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Webhook URL</span>
                            <input
                                type="url"
                                :name="`destinations[${index}][webhook_url]`"
                                x-model="destination.webhook_url"
                                placeholder="https://discord.com/api/webhooks/..."
                                class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                            >
                        </label>

                        <label class="space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Mode</span>
                            <select
                                :name="`destinations[${index}][mode]`"
                                x-model="destination.mode"
                                class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                            >
                                @foreach($modes as $mode)
                                    <option value="{{ $mode }}">{{ $mode }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <label class="flex items-center gap-2 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2">
                            <input type="hidden" :name="`destinations[${index}][is_badge]`" value="0">
                            <input
                                type="checkbox"
                                :name="`destinations[${index}][is_badge]`"
                                value="1"
                                x-model="destination.is_badge"
                                class="rounded border-[var(--admin-border)] bg-[var(--admin-surface)] text-[var(--osu-pink)] focus:ring-[var(--osu-pink)]"
                            >
                            <span class="text-sm font-semibold">Badge tournaments</span>
                        </label>

                        <label class="flex items-center gap-2 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2">
                            <input type="hidden" :name="`destinations[${index}][is_active]`" value="0">
                            <input
                                type="checkbox"
                                :name="`destinations[${index}][is_active]`"
                                value="1"
                                x-model="destination.is_active"
                                class="rounded border-[var(--admin-border)] bg-[var(--admin-surface)] text-[var(--osu-pink)] focus:ring-[var(--osu-pink)]"
                            >
                            <span class="text-sm font-semibold">Active</span>
                        </label>

                        <button
                            type="button"
                            class="rounded-lg border border-red-500/40 px-3 py-2 text-xs font-semibold text-red-300 transition hover:bg-red-500/10"
                            @click="destinations.splice(index, 1)"
                        >
                            Remove
                        </button>

                        <button
                            type="submit"
                            x-show="destination.id"
                            :form="`destination-test-${destination.id}`"
                            class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-xs font-semibold text-[var(--admin-text)] transition hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]"
                        >
                            Test
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <button type="submit" class="rounded-lg bg-[var(--osu-pink)] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('admin.discord-servers.index') }}" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold text-[var(--admin-text)] transition hover:bg-[var(--admin-bg)]">
            Cancel
        </a>
    </div>
</form>

@if($server->exists)
    @foreach($server->destinations as $destination)
        <form id="destination-test-{{ $destination->id }}" method="POST" action="{{ route('admin.discord-destinations.test-send', $destination) }}" class="hidden">
            @csrf
        </form>
    @endforeach
@endif
