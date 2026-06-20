@php($label = $label ?? null)

<label class="block">
    @if($label)
        <span class="mb-1 block text-xs font-semibold uppercase text-[var(--admin-muted)]">{{ $label }}</span>
    @endif
    <select
        data-inline-field="{{ $field }}"
        data-user-id="{{ $user->id }}"
        data-original-value="{{ $value }}"
        class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1.5 text-sm text-[var(--admin-text)] focus:border-[var(--osu-pink)] focus:outline-none"
    >
        @if($field === 'main_mode')
            <option value="" @selected($value === null)>N/A</option>
        @endif
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($value === $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</label>
