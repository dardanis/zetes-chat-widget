<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('widget-chat-create', function (Request $request): Limit {
            $key = sprintf('%s|%s', $request->route('widgetKey'), $request->ip());

            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('widget-chat-message', function (Request $request): Limit {
            $key = sprintf('%s|%s', $request->route('widgetKey'), $request->ip());

            return Limit::perMinute(90)->by($key);
        });
    }
}
