<?php

use App\Models\Question;
use App\QuestionTypes\MultipleChoiceType;

test('validates correct answer', function () {
    $type = new MultipleChoiceType;
    $question = Question::factory()->make(['category_id' => 1, 'correct_answer' => 'Option B']);
    expect($type->validateAnswer('Option B', $question))->toBeTrue();
    expect($type->validateAnswer('Option A', $question))->toBeFalse();
});

test('validates options require exactly 4 with labels', function () {
    $type = new MultipleChoiceType;
    $valid = [['label' => 'A'], ['label' => 'B'], ['label' => 'C'], ['label' => 'D']];
    expect($type->validateOptions($valid))->toBeTrue();
    $tooFew = [['label' => 'A'], ['label' => 'B']];
    expect($type->validateOptions($tooFew))->toBeFalse();
    $noLabel = [['text' => 'A'], ['label' => 'B'], ['label' => 'C'], ['label' => 'D']];
    expect($type->validateOptions($noLabel))->toBeFalse();
});

test('returns correct livewire component names', function () {
    $type = new MultipleChoiceType;
    expect($type->renderSpectatorComponent())->toBe('question-types.multiple-choice-spectator');
    expect($type->renderPlayerComponent())->toBe('question-types.multiple-choice-player');
});
