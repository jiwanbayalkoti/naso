<?php

namespace Tests\Feature;

use App\Helpers\ApprovalStatus;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MenuSeeder::class);
        Storage::fake('public');
    }

    public function test_shop_registration_stays_pending_until_admin_approval(): void
    {
        $response = $this->post(route('register.shop'), array_merge([
            'owner_name' => 'Pending Shop Owner',
            'owner_email' => 'pendingshop@example.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'name' => 'Pending Shop',
        ], $this->shopDocumentPayload()));

        $response->assertRedirect(route('registration.pending'));
        $this->assertGuest();

        $user = User::where('email', 'pendingshop@example.com')->first();
        $shop = Shop::where('user_id', $user->id)->first();

        $this->assertFalse($user->is_active);
        $this->assertSame(ApprovalStatus::PENDING, $shop->approval_status);

        $loginResponse = $this->post(route('login'), [
            'email' => 'pendingshop@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_approve_shop_registration(): void
    {
        $admin = $this->createSuperAdmin();

        $this->post(route('register.shop'), array_merge([
            'owner_name' => 'Approve Shop Owner',
            'owner_email' => 'approveshop@example.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'name' => 'Approve Shop',
        ], $this->shopDocumentPayload()));

        $shop = Shop::whereHas('user', fn ($q) => $q->where('email', 'approveshop@example.com'))->first();

        $response = $this->actingAs($admin)->postJson(
            route('registration-requests.approve', ['type' => 'shop', 'uuid' => $shop->uuid])
        );

        $response->assertOk()->assertJsonPath('success', true);

        $shop->refresh();
        $shop->user->refresh();

        $this->assertSame(ApprovalStatus::APPROVED, $shop->approval_status);
        $this->assertTrue($shop->is_active);
        $this->assertTrue($shop->user->is_active);
    }

    public function test_super_admin_can_reject_rider_registration(): void
    {
        $admin = $this->createSuperAdmin();

        $this->post(route('register.rider'), array_merge([
            'name' => 'Reject Rider',
            'email' => 'rejectrider@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'license_number' => 'LIC-REJ-01',
        ], $this->riderDocumentPayload()));

        $rider = Rider::whereHas('user', fn ($q) => $q->where('email', 'rejectrider@example.com'))->first();

        $response = $this->actingAs($admin)->postJson(
            route('registration-requests.reject', ['type' => 'rider', 'uuid' => $rider->uuid]),
            ['reason' => 'Documents are not clear.']
        );

        $response->assertOk()->assertJsonPath('success', true);

        $rider->refresh();
        $rider->user->refresh();

        $this->assertSame(ApprovalStatus::REJECTED, $rider->approval_status);
        $this->assertFalse($rider->user->is_active);
        $this->assertSame('Documents are not clear.', $rider->rejection_reason);
    }

    public function test_super_admin_can_view_registration_requests_page(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get(route('registration-requests.index'));

        $response->assertOk()
            ->assertSee('Registration Requests')
            ->assertSee('registration-requests-module');
    }

    public function test_super_admin_can_list_and_show_pending_shop_registration_via_api(): void
    {
        $admin = $this->createSuperAdmin();

        $this->post(route('register.shop'), array_merge([
            'owner_name' => 'Api Shop Owner',
            'owner_email' => 'apishop@example.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'name' => 'Api Pending Shop',
        ], $this->shopDocumentPayload()));

        $shop = Shop::whereHas('user', fn ($q) => $q->where('email', 'apishop@example.com'))->firstOrFail();

        Sanctum::actingAs($admin);

        $this->getJson('/api/registration-requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['uuid' => $shop->uuid, 'type' => 'shop']);

        $this->getJson("/api/registration-requests/shop/{$shop->uuid}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $shop->uuid)
            ->assertJsonStructure(['data' => ['name', 'documents', 'user']]);
    }

    protected function createSuperAdmin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopDocumentPayload(): array
    {
        return [
            'pan_number' => 'PAN-123456',
            'nid_number' => 'NID-987654',
            'documents' => [
                'pan' => UploadedFile::fake()->image('pan.jpg'),
                'citizenship' => UploadedFile::fake()->image('citizenship.jpg'),
                'nid' => UploadedFile::fake()->image('nid.jpg'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function riderDocumentPayload(): array
    {
        return [
            'documents' => [
                'license' => UploadedFile::fake()->image('license.jpg'),
                'blue_book' => UploadedFile::fake()->image('blue_book.jpg'),
                'citizenship' => UploadedFile::fake()->image('citizenship.jpg'),
            ],
        ];
    }
}
