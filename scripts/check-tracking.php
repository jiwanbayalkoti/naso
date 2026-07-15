<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = config('services.google_maps.api_key');
echo "API Key: ".($key ? substr($key, 0, 10).'...' : 'MISSING').PHP_EOL;

$delivery = App\Models\Delivery::with(['shop', 'rider'])->first();
if (! $delivery) {
    echo "No deliveries in DB\n";
    exit(0);
}

$tracking = app(App\Services\TrackingService::class)->buildDeliveryTracking($delivery);
echo json_encode($tracking, JSON_PRETTY_PRINT).PHP_EOL;
