<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsHelper
{
    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function geocode(string $address): ?array
    {
        $apiKey = config('services.google_maps.api_key');
        if (! $apiKey || trim($address) === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey,
            ]);

            if (! $response->ok()) {
                return null;
            }

            $result = $response->json('results.0.geometry.location');
            if (! is_array($result) || ! isset($result['lat'], $result['lng'])) {
                return null;
            }

            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lng'],
            ];
        } catch (\Throwable $exception) {
            Log::warning('Google geocode failed', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{address: string, latitude: float, longitude: float}|null
     */
    public static function reverseGeocode(float $latitude, float $longitude): ?array
    {
        $apiKey = config('services.google_maps.api_key');
        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => $latitude.','.$longitude,
                'key' => $apiKey,
            ]);

            if (! $response->ok() || $response->json('status') !== 'OK') {
                return null;
            }

            $address = $response->json('results.0.formatted_address');
            if (! is_string($address) || trim($address) === '') {
                return null;
            }

            return [
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Google reverse geocode failed', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{
     *     points: array<int, array{lat: float, lng: float}>,
     *     distance_text: string|null,
     *     duration_text: string|null,
     *     provider: string
     * }
     */
    public static function drivingRoute(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $google = self::drivingRouteGoogle($originLat, $originLng, $destLat, $destLng);
        if ($google) {
            return $google;
        }

        $osrm = self::drivingRouteOsrm($originLat, $originLng, $destLat, $destLng);
        if ($osrm) {
            return $osrm;
        }

        return [
            'points' => [
                ['lat' => $originLat, 'lng' => $originLng],
                ['lat' => $destLat, 'lng' => $destLng],
            ],
            'distance_text' => null,
            'duration_text' => null,
            'provider' => 'direct',
        ];
    }

    /**
     * @return array{
     *     points: array<int, array{lat: float, lng: float}>,
     *     distance_text: string|null,
     *     duration_text: string|null,
     *     provider: string
     * }|null
     */
    protected static function drivingRouteGoogle(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        $apiKey = config('services.google_maps.api_key');
        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => $originLat.','.$originLng,
                'destination' => $destLat.','.$destLng,
                'mode' => 'driving',
                'key' => $apiKey,
            ]);

            if (! $response->ok() || $response->json('status') !== 'OK') {
                return null;
            }

            $route = $response->json('routes.0');
            $polyline = $route['overview_polyline']['points'] ?? null;
            if (! is_string($polyline) || $polyline === '') {
                return null;
            }

            $leg = $route['legs'][0] ?? [];

            return [
                'points' => self::decodePolyline($polyline),
                'distance_text' => $leg['distance']['text'] ?? null,
                'duration_text' => $leg['duration']['text'] ?? null,
                'provider' => 'google',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Google directions failed', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{
     *     points: array<int, array{lat: float, lng: float}>,
     *     distance_text: string|null,
     *     duration_text: string|null,
     *     provider: string
     * }|null
     */
    protected static function drivingRouteOsrm(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        try {
            $coordinates = $originLng.','.$originLat.';'.$destLng.','.$destLat;
            $response = Http::timeout(12)->get(
                'https://router.project-osrm.org/route/v1/driving/'.$coordinates,
                [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                ]
            );

            if (! $response->ok() || $response->json('code') !== 'Ok') {
                return null;
            }

            $geometry = $response->json('routes.0.geometry.coordinates');
            if (! is_array($geometry) || count($geometry) < 2) {
                return null;
            }

            $points = [];
            foreach ($geometry as $coordinate) {
                if (! is_array($coordinate) || count($coordinate) < 2) {
                    continue;
                }

                $points[] = [
                    'lat' => (float) $coordinate[1],
                    'lng' => (float) $coordinate[0],
                ];
            }

            if (count($points) < 2) {
                return null;
            }

            $distanceMeters = $response->json('routes.0.distance');
            $durationSeconds = $response->json('routes.0.duration');

            return [
                'points' => $points,
                'distance_text' => self::formatDistance(is_numeric($distanceMeters) ? (float) $distanceMeters : null),
                'duration_text' => self::formatDuration(is_numeric($durationSeconds) ? (float) $durationSeconds : null),
                'provider' => 'osrm',
            ];
        } catch (\Throwable $exception) {
            Log::warning('OSRM directions failed', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    protected static function formatDistance(?float $meters): ?string
    {
        if ($meters === null) {
            return null;
        }

        if ($meters >= 1000) {
            return round($meters / 1000, 1).' km';
        }

        return round($meters).' m';
    }

    protected static function formatDuration(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $hours.' hr '.$remainingMinutes.' min';
    }

    /**
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function decodePolyline(string $encoded): array
    {
        $points = [];
        $index = 0;
        $length = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $length) {
            $shift = 0;
            $result = 0;

            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1f) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);

            $deltaLat = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lat += $deltaLat;

            $shift = 0;
            $result = 0;

            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1f) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);

            $deltaLng = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lng += $deltaLng;

            $points[] = [
                'lat' => $lat * 1e-5,
                'lng' => $lng * 1e-5,
            ];
        }

        return $points;
    }
}
