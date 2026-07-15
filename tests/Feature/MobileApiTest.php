<?php

namespace Tests\Feature;

use App\Helpers\ApprovalStatus;
use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\DeliveryCompletedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
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

        $this->shopUser = User::factory()->create([
            'email' => 'shop-mobile@naso.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->shopUser->assignRole('shop');
        $this->shop = Shop::factory()->create(['user_id' => $this->shopUser->id]);

        $this->riderUser = User::factory()->create([
            'email' => 'rider-mobile@naso.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->riderUser->assignRole('rider');
        $this->rider = Rider::factory()->online()->create([
            'user_id' => $this->riderUser->id,
            'approval_status' => ApprovalStatus::APPROVED,
        ]);
    }

    public function test_mobile_login_returns_sanctum_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'rider-mobile@naso.com',
            'password' => 'password',
            'device_name' => 'android-pixel',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_mobile_profile_includes_role_context(): void
    {
        Sanctum::actingAs($this->riderUser);

        $response = $this->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'rider-mobile@naso.com')
            ->assertJsonPath('data.rider.uuid', $this->rider->uuid)
            ->assertJsonPath('data.shop', null);
    }

    public function test_rider_can_fetch_available_offers_via_api(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        Sanctum::actingAs($this->riderUser);

        $this->getJson('/api/deliveries/available-offers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $delivery->uuid);
    }

    public function test_rider_can_claim_delivery_via_api(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => DeliveryStatus::PENDING,
            'rider_id' => null,
        ]);

        Sanctum::actingAs($this->riderUser);

        $this->postJson('/api/deliveries/'.$delivery->uuid.'/claim')
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::ACCEPTED);

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::ACCEPTED,
        ]);
    }

    public function test_rider_can_list_deliveries_via_api(): void
    {
        Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::ACCEPTED,
        ]);

        Sanctum::actingAs($this->riderUser);

        $this->getJson('/api/deliveries?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_shop_can_create_delivery_via_api(): void
    {
        Sanctum::actingAs($this->shopUser);

        $response = $this->postJson('/api/deliveries', [
            'customer_name' => 'Mobile Customer',
            'customer_phone' => '9800000000',
            'pickup_address' => 'Shop pickup',
            'delivery_address' => 'Customer home',
            'delivery_fee' => 120,
            'priority' => 'normal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer_name', 'Mobile Customer')
            ->assertJsonPath('data.status', DeliveryStatus::PENDING);
    }

    public function test_shop_can_fetch_notifications_via_api(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::COMPLETED,
        ]);

        $this->shopUser->notify(new DeliveryCompletedNotification($delivery));

        Sanctum::actingAs($this->shopUser);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_shop_can_fetch_assignable_riders_via_api(): void
    {
        Sanctum::actingAs($this->shopUser);

        $this->getJson('/api/riders/assignable')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $this->rider->id,
                'name' => $this->riderUser->name,
            ]);
    }

    public function test_rider_can_toggle_online_status_via_api(): void
    {
        Sanctum::actingAs($this->riderUser);

        $this->postJson('/api/riders/'.$this->rider->uuid.'/toggle-online')
            ->assertOk()
            ->assertJsonPath('data.is_online', false);
    }

    public function test_mobile_logout_revokes_token(): void
    {
        $accessToken = $this->riderUser->createToken('mobile-test');

        $this->withToken($accessToken->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $accessToken->accessToken->id,
        ]);
    }

    public function test_mobile_sidebar_returns_role_menus(): void
    {
        $this->seed(\Database\Seeders\MenuSeeder::class);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);

        $this->getJson('/api/menus/sidebar')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['route_name' => 'dashboard'])
            ->assertJsonFragment(['route_name' => 'shops.index']);
    }
}
