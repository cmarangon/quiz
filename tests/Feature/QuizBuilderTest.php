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

test('leaving the question time field blank saves it as inherited (null)', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is the capital of France?')
        ->set('questionType', 'true_false')
        ->set('questionCorrectAnswer', 'True')
        ->set('questionTimeLimit', '')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->firstOrFail();
    expect($question->time_limit_seconds)->toBeNull();
});

test('setting an explicit question time overrides the quiz default', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is the capital of France?')
        ->set('questionType', 'true_false')
        ->set('questionCorrectAnswer', 'True')
        ->set('questionTimeLimit', '15')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->firstOrFail();
    expect($question->time_limit_seconds)->toBe(15);
});

test('editing an inherited question loads a blank time field', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();
    $question = \App\Models\Question::factory()->for($category)->create([
        'type' => 'true_false',
        'options' => [],
        'correct_answer' => 'True',
        'time_limit_seconds' => null,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionTimeLimit', '');
});

test('a question time below 5 seconds fails validation', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is the capital of France?')
        ->set('questionType', 'true_false')
        ->set('questionCorrectAnswer', 'True')
        ->set('questionTimeLimit', '2')
        ->call('saveQuestion')
        ->assertHasErrors(['questionTimeLimit']);
});
