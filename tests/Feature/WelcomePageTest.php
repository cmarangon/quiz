<?php

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use App\Models\User;

test('welcome page returns successful response', function () {
    $response = $this->get(route('home'));
    $response->assertOk();
});

test('welcome page shows admin login link for guests', function () {
    $this->get(route('home'))
        ->assertSee('Admin Login')
        ->assertDontSee('Dashboard');
});

test('welcome page shows dashboard link for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertSee('Dashboard')
        ->assertDontSee('Admin Login');
});

test('welcome page shows active game sessions', function () {
    $quiz = Quiz::factory()->create(['title' => 'Fun Trivia Night']);
    $session = GameSession::factory()->for($quiz)->create(['status' => 'waiting']);
    Player::factory()->for($session, 'gameSession')->count(3)->create();

    $this->get(route('home'))
        ->assertSee('Fun Trivia Night')
        ->assertSee('3 players');
});

test('welcome page does not show finished game sessions', function () {
    $quiz = Quiz::factory()->create(['title' => 'Old Game']);
    GameSession::factory()->for($quiz)->create(['status' => 'finished']);

    $this->get(route('home'))
        ->assertDontSee('Old Game');
});

test('welcome page shows empty state when no active games', function () {
    $this->get(route('home'))
        ->assertSee('No live games right now');
});

test('welcome page shows watch link for active games', function () {
    $session = GameSession::factory()->create(['status' => 'waiting', 'join_code' => 'ABC123']);

    $this->get(route('home'))
        ->assertSee(route('game.spectator', 'ABC123'));
});

test('welcome page does not show stale open game sessions', function () {
    $quiz = Quiz::factory()->create(['title' => 'Abandoned Lobby']);
    $session = GameSession::factory()->for($quiz)->create(['status' => 'waiting']);
    GameSession::query()->whereKey($session)
        ->update(['updated_at' => now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES + 1)]);

    $this->get(route('home'))
        ->assertDontSee('Abandoned Lobby');
});

test('welcome page still shows recently updated open games', function () {
    $quiz = Quiz::factory()->create(['title' => 'Live Right Now']);
    GameSession::factory()->for($quiz)->create(['status' => 'playing']);

    $this->get(route('home'))
        ->assertSee('Live Right Now');
});
