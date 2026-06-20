@php
    use App\Models\AdminMaintenanceRun;
    use App\Models\AdminMaintenanceRunItem;

    $groups = match ($run->command) {
        AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE => [
            'Created' => $run->items->where('action', AdminMaintenanceRunItem::ACTION_CREATED),
            'Updated' => $run->items->where('action', AdminMaintenanceRunItem::ACTION_UPDATED),
        ],
        AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT => [
            'Synced Tournaments' => $run->items->where('action', AdminMaintenanceRunItem::ACTION_SYNCED),
        ],
        default => [
            'Users With New Badges' => $run->items->where('action', AdminMaintenanceRunItem::ACTION_NEW_BADGES),
        ],
    };
@endphp

<div class="space-y-4">
    @foreach($groups as $label => $items)
        <div>
            <div class="mb-2 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">{{ $label }}</h3>
                <span class="text-xs text-[var(--admin-muted)]">{{ $items->count() }}</span>
            </div>

            @if($items->isEmpty())
                <div class="rounded-md border border-[var(--admin-border)] px-3 py-2 text-sm text-[var(--admin-muted)]">
                    No records for this group.
                </div>
            @else
                <div class="space-y-2">
                    @foreach($items->take(25) as $item)
                        <div class="rounded-md border border-[var(--admin-border)] bg-[var(--admin-surface)] px-3 py-2">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    @if($item->tournament_id)
                                        <div class="truncate text-sm font-tournament font-medium">
                                            #{{ $item->tournament_id }} {{ $item->tournament_title }}
                                        </div>
                                    @else
                                        <div class="truncate text-sm font-medium">
                                            {{ $item->username ?? 'Unknown user' }}
                                        </div>
                                    @endif

                                    @if($item->username || $item->osu_id)
                                        <div class="truncate text-xs text-[var(--admin-muted)]">
                                            {{ $item->username ?? 'Unknown user' }}
                                            @if($item->user_id)
                                                &middot; user #{{ $item->user_id }}
                                            @endif
                                            @if($item->osu_id)
                                                &middot; osu! {{ $item->osu_id }}
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if(data_get($item->metadata, 'new_badges'))
                                    <span class="shrink-0 rounded bg-[var(--admin-bg)] px-2 py-1 text-xs text-[var(--success)]">
                                        +{{ data_get($item->metadata, 'new_badges') }} badges
                                    </span>
                                @elseif(data_get($item->metadata, 'placement'))
                                    <span class="shrink-0 rounded bg-[var(--admin-bg)] px-2 py-1 text-xs text-[var(--admin-muted)]">
                                        Place {{ data_get($item->metadata, 'placement') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($items->count() > 25)
                    <div class="mt-2 text-xs text-[var(--admin-muted)]">
                        Showing 25 of {{ $items->count() }} records.
                    </div>
                @endif
            @endif
        </div>
    @endforeach

    @if(count($run->errors ?? []) > 0)
        <div>
            <h3 class="mb-2 text-sm font-semibold text-[var(--danger)]">Errors</h3>
            <div class="space-y-2">
                @foreach(array_slice($run->errors ?? [], 0, 10) as $error)
                    <div class="rounded-md border border-[var(--danger)]/30 bg-[var(--danger)]/10 px-3 py-2 text-xs">
                        <div class="break-words text-[var(--admin-text)]">{{ $error['message'] ?? 'Unknown error' }}</div>
                        @if(isset($error['stage']))
                            <div class="mt-1 text-[var(--admin-muted)]">Stage: {{ $error['stage'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
