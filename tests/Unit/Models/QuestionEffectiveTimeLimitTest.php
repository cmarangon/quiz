<?php

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;

test('effective time limit uses the explicit value when set', function () {
    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->create(['time_limit_seconds' => 45]);

    expect($question->effectiveTimeLimitSeconds())->toBe(45);
});

test('effective time limit falls back to the quiz default when unset', function () {
    $quiz = Quiz::factory()->create([
        'settings' => ['enable_time_bonus' => true, 'default_question_duration_seconds' => 20],
    ]);
    $category = Category::factory()->for($quiz)->create();
    $question = Question::factory()->for($category)->create(['time_limit_seconds' => null]);

    expect($question->effectiveTimeLimitSeconds())->toBe(20);
});

test('effective time limit falls back to 30 when unset and the quiz has no default', function () {
    $quiz = Quiz::factory()->create(['settings' => ['enable_time_bonus' => true]]);
    $category = Category::factory()->for($quiz)->create();
    $question = Question::factory()->for($category)->create(['time_limit_seconds' => null]);

    expect($question->effectiveTimeLimitSeconds())->toBe(30);
});
