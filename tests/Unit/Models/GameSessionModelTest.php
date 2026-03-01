<?php

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use App\Models\User;

test('game session belongs to quiz and host', function () {
    $quiz = Quiz::factory()->create();
    $host = User::factory()->create();
    $session = GameSession::factory()->create([
        'quiz_id' => $quiz->id,
        'host_user_id' => $host->id,
    ]);

    expect($session->quiz)->toBeInstanceOf(Quiz::class);
    expect($session->quiz->id)->toBe($quiz->id);
    expect($session->host)->toBeInstanceOf(User::class);
    expect($session->host->id)->toBe($host->id);
});

test('game session has many players', function () {
    $session = GameSession::factory()->create();
    Player::factory()->count(4)->create(['game_session_id' => $session->id]);

    expect($session->players)->toHaveCount(4);
});

test('game session generates a 6 char join code on creation', function () {
    $session = GameSession::factory()->create();

    expect($session->join_code)->toHaveLength(6);
    expect($session->join_code)->toMatch('/^[A-Z0-9]{6}$/');
});

test('game session status defaults to waiting', function () {
    $session = GameSession::factory()->create();

    expect($session->status)->toBe('waiting');
});
