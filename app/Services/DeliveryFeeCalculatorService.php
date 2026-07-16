<?php

namespace App\Services;

use App\Helpers\GoogleMapsHelper;
use App\Models\Shop;

class DeliveryFeeCalculatorService
{
    public function __construct(
        protected AppSettingService $settings,
        protected OfferEngine $offerEngine
    ) {}

    /**
     * @return array{
     *   distance_km: float,
     *   delivery_fee: float,
     *   base_delivery_fee: float,
     *   base_fee: float,
     *   per_km: float,
     *   min_fee: float,
     *   pricing_mode: string,
     *   pricing_zone: string,
     *   pricing_slab: ?array,
     *   applied_offer_ids: list<int>,
     *   offer_notes: ?string,
     *   offer: ?array
     * }
     */
    public function estimate(
        ?float $pickupLat,
        ?float $pickupLng,
        ?float $dropLat,
        ?float $dropLng,
        ?string $pickupAddress = null,
        ?string $dropAddress = null,
        ?Shop $shop = null,
        ?float $codAmount = null
    ): array {
        $rates = $this->rates();
        $pricing = $this->settings->deliveryPricing();

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
                $distanceKm = $this->roadApproximateKm($pickupLat, $pickupLng, $dropLat, $dropLng);
            }
        }

        $resolved = $this->resolveBaseFee($distanceKm, $dropLat, $dropLng, $rates, $pricing);
        $baseFee = $resolved['base_fee'];

        $offered = $this->offerEngine->resolveShopFee($shop, $baseFee, $codAmount);

        return [
            'distance_km' => $distanceKm,
            'delivery_fee' => $offered['delivery_fee'],
            'base_delivery_fee' => $offered['base_delivery_fee'],
            'base_fee' => $rates['base_fee'],
            'per_km' => $rates['per_km'],
            'min_fee' => $rates['min_fee'],
            'pricing_mode' => $resolved['pricing_mode'],
            'pricing_zone' => $resolved['pricing_zone'],
            'pricing_slab' => $resolved['pricing_slab'],
            'applied_offer_ids' => $offered['applied_offer_ids'],
            'offer_notes' => $offered['offer_notes'],
            'offer' => $offered['offer'],
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

    /**
     * @param  array{base_fee: float, per_km: float, min_fee: float, commission_percent: float}  $rates
     * @param  array<string, mixed>  $pricing
     * @return array{base_fee: float, pricing_mode: string, pricing_zone: string, pricing_slab: ?array}
     */
    public function resolveBaseFee(
        float $distanceKm,
        ?float $dropLat,
        ?float $dropLng,
        array $rates,
        array $pricing
    ): array {
        $mode = (string) ($pricing['mode'] ?? 'zone_slabs');
        $minFee = (float) $rates['min_fee'];

        if ($mode !== 'zone_slabs') {
            $raw = $rates['base_fee'] + ($distanceKm * $rates['per_km']);

            return [
                'base_fee' => max($minFee, round($raw, 2)),
                'pricing_mode' => 'linear',
                'pricing_zone' => 'linear',
                'pricing_slab' => null,
            ];
        }

        $zone = $this->classifyZone($dropLat, $dropLng, $pricing['valley'] ?? []);
        if ($zone === 'unknown') {
            $raw = $rates['base_fee'] + ($distanceKm * $rates['per_km']);

            return [
                'base_fee' => max($minFee, round($raw, 2)),
                'pricing_mode' => 'zone_slabs',
                'pricing_zone' => 'unknown',
                'pricing_slab' => null,
            ];
        }

        $slabs = $zone === 'inside_valley'
            ? ($pricing['inside_valley'] ?? [])
            : ($pricing['outside_valley'] ?? []);

        $slab = $this->matchSlab($distanceKm, is_array($slabs) ? $slabs : []);
        $fee = $slab !== null ? (float) ($slab['fee'] ?? 0) : $minFee;

        return [
            'base_fee' => max($minFee, round($fee, 2)),
            'pricing_mode' => 'zone_slabs',
            'pricing_zone' => $zone,
            'pricing_slab' => $slab,
        ];
    }

    /**
     * @param  array<string, mixed>  $valley
     */
    public function classifyZone(?float $lat, ?float $lng, array $valley): string
    {
        if ($lat === null || $lng === null) {
            return 'unknown';
        }

        $centerLat = isset($valley['lat']) ? (float) $valley['lat'] : null;
        $centerLng = isset($valley['lng']) ? (float) $valley['lng'] : null;
        $radiusKm = isset($valley['radius_km']) ? (float) $valley['radius_km'] : null;

        if ($centerLat === null || $centerLng === null || $radiusKm === null || $radiusKm < 0) {
            return 'unknown';
        }

        $distance = $this->haversineKm($centerLat, $centerLng, $lat, $lng);

        return $distance <= $radiusKm ? 'inside_valley' : 'outside_valley';
    }

    /**
     * @param  list<array<string, mixed>>  $slabs
     * @return array<string, mixed>|null
     */
    public function matchSlab(float $distanceKm, array $slabs): ?array
    {
        if ($slabs === []) {
            return null;
        }

        usort($slabs, function ($a, $b) {
            return ((float) ($a['from_km'] ?? 0)) <=> ((float) ($b['from_km'] ?? 0));
        });

        foreach ($slabs as $slab) {
            $from = (float) ($slab['from_km'] ?? 0);
            $to = array_key_exists('to_km', $slab) && $slab['to_km'] !== null && $slab['to_km'] !== ''
                ? (float) $slab['to_km']
                : null;

            if ($distanceKm >= $from && ($to === null || $distanceKm < $to)) {
                return $this->normalizeSlab($slab);
            }
        }

        return $this->normalizeSlab($slabs[array_key_last($slabs)]);
    }

    /**
     * @param  array<string, mixed>  $slab
     * @return array<string, mixed>
     */
    protected function normalizeSlab(array $slab): array
    {
        $from = (float) ($slab['from_km'] ?? 0);
        $to = array_key_exists('to_km', $slab) && $slab['to_km'] !== null && $slab['to_km'] !== ''
            ? (float) $slab['to_km']
            : null;
        $label = isset($slab['label']) && is_string($slab['label']) && $slab['label'] !== ''
            ? $slab['label']
            : ($to === null ? sprintf('%.0f+ km', $from) : sprintf('%.0f–%.0f km', $from, $to));

        return [
            'label' => $label,
            'from_km' => $from,
            'to_km' => $to,
            'fee' => round((float) ($slab['fee'] ?? 0), 2),
        ];
    }

    protected function roadApproximateKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return round($this->haversineKm($lat1, $lng1, $lat2, $lng2) * 1.3, 2);
    }

    protected function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth * $c;
    }
}
