<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function maps(): JsonResponse
    {
        return $this->success([
            'google_maps_api_key' => config('services.google_maps.api_key'),
        ]);
    }
}
