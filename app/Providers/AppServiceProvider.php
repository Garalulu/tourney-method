<?php

namespace App\Providers;

use App\Services\BwsCalculator;
use App\Services\OsuSocialiteProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(BwsCalculator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom osu! OAuth provider
        $this->bootOsuSocialiteProvider();

        // Register rate limiters
        $this->bootRateLimiters();
    }

    /**
     * Register application rate limiters
     */
    private function bootRateLimiters(): void
    {
        RateLimiter::for('user_sync', function (Request $request) {
            return Limit::perDay(1)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('participation_writes', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('participation_search', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('participation_lobby_search', function (Request $request) {
            return Limit::perSecond(2)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('tournament_corrections', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Register osu! OAuth provider with Socialite
     */
    private function bootOsuSocialiteProvider(): void
    {
        /** @var SocialiteManager $socialite */
        $socialite = $this->app->make(Factory::class);

        $socialite->extend('osu', function ($app) use ($socialite) {
            /** @var array{client_id: string, client_secret: string, redirect: string} $config */
            $config = config('services.osu');

            return $socialite->buildProvider(
                OsuSocialiteProvider::class,
                $config
            );
        });
    }
}
