<?php

use App\Actions\SubmitAnswer;
use App\Events\QuestionEnded;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();

    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    $this->session = GameSession::factory()->for($this->quiz)->for($this->user, 'host')->create(['status' => 'waiting']);
    $this->action = app(SubmitAnswer::class);
});

test('finishQuestion broadcasts the answer distribution grouped by option with avatars', function () {
    $question = Question::factory()->for($this->category)->create([
        'order' => 0,
        'type' => 'multiple_choice',
        'options' => [
            ['label' => 'Option A'],
            ['label' => 'Option B'],
            ['label' => 'Option C'],
            ['label' => 'Option D'],
        ],
        'correct_answer' => 'Option A',
    ]);

    $alice = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Alice', 'emoji' => '🦊']);
    $bob = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Bob', 'emoji' => '🐸']);
    $cara = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Cara', 'emoji' => '🐱']);

    app(GameService::class)->start($this->session);

    $this->action->execute($this->session->fresh(), $alice, $question->id, 'Option A', 5000);
    $this->action->execute($this->session->fresh(), $bob, $question->id, 'Option A', 5000);
    $this->action->execute($this->session->fresh(), $cara, $question->id, 'Option B', 5000);

    app(GameService::class)->finishQuestion($this->session->fresh());

    Event::assertDispatched(QuestionEnded::class, function (QuestionEnded $event) {
        expect($event->distribution)->toBe([
            'Option A' => [
                ['nickname' => 'Alice', 'emoji' => '🦊'],
                ['nickname' => 'Bob', 'emoji' => '🐸'],
            ],
            'Option B' => [
                ['nickname' => 'Cara', 'emoji' => '🐱'],
            ],
        ]);

        return true;
    });
});

test('finishQuestion broadcasts an empty distribution for geo-guesser questions', function () {
    $question = Question::factory()->for($this->category)->geoGuesser()->create([
        'order' => 0,
        'correct_answer' => ['lat' => 0.0, 'lng' => 0.0],
        'options' => ['threshold_km' => 0, 'max_distance_km' => 2000, 'zoom' => 2, 'center' => ['lat' => 20, 'lng' => 0]],
    ]);

    $player = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Geo']);

    app(GameService::class)->start($this->session);
    $this->action->execute($this->session->fresh(), $player, $question->id, ['lat' => 1.0, 'lng' => 1.0], 5000);

    app(GameService::class)->finishQuestion($this->session->fresh());

    Event::assertDispatched(QuestionEnded::class, function (QuestionEnded $event) {
        expect($event->distribution)->toBe([]);

        return true;
    });
});

test('finishQuestion groups true/false answers by their label', function () {
    $question = Question::factory()->for($this->category)->create([
        'order' => 0,
        'type' => 'true_false',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
    ]);

    $yes = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Yes', 'emoji' => '✅']);
    $no = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'No', 'emoji' => '❌']);

    app(GameService::class)->start($this->session);

    $this->action->execute($this->session->fresh(), $yes, $question->id, 'True', 5000);
    $this->action->execute($this->session->fresh(), $no, $question->id, 'False', 5000);

    app(GameService::class)->finishQuestion($this->session->fresh());

    Event::assertDispatched(QuestionEnded::class, function (QuestionEnded $event) {
        expect($event->distribution)->toBe([
            'True' => [['nickname' => 'Yes', 'emoji' => '✅']],
            'False' => [['nickname' => 'No', 'emoji' => '❌']],
        ]);

        return true;
    });
});

test('finishQuestion excludes timed-out players with no answer from the distribution', function () {
    $question = Question::factory()->for($this->category)->create([
        'order' => 0,
        'type' => 'multiple_choice',
        'options' => [
            ['label' => 'Option A'],
            ['label' => 'Option B'],
            ['label' => 'Option C'],
            ['label' => 'Option D'],
        ],
        'correct_answer' => 'Option A',
    ]);

    $answered = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Quick', 'emoji' => '🐇']);
    $timedOut = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Slow', 'emoji' => '🐢']);

    app(GameService::class)->start($this->session);

    $this->action->execute($this->session->fresh(), $answered, $question->id, 'Option A', 5000);
    $this->action->timeout($this->session->fresh(), $timedOut, $question->id);

    app(GameService::class)->finishQuestion($this->session->fresh());

    Event::assertDispatched(QuestionEnded::class, function (QuestionEnded $event) {
        expect($event->distribution)->toBe([
            'Option A' => [['nickname' => 'Quick', 'emoji' => '🐇']],
        ]);

        return true;
    });
});
