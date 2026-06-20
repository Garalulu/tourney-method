@extends('layouts.app')

@section('title', '500 - Server Error')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center">
    <div class="text-center animate-fade-in">
        <!-- Error Code -->
        <div class="mb-8">
            <h1 class="font-display font-black text-9xl bg-gradient-to-r from-purple-500 to-pink-500 bg-clip-text text-transparent">
                500
            </h1>
        </div>

        <!-- Icon Illustration -->
        <div class="mb-8 flex justify-center">
            <div class="relative">
                <div class="absolute inset-0 bg-purple-500/20 blur-3xl rounded-full animate-pulse-slow"></div>
                <x-icon name="lucide-triangle-alert" class="relative w-32 h-32 text-purple-400" />
            </div>
        </div>

        <!-- Message -->
        <h2 class="font-display font-bold text-3xl mb-4 text-white">{{ __('common.errors.500.heading') }}</h2>
        <p class="text-gray-400 max-w-md mx-auto mb-8">
            Our servers encountered an unexpected error. We've been notified and are working to fix it. Please try again later.
        </p>

        <!-- Actions -->
        <div class="flex gap-4 justify-center">
            <a href="{{ route('home') }}"
               class="px-6 py-3 bg-gradient-to-r from-osu-pink to-osu-cyan rounded-lg font-display font-semibold text-white hover:shadow-lg hover:shadow-osu-pink/20 transition-all hover:scale-105">
                {{ __('common.errors.404.go_home') }}
            </a>
            <button onclick="location.reload()"
                    class="px-6 py-3 bg-dark-700 hover:bg-dark-600 rounded-lg font-display font-semibold text-white transition-all">
                {{ __('common.actions.reload') }}
            </button>
        </div>

        <!-- Error ID (if available) -->
        @if(isset($exception) && method_exists($exception, 'getId'))
            <p class="mt-8 text-xs text-gray-600">
                Error ID: {{ $exception->getId() }}
            </p>
        @endif
    </div>
</div>
@endsection
