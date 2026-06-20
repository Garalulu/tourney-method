<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Tourney Method') }} - @yield('title', 'Tournament Discovery Platform')</title>

    @hasSection('meta')
        @yield('meta')
    @else
        <!-- SEO Meta Tags -->
        <meta name="description" content="@yield('description', 'Discover and manage osu! tournaments with advanced BWS calculations and streamlined organization.')">
        <meta name="keywords" content="osu, tournament, BWS, badge weighted seeding, competitive gaming">
        <meta name="author" content="Tourney Method">

        <!-- Open Graph / Social Media -->
        <meta property="og:site_name" content="Tourney Method">
        <meta property="og:type" content="website">
        <meta property="og:title" content="@yield('title', 'Tournament Discovery Platform')">
        <meta property="og:description" content="@yield('description', 'Discover and manage osu! tournaments with advanced BWS calculations and streamlined organization.')">
        <meta property="og:url" content="{{ url()->current() }}">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="@yield('title', 'Tournament Discovery Platform')">
        <meta name="twitter:description" content="@yield('description', 'Discover and manage osu! tournaments with advanced BWS calculations and streamlined organization.')">

        <link rel="canonical" href="{{ url()->current() }}">
    @endif
    @stack('head')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800|manrope:400,500,600,700,800|noto-sans-kr:400,500,600,700|noto-sans-sc:400,500,600,700|noto-sans-tc:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    @stack('styles')
