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
    config()->set('quiz.geo_guesser.threshold_km', 50);
    config()->set('quiz.geo_guesser.max_distance_km', 2000);

    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    $this->question = Question::factory()->for($this->category)->geoGuesser()->create([
        'order' => 0,
        'points' => 100,
        'correct_answer' => ['lat' => 0.0, 'lng' => 0.0],
        'options' => ['threshold_km' => 0, 'max_distance_km' => 2000, 'zoom' => 2, 'center' => ['lat' => 20, 'lng' => 0]],
    ]);
    $this->session = GameSession::factory()->for($this->quiz)->for($this->user, 'host')->create(['status' => 'waiting']);
    $this->player = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Geo']);

    app(GameService::class)->start($this->session);
    $this->action = app(SubmitAnswer::class);
});

test('exact geo guess earns full points and is correct', function () {
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        ['lat' => 0.0, 'lng' => 0.0],
        5000,
    );

    expect($result['is_correct'])->toBeTrue();
    expect($result['points_earned'])->toBe(100);
    expect($this->player->fresh()->score)->toBe(100);
});

test('partial geo guess earns partial points but does not count as correct', function () {
    // ~1000 km north → factor ≈ 0.5 → ≈ 50 points, outside the (zero) threshold.
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        ['lat' => 8.9932, 'lng' => 0.0],
        5000,
    );

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBeGreaterThan(40)->toBeLessThan(60);
    expect($this->player->fresh()->score)->toBe($result['points_earned']);
    expect($this->player->fresh()->streak)->toBe(0);
});

test('far geo guess earns zero points', function () {
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        ['lat' => 40.7128, 'lng' => -74.0060],
        5000,
    );

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->score)->toBe(0);
});

test('finishQuestion broadcasts every geo guess tagged with the player avatar', function () {
    $this->player->update(['emoji' => '🦊']);

    $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        ['lat' => 12.5, 'lng' => -8.0],
        5000,
    );

    app(GameService::class)->finishQuestion($this->session->fresh());

    Event::assertDispatched(QuestionEnded::class, function (QuestionEnded $event) {
        expect($event->guesses)->toHaveCount(1);

        return $event->guesses[0] === [
            'lat' => 12.5,
            'lng' => -8.0,
            'nickname' => 'Geo',
            'emoji' => '🦊',
        ];
    });
});
