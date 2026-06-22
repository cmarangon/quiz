<?php

use App\Actions\SubmitAnswer;
use App\Livewire\SpectatorScreen;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    Event::fake();

    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    $this->question = Question::factory()->for($this->category)->ordering()->create([
        'order' => 0,
        'points' => 100,
        'options' => [
            ['label' => 'Step B'],
            ['label' => 'Step D'],
            ['label' => 'Step A'],
            ['label' => 'Step C'],
        ],
        'correct_answer' => ['Step A', 'Step B', 'Step C', 'Step D'],
    ]);
    $this->session = GameSession::factory()->for($this->quiz)->for($this->user, 'host')->create(['status' => 'waiting']);
    $this->player = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Sorter']);

    app(GameService::class)->start($this->session);
    $this->action = app(SubmitAnswer::class);
});

test('correct order earns full points and increments streak', function () {
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        ['Step A', 'Step B', 'Step C', 'Step D'],
        5000,
    );

    expect($result['is_correct'])->toBeTrue();
    expect($result['points_earned'])->toBe(100);
    expect($this->player->fresh()->score)->toBe(100);
    expect($this->player->fresh()->streak)->toBe(1);
});

test('wrong order earns zero points and resets streak', function () {
    $this->player->update(['streak' => 3]);

    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        ['Step B', 'Step A', 'Step C', 'Step D'],
        5000,
    );

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->score)->toBe(0);
    expect($this->player->fresh()->streak)->toBe(0);
});

test('partially correct order earns zero points (all-or-nothing)', function () {
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        ['Step A', 'Step B', 'Step D', 'Step C'],
        5000,
    );

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->score)->toBe(0);
});

test('spectator hides the ordering review leaderboard when show_scoreboard is false', function () {
    $this->quiz->update(['settings' => ['show_scoreboard' => false]]);

    Livewire::test(SpectatorScreen::class, ['code' => $this->session->join_code])
        ->call('onQuestionStarted', [
            'question_id' => $this->question->id,
            'question_index' => 0,
            'body' => $this->question->body,
            'type' => 'ordering',
            'category_name' => null,
            'theme' => 'science',
            'time_limit_seconds' => $this->question->time_limit_seconds,
            'options' => $this->question->options,
        ])
        ->call('onQuestionEnded', [
            'question_id' => $this->question->id,
            'correct_answer' => $this->question->correct_answer,
            'scores' => [['nickname' => 'Sorter', 'score' => 100]],
            'guesses' => [],
            'distribution' => [],
        ])
        ->assertSet('phase', 'review')
        ->assertDontSee(__('Leaderboard'));
});

test('spectator shows the ordering review leaderboard narrowed when show_scoreboard is true', function () {
    $this->quiz->update(['settings' => ['show_scoreboard' => true]]);

    Livewire::test(SpectatorScreen::class, ['code' => $this->session->join_code])
        ->call('onQuestionStarted', [
            'question_id' => $this->question->id,
            'question_index' => 0,
            'body' => $this->question->body,
            'type' => 'ordering',
            'category_name' => null,
            'theme' => 'science',
            'time_limit_seconds' => $this->question->time_limit_seconds,
            'options' => $this->question->options,
        ])
        ->call('onQuestionEnded', [
            'question_id' => $this->question->id,
            'correct_answer' => $this->question->correct_answer,
            'scores' => [['nickname' => 'Sorter', 'score' => 100]],
            'guesses' => [],
            'distribution' => [],
        ])
        ->assertSet('phase', 'review')
        ->assertSee(__('Leaderboard'))
        ->assertSeeHtml('max-w-3xl');
});
