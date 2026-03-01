<?php

use App\Actions\SubmitAnswer;
use App\Events\CategoryChanged;
use App\Events\GameFinished;
use App\Events\PlayerAnswered;
use App\Events\QuestionEnded;
use App\Events\QuestionStarted;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;

test('complete game flow end to end', function () {
    // Create all models first, then fake broadcast events
    $host = User::factory()->create();
    $quiz = Quiz::factory()->for($host)->create([
        'settings' => ['enable_time_bonus' => true, 'enable_streaks' => true],
    ]);
    $cat1 = Category::factory()->for($quiz)->create(['order' => 0, 'theme' => 'science']);
    $cat2 = Category::factory()->for($quiz)->create(['order' => 1, 'theme' => 'nature']);
    Question::factory()->for($cat1)->create(['order' => 0, 'correct_answer' => 'A', 'points' => 10]);
    Question::factory()->for($cat2)->create(['order' => 0, 'correct_answer' => 'B', 'points' => 20]);

    $session = GameSession::factory()->create([
        'quiz_id' => $quiz->id,
        'host_user_id' => $host->id,
    ]);

    $player1 = Player::factory()->create(['game_session_id' => $session->id, 'nickname' => 'Alice']);
    $player2 = Player::factory()->create(['game_session_id' => $session->id, 'nickname' => 'Bob']);

    // Fake only broadcast events (not model events)
    Event::fake([
        CategoryChanged::class,
        QuestionStarted::class,
        PlayerAnswered::class,
        QuestionEnded::class,
        GameFinished::class,
    ]);

    $service = app(GameService::class);
    $action = app(SubmitAnswer::class);

    // Start game
    $service->start($session);
    $session->refresh();
    expect($session->status)->toBe('playing');
    expect($session->currentCategory->theme)->toBe('science');

    // Both players answer question 1
    $q1 = $service->getCurrentQuestion($session);
    $action->execute($session, $player1, $q1->id, 'A', 5000);
    $action->execute($session, $player2, $q1->id, 'Wrong', 10000);

    // Finish + advance
    $service->finishQuestion($session->fresh());
    $service->advanceToNextQuestion($session->fresh());
    $session->refresh();
    expect($session->currentCategory->theme)->toBe('nature');

    // Answer question 2
    $q2 = $service->getCurrentQuestion($session);
    $action->execute($session, $player1, $q2->id, 'B', 3000);
    $action->execute($session, $player2, $q2->id, 'B', 8000);

    // Finish + advance (should end game)
    $service->finishQuestion($session->fresh());
    $result = $service->advanceToNextQuestion($session->fresh());
    expect($result)->toBeFalse();
    expect($session->fresh()->status)->toBe('finished');

    // Verify scores
    expect($player1->fresh()->score)->toBeGreaterThan(0);
    expect($player2->fresh()->score)->toBeGreaterThan(0);
    expect($player1->fresh()->score)->toBeGreaterThan($player2->fresh()->score);
});
