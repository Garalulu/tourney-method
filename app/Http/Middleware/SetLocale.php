<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set Locale Middleware
 *
 * Detects and applies user's preferred language from session or user settings.
 * Falls back to 'en' if no saved locale exists.
 * Applies to all web routes.
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = ['en', 'ko', 'ru', 'zh-Hans', 'zh-Hant', 'es'];
        $locale = session('locale');

        if (! $locale && auth()->check()) {
            $locale = auth()->user()->locale;
            session(['locale' => $locale]);
        }

        $locale ??= config('app.locale', 'en');

        if (! in_array($locale, $supportedLocales)) {
            $locale = 'en';
            session(['locale' => $locale]);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
