<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menus = [
            [
                'title' => 'Dashboard',
                'icon' => 'fa-solid fa-gauge-high',
                'route_name' => 'dashboard',
                'route_pattern' => 'dashboard',
                'permission' => 'dashboard.view',
                'sort_order' => 1,
            ],
            [
                'title' => 'Deliveries',
                'icon' => 'fa-solid fa-box',
                'route_name' => 'deliveries.index',
                'route_pattern' => 'deliveries.*',
                'permission' => 'deliveries.view',
                'sort_order' => 2,
            ],
            [
                'title' => 'Shops',
                'icon' => 'fa-solid fa-store',
                'route_name' => 'shops.index',
                'route_pattern' => 'shops.*',
                'permission' => 'shops.view',
                'sort_order' => 3,
            ],
            [
                'title' => 'Riders',
                'icon' => 'fa-solid fa-motorcycle',
                'route_name' => 'riders.index',
                'route_pattern' => 'riders.*',
                'permission' => 'riders.view',
                'sort_order' => 4,
            ],
            [
                'title' => 'Live Riders',
                'icon' => 'fa-solid fa-location-dot',
                'route_name' => 'riders.live-map',
                'route_pattern' => 'riders.live-*',
                'permission' => 'riders.view',
                'sort_order' => 4,
            ],
            [
                'title' => 'Users',
                'icon' => 'fa-solid fa-users',
                'route_name' => 'users.index',
                'route_pattern' => 'users.*',
                'permission' => 'users.view',
                'sort_order' => 5,
            ],
            [
                'title' => 'Registration Requests',
                'icon' => 'fa-solid fa-user-clock',
                'route_name' => 'registration-requests.index',
                'route_pattern' => 'registration-requests.*',
                'permission' => 'registration-requests.view',
                'sort_order' => 6,
            ],
            [
                'title' => 'Menus',
                'icon' => 'fa-solid fa-bars',
                'route_name' => 'menus.index',
                'route_pattern' => 'menus.*',
                'permission' => 'menus.view',
                'sort_order' => 7,
            ],
            [
                'title' => 'Activity Logs',
                'icon' => 'fa-solid fa-list-check',
                'route_name' => 'activity-logs.index',
                'route_pattern' => 'activity-logs.*',
                'permission' => 'activity-logs.view',
                'sort_order' => 8,
            ],
            [
                'title' => 'Audit Logs',
                'icon' => 'fa-solid fa-shield-halved',
                'route_name' => 'audit-logs.index',
                'route_pattern' => 'audit-logs.*',
                'permission' => 'audit-logs.view',
                'sort_order' => 9,
            ],
            [
                'title' => 'Profile',
                'icon' => 'fa-solid fa-user',
                'route_name' => 'profile.index',
                'route_pattern' => 'profile.*',
                'permission' => null,
                'sort_order' => 90,
            ],
            [
                'title' => 'Settings',
                'icon' => 'fa-solid fa-gear',
                'route_name' => 'settings.index',
                'route_pattern' => 'settings.*',
                'permission' => 'settings.view',
                'sort_order' => 91,
            ],
        ];

        foreach ($menus as $menu) {
            Menu::updateOrCreate(
                ['route_name' => $menu['route_name']],
                array_merge($menu, ['is_active' => true])
            );
        }
    }
}
