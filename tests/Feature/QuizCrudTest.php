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

    $questionData = [
        'type' => 'multiple_choice',
        'body' => 'What is 2+2?',
        'options' => [
            ['label' => '3'],
            ['label' => '4'],
            ['label' => '5'],
            ['label' => '6'],
        ],
        'correct_answer' => '4',
        'points' => 10,
        'time_limit_seconds' => 30,
    ];

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('addQuestion', $category->id, $questionData)
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
