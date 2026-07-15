<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodeController extends Controller
{
    public function __construct(
        protected GeocodingService $geocodingService
    ) {}

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $this->geocodingService->reverse(
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        if (! $result) {
            return $this->error('Unable to resolve address for this location.', 422);
        }

        return $this->success($result);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        return $this->success(
            $this->geocodingService->search($validated['q'])
        );
    }

    public function place(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:google,osm'],
            'id' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->geocodingService->resolve(
            $validated['provider'],
            $validated['id']
        );

        if (! $result) {
            return $this->error('Unable to resolve the selected place.', 422);
        }

        return $this->success($result);
    }
}
