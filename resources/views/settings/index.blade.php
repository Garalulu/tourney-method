@extends('layouts.app')

@section('title', 'Settings')

@section('header')
    <h1 class="text-2xl font-display font-bold text-white">{{ __('settings.title') }}</h1>
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-8">
    {{-- Main Gamemode --}}
    <x-ui.panel>
        <div class="px-6 py-4 border-b border-dark-700">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-osu-cyan/20">
                    <x-icon name="lucide-play-circle" class="w-5 h-5 text-osu-cyan" />
                </div>
                <div>
                    <h2 class="text-lg font-display font-semibold text-white">{{ __('settings.game_mode.title') }}</h2>
                    <p class="text-sm text-gray-400">{{ __('settings.game_mode.description') }}</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach(['osu' => 'osu!', 'taiko' => 'osu!taiko', 'catch' => 'osu!catch', 'mania' => 'osu!mania'] as $mode => $label)
                    <label class="relative cursor-pointer">
                        <input type="radio"
                               form="settings-form"
                               name="main_mode"
                               value="{{ $mode }}"
                               {{ ($settings['main_mode'] ?? '') === $mode ? 'checked' : '' }}
                               class="peer sr-only">
                        <div class="p-4 rounded-lg border-2 border-dark-600 bg-dark-900/30 text-center transition-all peer-checked:border-osu-pink peer-checked:bg-osu-pink/10 hover:border-dark-500">
                            <span class="block text-sm font-medium text-gray-300 peer-checked:text-osu-pink">{{ $label }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
    </x-ui.panel>


    {{-- Discord Webhook Section --}}
    <x-ui.panel>
        <div class="px-6 py-4 border-b border-dark-700">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-[#5865F2]/20">
                    <x-icon name="si-discord" class="w-5 h-5 text-[#5865F2]" />
                </div>
                <div>
                    <h2 class="text-lg font-display font-semibold text-white">{{ __('settings.discord_webhook.title') }}</h2>
                    <p class="text-sm text-gray-400">{{ __('settings.discord_webhook.description') }}</p>
                </div>
            </div>
        </div>

        <form id="settings-form" action="{{ route('settings.update') }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PATCH')

            {{-- Webhook URL Input --}}
            <div>
                <label for="discord_webhook_url" class="block text-sm font-medium text-gray-300 mb-2">
                    {{ __('settings.discord_webhook.webhook_url') }}
                </label>
                <div class="relative">
                    <input type="url"
                           id="discord_webhook_url"
                           name="discord_webhook_url"
                           value="{{ old('discord_webhook_url', $settings['discord_webhook_url'] ?? '') }}"
                           placeholder="https://discord.com/api/webhooks/..."
                           class="w-full px-4 py-3 bg-dark-900/50 border border-dark-600 rounded-lg text-gray-100 placeholder-gray-500 focus:outline-none focus:border-osu-pink focus:ring-1 focus:ring-osu-pink transition-colors">
                    @if(isset($settings['discord_webhook_valid']) && $settings['discord_webhook_url'])
                        <div class="absolute right-3 top-1/2 -translate-y-1/2" data-webhook-validity>
                            @if($settings['discord_webhook_valid'])
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400">
                                    <x-icon name="lucide-check" class="w-3 h-3" />
                                    {{ __('settings.discord_webhook.valid') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400">
                                    <x-icon name="lucide-x" class="w-3 h-3" />
                                    {{ __('settings.discord_webhook.invalid') }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
                @error('discord_webhook_url')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-gray-500">
                    <a href="{{ __('settings.discord_webhook.how_to_url') }}" target="_blank" rel="noopener" class="text-osu-cyan hover:underline">
                        {{ __('settings.discord_webhook.how_to') }}
                    </a>
                </p>
            </div>

            {{-- Test Webhook Button --}}
            @if($settings['discord_webhook_url'] ?? false)
                <div>
                    <button type="button"
                            id="test-webhook-btn"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-[#5865F2]/20 hover:bg-[#5865F2]/30 border border-[#5865F2]/30 rounded-lg text-sm font-medium text-[#5865F2] transition-colors">
                        <x-icon name="lucide-message-circle" class="w-4 h-4" />
                        {{ __('settings.discord_webhook.test_button') }}
                    </button>
                    <p id="test-result" class="mt-2 text-sm hidden"></p>
                </div>
            @endif
        </form>
    </x-ui.panel>

    {{-- Notification Preferences Section --}}
    <x-ui.panel>
        <div class="px-6 py-4 border-b border-dark-700">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-osu-pink/20">
                    <x-icon name="lucide-bell" class="w-5 h-5 text-osu-pink" />
                </div>
                <div>
                    <h2 class="text-lg font-display font-semibold text-white">{{ __('settings.in_app_notifications.title') }}</h2>
                    <p class="text-sm text-gray-400">{{ __('settings.in_app_notifications.description') }}</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            @foreach([
                'participation_records' => ['teammate_changes', 'participation_updates', 'deletion_requests'],
                'new_tournament_alert' => ['eligible_registration_open', 'eligible_registration_closing_24h'],
            ] as $category => $events)
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-400">{{ __('settings.in_app_notifications.categories.'.$category) }}</h3>
                    @foreach($events as $event)
                        <label class="flex items-start gap-4 p-4 bg-dark-900/30 rounded-lg cursor-pointer hover:bg-dark-900/50 transition-colors">
                            <div class="relative flex items-center">
                                <input type="hidden"
                                       form="settings-form"
                                       name="in_app_notification_preferences[{{ $event }}]"
                                       value="0">
                                <input type="checkbox"
                                       form="settings-form"
                                       name="in_app_notification_preferences[{{ $event }}]"
                                       value="1"
                                       {{ ($settings['in_app_notification_preferences'][$event] ?? true) ? 'checked' : '' }}
                                       class="peer sr-only">
                                <div class="w-11 h-6 bg-dark-600 rounded-full peer-checked:bg-osu-pink transition-colors"></div>
                                <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
                            </div>
                            <div class="flex-1">
                                <span class="block text-sm font-medium text-white">{{ __('settings.in_app_notifications.events.'.$event.'.title') }}</span>
                                <span class="block text-xs text-gray-400 mt-1">{{ __('settings.in_app_notifications.events.'.$event.'.description') }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-ui.panel>

    {{-- Save Button --}}
    <div class="flex justify-end">
        <button type="submit"
                form="settings-form"
                class="px-6 py-3 bg-gradient-to-r from-osu-pink to-osu-pink/80 hover:from-osu-pink/90 hover:to-osu-pink/70 rounded-lg text-white font-medium shadow-lg shadow-osu-pink/25 transition-all">
            {{ __('settings.save_button') }}
        </button>
    </div>
</div>

@push('scripts')
<script>
    // Helper function to show toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-dark-850/95 border-osu-cyan/30' : 'bg-dark-850/95 border-red-500/30';
        const textColor = type === 'success' ? 'text-osu-cyan' : 'text-red-400';
        const buttonColor = type === 'success' ? 'text-osu-cyan/60 hover:text-osu-cyan' : 'text-red-400/60 hover:text-red-400';
        const icon = type === 'success'
            ? '<x-icon name="lucide-check" class="w-5 h-5 text-osu-cyan" />'
            : '<x-icon name="lucide-circle-alert" class="w-5 h-5 text-red-400" />';
        
        toast.className = 'pointer-events-auto';
        toast.innerHTML = `
            <div x-data="{ show: true }" 
                 x-show="show" 
                 x-transition 
                 x-init="setTimeout(() => show = false, 5000); setTimeout(() => $el.remove(), 5500)"
                class="${bgColor} border rounded-lg p-4 shadow-xl shadow-black/30 backdrop-blur-sm flex items-center justify-between">
                <div class="flex items-center gap-3">
                    ${icon}
                    <p class="text-sm font-medium ${textColor}">${message}</p>
                </div>
                <button @click="show = false" class="${buttonColor} transition-colors">
                    <x-icon name="lucide-x" class="w-4 h-4" />
                </button>
            </div>
        `;
        
        let toastStack = document.getElementById('toast-stack');
        if (!toastStack) {
            toastStack = document.createElement('div');
            toastStack.id = 'toast-stack';
            toastStack.className = 'fixed bottom-4 right-4 z-[60] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 pointer-events-none';
            document.body.appendChild(toastStack);
        }

        toastStack.appendChild(toast);
        
        // Initialize Alpine reactivity
        if (window.Alpine) {
            window.Alpine.initTree(toast);
        }
    }

    // Settings form submission handler
    const settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.querySelector('button[form="settings-form"]');
            if (!submitBtn) return;
            
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <x-icon name="lucide-loader-circle" class="w-5 h-5 animate-spin inline mr-2" />
                {{ __('settings.saving') }}
            `;
            
            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    showToast('{{ __('settings.saved') }}', 'success');
                    
                    // Update webhook validity indicator or remove it if webhook was cleared
                    if (data.discord_webhook_url === null) {
                        removeWebhookValidityIndicator();
                    } else if (data.discord_webhook_valid !== undefined) {
                        updateWebhookValidityIndicator(data.discord_webhook_valid);
                    }
                } else {
                    // Handle validation errors
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0];
                        showToast(Array.isArray(firstError) ? firstError[0] : firstError, 'error');
                    } else if (data.message) {
                        showToast(data.message, 'error');
                    } else {
                        showToast('{{ __('settings.save_error') }}', 'error');
                    }
                }
            } catch (error) {
                console.error('Settings save error:', error);
                showToast('{{ __('settings.save_error') }}', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    // Helper function to remove webhook validity indicator
    function removeWebhookValidityIndicator() {
        const indicator = document.querySelector('[data-webhook-validity]');
        indicator?.remove();
    }

    // Helper function to update webhook validity indicator
    function updateWebhookValidityIndicator(isValid) {
        const container = document.querySelector('#discord_webhook_url')?.parentElement;
        if (!container) return;
        
        let existingIndicator = container.querySelector('[data-webhook-validity]');
        
        if (isValid) {
            const newIndicator = document.createElement('div');
            newIndicator.className = 'absolute right-3 top-1/2 -translate-y-1/2';
            newIndicator.setAttribute('data-webhook-validity', '');
            newIndicator.innerHTML = `
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400">
                    <x-icon name="lucide-check" class="w-3 h-3" />
                    {{ __('settings.discord_webhook.valid') }}
                </span>
            `;
            
            if (existingIndicator) {
                existingIndicator.replaceWith(newIndicator);
            } else {
                container.appendChild(newIndicator);
            }
        } else {
            // Show invalid indicator
            const newIndicator = document.createElement('div');
            newIndicator.className = 'absolute right-3 top-1/2 -translate-y-1/2';
            newIndicator.setAttribute('data-webhook-validity', '');
            newIndicator.innerHTML = `
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400">
                    <x-icon name="lucide-x" class="w-3 h-3" />
                    {{ __('settings.discord_webhook.invalid') }}
                </span>
            `;
            
            if (existingIndicator) {
                existingIndicator.replaceWith(newIndicator);
            } else {
                container.appendChild(newIndicator);
            }
        }
    }

    document.getElementById('test-webhook-btn')?.addEventListener('click', async function() {
        const btn = this;
        const resultEl = document.getElementById('test-result');

        btn.disabled = true;
        btn.innerHTML = `
            <x-icon name="lucide-loader-circle" class="w-4 h-4 animate-spin" />
            {{ __('settings.discord_webhook.test_sending') }}
        `;

        try {
            const response = await fetch('{{ route('webhook.test') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            resultEl.classList.remove('hidden', 'text-green-400', 'text-red-400');

            if (response.ok) {
                resultEl.classList.add('text-green-400');
                resultEl.textContent = '{{ __('settings.discord_webhook.test_success') }}';
            } else {
                resultEl.classList.add('text-red-400');
                resultEl.textContent = data.message || '{{ __('settings.discord_webhook.test_error') }}';
            }
        } catch (error) {
            resultEl.classList.remove('hidden', 'text-green-400');
            resultEl.classList.add('text-red-400');
            resultEl.textContent = '{{ __('settings.discord_webhook.test_error') }}';
        }

        btn.disabled = false;
        btn.innerHTML = `
            <x-icon name="lucide-message-circle" class="w-4 h-4" />
            {{ __('settings.discord_webhook.test_button') }}
        `;
    });
</script>
@endpush
@endsection
