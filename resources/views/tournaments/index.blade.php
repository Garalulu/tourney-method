@extends('layouts.app')

@section('title', 'Tournaments')

@section('content')
    {{-- Hero Header --}}
    <div class="tournament-hero-header">
        {{-- Animated Background Pattern --}}
        <div class="tournament-hero-pattern">
        </div>

        <div class="tournament-hero-content">
            {{-- Title and subtitle removed --}}
        </div>
    </div>

    @if(session('tournament_request_success'))
        <div class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 rounded-lg border border-cyan-400/30 bg-cyan-400/10 px-4 py-3 text-sm font-medium text-cyan-100">
                <x-icon name="lucide-check" class="h-5 w-5 shrink-0 text-cyan-300" />
                <span>{{ session('tournament_request_success') }}</span>
            </div>
        </div>
    @endif

    {{-- Livewire Component --}}
    <livewire:tournament-list />
@endsection

@push('styles')
<style>
    h1, h2, h3, h4, h5, h6 {
        font-family: var(--font-display);
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 12px;
    }

    ::-webkit-scrollbar-track {
        background: #0f172a;
    }

    ::-webkit-scrollbar-thumb {
        background: #ff66aa;
        border-radius: 6px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #ff80bb;
    }
</style>
@endpush

@push('scripts')
<script>
    // Store current URL before navigating to tournament detail
    document.addEventListener('DOMContentLoaded', function() {
        const returnStateKey = 'tournamentsReturnState';

        const restoreReturnState = function() {
            const rawState = localStorage.getItem(returnStateKey);

            if (!rawState) {
                return;
            }

            try {
                const state = JSON.parse(rawState);

                if (state.url === window.location.href && Number.isFinite(state.scrollY)) {
                    requestAnimationFrame(function() {
                        window.scrollTo({ top: state.scrollY, behavior: 'auto' });
                    });

                    window.addEventListener('load', function() {
                        window.scrollTo({ top: state.scrollY, behavior: 'auto' });
                        localStorage.removeItem(returnStateKey);
                    }, { once: true });
                }
            } catch (error) {
                localStorage.removeItem(returnStateKey);
            }
        };

        restoreReturnState();

        // Use event delegation for dynamic content
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a[href*="/tournaments/"]');

            if (link) {
                // Store current URL with all query parameters
                const currentUrl = window.location.href;
                localStorage.setItem('tournamentsReferrer', currentUrl);
                localStorage.setItem(returnStateKey, JSON.stringify({
                    url: currentUrl,
                    scrollY: window.scrollY,
                }));
                console.log('Stored referrer:', currentUrl);
            }
        }, true); // Use capture phase to catch clicks early
    });
</script>
@endpush
