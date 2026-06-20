@auth
<div x-data="notificationDropdown({
        indexUrl: @js(route('notifications.index')),
        readAllUrl: @js(route('notifications.read-all')),
        removeReadUrl: @js(route('notifications.destroy-read')),
        readUrlTemplate: @js(route('notifications.read', ['notification' => '__ID__'])),
        dismissUrlTemplate: @js(route('notifications.destroy', ['notification' => '__ID__'])),
    })"
     x-init="load"
     class="relative">
    <button type="button"
            @click="toggle"
            class="nav-icon-btn relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-300 transition-colors hover:bg-dark-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-osu-pink/40"
            aria-label="Notifications">
        <x-icon name="lucide-bell" class="h-5 w-5" />
        <span x-show="unreadCount > 0"
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-500 px-1.5 py-0.5 text-center text-[11px] font-bold leading-none text-white ring-2 ring-dark-850"
              style="display: none;"></span>
    </button>

    <div x-show="open"
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="transform opacity-0 scale-95 -translate-y-2"
         x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="transform opacity-0 scale-95 -translate-y-2"
         class="ui-panel-popover absolute right-0 z-50 mt-3 w-[min(22rem,calc(100vw-2rem))] overflow-hidden shadow-black/30"
         style="display: none;">
        <div class="flex items-center justify-between gap-3 border-b border-dark-700 px-4 py-3">
            <h2 class="text-sm font-semibold text-white">{{ __('common.notifications.title') }}</h2>
            <div class="flex flex-wrap justify-end gap-x-3 gap-y-1">
                <button x-show="notifications.some((notification) => !notification.read_at)"
                        type="button"
                        @click="readAll"
                        class="text-xs font-semibold text-osu-pink hover:text-pink-300"
                        style="display: none;">
                    {{ __('common.notifications.read_all') }}
                </button>
                <button x-show="notifications.some((notification) => notification.read_at)"
                        type="button"
                        @click="removeRead"
                        class="text-xs font-semibold text-gray-400 hover:text-red-300"
                        style="display: none;">
                    {{ __('common.notifications.remove_read') }}
                </button>
            </div>
        </div>

        <div class="max-h-96 overflow-y-auto">
            <template x-if="loading">
                <div class="px-4 py-6 text-center text-sm text-gray-400">{{ __('common.notifications.loading') }}</div>
            </template>

            <template x-if="!loading && notifications.length === 0">
                <div class="px-4 py-6 text-center text-sm text-gray-400">{{ __('common.notifications.empty') }}</div>
            </template>

            <template x-for="notification in notifications" :key="notification.id">
                <div class="group flex gap-3 border-b border-dark-700/70 px-4 py-3 last:border-b-0"
                     :class="notification.read_at ? 'bg-dark-850' : 'bg-red-500/10'">
                    <button type="button"
                            @click="openNotification(notification)"
                            class="min-w-0 flex-1 text-left">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-white" x-text="notification.title"></h3>
                            <span class="shrink-0 text-[11px] text-gray-500" x-text="notification.created_label"></span>
                        </div>
                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-gray-400" x-text="notification.body"></p>
                    </button>
                    <button type="button"
                            @click.stop="dismiss(notification)"
                            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-500 transition hover:bg-dark-700 hover:text-white"
                            aria-label="Dismiss notification">
                        <x-icon name="lucide-x" class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('notificationDropdown', (config) => ({
            open: false,
            loading: false,
            notifications: [],
            unreadCount: 0,

            async load() {
                this.loading = true;
                try {
                    const response = await fetch(config.indexUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    if (response.ok) {
                        const data = await response.json();
                        this.notifications = data.notifications || [];
                        this.unreadCount = data.unread_count || 0;
                    }
                } finally {
                    this.loading = false;
                }
            },

            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.load();
                }
            },

            async readAll() {
                await fetch(config.readAllUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                this.notifications = this.notifications.map((notification) => ({
                    ...notification,
                    read_at: notification.read_at || new Date().toISOString(),
                }));
                this.unreadCount = 0;
            },

            async removeRead() {
                const response = await fetch(config.removeReadUrl, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                if (response.ok) {
                    this.notifications = this.notifications.filter((notification) => !notification.read_at);
                    this.unreadCount = this.notifications.length;
                }
            },

            async openNotification(notification) {
                await fetch(config.readUrlTemplate.replace('__ID__', notification.id), {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                window.location.href = notification.action_url;
            },

            async dismiss(notification) {
                const response = await fetch(config.dismissUrlTemplate.replace('__ID__', notification.id), {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                if (response.ok) {
                    this.notifications = this.notifications.filter((item) => item.id !== notification.id);
                    this.unreadCount = this.notifications.filter((item) => !item.read_at).length;
                }
            },
        }));
    });
</script>
@endpush
@endonce
@endauth
