<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class LanguageController extends Controller
{
    /**
     * Switch application language.
     *
     * @return RedirectResponse
     */
    public function switch(Request $request)
    {
        $validated = $request->validate([
            'locale' => 'required|string|in:en,ko,ru,zh-Hans,zh-Hant,es',
            'redirect_url' => 'nullable|string',
        ]);

        $locale = $validated['locale'];

        // Update session
        Session::put('locale', $locale);

        // Update user's preferred locale if authenticated
        if (auth()->check()) {
            auth()->user()->update(['locale' => $locale]);
        } else {
            Session::put('guest_locale', $locale);
        }

        return redirect($this->redirectUrl($request, $validated['redirect_url'] ?? null))
            ->with('success', __('common.messages.language_updated'))
            ->with('locale_changed', $locale);
    }

    private function redirectUrl(Request $request, ?string $redirectUrl): string
    {
        if (! $redirectUrl) {
            return url()->previous();
        }

        if (Str::startsWith($redirectUrl, '/') && ! Str::startsWith($redirectUrl, '//')) {
            return url($redirectUrl);
        }

        $host = parse_url($redirectUrl, PHP_URL_HOST);

        if ($host === $request->getHost()) {
            return $redirectUrl;
        }

        return url()->previous();
    }
}
