<?php

use App\Events\PlayerJoined;
use App\Events\CategoryChanged;
use App\Events\QuestionStarted;
use App\Events\PlayerAnswered;
use App\Events\QuestionEnded;
use App\Events\GameFinished;
use App\Models\GameSession;
use App\Models\Category;
use App\Models\Player;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;

test('PlayerJoined broadcasts on game channel', function () {
    $session = GameSession::factory()->create();
    $player = Player::factory()->for($session, 'gameSession')->create();
    $event = new PlayerJoined($session, $player);
    $channels = $event->broadcastOn();
    expect(collect($channels)->map->name)->toContain('game.' . $session->id);
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
