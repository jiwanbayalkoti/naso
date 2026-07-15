<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MenuSeeder::class);
    }

    public function test_super_admin_can_view_menu_management_page(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get(route('menus.index'));

        $response->assertOk()
            ->assertSee('Menus')
            ->assertSee('menus-module');
    }

    public function test_super_admin_can_create_menu(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->postJson(route('menus.store'), [
            'title' => 'Reports',
            'icon' => 'fa-solid fa-chart-line',
            'route_name' => 'dashboard',
            'route_pattern' => 'dashboard',
            'permission' => 'dashboard.view',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Reports');

        $this->assertDatabaseHas('menus', ['title' => 'Reports']);
    }

    public function test_shop_user_cannot_access_menu_management(): void
    {
        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');

        $response = $this->actingAs($shopUser)->get(route('menus.index'));

        $response->assertForbidden();
    }

    protected function createSuperAdmin(): User
    {
        $admin = User::factory()->create([
            'email' => 'admin@naso.com',
            'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
