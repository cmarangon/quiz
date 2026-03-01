<?php

use App\Models\GameSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.{sessionId}.host', function ($user, int $sessionId) {
    $session = GameSession::find($sessionId);

    return $session && $session->host_user_id === $user->id;
});
