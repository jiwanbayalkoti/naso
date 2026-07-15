<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rider = App\Models\Rider::with('user')->first();
if (! $rider) {
    echo "NO_RIDER\n";
    exit(1);
}

$userId = $rider->user_id;
$svc = app(App\Services\RiderService::class);

// Prefer Online (as if toggled on)
$rider->forceFill([
    'is_online' => true,
    'is_available' => true,
    'last_seen_at' => now(),
])->save();

echo '1_present='.($rider->fresh()->isPresentlyOnline() ? 'yes' : 'no').PHP_EOL;

// Logout-style: clear presence, keep preference
$svc->clearPresenceByUserId($userId);
$afterLogout = $rider->fresh();
echo '2_after_logout prefer='.($afterLogout->is_online ? 'on' : 'off')
    .' present='.($afterLogout->isPresentlyOnline() ? 'yes' : 'no').PHP_EOL;

// Login-style restore
$svc->restorePresenceOnLogin($userId);
$afterLogin = $rider->fresh();
echo '3_after_login prefer='.($afterLogin->is_online ? 'on' : 'off')
    .' present='.($afterLogin->isPresentlyOnline() ? 'yes' : 'no').PHP_EOL;

// Manual offline
$rider->markOffline();
$svc->restorePresenceOnLogin($userId);
$manual = $rider->fresh();
echo '4_manual_off_then_login prefer='.($manual->is_online ? 'on' : 'off')
    .' present='.($manual->isPresentlyOnline() ? 'yes' : 'no').PHP_EOL;
