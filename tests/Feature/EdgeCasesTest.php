<?php

use App\Actions\SubmitAnswer;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;

test('late answer after timer is rejected', function () {
    Event::fake();
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create();
    $question = Question::factory()->for($category)->create([
        'time_limit_seconds' => 30,
        'correct_answer' => 'Option A',
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create();
    $player = Player::factory()->for($session, 'gameSession')->create();
    app(GameService::class)->start($session);

    // Answer after time limit + grace period (30s + 500ms = 30500ms)
    $action = app(SubmitAnswer::class);
    $action->execute($session->fresh(), $player, $question->id, 'Option A', 31000);
})->throws(LogicException::class);

test('disconnected player scores zero for missed questions', function () {
    Event::fake();
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $category = Category::factory()->for($quiz)->create();
    Question::factory()->for($category)->count(2)->sequence(
        ['order' => 0, 'correct_answer' => 'Option A'],
        ['order' => 1, 'correct_answer' => 'Option B'],
    )->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create();
    $player = Player::factory()->for($session, 'gameSession')->create(['is_connected' => false]);

    app(GameService::class)->start($session);

    // Player never answers — score stays 0
    expect($player->fresh()->score)->toBe(0);
});
