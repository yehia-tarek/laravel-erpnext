<?php

namespace YehiaTarek\ERPNext;

use Illuminate\Support\ServiceProvider;

class ERPNextServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/erpnext.php',
            'erpnext'
        );

        $this->app->singleton(ERPNextManager::class, function ($app) {
            return new ERPNextManager($app['config']['erpnext']);
        });

        // Alias so both class name and string key resolve to the same singleton
        $this->app->alias(ERPNextManager::class, 'erpnext');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/erpnext.php' => config_path('erpnext.php'),
            ], 'erpnext-config');
        }
    }
}
