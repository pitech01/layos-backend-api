<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('course-channel.{id}', function ($user, $id) {
    // Basic auth check for now
    return true;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
