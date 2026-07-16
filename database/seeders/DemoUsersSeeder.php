<?php

namespace Database\Seeders;

use App\Helpers\ApprovalStatus;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoUsersSeeder extends Seeder
{
    /**
     * Seed default admin, shop, and rider accounts for local/demo use.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $admin = User::firstOrCreate(
            ['email' => 'admin@naso.com'],
            [
                'name' => 'Super Admin',
                'phone' => '9800000000',
                'password' => $password,
                'is_active' => true,
            ]
        );
        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        $shopUser = User::firstOrCreate(
            ['email' => 'shop@naso.com'],
            [
                'name' => 'Demo Shop Owner',
                'phone' => '9800000001',
                'password' => $password,
                'is_active' => true,
            ]
        );
        if (! $shopUser->hasRole('shop')) {
            $shopUser->assignRole('shop');
        }

        Shop::firstOrCreate(
            ['user_id' => $shopUser->id],
            [
                'name' => 'Demo Shop',
                'slug' => 'demo-shop-'.Str::lower(Str::random(4)),
                'email' => 'shop@naso.com',
                'phone' => '9800000001',
                'address' => 'Kathmandu, Nepal',
                'city' => 'Kathmandu',
                'latitude' => 27.7172,
                'longitude' => 85.3240,
                'is_active' => true,
                'approval_status' => ApprovalStatus::APPROVED,
                'approved_at' => now(),
                'description' => 'Demo shop for testing deliveries and wallet.',
                'balance' => 0,
                'bank_name' => 'Nabil Bank',
                'bank_account_name' => 'Demo Shop',
                'bank_account_number' => '0123456789',
            ]
        );

        $riderUser = User::firstOrCreate(
            ['email' => 'rider@naso.com'],
            [
                'name' => 'Demo Rider',
                'phone' => '9800000002',
                'password' => $password,
                'is_active' => true,
            ]
        );
        if (! $riderUser->hasRole('rider')) {
            $riderUser->assignRole('rider');
        }

        Rider::firstOrCreate(
            ['user_id' => $riderUser->id],
            [
                'vehicle_type' => 'motorcycle',
                'vehicle_number' => 'BA-12-PA-3456',
                'license_number' => 'LIC-DEMO-001',
                'is_online' => false,
                'is_available' => true,
                'current_latitude' => 27.7172,
                'current_longitude' => 85.3240,
                'rating' => 5,
                'total_deliveries' => 0,
                'approval_status' => ApprovalStatus::APPROVED,
                'approved_at' => now(),
                'balance' => 0,
                'bank_name' => 'Nabil Bank',
                'bank_account_name' => 'Demo Rider',
                'bank_account_number' => '9876543210',
            ]
        );
    }
}
