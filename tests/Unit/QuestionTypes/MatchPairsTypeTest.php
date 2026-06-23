<?php

use App\Models\Question;
use App\QuestionTypes\MatchPairsType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function matchPairsQuestion(array $overrides = []): Question
{
    return Question::factory()->make(array_merge([
        'category_id' => 1,
        'type' => 'match_pairs',
        'points' => 100,
        'time_limit_seconds' => 30,
        'options' => [
            'left' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'Japan'],
                ['kind' => 'text', 'value' => 'Egypt'],
                ['kind' => 'text', 'value' => 'Brazil'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'Cairo'],
                ['kind' => 'text', 'value' => 'Paris'],
                ['kind' => 'text', 'value' => 'Brasilia'],
                ['kind' => 'text', 'value' => 'Tokyo'],
            ],
        ],
        // left[0] France -> right[1] Paris, left[1] Japan -> right[3] Tokyo,
        // left[2] Egypt -> right[0] Cairo, left[3] Brazil -> right[2] Brasilia.
        'correct_answer' => [1, 3, 0, 2],
    ], $overrides));
}

test('validates an exact match as correct', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer([1, 3, 0, 2], $question))->toBeTrue();
});

test('rejects a swapped pair', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer([1, 3, 2, 0], $question))->toBeFalse();
});

test('rejects a partially correct set of pairs (all-or-nothing)', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    // First two correct, last two swapped.
    expect($type->validateAnswer([1, 3, 2, 0], $question))->toBeFalse();
});

test('rejects an answer of the wrong length', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer([1, 3, 0], $question))->toBeFalse();
});

test('rejects non-array, null, and empty answers', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer('nope', $question))->toBeFalse();
    expect($type->validateAnswer(null, $question))->toBeFalse();
    expect($type->validateAnswer([], $question))->toBeFalse();
});

test('rejects an answer containing a null slot (incomplete pairing)', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer([1, 3, null, 2], $question))->toBeFalse();
});

test('scoreFactor is 1.0 for a correct match and 0.0 otherwise', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->scoreFactor([1, 3, 0, 2], $question))->toBe(1.0);
    expect($type->scoreFactor([1, 3, 2, 0], $question))->toBe(0.0);
});

test('calculatePoints awards full base when time bonus disabled', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->calculatePoints($question, 5000, ['enable_time_bonus' => false]))->toBe(100);
});

test('calculatePoints scales with remaining time', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion(['time_limit_seconds' => 10]);

    expect($type->calculatePoints($question, 5000, ['enable_time_bonus' => true]))->toBe(50);
});

test('validateOptions accepts a well-formed 4-pair set', function () {
    $type = new MatchPairsType;

    $options = [
        'left' => [
            ['kind' => 'text', 'value' => 'A'],
            ['kind' => 'image', 'value' => 'questions/a.png'],
            ['kind' => 'text', 'value' => 'C'],
            ['kind' => 'text', 'value' => 'D'],
        ],
        'right' => [
            ['kind' => 'text', 'value' => 'W'],
            ['kind' => 'text', 'value' => 'X'],
            ['kind' => 'image', 'value' => 'questions/y.png'],
            ['kind' => 'text', 'value' => 'Z'],
        ],
    ];

    expect($type->validateOptions($options))->toBeTrue();
});

test('validateOptions rejects missing left or right keys', function () {
    $type = new MatchPairsType;

    expect($type->validateOptions(['left' => [['kind' => 'text', 'value' => 'A']]]))->toBeFalse();
});

test('validateOptions rejects a side with fewer than four items', function () {
    $type = new MatchPairsType;

    $side = [['kind' => 'text', 'value' => 'A'], ['kind' => 'text', 'value' => 'B']];
    expect($type->validateOptions(['left' => $side, 'right' => $side]))->toBeFalse();
});

test('validateOptions rejects an invalid kind', function () {
    $type = new MatchPairsType;

    $bad = [
        ['kind' => 'video', 'value' => 'A'],
        ['kind' => 'text', 'value' => 'B'],
        ['kind' => 'text', 'value' => 'C'],
        ['kind' => 'text', 'value' => 'D'],
    ];
    $ok = [
        ['kind' => 'text', 'value' => 'W'],
        ['kind' => 'text', 'value' => 'X'],
        ['kind' => 'text', 'value' => 'Y'],
        ['kind' => 'text', 'value' => 'Z'],
    ];

    expect($type->validateOptions(['left' => $bad, 'right' => $ok]))->toBeFalse();
});

test('validateOptions rejects an empty value', function () {
    $type = new MatchPairsType;

    $bad = [
        ['kind' => 'text', 'value' => ''],
        ['kind' => 'text', 'value' => 'B'],
        ['kind' => 'text', 'value' => 'C'],
        ['kind' => 'text', 'value' => 'D'],
    ];
    $ok = [
        ['kind' => 'text', 'value' => 'W'],
        ['kind' => 'text', 'value' => 'X'],
        ['kind' => 'text', 'value' => 'Y'],
        ['kind' => 'text', 'value' => 'Z'],
    ];

    expect($type->validateOptions(['left' => $bad, 'right' => $ok]))->toBeFalse();
});

test('returns correct livewire component names', function () {
    $type = new MatchPairsType;

    expect($type->renderSpectatorComponent())->toBe('question-types.match-pairs-spectator');
    expect($type->renderPlayerComponent())->toBe('question-types.match-pairs-player');
});

test('calculatePoints falls back to the quiz default when time limit is not set', function () {
    $quiz = \App\Models\Quiz::factory()->create([
        'settings' => ['default_question_duration_seconds' => 10],
    ]);
    $category = \App\Models\Category::factory()->for($quiz)->create();
    $type = new MatchPairsType;
    $question = matchPairsQuestion(['category_id' => $category->id, 'time_limit_seconds' => null]);

    expect($type->calculatePoints($question, 5000, ['enable_time_bonus' => true]))->toBe(50);
});
