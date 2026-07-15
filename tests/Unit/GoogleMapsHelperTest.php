<?php

namespace Tests\Unit;

use App\Helpers\GoogleMapsHelper;
use Tests\TestCase;

class GoogleMapsHelperTest extends TestCase
{
    public function test_it_decodes_google_polyline(): void
    {
        $points = GoogleMapsHelper::decodePolyline('_p~iF~ps|U_ulLnnqC_mqNvxq`@');

        $this->assertNotEmpty($points);
        $this->assertEqualsWithDelta(38.5, $points[0]['lat'], 0.001);
        $this->assertEqualsWithDelta(-120.2, $points[0]['lng'], 0.001);
    }

    public function test_it_builds_osrm_or_direct_route_between_two_points(): void
    {
        $route = GoogleMapsHelper::drivingRoute(27.7172, 85.3240, 28.2096, 83.9856);

        $this->assertNotEmpty($route['points']);
        $this->assertGreaterThanOrEqual(2, count($route['points']));
        $this->assertContains($route['provider'], ['google', 'osrm', 'direct']);
    }
}
