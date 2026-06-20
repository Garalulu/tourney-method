<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Tourney Method</title>
    @yield('meta')
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/diff-viewer.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800|manrope:400,500,600,700,800|noto-sans-kr:400,500,600,700|noto-sans-sc:400,500,600,700|noto-sans-tc:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --admin-bg: #0f0f14;
            --admin-surface: #1a1a24;
            --admin-border: #2a2a38;
            --admin-text: #e4e4e7;
            --admin-muted: #a1a1aa;
            --osu-pink: #ff66aa;
            --osu-cyan: #00d9ff;
            --success: #4ade80;
            --danger: #f87171;
        }

        body {
            font-family: var(--font-body);
            background: var(--admin-bg);
            color: var(--admin-text);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-display);
        }

        .glow-pink {
            box-shadow: 0 0 20px rgba(255, 102, 170, 0.3);
        }

        .glow-cyan {
            box-shadow: 0 0 20px rgba(0, 217, 255, 0.3);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-item {
            animation: slideIn 0.3s ease-out backwards;
        }

        .nav-item:nth-child(1) { animation-delay: 0.05s; }
        .nav-item:nth-child(2) { animation-delay: 0.1s; }
        .nav-item:nth-child(3) { animation-delay: 0.15s; }
        .nav-item:nth-child(4) { animation-delay: 0.2s; }

        .flash-enter {
            animation: flashSlide 0.4s ease-out;
        }

        @keyframes flashSlide {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin-slow {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .animate-spin-slow {
            animation: spin-slow 3s linear infinite;
        }
    </style>
</head>
<body class="h-full overflow-hidden">
    <div class="flex h-full" x-data="{ adminSidebarOpen: false }">
        <div
            x-show="adminSidebarOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-black/60 lg:hidden"
            style="display: none;"
            @click="adminSidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-[var(--admin-border)] bg-[var(--admin-surface)] transition-transform duration-200 lg:static lg:translate-x-0"
            :class="{ 'translate-x-0': adminSidebarOpen }"
        >
            <!-- Logo/Header -->
            <div class="p-6 border-b border-[var(--admin-border)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center space-x-3">
                        <div>
                            <h1 class="text-lg font-bold tracking-tight">{{ __('admin.panel.title') }}</h1>
                            <p class="text-xs text-[var(--admin-muted)]">{{ __('admin.panel.subtitle') }}</p>
                        </div>
                    </div>
                    <button type="button" class="rounded-lg p-2 hover:bg-[var(--admin-bg)] lg:hidden" @click="adminSidebarOpen = false">
                        <x-icon name="lucide-x" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Navigation -->
            @php
                $adminNavItems = [
                    [
                        'label' => 'Dashboard',
                        'route' => route('admin.dashboard'),
                        'active' => request()->routeIs('admin.dashboard'),
                        'icon' => 'lucide-layout-dashboard',
                    ],
                    [
                        'label' => 'Tournament Queue',
                        'route' => route('admin.tournaments.pending'),
                        'active' => request()->routeIs('admin.tournaments.pending'),
                        'icon' => 'lucide-clock',
                    ],
                    [
                        'label' => 'Maintenance Runs',
                        'route' => route('admin.imports.index'),
                        'active' => request()->routeIs('admin.imports.*'),
                        'icon' => 'lucide-download',
                    ],
                    [
                        'label' => 'Discord Servers',
                        'route' => route('admin.discord-servers.index'),
                        'active' => request()->routeIs('admin.discord-servers.*'),
                        'icon' => 'lucide-message-circle',
                    ],
                    [
                        'label' => __('admin.nav.audit_log'),
                        'route' => route('admin.audit-log.index'),
                        'active' => request()->routeIs('admin.audit-log.*'),
                        'icon' => 'lucide-file-text',
                    ],
                    [
                        'label' => __('admin.nav.participation_moderation'),
                        'route' => route('admin.participation-moderation.index'),
                        'active' => request()->routeIs('admin.participation-moderation.*'),
                        'icon' => 'lucide-folder',
                    ],
                    [
                        'label' => __('admin.corrections.nav_label'),
                        'route' => route('admin.tournament-corrections.index'),
                        'active' => request()->routeIs('admin.tournament-corrections.*'),
                        'icon' => 'lucide-pencil',
                    ],
                ];

                if (Auth::user()->isMaster()) {
                    $adminNavItems[] = [
                        'label' => 'Users',
                        'route' => route('admin.users.index'),
                        'active' => request()->routeIs('admin.users.*'),
                        'icon' => 'lucide-users',
                    ];
                }
            @endphp

            <nav class="flex-1 space-y-1 overflow-y-auto p-4">
                @foreach($adminNavItems as $item)
                    <a href="{{ $item['route'] }}"
                       class="nav-item flex min-h-11 items-center gap-3 rounded-lg px-4 py-3 text-sm transition-all duration-200 hover:bg-[var(--admin-bg)] {{ $item['active'] ? 'border-l-2 border-[var(--osu-pink)] bg-[var(--admin-bg)] text-[var(--osu-pink)]' : 'text-[var(--admin-text)]' }}">
                        <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                        <span class="min-w-0 truncate font-medium">{{ $item['label'] }}</span>
                    </a>
                @endforeach

                <div class="my-3 border-t border-[var(--admin-border)]"></div>

                <a href="{{ route('dashboard') }}"
                   class="nav-item flex min-h-11 items-center gap-3 rounded-lg px-4 py-3 text-sm text-[var(--admin-muted)] transition-all duration-200 hover:bg-[var(--admin-bg)] hover:text-[var(--admin-text)]">
                    <x-icon name="lucide-home" class="h-5 w-5 shrink-0" />
                    <span class="min-w-0 truncate font-medium">{{ __('admin.nav.player_dashboard') }}</span>
                </a>

            </nav>

            <!-- Admin Info -->
            <div class="p-4 border-t border-[var(--admin-border)]">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <img
                            src="https://a.ppy.sh/{{ Auth::user()->osu_id }}"
                            alt="{{ Auth::user()->username }}"
                            class="h-8 w-8 rounded-full border border-[var(--admin-border)] object-cover"
                        >
                        <div class="text-sm">
                            <p class="font-semibold">{{ Auth::user()->username }}</p>
                            <p class="text-xs text-[var(--admin-muted)] capitalize">{{ Auth::user()->role }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="p-2 hover:bg-[var(--admin-bg)] rounded-lg transition-colors" title="Logout">
                            <x-icon name="lucide-log-out" class="w-5 h-5 text-[var(--admin-muted)] hover:text-[var(--danger)]" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-auto">
            <div class="flex items-center gap-3 border-b border-[var(--admin-border)] bg-[var(--admin-surface)] px-4 py-3 lg:hidden">
                <button type="button" class="rounded-lg border border-[var(--admin-border)] p-2" @click="adminSidebarOpen = true">
                    <x-icon name="lucide-menu" class="h-5 w-5 text-[var(--admin-text)]" />
                </button>
                <span class="font-semibold">{{ __('admin.panel.title') }}</span>
            </div>

            @if(session('success') || session('error'))
                <div class="fixed bottom-4 right-4 z-[60] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 pointer-events-none">
                    @if(session('success'))
                        <div class="flash-enter pointer-events-auto rounded-lg border border-green-500/40 bg-[var(--admin-surface)] px-4 py-3 text-white shadow-xl shadow-black/30">
                            <div class="flex items-center space-x-3">
                                <x-icon name="lucide-circle-check" class="w-5 h-5 text-[var(--success)]" />
                                <span class="font-medium">{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="flash-enter pointer-events-auto rounded-lg border border-red-500/40 bg-[var(--admin-surface)] px-4 py-3 text-white shadow-xl shadow-black/30">
                            <div class="flex items-center space-x-3">
                                <x-icon name="lucide-circle-alert" class="w-5 h-5 text-[var(--danger)]" />
                                <span class="font-medium">{{ session('error') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
