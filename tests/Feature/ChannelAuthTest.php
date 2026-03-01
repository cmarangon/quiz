<?php

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

test('host channel authorizes the quiz host', function () {
    $user = User::factory()->create();
    $session = GameSession::factory()->for($user, 'host')->create();

    $channels = Broadcast::getChannels();
    $callback = $channels['game.{sessionId}.host'];

    $result = $callback($user, $session->id);

    expect($result)->toBeTrue();
});

test('host channel rejects non-host user', function () {
    $other = User::factory()->create();
    $session = GameSession::factory()->create();

    $channels = Broadcast::getChannels();
    $callback = $channels['game.{sessionId}.host'];

    $result = $callback($other, $session->id);

    expect($result)->toBeFalse();
});
