<?php

use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('user can delete own quiz from dashboard', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create();
    $question = Question::factory()->for($category)->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmDeleteQuiz', $quiz->id)
        ->assertSet('pendingAction', 'delete-quiz')
        ->assertSet('pendingId', $quiz->id)
        ->call('deleteQuiz');

    expect(Quiz::find($quiz->id))->toBeNull();
    expect(Category::find($category->id))->toBeNull();
    expect(Question::find($question->id))->toBeNull();
});

test('user cannot delete another users quiz', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherQuiz = Quiz::factory()->for($other)->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmDeleteQuiz', $otherQuiz->id)
        ->call('deleteQuiz')
        ->assertStatus(403);

    expect(Quiz::find($otherQuiz->id))->not->toBeNull();
});

test('host can end a waiting session', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->assertSet('pendingAction', 'end-session')
        ->assertSet('pendingId', $session->id)
        ->call('endSession');

    expect($session->fresh()->status)->toBe('finished');
});

test('host can end a playing session', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'playing']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->call('endSession');

    expect($session->fresh()->status)->toBe('finished');
});

test('host can end a reviewing session', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'reviewing']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->call('endSession');

    expect($session->fresh()->status)->toBe('finished');
});

test('ending a session preserves players and answers', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create();
    $question = Question::factory()->for($category)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'playing']);
    $player = Player::factory()->for($session, 'gameSession')->create();
    $answer = PlayerAnswer::factory()
        ->for($player)
        ->for($session, 'gameSession')
        ->for($question)
        ->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->call('endSession');

    expect(Player::find($player->id))->not->toBeNull();
    expect(PlayerAnswer::find($answer->id))->not->toBeNull();
});

test('non-host cannot end a session', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();
    $session = GameSession::factory()->for($quiz)->for($owner, 'host')->create(['status' => 'waiting']);

    Livewire::actingAs($other)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->call('endSession')
        ->assertStatus(403);

    expect($session->fresh()->status)->toBe('waiting');
});
