<?php

use App\Actions\SubmitAnswer;
use App\Livewire\PlayerScreen;
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
    $this->question = Question::factory()->for($this->category)->matchPairs()->create([
        'order' => 0,
        'points' => 100,
    ]);
    $this->session = GameSession::factory()->for($this->quiz)->for($this->user, 'host')->create(['status' => 'waiting']);
    $this->player = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Matcher']);

    app(GameService::class)->start($this->session);
    $this->action = app(SubmitAnswer::class);
});

test('a fully correct match earns full points and increments streak', function () {
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        [1, 3, 0, 2],
        5000,
    );

    expect($result['is_correct'])->toBeTrue();
    expect($result['points_earned'])->toBe(100);
    expect($this->player->fresh()->score)->toBe(100);
    expect($this->player->fresh()->streak)->toBe(1);
});

test('an incorrect match earns zero points and resets streak', function () {
    $this->player->update(['streak' => 3]);

    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        [3, 1, 0, 2],
        5000,
    );

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->score)->toBe(0);
    expect($this->player->fresh()->streak)->toBe(0);
});

test('a partially correct match (3 of 4 pairs) earns zero points (all-or-nothing)', function () {
    // left[0]->1 and left[1]->3 correct, left[2] and left[3] swapped.
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        [1, 3, 2, 0],
        5000,
    );

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->score)->toBe(0);
});

test('PlayerScreen::submitMatches delegates to SubmitAnswer and advances the phase', function () {
    Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->assertSet('phase', 'answering')
        ->call('submitMatches', [1, 3, 0, 2])
        ->assertSet('phase', 'answered');

    expect($this->player->fresh()->score)->toBe(100);
});

test('player screen renders the match-pairs tap-to-pair UI during the answering phase', function () {
    Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->assertSet('phase', 'answering')
        ->assertSeeHtml('data-test="match-pairs-left-item"')
        ->assertSeeHtml('data-test="match-pairs-right-item"')
        ->assertSeeHtml('data-test="match-pairs-submit"');
});

test('spectator screen renders both columns during the question phase', function () {
    Livewire::test(SpectatorScreen::class, ['code' => $this->session->join_code])
        ->call('onQuestionStarted', [
            'question_id' => $this->question->id,
            'question_index' => 0,
            'body' => $this->question->body,
            'type' => 'match_pairs',
            'category_name' => null,
            'theme' => 'default',
            'time_limit_seconds' => $this->question->time_limit_seconds,
            'options' => $this->question->options,
        ])
        ->assertSet('phase', 'question')
        ->assertSeeHtml('data-test="match-pairs-columns"');
});

test('spectator screen renders the correct pairs during the review phase', function () {
    Livewire::test(SpectatorScreen::class, ['code' => $this->session->join_code])
        ->call('onQuestionStarted', [
            'question_id' => $this->question->id,
            'question_index' => 0,
            'body' => $this->question->body,
            'type' => 'match_pairs',
            'category_name' => null,
            'theme' => 'default',
            'time_limit_seconds' => $this->question->time_limit_seconds,
            'options' => $this->question->options,
        ])
        ->call('onQuestionEnded', [
            'question_id' => $this->question->id,
            'correct_answer' => $this->question->correct_answer,
            'scores' => [['nickname' => 'Matcher', 'score' => 100]],
            'guesses' => [],
            'distribution' => [],
        ])
        ->assertSet('phase', 'review')
        ->assertSeeHtml('data-test="match-pairs-correct-pairs"');
});
