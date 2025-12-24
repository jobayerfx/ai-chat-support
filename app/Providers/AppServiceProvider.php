<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Observers\TenantObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register the TenantObserver to auto-create tenant settings
        Tenant::observe(TenantObserver::class);
    }
}
