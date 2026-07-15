<?php

namespace Tests\Feature;

use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Shop $shop;

    protected Rider $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');
        $this->shop = Shop::factory()->create(['user_id' => $shopUser->id]);

        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        $this->rider = Rider::factory()->online()->create([
            'user_id' => $riderUser->id,
            'total_deliveries' => 0,
        ]);
    }

    public function test_full_delivery_workflow(): void
    {
        $this->actingAs($this->admin);

        $createResponse = $this->postJson(route('deliveries.store'), [
            'shop_id' => $this->shop->id,
            'customer_name' => 'John Doe',
            'customer_phone' => '9801111111',
            'pickup_address' => 'Shop Address',
            'delivery_address' => 'Customer Address',
            'priority' => 'normal',
            'delivery_fee' => 100,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.status', DeliveryStatus::PENDING);

        $uuid = $createResponse->json('data.uuid');
        $trackingNumber = $createResponse->json('data.tracking_number');

        $this->assertStringStartsWith('NASO-', $trackingNumber);

        $this->postJson(route('deliveries.assign', $uuid), [
            'rider_id' => $this->rider->id,
        ])->assertOk()->assertJsonPath('data.status', DeliveryStatus::ASSIGNED);

        $statuses = [
            DeliveryStatus::ACCEPTED,
            DeliveryStatus::PICKED_UP,
            DeliveryStatus::ON_THE_WAY,
            DeliveryStatus::DELIVERED,
            DeliveryStatus::COMPLETED,
        ];

        foreach ($statuses as $status) {
            $this->postJson(route('deliveries.status', $uuid), [
                'status' => $status,
            ])->assertOk()->assertJsonPath('data.status', $status);
        }

        $delivery = Delivery::where('uuid', $uuid)->first();
        $this->assertNotNull($delivery->completed_at);
        $this->assertEquals(1, $this->rider->fresh()->total_deliveries);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->actingAs($this->admin);

        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
        ]);

        $response = $this->postJson(route('deliveries.status', $delivery->uuid), [
            'status' => DeliveryStatus::DELIVERED,
        ]);

        $response->assertStatus(422);
    }

    public function test_delivery_can_be_cancelled_from_pending(): void
    {
        $this->actingAs($this->admin);

        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
        ]);

        $this->postJson(route('deliveries.status', $delivery->uuid), [
            'status' => DeliveryStatus::CANCELLED,
        ])->assertOk()->assertJsonPath('data.status', DeliveryStatus::CANCELLED);
    }

    public function test_public_tracking_endpoint_returns_delivery(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
        ]);

        $this->getJson(route('deliveries.track', $delivery->tracking_number))
            ->assertOk()
            ->assertJsonPath('data.tracking_number', $delivery->tracking_number);
    }

    public function test_delivery_show_page_returns_html_for_browser(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
        ]);

        $this->actingAs($this->admin)
            ->get(route('deliveries.show', $delivery->uuid))
            ->assertOk()
            ->assertViewIs('deliveries.show')
            ->assertSee($delivery->tracking_number, false);
    }

    public function test_delivery_show_returns_json_for_api_request(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('deliveries.show', $delivery->uuid))
            ->assertOk()
            ->assertJsonPath('data.tracking_number', $delivery->tracking_number);
    }
}
