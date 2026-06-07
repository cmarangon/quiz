<?php

use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Support\Carbon;

test('a heartbeat refreshes last_seen_at and marks the player connected', function () {
    Carbon::setTestNow('2026-06-07 12:00:00');

    $session = GameSession::factory()->create();
    $player = Player::factory()->create([
        'game_session_id' => $session->id,
        'is_connected' => false,
        'last_seen_at' => now()->subMinute(),
    ]);

    Carbon::setTestNow('2026-06-07 12:05:00');

    $this->post(route('game.heartbeat', ['code' => $session->join_code, 'player' => $player->id]))
        ->assertNoContent();

    $player->refresh();
    expect($player->is_connected)->toBeTrue();
    expect($player->last_seen_at->toDateTimeString())->toBe('2026-06-07 12:05:00');
});

test('a heartbeat for a player from another session is rejected', function () {
    $session = GameSession::factory()->create();
    $other = GameSession::factory()->create();
    $player = Player::factory()->create(['game_session_id' => $other->id]);

    $this->post(route('game.heartbeat', ['code' => $session->join_code, 'player' => $player->id]))
        ->assertNotFound();

    expect($player->refresh()->game_session_id)->toBe($other->id);
});

test('a heartbeat for an unknown player is rejected', function () {
    $session = GameSession::factory()->create();

    $this->post(route('game.heartbeat', ['code' => $session->join_code, 'player' => 999999]))
        ->assertNotFound();
});
