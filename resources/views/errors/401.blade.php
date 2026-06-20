@extends('layouts.app')

@section('title', '401 - Unauthorized')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center">
    <div class="text-center animate-fade-in">
        <!-- Error Code -->
        <div class="mb-8">
            <h1 class="font-display font-black text-9xl bg-gradient-to-r from-osu-pink to-osu-cyan bg-clip-text text-transparent">
                401
            </h1>
        </div>

        <!-- Icon Illustration -->
        <div class="mb-8 flex justify-center">
            <div class="relative">
                <div class="absolute inset-0 bg-osu-pink/20 blur-3xl rounded-full"></div>
                <x-icon name="lucide-lock" class="relative w-32 h-32 text-osu-pink" />
            </div>
        </div>

        <!-- Message -->
        <h2 class="font-display font-bold text-3xl mb-4 text-white">{{ __('common.errors.401.heading') }}</h2>
        <p class="text-gray-400 max-w-md mx-auto mb-8">
            You need to be logged in to access this resource. Please authenticate with your osu! account to continue.
        </p>

        <!-- Actions -->
        <div class="flex gap-4 justify-center">
            <a href="{{ route('login') }}"
               class="px-6 py-3 bg-gradient-to-r from-osu-pink to-osu-cyan rounded-lg font-display font-semibold text-white hover:shadow-lg hover:shadow-osu-pink/20 transition-all hover:scale-105">
                {{ __('common.nav.login_with_osu') }}
            </a>
            <a href="{{ route('home') }}"
               class="px-6 py-3 bg-dark-700 hover:bg-dark-600 rounded-lg font-display font-semibold text-white transition-all">
                {{ __('common.errors.404.go_home') }}
            </a>
        </div>
    </div>
</div>
@endsection
