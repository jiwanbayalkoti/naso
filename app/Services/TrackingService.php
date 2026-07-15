<?php

namespace App\Services;

use App\Helpers\DeliveryStatus;
use App\Helpers\GoogleMapsHelper;
use App\Models\Delivery;

class TrackingService extends BaseService
{
    public function __construct(
        protected GeocodingService $geocodingService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildDeliveryTracking(Delivery $delivery): array
    {
        $delivery->loadMissing(['shop', 'rider.user']);

        $pickupCoords = $this->resolveCoordinates(
            $delivery->shop?->latitude,
            $delivery->shop?->longitude,
            $delivery->pickup_address ?: $delivery->shop?->address
        );

        $dropoffCoords = $this->resolveCoordinates(
            $delivery->latitude,
            $delivery->longitude,
            $delivery->delivery_address
        );

        $rider = $delivery->rider;

        $route = null;
        if ($pickupCoords['latitude'] !== null && $pickupCoords['longitude'] !== null
            && $dropoffCoords['latitude'] !== null && $dropoffCoords['longitude'] !== null) {
            $route = GoogleMapsHelper::drivingRoute(
                $pickupCoords['latitude'],
                $pickupCoords['longitude'],
                $dropoffCoords['latitude'],
                $dropoffCoords['longitude']
            );
        }

        return [
            'uuid' => $delivery->uuid,
            'tracking_number' => $delivery->tracking_number,
            'status' => $delivery->status,
            'status_label' => DeliveryStatus::labels()[$delivery->status] ?? $delivery->status,
            'is_live' => $this->isLiveTrackable($delivery),
            'pickup' => [
                'label' => $delivery->shop?->name ?? 'Pickup',
                'address' => $delivery->pickup_address ?: $delivery->shop?->address,
                'latitude' => $pickupCoords['latitude'],
                'longitude' => $pickupCoords['longitude'],
            ],
            'dropoff' => [
                'label' => $delivery->customer_name,
                'address' => $delivery->delivery_address,
                'latitude' => $dropoffCoords['latitude'],
                'longitude' => $dropoffCoords['longitude'],
            ],
            'rider' => $rider ? [
                'uuid' => $rider->uuid,
                'name' => $rider->user?->name ?? 'Rider',
                'is_online' => (bool) $rider->is_online,
                'latitude' => $rider->current_latitude !== null ? (float) $rider->current_latitude : null,
                'longitude' => $rider->current_longitude !== null ? (float) $rider->current_longitude : null,
                'location_updated_at' => $rider->location_updated_at?->toIso8601String(),
            ] : null,
            'route' => $route,
            'google_maps_api_key' => config('services.google_maps.api_key'),
        ];
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    protected function resolveCoordinates(mixed $latitude, mixed $longitude, ?string $address): array
    {
        if ($latitude !== null && $longitude !== null) {
            return [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        }

        if ($address) {
            $geocoded = $this->geocodingService->forward($address);
            if ($geocoded) {
                return [
                    'latitude' => $geocoded['latitude'],
                    'longitude' => $geocoded['longitude'],
                ];
            }
        }

        return [
            'latitude' => null,
            'longitude' => null,
        ];
    }

    protected function isLiveTrackable(Delivery $delivery): bool
    {
        if ($delivery->isTerminal()) {
            return false;
        }

        return in_array($delivery->status, [
            DeliveryStatus::ASSIGNED,
            DeliveryStatus::ACCEPTED,
            DeliveryStatus::PICKED_UP,
            DeliveryStatus::ON_THE_WAY,
        ], true);
    }
}
