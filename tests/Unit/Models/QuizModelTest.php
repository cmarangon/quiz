<?php

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;

test('quiz belongs to a user', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);

    expect($quiz->user)->toBeInstanceOf(User::class);
    expect($quiz->user->id)->toBe($user->id);
});

test('quiz has many categories', function () {
    $quiz = Quiz::factory()->create();
    $categories = Category::factory()->count(3)->create(['quiz_id' => $quiz->id]);

    expect($quiz->categories)->toHaveCount(3);
});

test('quiz settings default to time bonus and streaks enabled', function () {
    $quiz = Quiz::factory()->create();

    expect($quiz->settings)->toBe([
        'enable_time_bonus' => true,
        'enable_streaks' => true,
    ]);
});

test('category has many questions', function () {
    $category = Category::factory()->create();
    Question::factory()->count(5)->create(['category_id' => $category->id]);

    expect($category->questions)->toHaveCount(5);
});

test('questions are ordered by order column', function () {
    $category = Category::factory()->create();
    Question::factory()->create(['category_id' => $category->id, 'order' => 3]);
    Question::factory()->create(['category_id' => $category->id, 'order' => 1]);
    Question::factory()->create(['category_id' => $category->id, 'order' => 2]);

    $questions = $category->questions;

    expect($questions->pluck('order')->all())->toBe([1, 2, 3]);
});
