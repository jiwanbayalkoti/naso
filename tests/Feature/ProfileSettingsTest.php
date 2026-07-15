<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $shopUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin-settings@naso.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $this->shopUser = User::factory()->create([
            'email' => 'shop-settings@naso.com',
            'password' => Hash::make('password'),
            'phone' => '9801111111',
            'is_active' => true,
        ]);
        $this->shopUser->assignRole('shop');
    }

    public function test_user_can_update_own_profile_via_api(): void
    {
        Sanctum::actingAs($this->shopUser);

        $response = $this->putJson('/api/user', [
            'name' => 'Updated Shop User',
            'email' => 'shop-settings@naso.com',
            'phone' => '9802222222',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Shop User')
            ->assertJsonPath('data.user.phone', '9802222222');

        $this->assertDatabaseHas('users', [
            'id' => $this->shopUser->id,
            'name' => 'Updated Shop User',
            'phone' => '9802222222',
        ]);
    }

    public function test_user_can_change_password_via_api(): void
    {
        Sanctum::actingAs($this->shopUser);

        $response = $this->putJson('/api/user/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertOk();

        $this->shopUser->refresh();
        $this->assertTrue(Hash::check('new-password-123', $this->shopUser->password));
    }

    public function test_super_admin_can_view_and_update_settings(): void
    {
        Sanctum::actingAs($this->admin);

        $show = $this->getJson('/api/settings');
        $show->assertOk()
            ->assertJsonPath('data.app_name', config('app.name'));

        $update = $this->putJson('/api/settings', [
            'app_name' => 'NASO Updated',
            'support_email' => 'help@naso.com',
            'support_phone' => '9800000001',
            'shop_registration_enabled' => false,
            'rider_registration_enabled' => true,
            'dashboard_refresh_interval' => 45,
        ]);

        $update->assertOk()
            ->assertJsonPath('data.app_name', 'NASO Updated')
            ->assertJsonPath('data.shop_registration_enabled', false)
            ->assertJsonPath('data.dashboard_refresh_interval', 45);

        $this->assertDatabaseHas('app_settings', [
            'key' => 'app_name',
            'value' => 'NASO Updated',
        ]);
    }

    public function test_non_admin_cannot_access_settings_api(): void
    {
        Sanctum::actingAs($this->shopUser);

        $this->getJson('/api/settings')->assertForbidden();
        $this->putJson('/api/settings', [
            'app_name' => 'Hack',
            'dashboard_refresh_interval' => 30,
        ])->assertForbidden();
    }

    public function test_profile_page_is_accessible_on_web(): void
    {
        $response = $this->actingAs($this->shopUser)->get(route('profile.index'));

        $response->assertOk()
            ->assertSee('Profile Information')
            ->assertSee('shop-settings@naso.com');
    }

    public function test_settings_page_is_super_admin_only_on_web(): void
    {
        $this->actingAs($this->shopUser)
            ->get(route('settings.index'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Application Settings');
    }

    public function test_user_can_upload_profile_avatar(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->shopUser);

        $response = $this->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user' => ['avatar_url']]]);

        $this->shopUser->refresh();
        $this->assertNotNull($this->shopUser->avatar);
        Storage::disk('public')->assertExists($this->shopUser->avatar);
    }

    public function test_super_admin_can_upload_app_logo(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['app_logo_url']]);

        $this->assertDatabaseHas('app_settings', ['key' => 'app_logo']);
        $logoPath = \App\Models\AppSetting::where('key', 'app_logo')->value('value');
        Storage::disk('public')->assertExists($logoPath);
    }
}
