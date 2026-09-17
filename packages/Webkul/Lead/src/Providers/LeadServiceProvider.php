<?php

namespace Webkul\Lead\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Webkul\Lead\Contracts\AvailabilityResolver;
use Webkul\Lead\Services\ConfiguredAvailabilityResolver;

class LeadServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AvailabilityResolver::class, ConfiguredAvailabilityResolver::class);
    }
}
