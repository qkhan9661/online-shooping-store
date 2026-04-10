<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (Phase 3 — WebSocket / Reverb)
|--------------------------------------------------------------------------
|
| Install Laravel Reverb and set BROADCAST_CONNECTION=reverb when ready.
| See docs/WEBSOCKETS_AND_STREAMING.md
|
*/

Broadcast::channel('user.{userId}', function ($user, string $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('user.{userId}.pose', function ($user, string $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('user.{userId}.cloth', function ($user, string $userId) {
    return (int) $user->id === (int) $userId;
});
