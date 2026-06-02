<?php

use App\Models\Question;
use App\QuestionTypes\OrderingType;

function orderingQuestion(array $overrides = []): Question
{
    return Question::factory()->make(array_merge([
        'category_id' => 1,
        'type' => 'ordering',
        'points' => 100,
        'time_limit_seconds' => 30,
        'options' => [
            ['label' => 'Step B'],
            ['label' => 'Step D'],
            ['label' => 'Step A'],
            ['label' => 'Step C'],
        ],
        'correct_answer' => ['Step A', 'Step B', 'Step C', 'Step D'],
    ], $overrides));
}

test('validates an exactly matching order as correct', function () {
    $type = new OrderingType;
    $question = orderingQuestion();

    expect($type->validateAnswer(['Step A', 'Step B', 'Step C', 'Step D'], $question))->toBeTrue();
});

test('rejects a wrong order', function () {
    $type = new OrderingType;
    $question = orderingQuestion();

    expect($type->validateAnswer(['Step B', 'Step A', 'Step C', 'Step D'], $question))->toBeFalse();
});

test('rejects a partially correct order', function () {
    $type = new OrderingType;
    $question = orderingQuestion();

    // First two correct, last two swapped.
    expect($type->validateAnswer(['Step A', 'Step B', 'Step D', 'Step C'], $question))->toBeFalse();
});

test('rejects an answer of the wrong length', function () {
    $type = new OrderingType;
    $question = orderingQuestion();

    expect($type->validateAnswer(['Step A', 'Step B', 'Step C'], $question))->toBeFalse();
});

test('rejects non-array and empty answers', function () {
    $type = new OrderingType;
    $question = orderingQuestion();

    expect($type->validateAnswer('Step A', $question))->toBeFalse();
    expect($type->validateAnswer(null, $question))->toBeFalse();
    expect($type->validateAnswer([], $question))->toBeFalse();
});

test('ignores surrounding whitespace when comparing', function () {
    $type = new OrderingType;
    $question = orderingQuestion();

    expect($type->validateAnswer([' Step A ', 'Step B', 'Step C', 'Step D'], $question))->toBeTrue();
});

test('validates options require at least two items with unique labels', function () {
    $type = new OrderingType;

    $valid = [['label' => 'A'], ['label' => 'B'], ['label' => 'C']];
    expect($type->validateOptions($valid))->toBeTrue();
});

test('rejects options with fewer than two items', function () {
    $type = new OrderingType;

    expect($type->validateOptions([['label' => 'A']]))->toBeFalse();
});

test('rejects options with a missing or empty label', function () {
    $type = new OrderingType;

    expect($type->validateOptions([['label' => 'A'], ['text' => 'B']]))->toBeFalse();
    expect($type->validateOptions([['label' => 'A'], ['label' => '  ']]))->toBeFalse();
});

test('rejects options with duplicate labels', function () {
    $type = new OrderingType;

    expect($type->validateOptions([['label' => 'A'], ['label' => 'A']]))->toBeFalse();
});

test('calculatePoints awards full base when time bonus disabled', function () {
    $type = new OrderingType;
    $question = orderingQuestion();

    expect($type->calculatePoints($question, 5000, ['enable_time_bonus' => false]))->toBe(100);
});

test('calculatePoints scales with remaining time', function () {
    $type = new OrderingType;
    $question = orderingQuestion(['time_limit_seconds' => 10]);

    // Half the time used → half the points.
    expect($type->calculatePoints($question, 5000, ['enable_time_bonus' => true]))->toBe(50);
});

test('returns correct livewire component names', function () {
    $type = new OrderingType;

    expect($type->renderSpectatorComponent())->toBe('question-types.ordering-spectator');
    expect($type->renderPlayerComponent())->toBe('question-types.ordering-player');
});
