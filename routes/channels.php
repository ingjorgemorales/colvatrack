<?php

use App\Models\Chat;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('notifications.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    $chat = Chat::with('request')->find($chatId);

    if (! $chat) {
        return false;
    }

    return $user->hasRole('Administrador')
        || (int) $chat->technician_id === (int) $user->id
        || (int) $chat->driver_id === (int) $user->id
        || (int) $chat->request?->technician_id === (int) $user->id
        || (int) $chat->request?->driver_id === (int) $user->id;
});
