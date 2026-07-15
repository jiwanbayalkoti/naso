<?php

namespace Tests\Feature\Auth;

use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Models\VerificationDocument;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_guest_can_view_shop_registration_form(): void
    {
        $response = $this->get(route('register.shop'));

        $response->assertOk()
            ->assertSee('Register Shop')
            ->assertSee('register-shop-form')
            ->assertSee('Verification documents');
    }

    public function test_guest_can_view_rider_registration_form(): void
    {
        $response = $this->get(route('register.rider'));

        $response->assertOk()
            ->assertSee('Register as Rider')
            ->assertSee('register-rider-form')
            ->assertSee('Blue Book');
    }

    public function test_shop_can_self_register_via_web(): void
    {
        $response = $this->post(route('register.shop'), array_merge([
            'owner_name' => 'Shop Owner',
            'owner_email' => 'shopowner@example.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'name' => 'My Test Shop',
            'email' => 'shop@example.com',
            'phone' => '9800000001',
            'city' => 'Kathmandu',
            'address' => 'Thamel',
        ], $this->shopDocumentPayload()));

        $response->assertRedirect(route('registration.pending'));

        $user = User::where('email', 'shopowner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('shop'));
        $this->assertGuest();
        $this->assertFalse($user->is_active);

        $shop = Shop::where('user_id', $user->id)->first();
        $this->assertNotNull($shop);
        $this->assertSame('My Test Shop', $shop->name);
        $this->assertSame('pending', $shop->approval_status);
        $this->assertNull($shop->pan_number);
        $this->assertNull($shop->nid_number);
        $this->assertCount(2, $shop->verificationDocuments);
    }

    public function test_rider_can_self_register_via_web(): void
    {
        $response = $this->post(route('register.rider'), array_merge([
            'name' => 'Rider User',
            'email' => 'rider@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'vehicle_type' => 'motorcycle',
            'vehicle_number' => 'BA-1-PA-1234',
            'license_number' => 'LIC-12345',
        ], $this->riderDocumentPayload()));

        $response->assertRedirect(route('registration.pending'));

        $user = User::where('email', 'rider@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('rider'));
        $this->assertGuest();
        $this->assertFalse($user->is_active);

        $rider = Rider::where('user_id', $user->id)->first();
        $this->assertNotNull($rider);
        $this->assertSame('pending', $rider->approval_status);
        $this->assertSame('motorcycle', $rider->vehicle_type);
        $this->assertNull($rider->pan_number);
        $this->assertNull($rider->nid_number);
        $this->assertCount(3, $rider->verificationDocuments);
    }

    public function test_shop_can_self_register_via_api(): void
    {
        $response = $this->post('/api/register/shop', array_merge($this->shopDocumentPayload(), [
            'owner_name' => 'API Shop Owner',
            'owner_email' => 'apishop@example.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'name' => 'API Shop',
        ]), [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_approval', true)
            ->assertJsonPath('data.shop.name', 'API Shop')
            ->assertJsonPath('data.user.email', 'apishop@example.com');

        $this->assertDatabaseHas('users', ['email' => 'apishop@example.com']);
        $this->assertDatabaseHas('shops', ['name' => 'API Shop']);
        $this->assertSame(2, VerificationDocument::count());
    }

    public function test_rider_can_self_register_via_api(): void
    {
        $response = $this->post('/api/register/rider', array_merge([
            'name' => 'API Rider',
            'email' => 'apirider@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'vehicle_type' => 'bicycle',
            'license_number' => 'LIC-API-99',
        ], $this->riderDocumentPayload()), [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_approval', true)
            ->assertJsonPath('data.rider.vehicle_type', 'bicycle')
            ->assertJsonPath('data.user.email', 'apirider@example.com');

        $this->assertDatabaseHas('users', ['email' => 'apirider@example.com']);
        $this->assertDatabaseHas('riders', ['vehicle_type' => 'bicycle', 'license_number' => 'LIC-API-99']);
        $this->assertSame(3, VerificationDocument::count());
    }

    public function test_rider_can_register_with_optional_nid(): void
    {
        $response = $this->post(route('register.rider'), array_merge([
            'name' => 'Rider With NID',
            'email' => 'riderwithnid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'license_number' => 'LIC-NID-01',
        ], $this->riderDocumentPayload([
            'nid' => UploadedFile::fake()->image('nid.jpg'),
        ])));

        $response->assertRedirect(route('registration.pending'));

        $rider = Rider::whereHas('user', fn ($query) => $query->where('email', 'riderwithnid@example.com'))->first();
        $this->assertNotNull($rider);
        $this->assertNull($rider->nid_number);
        $this->assertCount(4, $rider->verificationDocuments);
    }

    public function test_shop_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->post(route('register.shop'), array_merge([
            'owner_name' => 'Shop Owner',
            'owner_email' => 'duplicate@example.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'name' => 'Duplicate Shop',
        ], $this->shopDocumentPayload()));

        $response->assertSessionHasErrors('owner_email');
        $this->assertGuest();
    }

    public function test_rider_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->post(route('register.rider'), array_merge([
            'name' => 'Rider User',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'license_number' => 'LIC-DUP',
        ], $this->riderDocumentPayload()));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_shop_registration_requires_documents(): void
    {
        $response = $this->post(route('register.shop'), [
            'owner_name' => 'Shop Owner',
            'owner_email' => 'nodocs@example.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'name' => 'No Docs Shop',
        ]);

        $response->assertSessionHasErrors([
            'documents.pan',
            'documents.citizenship',
        ]);
        $this->assertGuest();
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopDocumentPayload(): array
    {
        return [
            'documents' => [
                'pan' => UploadedFile::fake()->image('pan.jpg'),
                'citizenship' => UploadedFile::fake()->image('citizenship.jpg'),
            ],
        ];
    }

    /**
     * @param  array<string, UploadedFile>  $extraDocuments
     * @return array<string, mixed>
     */
    protected function riderDocumentPayload(array $extraDocuments = []): array
    {
        return [
            'documents' => array_merge([
                'license' => UploadedFile::fake()->image('license.jpg'),
                'blue_book' => UploadedFile::fake()->image('blue_book.jpg'),
                'citizenship' => UploadedFile::fake()->image('citizenship.jpg'),
            ], $extraDocuments),
        ];
    }
}
