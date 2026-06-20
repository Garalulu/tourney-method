<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Secure Headers Middleware
 *
 * Adds security-related HTTP headers to all responses.
 * Follows OWASP secure headers recommendations.
 *
 * Headers added:
 * - X-Frame-Options: DENY (prevent clickjacking)
 * - X-Content-Type-Options: nosniff
 * - Referrer-Policy: no-referrer (prevent referrer leakage)
 * - Content-Security-Policy: script-src 'self' (adjust for CDNs)
 * - Strict-Transport-Security: max-age=31536000 (HTTPS only)
 */
class SecureHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'no-referrer');

        // Enable HSTS for HTTPS only
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Content Security Policy (basic, can be customized per route)
        $isLocal = app()->environment('local');
        $csp = "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com "
            .($isLocal ? ' http://localhost:5173' : '').'; '
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net "
            .($isLocal ? ' http://localhost:5173' : '').'; '
            ."font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net; "
            ."img-src 'self' data: https:; "
            ."connect-src 'self' https://osu.ppy.sh https://i.imgur.com "
            .($isLocal ? ' ws://localhost:5173 http://localhost:5173' : '').'; '
            ."frame-ancestors 'none';";

        $response->headers->set('Content-Security-Policy', $csp);

        // Permissions Policy (formerly Feature-Policy)
        $permissionsPolicy = 'camera=(), microphone=(), geolocation=()';
        $response->headers->set('Permissions-Policy', $permissionsPolicy);

        return $response;
    }
}
