<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopDeliveryCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_shop_can_create_delivery_without_selecting_shop(): void
    {
        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');
        $shop = Shop::factory()->create([
            'user_id' => $shopUser->id,
            'address' => 'Thamel, Kathmandu',
        ]);

        $response = $this->actingAs($shopUser)->postJson(route('deliveries.store'), [
            'customer_name' => 'Ram Bahadur',
            'customer_phone' => '9800000000',
            'pickup_address' => 'Thamel, Kathmandu',
            'delivery_address' => 'Baneshwor, Kathmandu',
            'priority' => 'normal',
            'delivery_fee' => 150,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('deliveries', [
            'shop_id' => $shop->id,
            'customer_name' => 'Ram Bahadur',
            'customer_phone' => '9800000000',
            'status' => 'pending',
        ]);
    }

    public function test_shop_deliveries_form_shows_own_shop(): void
    {
        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');
        $shop = Shop::factory()->create([
            'user_id' => $shopUser->id,
            'name' => 'Kathmandu Mart',
        ]);

        $response = $this->actingAs($shopUser)->get(route('deliveries.index'));

        $response->assertOk();
        $response->assertSee('Kathmandu Mart', false);
        $response->assertSee('value="'.$shop->id.'"', false);
    }
}
