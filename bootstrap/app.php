<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\EnsureUserSetupComplete;
use App\Http\Middleware\MarkJsonGetRequestsAsAjax;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackTournamentReferrer;
use App\Http\Resources\ErrorResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            MarkJsonGetRequestsAsAjax::class,
        ]);

        $middleware->alias([
            'user.setup' => EnsureUserSetupComplete::class,
            'role' => RoleMiddleware::class,
            'api.auth' => ApiAuthenticate::class,
            'locale' => SetLocale::class,
        ]);

        // Trust Railway's reverse proxy headers
        // This allows Laravel to correctly detect HTTPS behind Railway's proxy
        $middleware->trustProxies(at: '*');

        // Apply locale middleware to all web routes
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Apply secure headers to all routes
        $middleware->web(append: [
            SecureHeaders::class,
        ]);

        // Track tournament referrer for back button state preservation
        $middleware->web(append: [
            TrackTournamentReferrer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API error handling - return JSON responses
        $exceptions->render(function (Throwable $e, $request) {
            // Return JSON for API requests (Accept: application/json)
            if ($request->expectsJson()) {
                // Determine status code from exception
                $statusCode = 500;
                if (method_exists($e, 'getStatusCode')) {
                    $statusCode = $e->getStatusCode();
                } elseif ($e instanceof ValidationException) {
                    $statusCode = 422;
                } elseif ($e instanceof AuthenticationException) {
                    $statusCode = 401;
                } elseif ($e instanceof AuthorizationException) {
                    $statusCode = 403;
                } elseif ($e instanceof NotFoundHttpException) {
                    $statusCode = 404;
                }

                return ErrorResource::fromException($e, $statusCode);
            }

            // Return default error pages for web requests
            return null;
        });
    })->create();