</head>
<body class="min-h-screen bg-dark-900 text-gray-100 antialiased">
    <!-- Background Pattern -->
    <div class="fixed inset-0 bg-grid-pattern bg-grid opacity-40 pointer-events-none"></div>

    <!-- Main Layout -->
    <div class="relative min-h-screen flex flex-col">
        <!-- Navigation -->
        <x-navigation />

        <!-- Page Content -->
        <main class="flex-1">
            <!-- Page Header (if provided) -->
            @hasSection('header')
                <header class="bg-dark-850/80 backdrop-blur-sm border-b border-dark-700">
                    <div class="container py-6">
                        @yield('header')
                    </div>
                </header>
            @endif

            @if (session('success') || session('error'))
                <div class="fixed bottom-4 right-4 z-[60] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 pointer-events-none">
                    @if (session('success'))
                        <div x-data="{ show: true }"
                             x-show="show"
                             x-transition
                             x-init="setTimeout(() => show = false, 5000)"
                             class="pointer-events-auto bg-dark-850/95 border border-osu-cyan/30 rounded-lg p-4 shadow-xl shadow-black/30 backdrop-blur-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <x-icon name="lucide-check" class="w-5 h-5 text-osu-cyan" />
                            <p class="text-sm font-medium text-osu-cyan">{{ session('success') }}</p>
                        </div>
                        <button @click="show = false" class="text-osu-cyan/60 hover:text-osu-cyan transition-colors">
                            <x-icon name="lucide-x" class="w-4 h-4" />
                        </button>
                    </div>
                    @endif

                    @if (session('error'))
                        <div x-data="{ show: true }"
                             x-show="show"
                             x-transition
                             x-init="setTimeout(() => show = false, 5000)"
                             class="pointer-events-auto bg-dark-850/95 border border-red-500/30 rounded-lg p-4 shadow-xl shadow-black/30 backdrop-blur-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <x-icon name="lucide-circle-alert" class="w-5 h-5 text-red-400" />
                            <p class="text-sm font-medium text-red-400">{{ session('error') }}</p>
                        </div>
                        <button @click="show = false" class="text-red-400/60 hover:text-red-400 transition-colors">
                            <x-icon name="lucide-x" class="w-4 h-4" />
                        </button>
                    </div>
                    @endif
                </div>
            @endif

            <div x-data="{
                    toast: null,
                    show: false,
                    timer: null,
                    notify(event) {
                        this.toast = event.detail || null;
                        this.show = !!this.toast;
                        clearTimeout(this.timer);
                        this.timer = setTimeout(() => this.show = false, 5000);
                    },
                }"
                 @app-toast.window="notify($event)"
                 class="fixed bottom-4 right-4 z-[60] w-[calc(100vw-2rem)] max-w-sm pointer-events-none">
                <div x-show="show"
                     x-transition
                     class="pointer-events-auto bg-dark-850/95 rounded-lg p-4 shadow-xl shadow-black/30 backdrop-blur-sm flex items-center justify-between"
                     :class="toast?.type === 'error' ? 'border border-red-500/30' : 'border border-osu-cyan/30'"
                     style="display: none;">
                    <div class="flex items-center gap-3">
                        <x-icon name="lucide-check" x-show="toast?.type !== 'error'" class="w-5 h-5 text-osu-cyan" />
                        <x-icon name="lucide-circle-alert" x-show="toast?.type === 'error'" class="w-5 h-5 text-red-400" />
                        <p class="text-sm font-medium" :class="toast?.type === 'error' ? 'text-red-400' : 'text-osu-cyan'" x-text="toast?.message"></p>
                    </div>
                    <button @click="show = false" class="transition-colors" :class="toast?.type === 'error' ? 'text-red-400/60 hover:text-red-400' : 'text-osu-cyan/60 hover:text-osu-cyan'">
                        <x-icon name="lucide-x" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="container py-8">
                @yield('content')
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-dark-850/30 backdrop-blur-sm border-t border-dark-700/50 mt-auto">
            <div class="container py-6">
                <!-- Links -->
                <div class="flex items-center justify-center gap-6 text-sm mb-4">
                    <a href="https://osu.ppy.sh/users/757783" class="footer-link">{{ __('common.footer.contact') }}</a>

                    <span class="footer-separator"></span>

                    <a href="https://discord.gg/a6P57Eb6Js" target="_blank" rel="noopener noreferrer" class="footer-link">{{ __('common.footer.discord') }}</a>

                    <span class="footer-separator"></span>

                    <a href="https://ko-fi.com/garalulu" class="footer-link">{{ __('common.footer.donate') }}</a>

                    <span class="footer-separator"></span>

                    <a href="{{ route('contribute') }}" class="footer-link">{{ __('common.footer.contribute') }}</a>

                </div>

                <!-- Copyright -->
                <div class="text-center text-xs text-gray-500">
                    {{ __('common.footer.copyright') }}
                </div>
            </div>
        </footer>
    </div>

    @livewireScripts
    <div x-data="changelogPopup({
            version: @js(config('changelog.version')),
            seenKey: @js('tourney-method-changelog-seen:'.config('changelog.version')),
            lastShownKey: @js('tourney-method-changelog-last-shown:'.config('changelog.version')),
        })"
         x-show="open"
         x-cloak
         class="fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
         style="display: none;">
        <div class="w-full max-w-lg rounded-lg border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-700 px-5 py-4">
                <div>
                    <p class="text-xs font-bold uppercase text-pink-300">{{ __('common.changelog.eyebrow') }}</p>
                    <h2 class="mt-1 font-display text-xl font-bold text-white">{{ config('changelog.title') }}</h2>
                </div>
                <button type="button" @click="close(false)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="{{ __('common.changelog.close') }}">
                    <x-icon name="lucide-x" class="h-5 w-5" />
                </button>
            </div>
            <div class="space-y-4 px-5 py-4">
                <p class="text-sm leading-6 text-slate-300">{{ config('changelog.body') }}</p>
                <ul class="space-y-2 text-sm text-slate-300">
                    @foreach(config('changelog.items', []) as $item)
                        <li class="flex gap-2">
                            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-pink-400"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <label class="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-300">
                    <input type="checkbox" x-model="dontShowAgain" class="rounded border-slate-700 bg-slate-900 text-pink-500 focus:ring-pink-500">
                    <span>{{ __('common.changelog.dont_show_again') }}</span>
                </label>
            </div>
            <div class="flex justify-end border-t border-slate-700 px-5 py-4">
                <button type="button" @click="close(dontShowAgain)" class="rounded-lg bg-pink-500 px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                    {{ __('common.changelog.ok') }}
                </button>
            </div>
        </div>
    </div>

    <script>
        function changelogPopup(config) {
            return {
                open: false,
                dontShowAgain: false,
                init() {
                    const lastShown = Number(localStorage.getItem(config.lastShownKey) || 0);
                    const oneDay = 24 * 60 * 60 * 1000;

                    if (localStorage.getItem(config.seenKey) === config.version) return;
                    if (lastShown && Date.now() - lastShown < oneDay) return;

                    this.open = true;
                    localStorage.setItem(config.lastShownKey, String(Date.now()));
                },
                close(remember) {
                    if (remember) {
                        localStorage.setItem(config.seenKey, config.version);
                    }

                    this.open = false;
                },
            };
        }
    </script>
    @stack('scripts')
</body>
</html>
