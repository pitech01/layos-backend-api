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

    public function boot(): void
    {
        // Force HTTPS specifically in production environments
        if($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Force Laravel to resolve URLs and assets relative to the APP_URL
        // This stops subfolder deployments from dropping the '/backend' prefix.
        if (config('app.url')) {
            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        }
    }
}
