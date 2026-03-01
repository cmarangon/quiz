<?php

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows users quizzes', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create(['title' => 'My Trivia']);

    $this->actingAs($user)->get('/dashboard')->assertSee('My Trivia');
});

test('dashboard shows recent game sessions', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'finished']);
    Player::factory()->for($session, 'gameSession')->count(5)->create();

    $this->actingAs($user)->get('/dashboard')->assertSee('5 players');
});

test('dashboard shows player stats when user has played games', function () {
    $user = User::factory()->create();
    $session = GameSession::factory()->create(['status' => 'finished']);
    Player::factory()->for($session, 'gameSession')->create([
        'user_id' => $user->id,
        'score' => 150,
    ]);

    $this->actingAs($user)->get('/dashboard')->assertSee('150');
});
