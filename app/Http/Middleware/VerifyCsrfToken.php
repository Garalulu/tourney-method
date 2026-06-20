<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Determine if the request has a valid CSRF token.
     *
     * Always return true during tests to avoid CSRF token issues.
     */
    protected function tokensMatch($request): bool
    {
        if ($this->app->environment('testing')) {
            return true;
        }

        return parent::tokensMatch($request);
    }
}
