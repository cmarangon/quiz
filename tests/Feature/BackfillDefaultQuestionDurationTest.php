<?php

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;

test('backfill stamps a default duration on every quiz and nulls every question time limit', function () {
    $quizWithoutDefault = Quiz::factory()->create(['settings' => ['enable_time_bonus' => true]]);
    $categoryA = Category::factory()->for($quizWithoutDefault)->create();
    Question::factory()->for($categoryA)->create(['time_limit_seconds' => 45]);

    $quizWithDefault = Quiz::factory()->create([
        'settings' => ['enable_time_bonus' => true, 'default_question_duration_seconds' => 20],
    ]);
    $categoryB = Category::factory()->for($quizWithDefault)->create();
    Question::factory()->for($categoryB)->create(['time_limit_seconds' => 20]);

    (require database_path('migrations/2026_06_23_140001_backfill_default_question_duration.php'))->up();

    expect($quizWithoutDefault->fresh()->settings['default_question_duration_seconds'])->toBe(30);
    expect($quizWithDefault->fresh()->settings['default_question_duration_seconds'])->toBe(20);

    expect(Question::pluck('time_limit_seconds')->unique()->all())->toBe([null]);
});
