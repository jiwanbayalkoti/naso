<?php

use App\Repositories\ActivityLogRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use App\Repositories\Contracts\RiderRepositoryInterface;
use App\Repositories\Contracts\ShopRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\MenuRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\RiderRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;

return [

    /*
    |--------------------------------------------------------------------------
    | Repository Bindings
    |--------------------------------------------------------------------------
    |
    | Map repository interfaces to their concrete implementations.
    | New module repositories should be registered here.
    |
    */

    'bindings' => [
        MenuRepositoryInterface::class => MenuRepository::class,
        ShopRepositoryInterface::class => ShopRepository::class,
        RiderRepositoryInterface::class => RiderRepository::class,
        DeliveryRepositoryInterface::class => DeliveryRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        ActivityLogRepositoryInterface::class => ActivityLogRepository::class,
        AuditLogRepositoryInterface::class => AuditLogRepository::class,
        DashboardRepositoryInterface::class => DashboardRepository::class,
    ],

];
