<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Reverb channel authorization (DOC-08)
|--------------------------------------------------------------------------
|
| Domain channels (alerts, tracking, gas, …) are authorized for any
| authenticated user; payload identity stripping is per-viewer.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('alerts', fn ($user) => $user !== null);
Broadcast::channel('ppe', fn ($user) => $user !== null);
Broadcast::channel('tracking', fn ($user) => $user !== null);
Broadcast::channel('gas', fn ($user) => $user !== null);
Broadcast::channel('environment', fn ($user) => $user !== null);
Broadcast::channel('system', fn ($user) => $user !== null);
