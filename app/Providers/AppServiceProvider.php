<?php

namespace App\Providers;

use App\Contracts\EmbeddingClient;
use App\Contracts\LLMClient;
use App\Models\Tenant;
use App\Observers\TenantObserver;
use App\Services\OpenAIEmbeddingClient;
use App\Services\OpenAILLMClient;
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
        $this->app->bind(EmbeddingClient::class, OpenAIEmbeddingClient::class);
        $this->app->bind(LLMClient::class, OpenAILLMClient::class);
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
