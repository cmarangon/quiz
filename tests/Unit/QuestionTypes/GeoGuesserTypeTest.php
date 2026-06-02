<?php

use App\Models\Question;
use App\QuestionTypes\GeoGuesserType;

beforeEach(function () {
    config()->set('quiz.geo_guesser.threshold_km', 50);
    config()->set('quiz.geo_guesser.max_distance_km', 2000);
});

function geoQuestion(array $overrides = []): Question
{
    return Question::factory()->make(array_merge([
        'category_id' => 1,
        'type' => 'geo_guesser',
        'points' => 100,
        'options' => ['zoom' => 2, 'center' => ['lat' => 20.0, 'lng' => 0.0]],
        'correct_answer' => ['lat' => 48.8584, 'lng' => 2.2945], // Eiffel Tower
    ], $overrides));
}

test('haversine computes known distances', function () {
    // Paris -> London is roughly 343 km.
    $distance = GeoGuesserType::haversine(48.8566, 2.3522, 51.5074, -0.1278);
    expect($distance)->toBeGreaterThan(330)->toBeLessThan(360);

    // Same point is zero.
    expect(GeoGuesserType::haversine(10, 10, 10, 10))->toBe(0.0);
});

test('exact guess earns full score factor and is correct', function () {
    $type = new GeoGuesserType;
    $question = geoQuestion();
    $answer = ['lat' => 48.8584, 'lng' => 2.2945];

    expect($type->scoreFactor($answer, $question))->toBe(1.0);
    expect($type->validateAnswer($answer, $question))->toBeTrue();
});

test('guess within threshold earns full score and is correct', function () {
    $type = new GeoGuesserType;
    $question = geoQuestion();
    // ~3 km from the Eiffel Tower, inside the 50 km threshold.
    $answer = ['lat' => 48.8738, 'lng' => 2.2950];

    expect($type->scoreFactor($answer, $question))->toBe(1.0);
    expect($type->validateAnswer($answer, $question))->toBeTrue();
});

test('guess beyond max distance earns zero and is not correct', function () {
    $type = new GeoGuesserType;
    $question = geoQuestion();
    // New York, far beyond 2000 km.
    $answer = ['lat' => 40.7128, 'lng' => -74.0060];

    expect($type->scoreFactor($answer, $question))->toBe(0.0);
    expect($type->validateAnswer($answer, $question))->toBeFalse();
});

test('partial guess decays linearly between threshold and max', function () {
    $type = new GeoGuesserType;
    // Correct at equator/prime meridian, no threshold, max 2000 km.
    $question = geoQuestion([
        'correct_answer' => ['lat' => 0.0, 'lng' => 0.0],
        'options' => ['threshold_km' => 0, 'max_distance_km' => 2000],
    ]);

    // Pick a point ~1000 km north (≈ 9 degrees latitude) → factor ≈ 0.5.
    $answer = ['lat' => 8.9932, 'lng' => 0.0];
    $factor = $type->scoreFactor($answer, $question);

    expect($factor)->toBeGreaterThan(0.45)->toBeLessThan(0.55);
    // Not within (zero) threshold, so not "correct" for streak purposes.
    expect($type->validateAnswer($answer, $question))->toBeFalse();
});

test('per-question options override config defaults', function () {
    $type = new GeoGuesserType;
    $question = geoQuestion([
        'correct_answer' => ['lat' => 0.0, 'lng' => 0.0],
        'options' => ['threshold_km' => 0, 'max_distance_km' => 100],
    ]);

    // ~111 km away (1 degree) → beyond the tightened 100 km max → zero.
    $answer = ['lat' => 1.0, 'lng' => 0.0];
    expect($type->scoreFactor($answer, $question))->toBe(0.0);
});

test('invalid answers score zero without throwing', function () {
    $type = new GeoGuesserType;
    $question = geoQuestion();

    foreach ([null, 'nope', [], ['lat' => 10], ['lat' => 'x', 'lng' => 'y'], ['lat' => 200, 'lng' => 0]] as $answer) {
        expect($type->scoreFactor($answer, $question))->toBe(0.0);
        expect($type->validateAnswer($answer, $question))->toBeFalse();
    }
});

test('validates options', function () {
    $type = new GeoGuesserType;

    expect($type->validateOptions([]))->toBeTrue();
    expect($type->validateOptions(['zoom' => 3, 'center' => ['lat' => 10, 'lng' => 20]]))->toBeTrue();
    expect($type->validateOptions(['max_distance_km' => 1000, 'threshold_km' => 50]))->toBeTrue();

    expect($type->validateOptions(['center' => ['lat' => 10]]))->toBeFalse();
    expect($type->validateOptions(['center' => ['lat' => 200, 'lng' => 0]]))->toBeFalse();
    expect($type->validateOptions(['zoom' => -1]))->toBeFalse();
    expect($type->validateOptions(['max_distance_km' => 0]))->toBeFalse();
    expect($type->validateOptions(['max_distance_km' => 100, 'threshold_km' => 100]))->toBeFalse();
});

test('returns correct livewire component names', function () {
    $type = new GeoGuesserType;
    expect($type->renderSpectatorComponent())->toBe('question-types.geo-guesser-spectator');
    expect($type->renderPlayerComponent())->toBe('question-types.geo-guesser-player');
});
