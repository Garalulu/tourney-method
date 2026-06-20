@extends('layouts.app')

@section('title', 'Complete Setup')

@section('content')
<div class="min-h-[calc(100vh-20rem)] flex items-center justify-center py-12">
    <div class="w-full max-w-2xl">
        <x-ui.panel as="section">
            <div class="border-b border-dark-700 px-6 py-5">
                <div class="flex items-center gap-3">
                    <img src="{{ auth()->user()->avatar_url }}"
                         alt="{{ auth()->user()->username }}"
                         class="h-12 w-12 rounded-full border-2 border-dark-600 object-cover">
                    <div>
                        <p class="text-lg font-semibold text-white">{{ auth()->user()->username }}</p>
                        <p class="text-sm text-gray-400">{{ __('auth.setup.account_connected') }}</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('setup.store') }}" class="space-y-6 p-6">
                @csrf

                <div>
                    <h1 class="font-display text-2xl font-bold text-white">
                        {{ __('auth.setup.select_mode') }}
                    </h1>
                    <p class="mt-2 text-sm text-gray-400">
                        {{ __('auth.setup.info') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach([
                        'osu' => [
                            'label' => 'osu!',
                            'icon' => 'std_white.webp',
                            'accent' => 'bg-osu-pink/15 ring-osu-pink/30',
                        ],
                        'taiko' => [
                            'label' => 'osu!taiko',
                            'icon' => 'taiko_white.webp',
                            'accent' => 'bg-osu-cyan/15 ring-osu-cyan/30',
                        ],
                        'catch' => [
                            'label' => 'osu!catch',
                            'icon' => 'catch_white.webp',
                            'accent' => 'bg-green-400/15 ring-green-400/30',
                        ],
                        'mania' => [
                            'label' => 'osu!mania',
                            'icon' => 'mania_white.webp',
                            'accent' => 'bg-purple-400/15 ring-purple-400/30',
                        ],
                    ] as $mode => $config)
                        <label class="relative cursor-pointer">
                            <input type="radio"
                                   name="main_mode"
                                   value="{{ $mode }}"
                                   class="peer sr-only"
                                   {{ $loop->first ? 'required' : '' }}>

                            <div class="flex h-20 items-center gap-4 rounded-lg border-2 border-dark-600 bg-dark-900/30 px-4 pr-12 transition-colors hover:border-dark-500 peer-checked:border-osu-pink peer-checked:bg-osu-pink/10">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg ring-1 {{ $config['accent'] }}">
                                    <img src="{{ asset('images/gamemodes/'.$config['icon']) }}"
                                         alt=""
                                         class="h-7 w-7 object-contain opacity-90"
                                         aria-hidden="true">
                                </span>

                                <span class="font-display text-base font-semibold text-gray-300 transition-colors peer-checked:text-white">
                                    {{ $config['label'] }}
                                </span>
                            </div>

                            <span class="absolute right-4 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border border-dark-500 text-transparent transition-colors peer-checked:border-osu-pink peer-checked:bg-osu-pink peer-checked:text-white">
                                <x-icon name="lucide-check" class="h-3.5 w-3.5" />
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('main_mode')
                    <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-4">
                        <div class="flex items-center gap-3">
                            <x-icon name="lucide-circle-x" class="h-5 w-5 flex-shrink-0 text-red-400" />
                            <p class="text-sm text-red-400">{{ $message }}</p>
                        </div>
                    </div>
                @enderror

                <div class="flex justify-end border-t border-dark-700 pt-6">
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-osu-pink px-5 py-2.5 font-display text-sm font-semibold text-white transition hover:bg-osu-pink/90 focus:outline-none focus:ring-2 focus:ring-osu-pink/40 focus:ring-offset-2 focus:ring-offset-dark-900">
                        {{ __('auth.setup.submit') }}
                        <x-icon name="lucide-arrow-right" class="h-4 w-4" />
                    </button>
                </div>
            </form>
        </x-ui.panel>
    </div>
</div>
@endsection
