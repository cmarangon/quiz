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

test('saving a new question persists its comment', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();

    Livewire::actingAs($user)
        ->test(\App\Livewire\QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is 2+2?')
        ->set('questionType', 'true_false')
        ->set('questionCorrectAnswer', 'True')
        ->set('questionComment', 'Source: basic arithmetic')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->firstOrFail();
    expect($question->comment)->toBe('Source: basic arithmetic');
});

test('saving a question with blank comment stores null', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();

    Livewire::actingAs($user)
        ->test(\App\Livewire\QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is 2+2?')
        ->set('questionType', 'true_false')
        ->set('questionCorrectAnswer', 'True')
        ->set('questionComment', '')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->firstOrFail();
    expect($question->comment)->toBeNull();
});

test('editing a question loads its existing comment', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();
    $question = \App\Models\Question::factory()->for($category)->create([
        'type' => 'true_false',
        'body' => 'Is the sky blue?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Remember to mention clouds',
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionComment', 'Remember to mention clouds');
});

test('updating a question overwrites its comment', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();
    $question = \App\Models\Question::factory()->for($category)->create([
        'type' => 'true_false',
        'body' => 'Is the sky blue?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Old note',
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('questionComment', 'New note')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    expect($question->fresh()->comment)->toBe('New note');
});
