<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'shops.view',
            'shops.create',
            'shops.update',
            'shops.delete',
            'riders.view',
            'riders.create',
            'riders.update',
            'riders.delete',
            'deliveries.view',
            'deliveries.create',
            'deliveries.update',
            'deliveries.delete',
            'deliveries.assign',
            'deliveries.update_status',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'activity-logs.view',
            'audit-logs.view',
            'menus.view',
            'menus.create',
            'menus.update',
            'menus.delete',
            'registration-requests.view',
            'registration-requests.approve',
            'registration-requests.reject',
            'settings.view',
            'settings.update',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $shop = Role::firstOrCreate(['name' => 'shop', 'guard_name' => 'web']);
        $rider = Role::firstOrCreate(['name' => 'rider', 'guard_name' => 'web']);

        $superAdmin->syncPermissions($permissions);

        $shop->syncPermissions([
            'dashboard.view',
            'shops.view',
            'shops.update',
            'deliveries.view',
            'deliveries.create',
            'deliveries.update',
            'deliveries.delete',
            'deliveries.assign',
            'deliveries.update_status',
        ]);

        $rider->syncPermissions([
            'dashboard.view',
            'riders.view',
            'riders.update',
            'deliveries.view',
            'deliveries.update_status',
        ]);
    }
}
