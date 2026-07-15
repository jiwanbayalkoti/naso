<?php

namespace Tests\Feature;

use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleScopedListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_rider_datatable_only_returns_own_profile(): void
    {
        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        $ownRider = Rider::factory()->create(['user_id' => $riderUser->id]);

        $otherUser = User::factory()->create(['is_active' => true]);
        $otherUser->assignRole('rider');
        Rider::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($riderUser)->getJson(route('riders.datatable'));

        $response->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertCount(1, $uuids);
        $this->assertTrue($uuids->contains($ownRider->uuid));
    }

    public function test_rider_cannot_view_other_rider_profile(): void
    {
        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        Rider::factory()->create(['user_id' => $riderUser->id]);

        $otherUser = User::factory()->create(['is_active' => true]);
        $otherUser->assignRole('rider');
        $otherRider = Rider::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($riderUser)->getJson(route('riders.edit', $otherRider->uuid));

        $response->assertForbidden();
    }

    public function test_rider_can_toggle_own_online_status(): void
    {
        $riderUser = User::factory()->create(['is_active' => true]);
        $riderUser->assignRole('rider');
        $rider = Rider::factory()->create([
            'user_id' => $riderUser->id,
            'is_online' => false,
        ]);

        $response = $this->actingAs($riderUser)->postJson(
            route('riders.toggle-online', $rider->uuid)
        );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($rider->fresh()->is_online);
    }

    public function test_shop_datatable_only_returns_own_shop(): void
    {
        $shopUser = User::factory()->create(['is_active' => true]);
        $shopUser->assignRole('shop');
        $ownShop = Shop::factory()->create(['user_id' => $shopUser->id]);

        $otherUser = User::factory()->create(['is_active' => true]);
        $otherUser->assignRole('shop');
        Shop::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($shopUser)->getJson(route('shops.datatable'));

        $response->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertCount(1, $uuids);
        $this->assertTrue($uuids->contains($ownShop->uuid));
    }
}
