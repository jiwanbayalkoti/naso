<?php

namespace Tests\Feature;

use App\Helpers\ApprovalStatus;
use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderDeliveryOfferTest extends TestCase
{
    use RefreshDatabase;

    protected User $shopUser;

    protected Shop $shop;

    protected User $riderUser;

    protected Rider $rider;

    protected User $otherRiderUser;

    protected Rider $otherRider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->shopUser = User::factory()->create(['is_active' => true]);
        $this->shopUser->assignRole('shop');
        $this->shop = Shop::factory()->create(['user_id' => $this->shopUser->id]);

        $this->riderUser = User::factory()->create(['is_active' => true]);
        $this->riderUser->assignRole('rider');
        $this->rider = Rider::factory()->online()->create([
            'user_id' => $this->riderUser->id,
            'approval_status' => ApprovalStatus::APPROVED,
        ]);

        $this->otherRiderUser = User::factory()->create(['is_active' => true]);
        $this->otherRiderUser->assignRole('rider');
        $this->otherRider = Rider::factory()->online()->create([
            'user_id' => $this->otherRiderUser->id,
            'approval_status' => ApprovalStatus::APPROVED,
        ]);
    }

    public function test_online_rider_sees_available_delivery_offer(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
            'delivery_fee' => 150,
        ]);

        $response = $this->actingAs($this->riderUser)
            ->getJson(route('deliveries.available-offers'));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($delivery->uuid, $response->json('data.0.uuid'));
        $this->assertSame($this->shop->name, $response->json('data.0.shop_name'));
        $this->assertEquals(150, $response->json('data.0.delivery_fee'));
    }

    public function test_offline_rider_does_not_see_offers(): void
    {
        $this->rider->update(['is_online' => false]);

        Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        $this->actingAs($this->riderUser)
            ->getJson(route('deliveries.available-offers'))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_rider_can_claim_pending_delivery(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.claim', $delivery->uuid))
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::ACCEPTED)
            ->assertJsonPath('data.rider_id', $this->rider->id);

        $this->assertFalse($this->rider->fresh()->is_available);
    }

    public function test_offer_disappears_for_other_riders_after_claim(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.claim', $delivery->uuid))
            ->assertOk();

        $this->actingAs($this->otherRiderUser)
            ->getJson(route('deliveries.available-offers'))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_second_rider_cannot_claim_already_taken_delivery(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.claim', $delivery->uuid))
            ->assertOk();

        $this->actingAs($this->otherRiderUser)
            ->postJson(route('deliveries.claim', $delivery->uuid))
            ->assertForbidden();
    }

    public function test_available_offers_are_not_filtered_by_decline(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        $this->actingAs($this->riderUser)
            ->getJson(route('deliveries.available-offers'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $delivery->uuid);
    }

    public function test_rider_layout_includes_offer_popup(): void
    {
        $this->actingAs($this->riderUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('rider-delivery-offers', false)
            ->assertSee('rider-offer-stack-list', false);
    }

    public function test_rider_sees_all_available_offers(): void
    {
        Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        $response = $this->actingAs($this->riderUser)
            ->getJson(route('deliveries.available-offers'));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
