<?php

use App\Models\Question;
use App\QuestionTypes\TrueFalseType;

test('validates correct answer as boolean', function () {
    $type = new TrueFalseType();
    $question = Question::factory()->make(['category_id' => 1, 'correct_answer' => true]);
    expect($type->validateAnswer(true, $question))->toBeTrue();
    expect($type->validateAnswer(false, $question))->toBeFalse();
});

test('validates options require exactly 2', function () {
    $type = new TrueFalseType();
    expect($type->validateOptions([['label' => 'True'], ['label' => 'False']]))->toBeTrue();
    expect($type->validateOptions([['label' => 'A']]))->toBeFalse();
});

test('returns correct livewire component names', function () {
    $type = new TrueFalseType();
    expect($type->renderSpectatorComponent())->toBe('question-types.true-false-spectator');
    expect($type->renderPlayerComponent())->toBe('question-types.true-false-player');
});
