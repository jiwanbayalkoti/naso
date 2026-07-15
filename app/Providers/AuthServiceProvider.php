<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Shop::class => \App\Policies\ShopPolicy::class,
        \App\Models\Rider::class => \App\Policies\RiderPolicy::class,
        \App\Models\Delivery::class => \App\Policies\DeliveryPolicy::class,
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\ActivityLog::class => \App\Policies\ActivityLogPolicy::class,
        \App\Models\AuditLog::class => \App\Policies\AuditLogPolicy::class,
        \App\Models\Menu::class => \App\Policies\MenuPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });
    }
}
