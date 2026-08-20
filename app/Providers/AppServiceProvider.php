<?php

namespace App\Providers;

use App\Services\SensitiveDataService;
use App\Services\SensitiveFileService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SensitiveDataService::class);
        $this->app->singleton(SensitiveFileService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(resource_path('lang'), 'topwebcrm');
    }
}
