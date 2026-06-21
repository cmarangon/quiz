<?php

use App\Events\QuestionStarted;
use App\Livewire\PlayerScreen;
use App\Livewire\SpectatorScreen;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

function themedSession(string $theme): GameSession
{
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create(['theme' => $theme, 'name' => ucfirst($theme)]);
    Question::factory()->for($category)->create();

    return GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'active']);
}

function questionPayload(string $theme): array
{
    return [
        'question_id' => 'q-1',
        'question_index' => 0,
        'body' => 'Sample themed question?',
        'category_name' => ucfirst($theme),
        'theme' => $theme,
        'time_limit_seconds' => 30,
        'options' => [
            ['label' => 'Option A'],
            ['label' => 'Option B'],
            ['label' => 'Option C'],
            ['label' => 'Option D'],
        ],
    ];
}

function typedPayload(string $theme, string $type): array
{
    return [
        'question_id' => 'q-'.$type,
        'question_index' => 0,
        'type' => $type,
        'body' => 'Sample themed question?',
        'category_name' => ucfirst($theme),
        'theme' => $theme,
        'time_limit_seconds' => 30,
        'options' => $type === 'geo_guesser'
            ? ['center' => ['lat' => 0, 'lng' => 0], 'zoom' => 2]
            : [['label' => 'Alpha'], ['label' => 'Beta'], ['label' => 'Gamma']],
    ];
}

$themes = ['science', 'history', 'pop-culture', 'general-knowledge', 'geography', 'nature', 'sports', 'crime'];

test('QuestionStarted carries the theme for every styled category', function (string $theme) {
    $category = Category::factory()->create(['theme' => $theme]);
    $question = Question::factory()->for($category)->create();
    $session = GameSession::factory()->create();

    $data = (new QuestionStarted($session, $question))->broadcastWith();

    expect($data['theme'])->toBe($theme);
    expect($data)->not->toHaveKey('correct_answer');
})->with($themes);

test('player answering screen renders the themed partial', function (string $theme) {
    $session = themedSession($theme);
    $player = Player::factory()->for($session, 'gameSession')->create();

    Livewire::withQueryParams(['player_id' => $player->id])
        ->test(PlayerScreen::class, ['code' => $session->join_code])
        ->call('onQuestionStarted', questionPayload($theme))
        ->assertSee('qz-theme--'.$theme, false)
        ->assertSee('player-answer-option', false)
        ->assertSee('choiceAnswer()', false)
        ->assertSee('multiple-choice-submit', false)
        // Tapping an option must only stage the choice (choose), never submit it.
        ->assertDontSee("submitAnswer('Option A')", false);
})->with($themes);

test('spectator question and review screens render the themed partial', function (string $theme) {
    $session = themedSession($theme);

    $component = Livewire::test(SpectatorScreen::class, ['code' => $session->join_code])
        ->call('onQuestionStarted', questionPayload($theme))
        ->assertSee('qz-theme--'.$theme, false)
        ->assertSee('spectator-question-body', false);

    $component->call('onQuestionEnded', [
        'correct_answer' => 'Option A',
        'scores' => [['nickname' => 'Sam', 'score' => 50]],
    ])
        ->assertSee('qz-theme--'.$theme, false)
        ->assertSee('is-correct', false);
})->with($themes);

test('spectator keeps showing the themed question when category.changed is processed after question.started', function (string $theme) {
    $session = themedSession($theme);

    // Simulate the game-start race where category.changed is delivered/processed
    // after question.started. The styled question must remain visible instead of
    // reverting to the category intro screen.
    Livewire::test(SpectatorScreen::class, ['code' => $session->join_code])
        ->call('onQuestionStarted', questionPayload($theme))
        ->call('onCategoryChanged', [
            'theme' => $theme,
            'name' => ucfirst($theme),
            'category_id' => 1,
        ])
        ->assertSet('phase', 'question')
        ->assertSee('qz-theme--'.$theme, false)
        ->assertSee('spectator-question-body', false);
})->with($themes);

test('spectator applies the theme to ordering and geo_guesser questions', function (string $theme) {
    $session = themedSession($theme);

    foreach (['ordering', 'geo_guesser'] as $type) {
        Livewire::test(SpectatorScreen::class, ['code' => $session->join_code])
            ->call('onQuestionStarted', typedPayload($theme, $type))
            ->assertSee('qz-theme--'.$theme, false);
    }
})->with($themes);

test('player applies the theme to ordering and geo_guesser questions', function (string $theme) {
    $session = themedSession($theme);
    $player = Player::factory()->for($session, 'gameSession')->create();

    foreach (['ordering', 'geo_guesser'] as $type) {
        Livewire::withQueryParams(['player_id' => $player->id])
            ->test(PlayerScreen::class, ['code' => $session->join_code])
            ->call('onQuestionStarted', typedPayload($theme, $type))
            ->assertSee('qz-theme--'.$theme, false);
    }
})->with($themes);

test('player true/false question is select-then-submit, not tap-to-submit', function () {
    $session = themedSession('default');
    $player = Player::factory()->for($session, 'gameSession')->create();

    $payload = typedPayload('default', 'true_false');
    $payload['options'] = ['True', 'False'];

    Livewire::withQueryParams(['player_id' => $player->id])
        ->test(PlayerScreen::class, ['code' => $session->join_code])
        ->call('onQuestionStarted', $payload)
        ->assertSee('choiceAnswer()', false)
        ->assertSee('true-false-submit', false)
        ->assertSee('data-answer-label="True"', false)
        ->assertSee('data-answer-label="False"', false)
        // Tapping an option must only stage the choice (choose), never submit it.
        ->assertDontSee('submitAnswer(', false);
});

test('an unknown theme falls back to the default markup', function () {
    $session = themedSession('default');
    $player = Player::factory()->for($session, 'gameSession')->create();

    Livewire::withQueryParams(['player_id' => $player->id])
        ->test(PlayerScreen::class, ['code' => $session->join_code])
        ->call('onQuestionStarted', questionPayload('default'))
        ->assertDontSee('qz-theme--', false)
        ->assertSee('player-answer-option', false)
        ->assertSee('choiceAnswer()', false)
        ->assertSee('multiple-choice-submit', false)
        // Tapping an option must only stage the choice (choose), never submit it.
        ->assertDontSee("submitAnswer('Option A')", false);
});
