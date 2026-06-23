<?php

use App\Livewire\QuizBuilder;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('creating a quiz persists the default question duration', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'My Quiz')
        ->set('defaultQuestionDuration', 45)
        ->call('save')
        ->assertHasNoErrors();

    $quiz = Quiz::where('title', 'My Quiz')->firstOrFail();
    expect($quiz->settings['default_question_duration_seconds'])->toBe(45);
});

test('editing a quiz loads its existing default question duration', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create([
        'settings' => ['default_question_duration_seconds' => 25],
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->assertSet('defaultQuestionDuration', 25);
});

test('default question duration must be at least 5 seconds', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'My Quiz')
        ->set('defaultQuestionDuration', 2)
        ->call('save')
        ->assertHasErrors(['defaultQuestionDuration']);
});
