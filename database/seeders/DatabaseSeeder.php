<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(MenuSeeder::class);
        $this->call(OfferSeeder::class);
        $this->call(DemoUsersSeeder::class);
    }
}
