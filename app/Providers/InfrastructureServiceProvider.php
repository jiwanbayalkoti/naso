<?php

namespace App\Providers;

use App\Services\InfrastructureService;
use Illuminate\Support\ServiceProvider;

class InfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InfrastructureService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        if (config('cache.default') !== 'redis') {
            return;
        }

        try {
            $this->app->make(InfrastructureService::class)->isRedisAvailable();
        } catch (\Throwable) {
            config([
                'cache.default' => 'file',
                'queue.default' => env('QUEUE_FALLBACK', 'database'),
            ]);
        }
    }
}
