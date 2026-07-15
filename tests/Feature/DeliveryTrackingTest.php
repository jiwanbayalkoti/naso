<?php

namespace Tests\Feature;

use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_user_can_fetch_delivery_tracking_payload(): void
    {
        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');
        $shop = Shop::factory()->create([
            'user_id' => $shopUser->id,
            'latitude' => 27.7172,
            'longitude' => 85.3240,
        ]);

        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        $rider = Rider::factory()->create([
            'user_id' => $riderUser->id,
            'current_latitude' => 27.7180,
            'current_longitude' => 85.3250,
            'is_online' => true,
        ]);

        $delivery = Delivery::factory()->create([
            'shop_id' => $shop->id,
            'rider_id' => $rider->id,
            'status' => DeliveryStatus::ON_THE_WAY,
            'latitude' => 27.7200,
            'longitude' => 85.3300,
        ]);

        Sanctum::actingAs($shopUser);

        $this->getJson('/api/deliveries/'.$delivery->uuid.'/tracking')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tracking_number', $delivery->tracking_number)
            ->assertJsonPath('data.pickup.latitude', 27.7172)
            ->assertJsonPath('data.rider.latitude', 27.718)
            ->assertJsonPath('data.is_live', true);
    }

    public function test_rider_can_update_location_via_api(): void
    {
        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        $rider = Rider::factory()->create(['user_id' => $riderUser->id]);

        Sanctum::actingAs($riderUser);

        $this->postJson('/api/riders/'.$rider->uuid.'/location', [
            'latitude' => 27.7100,
            'longitude' => 85.3200,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_latitude', '27.71000000');

        $this->assertNotNull($rider->fresh()->location_updated_at);
    }
}
