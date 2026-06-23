<?php

use App\Models\Question;
use App\Services\ScoringService;

test('full points when time bonus disabled', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => false];
    $points = $service->calculate($question, timeTakenMs: 25000, streak: 0, settings: $settings);
    expect($points)->toBe(10);
});

test('time bonus gives full points at instant answer', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => false];
    $points = $service->calculate($question, timeTakenMs: 0, streak: 0, settings: $settings);
    expect($points)->toBe(10);
});

test('time bonus gives zero points at time limit', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => false];
    $points = $service->calculate($question, timeTakenMs: 30000, streak: 0, settings: $settings);
    expect($points)->toBe(0);
});

test('time bonus gives half points at half time', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => false];
    $points = $service->calculate($question, timeTakenMs: 15000, streak: 0, settings: $settings);
    expect($points)->toBe(5);
});

test('streak multiplier 1x for streak 0-2', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => true];
    expect($service->calculate($question, 0, streak: 0, settings: $settings))->toBe(10);
    expect($service->calculate($question, 0, streak: 1, settings: $settings))->toBe(10);
    expect($service->calculate($question, 0, streak: 2, settings: $settings))->toBe(10);
});

test('streak multiplier 1.5x for streak 3-4', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => true];
    expect($service->calculate($question, 0, streak: 3, settings: $settings))->toBe(15);
    expect($service->calculate($question, 0, streak: 4, settings: $settings))->toBe(15);
});

test('streak multiplier 2x for streak 5+', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => true];
    expect($service->calculate($question, 0, streak: 5, settings: $settings))->toBe(20);
    expect($service->calculate($question, 0, streak: 10, settings: $settings))->toBe(20);
});

test('time bonus and streak combine correctly', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => true];
    $points = $service->calculate($question, timeTakenMs: 15000, streak: 5, settings: $settings);
    expect($points)->toBe(10);
});

test('streaks disabled ignores streak value', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => false];
    $points = $service->calculate($question, 0, streak: 10, settings: $settings);
    expect($points)->toBe(10);
});

test('accuracy factor scales the base points', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 100, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => false];

    expect($service->calculate($question, 0, 0, $settings, accuracyFactor: 1.0))->toBe(100);
    expect($service->calculate($question, 0, 0, $settings, accuracyFactor: 0.5))->toBe(50);
    expect($service->calculate($question, 0, 0, $settings, accuracyFactor: 0.0))->toBe(0);
});

test('accuracy factor is clamped to the 0..1 range', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 100, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => false];

    expect($service->calculate($question, 0, 0, $settings, accuracyFactor: 2.0))->toBe(100);
    expect($service->calculate($question, 0, 0, $settings, accuracyFactor: -1.0))->toBe(0);
});

test('accuracy factor combines with time bonus and streak', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 100, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => true];
    // half accuracy × half time × 2x streak = base.
    $points = $service->calculate($question, timeTakenMs: 15000, streak: 5, settings: $settings, accuracyFactor: 0.5);
    expect($points)->toBe(50);
});

test('breakdown exposes running subtotals after each factor', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 100, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => true];

    // 80% time left (answered at 6s of 30s) with a 1.5x streak.
    $bd = $service->breakdown($question, timeTakenMs: 6000, streak: 3, settings: $settings, accuracyFactor: 1.0);

    expect($bd['base'])->toBe(100)
        ->and($bd['accuracy'])->toBe(1.0)
        ->and(round($bd['time_factor'], 2))->toBe(0.8)
        ->and($bd['streak_multiplier'])->toBe(1.5)
        ->and($bd['time_bonus_enabled'])->toBeTrue()
        ->and($bd['streak_enabled'])->toBeTrue()
        ->and($bd['correct_points'])->toBe(100)
        ->and($bd['speed_points'])->toBe(80)
        ->and($bd['total'])->toBe(120);
});

test('breakdown total always matches calculate', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 100, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => true];

    foreach ([0, 6000, 15000, 30000] as $ms) {
        foreach ([0, 3, 5] as $streak) {
            foreach ([0.5, 1.0] as $acc) {
                $bd = $service->breakdown($question, $ms, $streak, $settings, $acc);
                $calc = $service->calculate($question, $ms, $streak, $settings, $acc);
                expect($bd['total'])->toBe($calc);
            }
        }
    }
});

test('breakdown reflects disabled time bonus and streaks', function () {
    $service = new ScoringService;
    $question = Question::factory()->make(['category_id' => 1, 'points' => 100, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => false];

    $bd = $service->breakdown($question, timeTakenMs: 15000, streak: 5, settings: $settings, accuracyFactor: 1.0);

    expect($bd['time_bonus_enabled'])->toBeFalse()
        ->and($bd['streak_enabled'])->toBeFalse()
        ->and($bd['time_factor'])->toBe(1.0)
        ->and($bd['streak_multiplier'])->toBe(1.0)
        ->and($bd['speed_points'])->toBe(100)
        ->and($bd['total'])->toBe(100);
});

test('time bonus falls back to the quiz default when the question has no explicit limit', function () {
    // Create a partial mock question that preserves real behavior but mocks relations.
    $question = \Mockery::mock(Question::class)->makePartial();
    $question->points = 10;
    $question->time_limit_seconds = null;

    // Create real quiz and category as simple objects for the relation chain.
    $quiz = (object) ['settings' => ['default_question_duration_seconds' => 10]];
    $category = (object) ['quiz' => $quiz];

    $question->category = $category;

    $service = new ScoringService;
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => false];

    // Inherited 10s limit, answered at 5s -> half the time left -> half points.
    $points = $service->calculate($question, timeTakenMs: 5000, streak: 0, settings: $settings);
    expect($points)->toBe(5);
});
