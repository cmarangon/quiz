<?php

use App\Models\Player;

test('a player seen within the threshold is online', function () {
    $player = Player::factory()->make(['last_seen_at' => now()]);

    expect($player->isOnline())->toBeTrue();
});

test('a player not seen past the threshold is offline', function () {
    $player = Player::factory()->make([
        'last_seen_at' => now()->subSeconds(Player::PRESENCE_THRESHOLD_SECONDS + 1),
    ]);

    expect($player->isOnline())->toBeFalse();
});

test('a player that never sent a heartbeat is offline', function () {
    $player = Player::factory()->make(['last_seen_at' => null]);

    expect($player->isOnline())->toBeFalse();
});
