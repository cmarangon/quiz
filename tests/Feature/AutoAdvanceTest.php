<?php

use App\Livewire\HostDashboard;
use App\Livewire\SpectatorScreen;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    $this->questions = Question::factory()->count(2)->for($this->category)->sequence(
        ['order' => 0, 'correct_answer' => 'Option A', 'points' => 10],
        ['order' => 1, 'correct_answer' => 'Option B', 'points' => 10],
    )->create();
    $this->session = GameSession::factory()
        ->for($this->quiz)
        ->for($this->user, 'host')
        ->create(['status' => 'waiting']);
    Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Alex']);

    app(GameService::class)->start($this->session);
    $this->session->refresh();
    $this->currentQuestionId = app(GameService::class)->getCurrentQuestion($this->session)->id;
});

test('host auto-finishes the question once everyone has answered', function () {
    Livewire::actingAs($this->user)
        ->test(HostDashboard::class, ['code' => $this->session->join_code])
        ->assertSet('phase', 'playing')
        ->assertSet('currentQuestionId', $this->currentQuestionId)
        ->call('onPlayerAnswered', [
            'answered_count' => 1,
            'total_players' => 1,
            'question_id' => $this->currentQuestionId,
        ])
        ->call('autoFinishQuestion', $this->currentQuestionId)
        ->assertSet('phase', 'reviewing');

    expect($this->session->fresh()->status)->toBe('reviewing');
});

test('host auto-finish is a no-op when not everyone has answered', function () {
    Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Sam']);

    Livewire::actingAs($this->user)
        ->test(HostDashboard::class, ['code' => $this->session->join_code])
        ->call('onPlayerAnswered', [
            'answered_count' => 1,
            'total_players' => 2,
            'question_id' => $this->currentQuestionId,
        ])
        ->call('autoFinishQuestion', $this->currentQuestionId)
        ->assertSet('phase', 'playing');

    expect($this->session->fresh()->status)->toBe('playing');
});

test('host auto-finish ignores a stale question id', function () {
    Livewire::actingAs($this->user)
        ->test(HostDashboard::class, ['code' => $this->session->join_code])
        ->call('onPlayerAnswered', [
            'answered_count' => 1,
            'total_players' => 1,
            'question_id' => $this->currentQuestionId,
        ])
        ->call('autoFinishQuestion', $this->currentQuestionId + 999)
        ->assertSet('phase', 'playing');

    expect($this->session->fresh()->status)->toBe('playing');
});

test('finishQuestion is idempotent and does not throw on a double call', function () {
    Livewire::actingAs($this->user)
        ->test(HostDashboard::class, ['code' => $this->session->join_code])
        ->call('finishQuestion')
        ->assertSet('phase', 'reviewing')
        ->call('finishQuestion')
        ->assertSet('phase', 'reviewing');

    expect($this->session->fresh()->status)->toBe('reviewing');
});

test('host stale player.answered from another question is ignored', function () {
    Livewire::actingAs($this->user)
        ->test(HostDashboard::class, ['code' => $this->session->join_code])
        ->call('onPlayerAnswered', [
            'answered_count' => 5,
            'total_players' => 5,
            'question_id' => $this->currentQuestionId + 999,
        ])
        ->assertSet('answeredCount', 0);
});

test('host dashboard renders the auto-advance countdown when everyone answered', function () {
    Livewire::actingAs($this->user)
        ->test(HostDashboard::class, ['code' => $this->session->join_code])
        ->call('onPlayerAnswered', [
            'answered_count' => 1,
            'total_players' => 1,
            'question_id' => $this->currentQuestionId,
        ])
        ->assertSeeHtml('data-test="host-auto-advance"')
        ->assertSeeHtml('autoFinishQuestion('.$this->currentQuestionId.')');
});

test('spectator screen shows the countdown when everyone answered', function () {
    Livewire::test(SpectatorScreen::class, ['code' => $this->session->join_code])
        ->call('onQuestionStarted', [
            'question_id' => $this->currentQuestionId,
            'body' => 'Q?',
            'type' => 'multiple_choice',
            'options' => [['label' => 'Option A'], ['label' => 'Option B']],
            'time_limit_seconds' => 30,
            'question_index' => 0,
            'theme' => 'default',
            'category_name' => 'Cat',
        ])
        ->call('onPlayerAnswered', [
            'answered_count' => 1,
            'total_players' => 1,
            'question_id' => $this->currentQuestionId,
        ])
        ->assertSeeHtml('data-test="spectator-countdown"');
});

test('spectator ignores a stale player.answered from a previous question', function () {
    Livewire::test(SpectatorScreen::class, ['code' => $this->session->join_code])
        ->call('onQuestionStarted', [
            'question_id' => $this->currentQuestionId,
            'body' => 'Q?',
            'type' => 'multiple_choice',
            'options' => [['label' => 'Option A']],
            'time_limit_seconds' => 30,
            'question_index' => 0,
            'theme' => 'default',
            'category_name' => 'Cat',
        ])
        ->call('onPlayerAnswered', [
            'answered_count' => 9,
            'total_players' => 9,
            'question_id' => $this->currentQuestionId + 999,
        ])
        ->assertSet('answeredCount', 0)
        ->assertDontSeeHtml('data-test="spectator-countdown"');
});
