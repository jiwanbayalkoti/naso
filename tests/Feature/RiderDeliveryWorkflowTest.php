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

class RiderDeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $shopUser;

    protected Shop $shop;

    protected User $riderUser;

    protected Rider $rider;

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
            'total_deliveries' => 0,
        ]);
    }

    public function test_shop_assign_marks_rider_unavailable(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
        ]);

        $this->actingAs($this->shopUser)
            ->postJson(route('deliveries.assign', $delivery->uuid), [
                'rider_id' => $this->rider->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::ASSIGNED);

        $this->assertFalse($this->rider->fresh()->is_available);
    }

    public function test_rider_sees_assigned_delivery_in_datatable(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::ASSIGNED,
        ]);

        $response = $this->actingAs($this->riderUser)
            ->getJson(route('deliveries.datatable'));

        $response->assertOk();
        $this->assertStringContainsString($delivery->tracking_number, $response->getContent());
        $this->assertStringContainsString('btn-rider-accept', $response->getContent());
    }

    public function test_rider_can_accept_assignment(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::ASSIGNED,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.status', $delivery->uuid), [
                'status' => DeliveryStatus::ACCEPTED,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::ACCEPTED);
    }

    public function test_rider_can_reject_assignment(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::ASSIGNED,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.reject-assignment', $delivery->uuid))
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::PENDING);

        $delivery->refresh();
        $this->assertNull($delivery->rider_id);
        $this->assertTrue($this->rider->fresh()->is_available);
    }

    public function test_rider_can_complete_full_delivery_flow(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::ASSIGNED,
        ]);

        $statuses = [
            DeliveryStatus::ACCEPTED,
            DeliveryStatus::PICKED_UP,
            DeliveryStatus::ON_THE_WAY,
            DeliveryStatus::DELIVERED,
            DeliveryStatus::COMPLETED,
        ];

        foreach ($statuses as $status) {
            $this->actingAs($this->riderUser)
                ->postJson(route('deliveries.status', $delivery->uuid), [
                    'status' => $status,
                ])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->assertTrue($this->rider->fresh()->is_available);
        $this->assertEquals(1, $this->rider->fresh()->total_deliveries);
    }

    public function test_rider_cannot_reject_other_riders_delivery(): void
    {
        $otherRiderUser = User::factory()->create(['is_active' => true]);
        $otherRiderUser->assignRole('rider');
        $otherRider = Rider::factory()->online()->create(['user_id' => $otherRiderUser->id]);

        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $otherRider->id,
            'status' => DeliveryStatus::ASSIGNED,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.reject-assignment', $delivery->uuid))
            ->assertForbidden();
    }

    public function test_rider_deliveries_page_shows_assignment_banner(): void
    {
        Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::ASSIGNED,
        ]);

        $response = $this->actingAs($this->riderUser)->get(route('deliveries.index'));

        $response->assertOk()
            ->assertSee('My Deliveries', false)
            ->assertSee('waiting for your response', false)
            ->assertDontSee('id="btn-create-delivery"', false);
    }
}
