<?php

use App\Models\Category;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create();
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    Question::factory()->count(3)->for($this->category)->sequence(
        ['order' => 0],
        ['order' => 1],
        ['order' => 2],
    )->create();
    $this->session = GameSession::factory()
        ->for($this->quiz)
        ->for($this->user, 'host')
        ->create(['status' => 'waiting']);
    $this->service = new GameService;
});

test('start game transitions from waiting to playing', function () {
    $this->service->start($this->session);
    expect($this->session->fresh()->status)->toBe('playing');
    expect($this->session->fresh()->current_question_index)->toBe(0);
    expect($this->session->fresh()->current_category_id)->toBe($this->category->id);
});

test('start game fails if not in waiting status', function () {
    $this->session->update(['status' => 'playing']);
    $this->service->start($this->session);
})->throws(LogicException::class);

test('getCurrentQuestion returns the correct question', function () {
    $this->service->start($this->session);
    $question = $this->service->getCurrentQuestion($this->session->fresh());
    expect($question->order)->toBe(0);
});

test('advanceToNextQuestion increments index', function () {
    $this->service->start($this->session);
    $this->session->update(['status' => 'reviewing']);
    $result = $this->service->advanceToNextQuestion($this->session->fresh());
    expect($result)->toBeTrue();
    expect($this->session->fresh()->current_question_index)->toBe(1);
    expect($this->session->fresh()->status)->toBe('playing');
});

test('advanceToNextQuestion returns false when no more questions', function () {
    $this->service->start($this->session);
    $this->session->update(['status' => 'reviewing', 'current_question_index' => 2]);
    $result = $this->service->advanceToNextQuestion($this->session->fresh());
    expect($result)->toBeFalse();
    expect($this->session->fresh()->status)->toBe('finished');
});

test('finishQuestion transitions from playing to reviewing', function () {
    $this->service->start($this->session);
    $this->service->finishQuestion($this->session->fresh());
    expect($this->session->fresh()->status)->toBe('reviewing');
});
