<?php

namespace App\Services;

use App\Helpers\MediaUrlHelper;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class AppSettingService extends BaseService
{
    protected const CACHE_KEY = 'app_settings.all.v3';

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
            'delivery_base_fee' => 50,
            'delivery_fee_per_km' => 25,
            'delivery_min_fee' => 50,
            'platform_commission_percent' => 20,
            'delivery_pricing' => $this->defaultDeliveryPricing(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultDeliveryPricing(): array
    {
        return [
            'mode' => 'zone_slabs',
            'valley' => [
                'lat' => 27.7172,
                'lng' => 85.3240,
                'radius_km' => 18,
            ],
            'inside_valley' => [
                ['from_km' => 0, 'to_km' => 5, 'fee' => 50],
                ['from_km' => 5, 'to_km' => 10, 'fee' => 100],
                ['from_km' => 10, 'to_km' => 20, 'fee' => 150],
                ['from_km' => 20, 'to_km' => null, 'fee' => 200],
            ],
            'outside_valley' => [
                ['label' => 'short', 'from_km' => 0, 'to_km' => 30, 'fee' => 300],
                ['label' => 'medium', 'from_km' => 30, 'to_km' => 80, 'fee' => 500],
                ['label' => 'long', 'from_km' => 80, 'to_km' => null, 'fee' => 800],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deliveryPricing(): array
    {
        $pricing = $this->get('delivery_pricing', $this->defaultDeliveryPricing());

        return is_array($pricing) ? $this->normalizeDeliveryPricing($pricing) : $this->defaultDeliveryPricing();
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

        $settings['delivery_pricing'] = $this->normalizeDeliveryPricing(
            is_array($settings['delivery_pricing'] ?? null)
                ? $settings['delivery_pricing']
                : $this->defaultDeliveryPricing()
        );

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

                if ($key === 'delivery_pricing' && is_array($value)) {
                    $value = $this->normalizeDeliveryPricing($value);
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
            'delivery_base_fee' => (float) ($settings['delivery_base_fee'] ?? 50),
            'delivery_fee_per_km' => (float) ($settings['delivery_fee_per_km'] ?? 25),
            'delivery_min_fee' => (float) ($settings['delivery_min_fee'] ?? 50),
            'platform_commission_percent' => (float) ($settings['platform_commission_percent'] ?? 20),
            'delivery_pricing' => $settings['delivery_pricing'] ?? $this->defaultDeliveryPricing(),
            'google_maps_api_key' => config('services.google_maps.api_key'),
        ];
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array<string, mixed>
     */
    public function normalizeDeliveryPricing(array $pricing): array
    {
        $defaults = $this->defaultDeliveryPricing();
        $mode = ($pricing['mode'] ?? 'zone_slabs') === 'linear' ? 'linear' : 'zone_slabs';

        $valleyIn = is_array($pricing['valley'] ?? null) ? $pricing['valley'] : [];
        $valley = [
            'lat' => round((float) ($valleyIn['lat'] ?? $defaults['valley']['lat']), 6),
            'lng' => round((float) ($valleyIn['lng'] ?? $defaults['valley']['lng']), 6),
            'radius_km' => max(0, round((float) ($valleyIn['radius_km'] ?? $defaults['valley']['radius_km']), 2)),
        ];

        return [
            'mode' => $mode,
            'valley' => $valley,
            'inside_valley' => $this->normalizeSlabs($pricing['inside_valley'] ?? $defaults['inside_valley'], false),
            'outside_valley' => $this->normalizeSlabs($pricing['outside_valley'] ?? $defaults['outside_valley'], true),
        ];
    }

    /**
     * @param  mixed  $slabs
     * @return list<array<string, mixed>>
     */
    protected function normalizeSlabs(mixed $slabs, bool $withLabel): array
    {
        if (! is_array($slabs)) {
            return [];
        }

        $normalized = [];
        foreach ($slabs as $slab) {
            if (! is_array($slab)) {
                continue;
            }

            $from = max(0, round((float) ($slab['from_km'] ?? 0), 2));
            $toRaw = $slab['to_km'] ?? null;
            $to = ($toRaw === null || $toRaw === '') ? null : max(0, round((float) $toRaw, 2));
            $fee = max(0, round((float) ($slab['fee'] ?? 0), 2));

            $row = [
                'from_km' => $from,
                'to_km' => $to,
                'fee' => $fee,
            ];

            if ($withLabel) {
                $label = trim((string) ($slab['label'] ?? ''));
                $row['label'] = $label !== '' ? $label : (
                    $to === null ? sprintf('%.0f+ km', $from) : sprintf('%.0f–%.0f km', $from, $to)
                );
            }

            $normalized[] = $row;
        }

        usort($normalized, fn ($a, $b) => $a['from_km'] <=> $b['from_km']);

        return $normalized;
    }

    protected function castValue(string $key, ?string $value): mixed
    {
        if ($value === null) {
            return $this->defaults()[$key] ?? null;
        }

        return match ($key) {
            'shop_registration_enabled', 'rider_registration_enabled' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'dashboard_refresh_interval', 'delivery_offer_timeout_minutes' => (int) $value,
            'delivery_base_fee', 'delivery_fee_per_km', 'delivery_min_fee', 'platform_commission_percent' => (float) $value,
            'delivery_pricing' => $this->decodeJsonSetting($value, $this->defaultDeliveryPricing()),
            default => $value,
        };
    }

    protected function serializeValue(string $key, mixed $value): string
    {
        return match ($key) {
            'shop_registration_enabled', 'rider_registration_enabled' => $value ? '1' : '0',
            'dashboard_refresh_interval' => (string) max(5, (int) $value),
            'delivery_offer_timeout_minutes' => (string) max(1, (int) $value),
            'delivery_base_fee', 'delivery_fee_per_km', 'delivery_min_fee' => (string) max(0, (float) $value),
            'platform_commission_percent' => (string) max(0, min(100, (float) $value)),
            'delivery_pricing' => json_encode(
                is_array($value) ? $this->normalizeDeliveryPricing($value) : $this->defaultDeliveryPricing(),
                JSON_UNESCAPED_UNICODE
            ) ?: '{}',
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    protected function decodeJsonSetting(string $value, array $fallback): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $fallback;
    }
}
