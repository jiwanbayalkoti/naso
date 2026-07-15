<?php

namespace App\Services;

use App\Helpers\MediaUrlHelper;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class AppSettingService extends BaseService
{
    protected const CACHE_KEY = 'app_settings.all.v2';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'app_name' => config('app.name', 'NASO Delivery'),
            'app_logo' => null,
            'support_email' => env('SUPPORT_EMAIL', 'support@naso.com'),
            'support_phone' => env('SUPPORT_PHONE', ''),
            'shop_registration_enabled' => true,
            'rider_registration_enabled' => true,
            'dashboard_refresh_interval' => (int) env('DASHBOARD_REFRESH_INTERVAL', 30),
            'delivery_offer_timeout_minutes' => (int) env('DELIVERY_OFFER_TIMEOUT_MINUTES', 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return AppSetting::query()->pluck('value', 'key')->all();
        });

        $settings = $this->defaults();

        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = $this->castValue($key, $value);
            }
        }

        return $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateMany(array $settings, ?int $actorId = null): array
    {
        return $this->transaction(function () use ($settings) {
            $allowed = array_keys($this->defaults());

            foreach ($settings as $key => $value) {
                if (! in_array($key, $allowed, true)) {
                    continue;
                }

                AppSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $this->serializeValue($key, $value)]
                );
            }

            Cache::forget(self::CACHE_KEY);

            return $this->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $settings = $this->all();

        return [
            'app_name' => $settings['app_name'] ?? $this->defaults()['app_name'],
            'app_logo' => $settings['app_logo'] ?? null,
            'app_logo_url' => MediaUrlHelper::url($settings['app_logo'] ?? null),
            'support_email' => $settings['support_email'] ?? null,
            'support_phone' => $settings['support_phone'] ?? null,
            'shop_registration_enabled' => (bool) ($settings['shop_registration_enabled'] ?? true),
            'rider_registration_enabled' => (bool) ($settings['rider_registration_enabled'] ?? true),
            'dashboard_refresh_interval' => (int) ($settings['dashboard_refresh_interval'] ?? 30),
            'delivery_offer_timeout_minutes' => (int) ($settings['delivery_offer_timeout_minutes'] ?? 15),
            'google_maps_api_key' => config('services.google_maps.api_key'),
        ];
    }

    protected function castValue(string $key, ?string $value): mixed
    {
        if ($value === null) {
            return $this->defaults()[$key] ?? null;
        }

        return match ($key) {
            'shop_registration_enabled', 'rider_registration_enabled' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'dashboard_refresh_interval', 'delivery_offer_timeout_minutes' => (int) $value,
            default => $value,
        };
    }

    protected function serializeValue(string $key, mixed $value): string
    {
        return match ($key) {
            'shop_registration_enabled', 'rider_registration_enabled' => $value ? '1' : '0',
            'dashboard_refresh_interval' => (string) max(5, (int) $value),
            'delivery_offer_timeout_minutes' => (string) max(1, (int) $value),
            default => (string) $value,
        };
    }
}
