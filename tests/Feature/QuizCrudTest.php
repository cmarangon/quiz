<?php

use App\Livewire\QuizBuilder;
use App\Livewire\QuizIndex;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('guest cannot access quiz index', function () {
    $this->get('/quizzes')->assertRedirect('/login');
});

test('authenticated user sees their quizzes', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id, 'title' => 'My Awesome Quiz']);

    Livewire::actingAs($user)
        ->test(QuizIndex::class)
        ->assertSee('My Awesome Quiz');
});

test('user can create a quiz', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'New Quiz Title')
        ->set('description', 'A quiz description')
        ->call('save')
        ->assertHasNoErrors();

    expect(Quiz::where('title', 'New Quiz Title')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('quiz title is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

test('user can add a category to a quiz', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->set('newCategoryName', 'Science')
        ->set('newCategoryTheme', 'science')
        ->call('addCategory')
        ->assertHasNoErrors();

    expect($quiz->categories()->where('name', 'Science')->exists())->toBeTrue();
});

test('user can add a question to a category', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is 2+2?')
        ->set('questionType', 'multiple_choice')
        ->set('questionOptions', ['3', '4', '5', '6'])
        ->set('questionCorrectAnswer', '4')
        ->set('questionPoints', 10)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    expect($category->questions()->where('body', 'What is 2+2?')->exists())->toBeTrue();
});

test('user cannot edit another users quiz', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($otherUser)
        ->get("/quizzes/{$quiz->id}/edit")
        ->assertStatus(403);
});
