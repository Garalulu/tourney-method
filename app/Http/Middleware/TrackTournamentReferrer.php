<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;

class TrackTournamentReferrer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track when visiting tournament detail page
        $route = $request->route();

        // PHPStan: Route can be null in some cases
        if ($route === null) {
            return $response;
        }

        /** @var Route $route */
        $routeName = $route->getName();

        if ($routeName === 'tournaments.show') {
            $referrer = $request->headers->get('referer');

            // Only store if referrer is from our own app
            if ($referrer && str_starts_with($referrer, config('app.url'))) {
                $parsedReferrer = parse_url($referrer);
                $path = $parsedReferrer['path'] ?? '';

                // Only store if coming from tournaments index (with or without query params)
                if ($path === '/tournaments') {
                    session(['tournaments_referrer_url' => $referrer]);
                }
            }
        }

        return $response;
    }
}
