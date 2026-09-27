<?php

use Illuminate\Support\Facades\Broadcast;



Broadcast::channel('chat.{receiver_id}', function ($user, $receiver_id) {

    logger()->info('Broadcast channel authorization', [
        'user_id' => $user->id,
        'receiver_id' => $receiver_id,
        'match' => $user->id === $receiver_id,
    ]);

    return $user->id === $receiver_id;
});