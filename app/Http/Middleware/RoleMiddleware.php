<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  Allowed roles (e.g., 'admin', 'master')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Check authentication first - return 401 JSON for unauthenticated requests
        if (! auth()->check()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = $request->user();

        // Check role authorization - return 403 JSON for unauthorized users
        if (! in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
