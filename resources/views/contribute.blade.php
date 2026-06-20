@extends('layouts.app')

@section('title', __('contribute.title'))
@section('description', __('contribute.description'))

@section('content')
<div class="mx-auto max-w-5xl space-y-8">
    <section class="rounded-lg border border-slate-700 bg-slate-900/70 p-6">
        <h1 class="font-display text-3xl font-black text-white">{{ __('contribute.hero.title') }}</h1>
        <p class="mt-3 max-w-3xl text-slate-300">{{ __('contribute.hero.body') }}</p>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-lg border border-slate-700 bg-slate-900/70 p-6">
            <h2 class="font-display text-xl font-bold text-white">{{ __('contribute.translation.title') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ __('contribute.translation.body') }}</p>
            <a href="https://crowdin.com/project/tourney-method" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex rounded-lg bg-pink-500 px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                {{ __('contribute.translation.action') }}
            </a>
        </article>

        <article class="rounded-lg border border-slate-700 bg-slate-900/70 p-6">
            <h2 class="font-display text-xl font-bold text-white">{{ __('contribute.corrections.title') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ __('contribute.corrections.body') }}</p>
            <ol class="mt-4 space-y-2 text-sm text-slate-300">
                @foreach(__('contribute.corrections.steps') as $step)
                    <li class="flex gap-3">
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-pink-500/20 text-xs font-bold text-pink-200">{{ $loop->iteration }}</span>
                        <span>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="mt-5 text-sm leading-6 text-slate-300">{{ __('contribute.corrections.add_body') }}</p>
            <a href="{{ auth()->check() ? route('tournaments.add') : route('login') }}" class="mt-5 inline-flex rounded-lg bg-pink-500 px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                {{ __('contribute.corrections.add_action') }}
            </a>
        </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-lg border border-slate-700 bg-slate-900/70 p-6">
            <h2 class="font-display text-xl font-bold text-white">{{ __('contribute.personal_participation.title') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ __('contribute.personal_participation.body') }}</p>
            <a href="{{ auth()->check() ? route('users.show', auth()->user()) : route('login') }}" class="mt-5 inline-flex rounded-lg border border-cyan-400/50 px-4 py-2 text-sm font-semibold text-cyan-200 hover:bg-cyan-400/10">
                {{ __('contribute.personal_participation.action') }}
            </a>
        </article>

        <article class="rounded-lg border border-slate-700 bg-slate-900/70 p-6">
            <h2 class="font-display text-xl font-bold text-white">{{ __('contribute.donate.title') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ __('contribute.donate.body') }}</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="https://ko-fi.com/garalulu" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg bg-pink-500 px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                    {{ __('contribute.donate.kofi') }}
                </a>
                <a href="https://paypal.me/garalulu" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">
                    {{ __('contribute.donate.paypal') }}
                </a>
            </div>
            <div class="mt-4 rounded-lg border border-slate-800 bg-slate-950/70 p-3">
                <p class="text-xs font-bold uppercase text-slate-500">{{ __('contribute.donate.bank_label') }}</p>
                <p class="mt-1 font-mono text-sm text-slate-200">{{ __('contribute.donate.bank_account') }}</p>
            </div>
        </article>
    </section>
</div>
@endsection
