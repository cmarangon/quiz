<?php

use App\Actions\SubmitAnswer;
use App\Events\PlayerAnswered;
use App\Events\QuestionStarted;
use App\Livewire\PlayerScreen;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    $this->question = Question::factory()->for($this->category)->create([
        'order' => 0,
        'correct_answer' => 'Option A',
        'time_limit_seconds' => 30,
    ]);
    $this->session = GameSession::factory()
        ->for($this->quiz)
        ->for($this->user, 'host')
        ->create(['status' => 'waiting']);
    $this->player = Player::factory()
        ->for($this->session, 'gameSession')
        ->create(['nickname' => 'Alex', 'streak' => 4]);
});

test('starting a question stamps the server-side start time', function () {
    app(GameService::class)->start($this->session);

    expect($this->session->fresh()->current_question_started_at)->not->toBeNull();
});

test('QuestionStarted broadcasts the start timestamp in epoch ms', function () {
    app(GameService::class)->start($this->session);
    $session = $this->session->fresh();

    $payload = (new QuestionStarted($session, $this->question))->broadcastWith();

    expect($payload['started_at'])
        ->toBe($session->current_question_started_at->getTimestampMs());
});

test('timeout records a null answer worth zero points and resets the streak', function () {
    Event::fake([PlayerAnswered::class]);
    app(GameService::class)->start($this->session);

    $result = app(SubmitAnswer::class)->timeout(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
    );

    expect($result['timed_out'])->toBeTrue();
    expect($result['points_earned'])->toBe(0);

    $answer = PlayerAnswer::where('player_id', $this->player->id)
        ->where('question_id', $this->question->id)
        ->first();

    expect($answer)->not->toBeNull();
    expect($answer->answer)->toBeNull();
    expect($answer->is_correct)->toBeFalse();
    expect($answer->points_earned)->toBe(0);
    expect($this->player->fresh()->streak)->toBe(0);

    Event::assertDispatched(PlayerAnswered::class);
});

test('timeout is rejected once the player has already answered', function () {
    Event::fake();
    app(GameService::class)->start($this->session);

    app(SubmitAnswer::class)->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        'Option A',
        1000,
    );

    expect(fn () => app(SubmitAnswer::class)->timeout(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
    ))->toThrow(LogicException::class);
});

test('player markTimedOut locks the player and records the timeout', function () {
    Event::fake([PlayerAnswered::class]);
    app(GameService::class)->start($this->session);

    Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->assertSet('phase', 'answering')
        ->call('markTimedOut')
        ->assertSet('phase', 'answered')
        ->assertSet('timedOut', true);

    expect(PlayerAnswer::where('player_id', $this->player->id)
        ->where('question_id', $this->question->id)
        ->exists())->toBeTrue();
});

test('player markTimedOut submits the current selection instead of a blank timeout', function () {
    Event::fake([PlayerAnswered::class]);
    app(GameService::class)->start($this->session);

    Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->assertSet('phase', 'answering')
        ->call('markTimedOut', 'Option A')
        ->assertSet('phase', 'answered')
        ->assertSet('timedOut', false);

    $answer = PlayerAnswer::where('player_id', $this->player->id)
        ->where('question_id', $this->question->id)
        ->first();

    expect($answer)->not->toBeNull();
    expect($answer->answer)->toBe('Option A');
    expect($answer->is_correct)->toBeTrue();
    // Time bonus is disabled in this suite, so a correct buzzer-beating answer
    // still earns its full base points rather than being wasted.
    expect($answer->points_earned)->toBeGreaterThan(0);
});

test('player markTimedOut keeps the timeout penalty when nothing is selected', function () {
    Event::fake([PlayerAnswered::class]);
    app(GameService::class)->start($this->session);

    Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->assertSet('phase', 'answering')
        ->call('markTimedOut', null)
        ->assertSet('phase', 'answered')
        ->assertSet('timedOut', true);

    $answer = PlayerAnswer::where('player_id', $this->player->id)
        ->where('question_id', $this->question->id)
        ->first();

    expect($answer)->not->toBeNull();
    expect($answer->answer)->toBeNull();
    expect($answer->points_earned)->toBe(0);
});

test('player markTimedOut is a no-op after the player already answered', function () {
    Event::fake();
    app(GameService::class)->start($this->session);

    $component = Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->call('submitAnswer', 'Option A')
        ->assertSet('phase', 'answered')
        ->assertSet('timedOut', false);

    $component->call('markTimedOut')->assertSet('timedOut', false);

    $answers = PlayerAnswer::where('player_id', $this->player->id)
        ->where('question_id', $this->question->id)
        ->get();

    expect($answers)->toHaveCount(1);
    expect($answers->first()->answer)->toBe('Option A');
});

test('player answering screen renders the cartoony countdown', function () {
    app(GameService::class)->start($this->session);

    Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->assertSet('phase', 'answering')
        ->assertSeeHtml('data-test="question-timer"')
        ->assertSeeHtml('questionTimer(');
});

test('execute rejects an answer submitted after the inherited time limit', function () {
    $question = Question::factory()->for($this->category)->create([
        'order' => 1,
        'correct_answer' => 'Option A',
        'time_limit_seconds' => null,
    ]);
    app(GameService::class)->start($this->session);

    expect(fn () => app(SubmitAnswer::class)->execute(
        $this->session->fresh(),
        $this->player,
        $question->id,
        'Option A',
        30501, // inherited 30s limit + 500ms grace, exceeded by 1ms
    ))->toThrow(LogicException::class, 'Answer submitted after time limit.');
});

test('timeout records the inherited time limit as the time taken', function () {
    Event::fake([PlayerAnswered::class]);
    $question = Question::factory()->for($this->category)->create([
        'order' => 1,
        'correct_answer' => 'Option A',
        'time_limit_seconds' => null,
    ]);
    app(GameService::class)->start($this->session);

    app(SubmitAnswer::class)->timeout($this->session->fresh(), $this->player, $question->id);

    $answer = PlayerAnswer::where('player_id', $this->player->id)
        ->where('question_id', $question->id)
        ->first();

    expect($answer->time_taken_ms)->toBe(30000);
});

test('QuestionStarted broadcasts the inherited quiz default when the question has no explicit limit', function () {
    $quiz = Quiz::factory()->for($this->user)->create([
        'settings' => ['default_question_duration_seconds' => 15],
    ]);
    $category = Category::factory()->for($quiz)->create(['order' => 0]);
    $question = Question::factory()->for($category)->create([
        'order' => 0,
        'time_limit_seconds' => null,
    ]);
    $session = GameSession::factory()->for($quiz)->for($this->user, 'host')->create(['status' => 'waiting']);

    app(GameService::class)->start($session);

    $payload = (new QuestionStarted($session->fresh(), $question))->broadcastWith();

    expect($payload['time_limit_seconds'])->toBe(15);
});
