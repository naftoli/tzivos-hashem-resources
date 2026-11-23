<?php

namespace App\Providers;

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
        // On the server: links always https, and trust the proxy (e.g. Cloudflare) in front
        if (config('app.force_https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
        if ($proxies = config('app.trusted_proxies')) {
            \Illuminate\Http\Middleware\TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // The site being worked on, for every admin screen ($siteNow)
        \Illuminate\Support\Facades\View::composer(['layouts.admin', 'dashboard', 'admin.*', 'account'], function ($view) {
            if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('sites')) {
                $view->with('siteNow', \App\Support\CurrentSite::get());
            }
        });
    }
}
