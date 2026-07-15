<?php

namespace App\Providers;

use App\Notifications\Channels\SmsChannel;
use App\Services\MenuService;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRepositoryBindings();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(ChannelManager::class)->extend('sms', function ($app) {
            return $app->make(SmsChannel::class);
        });

        View::composer('layouts.partials.sidebar-nav', function ($view) {
            if (auth()->check()) {
                $view->with('sidebarMenus', app(MenuService::class)->getSidebarForUser(auth()->user()));
            }
        });

        View::share('googleMapsApiKey', config('services.google_maps.api_key'));
    }

    /**
     * Register repository interface bindings from config.
     */
    protected function registerRepositoryBindings(): void
    {
        foreach (config('repositories.bindings', []) as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
