<?php

use App\Actions\SubmitAnswer;
use App\Events\CategoryChanged;
use App\Events\GameFinished;
use App\Events\PlayerAnswered;
use App\Events\QuestionStarted;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;

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
    $this->player = Player::factory()
        ->for($this->session, 'gameSession')
        ->create(['nickname' => 'Alex']);
});

test('starting game broadcasts CategoryChanged and QuestionStarted', function () {
    Event::fake([CategoryChanged::class, QuestionStarted::class]);
    app(GameService::class)->start($this->session);
    Event::assertDispatched(CategoryChanged::class);
    Event::assertDispatched(QuestionStarted::class);
});

test('player can submit a correct answer', function () {
    Event::fake([PlayerAnswered::class, CategoryChanged::class, QuestionStarted::class]);
    app(GameService::class)->start($this->session);
    $action = app(SubmitAnswer::class);
    $result = $action->execute(
        session: $this->session->fresh(),
        player: $this->player,
        questionId: $this->questions[0]->id,
        answer: 'Option A',
        timeTakenMs: 5000,
    );
    expect($result['is_correct'])->toBeTrue();
    expect($result['points_earned'])->toBe(10);
    expect($this->player->fresh()->score)->toBe(10);
    Event::assertDispatched(PlayerAnswered::class);
});

test('player cannot submit answer twice for same question', function () {
    Event::fake();
    app(GameService::class)->start($this->session);
    $action = app(SubmitAnswer::class);
    $action->execute($this->session->fresh(), $this->player, $this->questions[0]->id, 'Option A', 5000);
    $action->execute($this->session->fresh(), $this->player, $this->questions[0]->id, 'Option B', 5000);
})->throws(LogicException::class);

test('wrong answer gives zero points and resets streak', function () {
    Event::fake();
    app(GameService::class)->start($this->session);
    $this->player->update(['streak' => 3]);
    $action = app(SubmitAnswer::class);
    $result = $action->execute($this->session->fresh(), $this->player, $this->questions[0]->id, 'Wrong', 5000);
    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->streak)->toBe(0);
});

test('full game flow from start to finish', function () {
    Event::fake();
    $service = app(GameService::class);
    $action = app(SubmitAnswer::class);

    $service->start($this->session);
    expect($this->session->fresh()->status)->toBe('playing');

    $action->execute($this->session->fresh(), $this->player, $this->questions[0]->id, 'Option A', 5000);

    $service->finishQuestion($this->session->fresh());
    expect($this->session->fresh()->status)->toBe('reviewing');

    $service->advanceToNextQuestion($this->session->fresh());
    expect($this->session->fresh()->status)->toBe('playing');
    expect($this->session->fresh()->current_question_index)->toBe(1);

    $action->execute($this->session->fresh(), $this->player, $this->questions[1]->id, 'Option B', 5000);

    $service->finishQuestion($this->session->fresh());

    $result = $service->advanceToNextQuestion($this->session->fresh());
    expect($result)->toBeFalse();
    expect($this->session->fresh()->status)->toBe('finished');

    Event::assertDispatched(GameFinished::class);
});
