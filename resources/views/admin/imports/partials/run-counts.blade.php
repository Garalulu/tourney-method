@php
    use App\Models\AdminMaintenanceRun;
    use App\Models\AdminMaintenanceRunItem;

    $summary = $run->summary ?? [];
    $count = fn (string $key): int => (int) data_get($summary, $key, 0);
@endphp

<div class="flex flex-wrap gap-2 text-xs">
    @if($run->command === AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE)
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--success)]">+{{ $count(AdminMaintenanceRunItem::ACTION_CREATED) }} created</span>
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--osu-cyan)]">{{ $count(AdminMaintenanceRunItem::ACTION_UPDATED) }} updated</span>
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--admin-muted)]">{{ $count('queued') }} queued</span>
    @elseif($run->command === AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT)
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--success)]">{{ $count(AdminMaintenanceRunItem::ACTION_SYNCED) }} synced</span>
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--admin-muted)]">{{ $count('tournaments') }} checked</span>
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--admin-muted)]">{{ $count('unmatched') }} unmatched</span>
    @else
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--success)]">{{ $count('new_badge_users') }} users</span>
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--osu-cyan)]">{{ $count('new_badges') }} badges</span>
        <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--admin-muted)]">{{ $count('processed') }} processed</span>
    @endif
</div>
