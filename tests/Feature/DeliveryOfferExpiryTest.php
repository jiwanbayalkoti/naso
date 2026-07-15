<?php

namespace Tests\Feature;

use App\Helpers\ApprovalStatus;
use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Services\AppSettingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeliveryOfferExpiryTest extends TestCase
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
        ]);
    }

    public function test_new_delivery_gets_offer_expiry_from_settings(): void
    {
        app(AppSettingService::class)->updateMany(['delivery_offer_timeout_minutes' => 20]);

        Carbon::setTestNow('2026-07-13 12:00:00');

        $response = $this->actingAs($this->shopUser)->postJson(route('deliveries.store'), [
            'customer_name' => 'Customer',
            'customer_phone' => '9800000000',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Dropoff',
            'delivery_fee' => 100,
        ]);

        $response->assertCreated();

        $delivery = Delivery::query()->first();
        $this->assertNotNull($delivery?->offer_expires_at);
        $this->assertTrue($delivery->offer_expires_at->equalTo(now()->addMinutes(20)));
    }

    public function test_expired_delivery_is_removed_from_rider_offers_and_cancelled(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');

        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
            'offer_expires_at' => now()->addMinutes(15),
        ]);

        Carbon::setTestNow('2026-07-13 12:16:00');

        $response = $this->actingAs($this->riderUser)
            ->getJson(route('deliveries.available-offers'));

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(0, $response->json('data'));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::CANCELLED, $delivery->status);
    }

    public function test_rider_cannot_claim_expired_delivery(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');

        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
            'offer_expires_at' => now()->addMinutes(15),
        ]);

        Carbon::setTestNow('2026-07-13 12:20:00');

        $response = $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.claim', ['delivery' => $delivery->uuid]));

        $response->assertStatus(422);
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::CANCELLED, $delivery->status);
    }

    public function test_super_admin_can_update_offer_timeout_setting(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->putJson('/api/settings', [
            'app_name' => 'NASO',
            'dashboard_refresh_interval' => 30,
            'delivery_offer_timeout_minutes' => 25,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.delivery_offer_timeout_minutes', 25);

        $this->assertSame(25, app(AppSettingService::class)->get('delivery_offer_timeout_minutes'));
    }
}
