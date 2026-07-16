<?php

namespace Tests\Unit;

use App\Services\AppSettingService;
use App\Services\DeliveryFeeCalculatorService;
use App\Services\OfferEngine;
use Mockery;
use Tests\TestCase;

class DeliveryFeeCalculatorServiceTest extends TestCase
{
    protected DeliveryFeeCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = Mockery::mock(AppSettingService::class);
        $settings->shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            return match ($key) {
                'delivery_base_fee' => 50,
                'delivery_fee_per_km' => 25,
                'delivery_min_fee' => 50,
                'platform_commission_percent' => 20,
                default => $default,
            };
        });
        $settings->shouldReceive('deliveryPricing')->andReturn([
            'mode' => 'zone_slabs',
            'valley' => ['lat' => 27.7172, 'lng' => 85.3240, 'radius_km' => 18],
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
        ]);

        $this->calculator = new DeliveryFeeCalculatorService(
            $settings,
            Mockery::mock(OfferEngine::class)
        );
    }

    public function test_classifies_inside_and_outside_valley(): void
    {
        $valley = ['lat' => 27.7172, 'lng' => 85.3240, 'radius_km' => 18];

        $this->assertSame('inside_valley', $this->calculator->classifyZone(27.7172, 85.3240, $valley));
        // Pokhara — clearly outside Kathmandu Valley radius
        $this->assertSame('outside_valley', $this->calculator->classifyZone(28.2096, 83.9856, $valley));
        $this->assertSame('unknown', $this->calculator->classifyZone(null, null, $valley));
    }

    public function test_inside_valley_slab_fees(): void
    {
        $rates = $this->calculator->rates();
        $pricing = app(AppSettingService::class)->defaultDeliveryPricing();

        $near = $this->calculator->resolveBaseFee(3, 27.7172, 85.3240, $rates, $pricing);
        $this->assertSame('inside_valley', $near['pricing_zone']);
        $this->assertEquals(50.0, $near['base_fee']);

        $mid = $this->calculator->resolveBaseFee(8, 27.7172, 85.3240, $rates, $pricing);
        $this->assertEquals(100.0, $mid['base_fee']);
        $this->assertSame('5–10 km', $mid['pricing_slab']['label']);

        $far = $this->calculator->resolveBaseFee(25, 27.7172, 85.3240, $rates, $pricing);
        $this->assertEquals(200.0, $far['base_fee']);
    }

    public function test_outside_valley_categories(): void
    {
        $rates = $this->calculator->rates();
        $pricing = app(AppSettingService::class)->defaultDeliveryPricing();
        $dropLat = 28.2096;
        $dropLng = 83.9856;

        $short = $this->calculator->resolveBaseFee(20, $dropLat, $dropLng, $rates, $pricing);
        $this->assertSame('outside_valley', $short['pricing_zone']);
        $this->assertEquals(300.0, $short['base_fee']);
        $this->assertSame('short', $short['pricing_slab']['label']);

        $medium = $this->calculator->resolveBaseFee(50, $dropLat, $dropLng, $rates, $pricing);
        $this->assertEquals(500.0, $medium['base_fee']);
        $this->assertSame('medium', $medium['pricing_slab']['label']);

        $long = $this->calculator->resolveBaseFee(120, $dropLat, $dropLng, $rates, $pricing);
        $this->assertEquals(800.0, $long['base_fee']);
        $this->assertSame('long', $long['pricing_slab']['label']);
    }

    public function test_minimum_fee_floor_applies(): void
    {
        $rates = $this->calculator->rates();
        $pricing = app(AppSettingService::class)->defaultDeliveryPricing();
        $pricing['inside_valley'] = [
            ['from_km' => 0, 'to_km' => null, 'fee' => 20],
        ];

        $resolved = $this->calculator->resolveBaseFee(2, 27.7172, 85.3240, $rates, $pricing);
        $this->assertEquals(50.0, $resolved['base_fee']);
    }
}
