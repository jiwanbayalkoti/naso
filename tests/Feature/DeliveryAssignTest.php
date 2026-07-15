<?php

namespace Tests\Feature;

use App\Helpers\ApprovalStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_shop_deliveries_page_includes_assignable_riders(): void
    {
        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');
        Shop::factory()->create(['user_id' => $shopUser->id]);

        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        Rider::factory()->online()->create([
            'user_id' => $riderUser->id,
            'approval_status' => ApprovalStatus::APPROVED,
        ]);

        $response = $this->actingAs($shopUser)->get(route('deliveries.index'));

        $response->assertOk();
        $response->assertSee($riderUser->name, false);
    }

    public function test_shop_can_fetch_assignable_riders(): void
    {
        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');
        Shop::factory()->create(['user_id' => $shopUser->id]);

        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        Rider::factory()->online()->create([
            'user_id' => $riderUser->id,
            'approval_status' => ApprovalStatus::APPROVED,
        ]);

        $response = $this->actingAs($shopUser)->getJson(route('riders.assignable'));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($riderUser->name, $response->json('data.0.name'));
    }

    public function test_shop_can_assign_rider_to_own_delivery(): void
    {
        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');
        $shop = Shop::factory()->create(['user_id' => $shopUser->id]);

        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        $rider = Rider::factory()->online()->create([
            'user_id' => $riderUser->id,
            'approval_status' => ApprovalStatus::APPROVED,
        ]);

        $delivery = Delivery::factory()->create([
            'shop_id' => $shop->id,
            'rider_id' => null,
        ]);

        $response = $this->actingAs($shopUser)->postJson(
            route('deliveries.assign', $delivery->uuid),
            ['rider_id' => $rider->id]
        );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($rider->id, $delivery->fresh()->rider_id);
    }
}
