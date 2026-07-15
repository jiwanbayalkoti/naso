<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(MenuSeeder::class);
    }

    public function test_rider_can_load_dashboard_without_permission_recursion(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('rider');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_rider_sidebar_only_includes_permitted_menus(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('rider');

        $menus = app(\App\Services\MenuService::class)->getSidebarForUser($user);

        $this->assertGreaterThan(0, $menus->count());
        $this->assertTrue($menus->pluck('route_name')->contains('dashboard'));
        $this->assertFalse($menus->pluck('route_name')->contains('registration-requests.index'));
    }
}
