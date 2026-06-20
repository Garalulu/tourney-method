@props([
    'tournament',
])

@if($tournament->banner_url)
    <div class="mb-6 overflow-hidden rounded-lg bg-[var(--admin-dark)] shadow-lg">
        <div class="relative">
            <img
                src="{{ $tournament->cached_banner_url }}"
                alt="{{ $tournament->title }} banner"
                class="h-48 w-full object-cover"
                loading="lazy"
                decoding="async"
                onerror="this.parentElement.innerHTML='<div class=\'flex h-48 items-center justify-center text-[var(--admin-muted)]\'>Failed to load banner image</div>'"
            >

            @if($tournament->banner_image_cached_at)
                <div class="absolute bottom-2 right-2 rounded bg-black/70 px-2 py-1 text-xs text-white">
                    Cached {{ $tournament->banner_image_cached_at->diffForHumans() }}
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-2 p-2">
            <button
                type="button"
                class="rounded bg-[var(--osu-pink)] px-3 py-1 text-xs text-white transition hover:bg-pink-600 disabled:opacity-50"
                onclick="refreshBanner({{ $tournament->id }}, this)"
            >
                Refresh Cache
            </button>

            <a
                href="{{ $tournament->banner_url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded bg-gray-600 px-3 py-1 text-xs text-white hover:bg-gray-700"
            >
                Open Original
            </a>
        </div>
    </div>
@endif
