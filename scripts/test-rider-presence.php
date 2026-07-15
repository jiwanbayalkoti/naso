<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'flag_online_count='.App\Models\Rider::where('is_online', true)->count().PHP_EOL;
echo 'present_online_count='.App\Models\Rider::presentOnline()->count().PHP_EOL;

$r = App\Models\Rider::with('user')->first();
if (! $r) {
    echo "NO_RIDER\n";
    exit(1);
}

echo 'rider='.$r->user?->email.PHP_EOL;

$r->forceFill([
    'is_online' => true,
    'is_available' => true,
    'last_seen_at' => now(),
])->save();
echo 'fresh_present='.($r->fresh()->isPresentlyOnline() ? 'yes' : 'no').PHP_EOL;

$r->forceFill(['last_seen_at' => now()->subMinutes(10)])->save();
echo 'stale_present='.($r->fresh()->isPresentlyOnline() ? 'yes' : 'no').PHP_EOL;

$r->markOffline();
$fresh = $r->fresh();
echo 'offline_present='.($fresh->isPresentlyOnline() ? 'yes' : 'no').' flag='.($fresh->is_online ? '1' : '0').PHP_EOL;

$cleaned = App\Models\Rider::query()
    ->where('is_online', true)
    ->where(function ($q) {
        $q->whereNull('last_seen_at')
            ->orWhere('last_seen_at', '<', now()->subMinutes((int) config('naso.rider_presence_minutes', 5)));
    })
    ->update([
        'is_online' => false,
        'is_available' => false,
        'last_seen_at' => null,
    ]);

echo 'cleaned_stale='.$cleaned.PHP_EOL;
echo 'present_now='.App\Models\Rider::presentOnline()->count().PHP_EOL;
