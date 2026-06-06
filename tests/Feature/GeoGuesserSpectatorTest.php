<?php

use App\Livewire\SpectatorScreen;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

function geoSpectatorSession(): GameSession
{
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create(['theme' => 'geography', 'name' => 'Geography']);
    Question::factory()->for($category)->geoGuesser()->create();

    return GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'active']);
}

function geoQuestionPayload(): array
{
    return [
        'question_id' => 'q-geo',
        'question_index' => 0,
        'type' => 'geo_guesser',
        'body' => 'Where is this?',
        'category_name' => 'Geography',
        'theme' => 'geography',
        'time_limit_seconds' => 30,
        'options' => ['center' => ['lat' => 0, 'lng' => 0], 'zoom' => 2],
    ];
}

test('spectator review plots every player guess on the geo map', function () {
    $session = geoSpectatorSession();

    Livewire::test(SpectatorScreen::class, ['code' => $session->join_code])
        ->call('onQuestionStarted', geoQuestionPayload())
        ->call('onQuestionEnded', [
            'correct_answer' => ['lat' => 10.0, 'lng' => 20.0],
            'scores' => [],
            'guesses' => [
                ['lat' => 11.0, 'lng' => 21.0, 'nickname' => 'Cartographer', 'emoji' => '🦊'],
                ['lat' => -5.0, 'lng' => 8.0, 'nickname' => 'Wanderer', 'emoji' => '🦉'],
            ],
        ])
        ->assertSet('phase', 'review')
        ->assertSee('data-test="geo-map"', false)
        // The guesses are handed to the Alpine map via the x-data config.
        ->assertSee('Cartographer', false)
        ->assertSee('Wanderer', false);
});

test('spectator does not leak guesses into the map while the question is live', function () {
    $session = geoSpectatorSession();

    Livewire::test(SpectatorScreen::class, ['code' => $session->join_code])
        ->call('onQuestionStarted', geoQuestionPayload())
        ->assertSet('phase', 'question')
        ->assertDontSee('Cartographer', false);
});
