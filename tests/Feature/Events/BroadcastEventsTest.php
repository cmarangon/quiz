<?php

use App\Events\GameFinished;
use App\Events\PlayerJoined;
use App\Events\QuestionEnded;
use App\Events\QuestionStarted;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;

test('PlayerJoined broadcasts on game channel', function () {
    $session = GameSession::factory()->create();
    $player = Player::factory()->for($session, 'gameSession')->create();
    $event = new PlayerJoined($session, $player);
    $channels = $event->broadcastOn();
    expect(collect($channels)->map->name)->toContain('game.'.$session->id);
});

test('QuestionStarted broadcasts on game channel without correct answer', function () {
    $session = GameSession::factory()->create();
    $question = Question::factory()->create();
    $event = new QuestionStarted($session, $question);
    $data = $event->broadcastWith();
    expect($data)->toHaveKey('body');
    expect($data)->toHaveKey('options');
    expect($data)->not->toHaveKey('correct_answer');
});

test('QuestionStarted broadcasts the category theme and name', function () {
    $category = Category::factory()->create(['theme' => 'science', 'name' => 'Science']);
    $question = Question::factory()->for($category)->create();
    $session = GameSession::factory()->create();

    $data = (new QuestionStarted($session, $question))->broadcastWith();

    expect($data['theme'])->toBe('science');
    expect($data['category_name'])->toBe('Science');
});

test('QuestionStarted falls back to the default theme without a category', function () {
    $session = GameSession::factory()->create();
    $question = Question::factory()->create();
    $question->setRelation('category', null);

    $data = (new QuestionStarted($session, $question))->broadcastWith();

    expect($data['theme'])->toBe('default');
});

test('QuestionEnded broadcasts with correct answer and scores', function () {
    $session = GameSession::factory()->create();
    $question = Question::factory()->create();
    $event = new QuestionEnded($session, $question, scores: [['player_id' => 1, 'points' => 10]]);
    $data = $event->broadcastWith();
    expect($data)->toHaveKey('correct_answer');
    expect($data)->toHaveKey('scores');
});

test('GameFinished broadcasts final leaderboard', function () {
    $session = GameSession::factory()->create();
    $event = new GameFinished($session, leaderboard: [['nickname' => 'Alex', 'score' => 100]]);
    $data = $event->broadcastWith();
    expect($data)->toHaveKey('leaderboard');
});
