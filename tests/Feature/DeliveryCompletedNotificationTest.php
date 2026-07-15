<?php

namespace Tests\Feature;

use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\DeliveryCompletedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeliveryCompletedNotificationTest extends TestCase
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
            'total_deliveries' => 0,
        ]);
    }

    public function test_shop_receives_notification_when_delivery_is_completed(): void
    {
        Notification::fake();

        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::DELIVERED,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.status', $delivery->uuid), [
                'status' => DeliveryStatus::COMPLETED,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::COMPLETED);

        Notification::assertSentTo(
            $this->shopUser,
            DeliveryCompletedNotification::class,
            function (DeliveryCompletedNotification $notification) use ($delivery) {
                $data = $notification->toDatabase($this->shopUser);

                return $data['type'] === 'delivery_completed'
                    && $data['delivery_uuid'] === $delivery->uuid
                    && $data['tracking_number'] === $delivery->tracking_number;
            }
        );
    }

    public function test_shop_can_fetch_unread_notification_count(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::DELIVERED,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.status', $delivery->uuid), [
                'status' => DeliveryStatus::COMPLETED,
            ])
            ->assertOk();

        $this->actingAs($this->shopUser)
            ->getJson(route('api.notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_shop_can_list_notifications(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::DELIVERED,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.status', $delivery->uuid), [
                'status' => DeliveryStatus::COMPLETED,
            ])
            ->assertOk();

        $response = $this->actingAs($this->shopUser)
            ->getJson(route('api.notifications.index'));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('delivery_completed', $response->json('data.0.type'));
        $this->assertSame($delivery->tracking_number, $response->json('data.0.tracking_number'));
    }

    public function test_shop_can_mark_notification_as_read(): void
    {
        $delivery = Delivery::factory()->create([
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'status' => DeliveryStatus::DELIVERED,
        ]);

        $this->actingAs($this->riderUser)
            ->postJson(route('deliveries.status', $delivery->uuid), [
                'status' => DeliveryStatus::COMPLETED,
            ])
            ->assertOk();

        $notificationId = $this->shopUser->fresh()->notifications()->first()->id;

        $this->actingAs($this->shopUser)
            ->postJson(route('api.notifications.read', $notificationId))
            ->assertOk();

        $this->assertNotNull($this->shopUser->fresh()->notifications()->first()->read_at);
    }
}
