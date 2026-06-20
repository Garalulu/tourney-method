@props(['mobile' => false])

@php
    $localeNames = [
        'en' => 'English',
        'es' => 'Español',
        'ko' => '한국어',
        'ru' => 'Русский',
        'zh-Hans' => '简体中文',
        'zh-Hant' => '繁體中文（台灣）',
    ];
    $currentLocaleName = $localeNames[app()->getLocale()] ?? app()->getLocale();
@endphp

<div x-data="{ open: false }" class="relative {{ $mobile ? 'w-full' : '' }}">
    <!-- Trigger -->
    <button
        @click="open = !open"
        class="{{ $mobile 
            ? 'nav-mobile-item w-full flex items-center justify-between' 
            : 'nav-icon-btn inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-300 transition-colors hover:bg-dark-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-osu-pink/40' }}"
        aria-label="{{ $currentLocaleName }}"
        title="{{ $currentLocaleName }}"
    >
        <span class="flex items-center gap-2">
            @if(app()->getLocale() === 'en') <img src="https://flagcdn.com/w40/us.png" class="inline-block w-6 h-4 rounded shadow-sm"> {{ $mobile ? 'English' : '' }} @endif
            @if(app()->getLocale() === 'es') <img src="https://flagcdn.com/w40/es.png" class="inline-block w-6 h-4 rounded shadow-sm"> {{ $mobile ? 'Español' : '' }} @endif
            @if(app()->getLocale() === 'ko') <img src="https://flagcdn.com/w40/kr.png" class="inline-block w-6 h-4 rounded shadow-sm"> {{ $mobile ? '한국어' : '' }} @endif
            @if(app()->getLocale() === 'ru') <img src="https://flagcdn.com/w40/ru.png" class="inline-block w-6 h-4 rounded shadow-sm"> {{ $mobile ? 'Русский' : '' }} @endif
            @if(app()->getLocale() === 'zh-Hans') <img src="https://flagcdn.com/w40/cn.png" class="inline-block w-6 h-4 rounded shadow-sm"> {{ $mobile ? '简体中文' : '' }} @endif
            @if(app()->getLocale() === 'zh-Hant') <img src="https://flagcdn.com/w40/tw.png" class="inline-block w-6 h-4 rounded shadow-sm"> {{ $mobile ? '繁體中文（台灣）' : '' }} @endif
        </span>
        @if($mobile)
            <x-icon name="lucide-chevron-down" class="w-4 h-4 transition-transform" x-bind:class="{ 'rotate-180': open }" />
        @endif
    </button>

    <!-- Dropdown / Expandable List -->
    <div x-show="open"
         @click.away="open = false"
         x-cloak
         @class([
            'nav-dropdown' => !$mobile,
            'w-full bg-dark-900 border-t border-dark-700' => $mobile
         ])>
        
        <div class="{{ $mobile ? '' : 'nav-dropdown-content' }}">
            <form method="POST" action="{{ route('language.switch') }}" @submit="$refs.redirectUrl.value = window.location.href; if (window.location.pathname === '/tournaments') localStorage.setItem('tournamentsReturnState', JSON.stringify({ url: window.location.href, scrollY: window.scrollY }))">
                @csrf
                <input x-ref="redirectUrl" type="hidden" name="redirect_url" value="{{ url()->full() }}">
                <div class="{{ $mobile ? 'py-1' : 'py-1' }}">
                    <button type="submit" name="locale" value="en" 
                            class="{{ $mobile ? 'nav-mobile-item pl-8' : 'nav-dropdown-item' }} w-full text-left {{ app()->getLocale() === 'en' ? 'text-osu-pink' : '' }}">
                        <img src="https://flagcdn.com/w40/us.png" class="inline-block w-6 h-4 mr-2 rounded shadow-sm"> English
                    </button>
                    <button type="submit" name="locale" value="es" 
                            class="{{ $mobile ? 'nav-mobile-item pl-8' : 'nav-dropdown-item' }} w-full text-left {{ app()->getLocale() === 'es' ? 'text-osu-pink' : '' }}">
                        <img src="https://flagcdn.com/w40/es.png" class="inline-block w-6 h-4 mr-2 rounded shadow-sm"> Español
                    </button>
                    <button type="submit" name="locale" value="ko" 
                            class="{{ $mobile ? 'nav-mobile-item pl-8' : 'nav-dropdown-item' }} w-full text-left {{ app()->getLocale() === 'ko' ? 'text-osu-pink' : '' }}">
                        <img src="https://flagcdn.com/w40/kr.png" class="inline-block w-6 h-4 mr-2 rounded shadow-sm"> 한국어
                    </button>
                    <button type="submit" name="locale" value="ru" 
                            class="{{ $mobile ? 'nav-mobile-item pl-8' : 'nav-dropdown-item' }} w-full text-left {{ app()->getLocale() === 'ru' ? 'text-osu-pink' : '' }}">
                        <img src="https://flagcdn.com/w40/ru.png" class="inline-block w-6 h-4 mr-2 rounded shadow-sm"> Русский
                    </button>
                    <button type="submit" name="locale" value="zh-Hans" 
                            class="{{ $mobile ? 'nav-mobile-item pl-8' : 'nav-dropdown-item' }} w-full text-left {{ app()->getLocale() === 'zh-Hans' ? 'text-osu-pink' : '' }}">
                        <img src="https://flagcdn.com/w40/cn.png" class="inline-block w-6 h-4 mr-2 rounded shadow-sm"> 简体中文
                    </button>
                    <button type="submit" name="locale" value="zh-Hant" 
                            class="{{ $mobile ? 'nav-mobile-item pl-8' : 'nav-dropdown-item' }} w-full text-left {{ app()->getLocale() === 'zh-Hant' ? 'text-osu-pink' : '' }}">
                        <img src="https://flagcdn.com/w40/tw.png" class="inline-block w-6 h-4 mr-2 rounded shadow-sm"> 繁體中文（台灣）
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
