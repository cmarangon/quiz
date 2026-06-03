<?php

use App\Livewire\QuizBuilder;
use App\Models\GameSession;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('game session defaults to party-pop presentation style', function () {
    $quiz = Quiz::factory()->create(['settings' => []]);
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id]);

    expect($session->presentationStyle())->toBe('party-pop');
});

test('game session reads presentation style from quiz settings', function () {
    $quiz = Quiz::factory()->create(['settings' => ['presentation_style' => 'game-show']]);
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id]);

    expect($session->presentationStyle())->toBe('game-show');
});

test('quiz builder saves the chosen presentation style', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'My Quiz')
        ->set('presentationStyle', 'bright-bouncy')
        ->call('save');

    expect(Quiz::where('title', 'My Quiz')->first()->settings['presentation_style'])
        ->toBe('bright-bouncy');
});

test('quiz builder loads existing presentation style on mount', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id, 'settings' => ['presentation_style' => 'game-show']]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->assertSet('presentationStyle', 'game-show');
});

test('quiz builder rejects an invalid presentation style', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'My Quiz')
        ->set('presentationStyle', 'totally-bogus')
        ->call('save')
        ->assertHasErrors(['presentationStyle']);

    expect(Quiz::where('title', 'My Quiz')->exists())->toBeFalse();
});
