<?php

use App\Livewire\Dashboard;
use App\Models\Category;
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
