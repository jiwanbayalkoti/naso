<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(MenuSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@naso.com'],
            [
                'name' => 'Super Admin',
                'phone' => '9800000000',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $admin->assignRole('super_admin');
    }
}
