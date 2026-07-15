<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rider presence window (minutes)
    |--------------------------------------------------------------------------
    |
    | A rider counts as online only when:
    | - is_online is true (they toggled online after login)
    | - last_seen_at is within this many minutes (app is open / heartbeat)
    | - their user account is active
    |
    */
    'rider_presence_minutes' => (int) env('RIDER_PRESENCE_MINUTES', 5),
];
