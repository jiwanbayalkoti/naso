<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('deliveries', function ($user) {
    return $user !== null;
});

Broadcast::channel('delivery.{uuid}', function ($user, string $uuid) {
    return $user !== null;
});
