<?php

use App\Models\Category;
use App\Models\Question;

test('comment column accepts null', function () {
    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->create(['comment' => null]);

    expect($question->fresh()->comment)->toBeNull();
});

test('comment column persists a string value', function () {
    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->create(['comment' => 'Check source: Wikipedia']);

    expect($question->fresh()->comment)->toBe('Check source: Wikipedia');
});
