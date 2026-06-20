<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     * Returns 401 JSON instead of redirecting for unauthenticated users.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}
