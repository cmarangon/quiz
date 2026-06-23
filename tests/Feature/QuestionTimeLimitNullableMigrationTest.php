<?php

use App\Models\Category;
use App\Models\Question;

test('time_limit_seconds column accepts null', function () {
    $category = Category::factory()->create();

    $question = Question::factory()->for($category)->create(['time_limit_seconds' => null]);

    expect($question->fresh()->time_limit_seconds)->toBeNull();
});
