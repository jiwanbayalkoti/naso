<?php

namespace App\Services;

use App\Helpers\GoogleMapsHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
  /**
   * @return array{address: string, latitude: float, longitude: float, provider: string}|null
   */
  public function reverse(float $latitude, float $longitude): ?array
  {
    $google = GoogleMapsHelper::reverseGeocode($latitude, $longitude);
    if ($google) {
      return [
        'address' => $google['address'],
        'latitude' => $google['latitude'],
        'longitude' => $google['longitude'],
        'provider' => 'google',
      ];
    }

    $nominatim = $this->reverseWithNominatim($latitude, $longitude);
    if ($nominatim) {
      return $nominatim;
    }

    return [
      'address' => sprintf('%.6f, %.6f', $latitude, $longitude),
      'latitude' => $latitude,
      'longitude' => $longitude,
      'provider' => 'coordinates',
    ];
  }

  /**
   * @return array<int, array{id: string, label: string, address: string, latitude: float|null, longitude: float|null, provider: string}>
   */
  public function search(string $query, int $limit = 6): array
  {
    $query = trim($query);
    if (mb_strlen($query) < 3) {
      return [];
    }

    $google = $this->searchWithGoogle($query, $limit);
    if ($google !== []) {
      return $google;
    }

    return $this->searchWithNominatim($query, $limit);
  }

  /**
   * @return array{address: string, latitude: float, longitude: float, provider: string}|null
   */
  public function resolve(string $provider, string $id): ?array
  {
    if ($provider === 'google') {
      return $this->resolveGooglePlace($id);
    }

    if ($provider === 'osm') {
      return $this->resolveOsmPlace($id);
    }

    return null;
  }

  /**
   * @return array{latitude: float, longitude: float, provider: string}|null
   */
  public function forward(string $address): ?array
  {
    $address = trim($address);
    if ($address === '') {
      return null;
    }

    $google = GoogleMapsHelper::geocode($address);
    if ($google) {
      return [
        'latitude' => $google['lat'],
        'longitude' => $google['lng'],
        'provider' => 'google',
      ];
    }

    $results = $this->searchWithNominatim($address, 1);
    $first = $results[0] ?? null;
    if ($first && $first['latitude'] !== null && $first['longitude'] !== null) {
      return [
        'latitude' => $first['latitude'],
        'longitude' => $first['longitude'],
        'provider' => 'osm',
      ];
    }

    return null;
  }

  /**
   * @return array{address: string, latitude: float, longitude: float, provider: string}|null
   */
  protected function reverseWithNominatim(float $latitude, float $longitude): ?array
  {
    try {
      $response = Http::withHeaders([
        'User-Agent' => config('app.name', 'NASO').'/1.0',
      ])->timeout(8)->get('https://nominatim.openstreetmap.org/reverse', [
        'lat' => $latitude,
        'lon' => $longitude,
        'format' => 'json',
        'addressdetails' => 1,
      ]);

      if (! $response->ok()) {
        return null;
      }

      $displayName = $response->json('display_name');
      if (! is_string($displayName) || trim($displayName) === '') {
        return null;
      }

      return [
        'address' => $displayName,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'provider' => 'osm',
      ];
    } catch (\Throwable $exception) {
      Log::warning('Nominatim reverse geocode failed', ['message' => $exception->getMessage()]);

      return null;
    }
  }

  /**
   * @return array<int, array{id: string, label: string, address: string, latitude: float|null, longitude: float|null, provider: string}>
   */
  protected function searchWithGoogle(string $query, int $limit): array
  {
    $apiKey = config('services.google_maps.api_key');
    if (! $apiKey) {
      return [];
    }

    try {
      $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
        'input' => $query,
        'key' => $apiKey,
      ]);

      if (! $response->ok() || $response->json('status') !== 'OK') {
        return [];
      }

      $predictions = $response->json('predictions', []);
      $results = [];

      foreach (array_slice($predictions, 0, $limit) as $prediction) {
        if (! is_array($prediction) || empty($prediction['place_id'])) {
          continue;
        }

        $label = (string) ($prediction['structured_formatting']['main_text'] ?? $prediction['description'] ?? '');
        $address = (string) ($prediction['description'] ?? $label);

        $results[] = [
          'id' => (string) $prediction['place_id'],
          'label' => $label,
          'address' => $address,
          'latitude' => null,
          'longitude' => null,
          'provider' => 'google',
        ];
      }

      return $results;
    } catch (\Throwable $exception) {
      Log::warning('Google place autocomplete failed', ['message' => $exception->getMessage()]);

      return [];
    }
  }

  /**
   * @return array<int, array{id: string, label: string, address: string, latitude: float|null, longitude: float|null, provider: string}>
   */
  protected function searchWithNominatim(string $query, int $limit): array
  {
    try {
      $response = Http::withHeaders([
        'User-Agent' => config('app.name', 'NASO').'/1.0',
      ])->timeout(8)->get('https://nominatim.openstreetmap.org/search', [
        'q' => $query,
        'format' => 'json',
        'addressdetails' => 1,
        'limit' => $limit,
      ]);

      if (! $response->ok()) {
        return [];
      }

      $items = $response->json();
      if (! is_array($items)) {
        return [];
      }

      $results = [];
      foreach ($items as $item) {
        if (! is_array($item) || ! isset($item['osm_type'], $item['osm_id'], $item['display_name'])) {
          continue;
        }

        $results[] = [
          'id' => $item['osm_type'].':'.$item['osm_id'],
          'label' => $this->nominatimLabel($item),
          'address' => (string) $item['display_name'],
          'latitude' => isset($item['lat']) ? (float) $item['lat'] : null,
          'longitude' => isset($item['lon']) ? (float) $item['lon'] : null,
          'provider' => 'osm',
        ];
      }

      return $results;
    } catch (\Throwable $exception) {
      Log::warning('Nominatim search failed', ['message' => $exception->getMessage()]);

      return [];
    }
  }

  /**
   * @return array{address: string, latitude: float, longitude: float, provider: string}|null
   */
  protected function resolveGooglePlace(string $placeId): ?array
  {
    $apiKey = config('services.google_maps.api_key');
    if (! $apiKey) {
      return null;
    }

    try {
      $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/details/json', [
        'place_id' => $placeId,
        'fields' => 'formatted_address,geometry',
        'key' => $apiKey,
      ]);

      if (! $response->ok() || $response->json('status') !== 'OK') {
        return null;
      }

      $result = $response->json('result', []);
      $location = $result['geometry']['location'] ?? null;
      $address = $result['formatted_address'] ?? null;

      if (! is_array($location) || ! isset($location['lat'], $location['lng']) || ! is_string($address)) {
        return null;
      }

      return [
        'address' => $address,
        'latitude' => (float) $location['lat'],
        'longitude' => (float) $location['lng'],
        'provider' => 'google',
      ];
    } catch (\Throwable $exception) {
      Log::warning('Google place details failed', ['message' => $exception->getMessage()]);

      return null;
    }
  }

  /**
   * @return array{address: string, latitude: float, longitude: float, provider: string}|null
   */
  protected function resolveOsmPlace(string $id): ?array
  {
    [$type, $osmId] = array_pad(explode(':', $id, 2), 2, null);
    if (! $type || ! $osmId) {
      return null;
    }

    try {
      $response = Http::withHeaders([
        'User-Agent' => config('app.name', 'NASO').'/1.0',
      ])->timeout(8)->get('https://nominatim.openstreetmap.org/lookup', [
        'osm_ids' => strtoupper(substr($type, 0, 1)).':'.$osmId,
        'format' => 'json',
      ]);

      if (! $response->ok()) {
        return null;
      }

      $item = $response->json('0');
      if (! is_array($item) || ! isset($item['lat'], $item['lon'], $item['display_name'])) {
        return null;
      }

      return [
        'address' => (string) $item['display_name'],
        'latitude' => (float) $item['lat'],
        'longitude' => (float) $item['lon'],
        'provider' => 'osm',
      ];
    } catch (\Throwable $exception) {
      Log::warning('Nominatim place lookup failed', ['message' => $exception->getMessage()]);

      return null;
    }
  }

  /**
   * @param  array<string, mixed>  $item
   */
  protected function nominatimLabel(array $item): string
  {
    $address = $item['address'] ?? [];
    if (! is_array($address)) {
      return (string) ($item['display_name'] ?? 'Location');
    }

    $parts = array_filter([
      $address['road'] ?? null,
      $address['suburb'] ?? $address['neighbourhood'] ?? null,
      $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
    ]);

    if ($parts !== []) {
      return implode(', ', $parts);
    }

    return (string) ($item['display_name'] ?? 'Location');
  }
}
