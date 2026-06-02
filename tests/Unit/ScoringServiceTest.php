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
