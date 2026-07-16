<?php

namespace Tests\Feature;

use App\Helpers\DeliveryStatus;
use App\Helpers\OfferType;
use App\Models\Delivery;
use App\Models\Offer;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Helpers\WalletTransactionType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $shopUser;

    protected Shop $shop;

    protected User $riderUser;

    protected Rider $rider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super_admin');

        $this->shopUser = User::factory()->create(['is_active' => true]);
        $this->shopUser->assignRole('shop');
        $this->shop = Shop::factory()->create(['user_id' => $this->shopUser->id, 'balance' => 0]);

        $this->riderUser = User::factory()->create(['is_active' => true]);
        $this->riderUser->assignRole('rider');
        $this->rider = Rider::factory()->online()->create([
            'user_id' => $this->riderUser->id,
            'total_deliveries' => 0,
            'balance' => 0,
        ]);
    }

    public function test_shop_nth_free_zeroes_fee_but_rider_paid_from_base(): void
    {
        Offer::create([
            'name' => 'Every 5th free',
            'audience' => 'shop',
            'type' => OfferType::SHOP_NTH_FREE,
            'is_active' => true,
            'priority' => 1,
            'window' => 'lifetime',
            'config' => ['every_n' => 5],
        ]);

        for ($i = 0; $i < 4; $i++) {
            Delivery::factory()->create([
                'shop_id' => $this->shop->id,
                'rider_id' => $this->rider->id,
                'status' => DeliveryStatus::COMPLETED,
                'delivery_fee' => 100,
                'base_delivery_fee' => 100,
            ]);
        }

        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::DELIVERED,
            'delivery_fee' => 0,
            'base_delivery_fee' => 100,
            'cod_amount' => 0,
            'applied_offer_ids' => [],
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.status', $delivery->uuid), [
                'status' => DeliveryStatus::COMPLETED,
            ])
            ->assertOk();

        $delivery->refresh();
        $this->assertSame(0.0, (float) $delivery->delivery_fee);
        $this->assertEquals(80.0, (float) $delivery->rider_earning); // 20% default commission of 100
        $this->assertEquals(20.0, (float) $delivery->platform_commission);

        $this->assertFalse(
            WalletTransaction::query()
                ->where('shop_id', $this->shop->id)
                ->where('type', WalletTransactionType::FEE_DEBIT)
                ->where('delivery_id', $delivery->id)
                ->exists()
        );
    }

    public function test_rider_commission_reduce_after_min_rides(): void
    {
        Offer::create([
            'name' => 'Lower commission',
            'audience' => 'rider',
            'type' => OfferType::RIDER_COMMISSION_REDUCE,
            'is_active' => true,
            'priority' => 1,
            'window' => 'lifetime',
            'config' => [
                'min_completed' => 5,
                'commission_percent' => 10,
            ],
        ]);

        for ($i = 0; $i < 5; $i++) {
            Delivery::factory()->create([
                'shop_id' => $this->shop->id,
                'rider_id' => $this->rider->id,
                'status' => DeliveryStatus::COMPLETED,
                'delivery_fee' => 100,
                'base_delivery_fee' => 100,
            ]);
        }

        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::DELIVERED,
            'delivery_fee' => 100,
            'base_delivery_fee' => 100,
            'cod_amount' => 0,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.status', $delivery->uuid), [
                'status' => DeliveryStatus::COMPLETED,
            ])
            ->assertOk();

        $delivery->refresh();
        $this->assertEquals(10.0, (float) $delivery->platform_commission);
        $this->assertEquals(90.0, (float) $delivery->rider_earning);
    }

    public function test_inactive_offer_ignored(): void
    {
        Offer::create([
            'name' => 'Inactive free',
            'audience' => 'shop',
            'type' => OfferType::SHOP_NTH_FREE,
            'is_active' => false,
            'priority' => 1,
            'window' => 'lifetime',
            'config' => ['every_n' => 1],
        ]);

        $engine = app(\App\Services\OfferEngine::class);
        $resolved = $engine->resolveShopFee($this->shop, 120);

        $this->assertEquals(120.0, $resolved['delivery_fee']);
        $this->assertSame([], $resolved['applied_offer_ids']);
    }
}
