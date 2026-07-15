<?php

namespace App\Services;

use App\Helpers\GoogleMapsHelper;
use App\Models\Shop;

class DeliveryFeeCalculatorService
{
    public function __construct(
        protected AppSettingService $settings
    ) {}

    /**
     * @return array{distance_km: float, delivery_fee: float, base_fee: float, per_km: float, min_fee: float}
     */
    public function estimate(
        ?float $pickupLat,
        ?float $pickupLng,
        ?float $dropLat,
        ?float $dropLng,
        ?string $pickupAddress = null,
        ?string $dropAddress = null,
        ?Shop $shop = null
    ): array {
        $rates = $this->rates();

        if ($pickupLat === null || $pickupLng === null) {
            if ($pickupAddress) {
                $geo = GoogleMapsHelper::geocode($pickupAddress);
                $pickupLat = $geo['lat'] ?? null;
                $pickupLng = $geo['lng'] ?? null;
            }
            if (($pickupLat === null || $pickupLng === null) && $shop) {
                $pickupLat = $shop->latitude !== null ? (float) $shop->latitude : null;
                $pickupLng = $shop->longitude !== null ? (float) $shop->longitude : null;
            }
        }

        if ($dropLat === null || $dropLng === null) {
            if ($dropAddress) {
                $geo = GoogleMapsHelper::geocode($dropAddress);
                $dropLat = $geo['lat'] ?? null;
                $dropLng = $geo['lng'] ?? null;
            }
        }

        $distanceKm = 0.0;
        if ($pickupLat !== null && $pickupLng !== null && $dropLat !== null && $dropLng !== null) {
            $route = GoogleMapsHelper::drivingRoute($pickupLat, $pickupLng, $dropLat, $dropLng);
            if (isset($route['distance_meters']) && is_numeric($route['distance_meters'])) {
                $distanceKm = round(((float) $route['distance_meters']) / 1000, 2);
            } else {
                $distanceKm = $this->haversineKm($pickupLat, $pickupLng, $dropLat, $dropLng);
            }
        }

        $raw = $rates['base_fee'] + ($distanceKm * $rates['per_km']);
        $fee = max($rates['min_fee'], round($raw, 2));

        return [
            'distance_km' => $distanceKm,
            'delivery_fee' => $fee,
            'base_fee' => $rates['base_fee'],
            'per_km' => $rates['per_km'],
            'min_fee' => $rates['min_fee'],
        ];
    }

    /**
     * @return array{base_fee: float, per_km: float, min_fee: float, commission_percent: float}
     */
    public function rates(): array
    {
        return [
            'base_fee' => (float) $this->settings->get('delivery_base_fee', 50),
            'per_km' => (float) $this->settings->get('delivery_fee_per_km', 25),
            'min_fee' => (float) $this->settings->get('delivery_min_fee', 50),
            'commission_percent' => (float) $this->settings->get('platform_commission_percent', 20),
        ];
    }

    protected function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Road distance ≈ straight-line * 1.3
        return round(($earth * $c) * 1.3, 2);
    }
}
