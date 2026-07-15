<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_user_can_fetch_dashboard_stats(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->getJson(route('api.dashboard.stats'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total_deliveries']]);
    }

    public function test_mobile_token_can_fetch_dashboard_stats(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $token = $user->createToken('mobile-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total_deliveries']]);
    }

    public function test_rider_mobile_token_can_fetch_scoped_dashboard_stats(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('rider');
        $rider = \App\Models\Rider::factory()->create(['user_id' => $user->id]);

        \App\Models\Delivery::factory()->create([
            'rider_id' => $rider->id,
            'status' => \App\Helpers\DeliveryStatus::ASSIGNED,
        ]);
        \App\Models\Delivery::factory()->create([
            'rider_id' => null,
            'status' => \App\Helpers\DeliveryStatus::PENDING,
        ]);

        $token = $user->createToken('mobile-rider')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_deliveries', 1)
            ->assertJsonPath('data.assigned_deliveries', 1);
    }
}
