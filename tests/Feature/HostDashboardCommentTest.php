<?php

use App\Livewire\HostDashboard;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('host dashboard shows question comment during playing phase', function () {
    Event::fake();

    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $category = Category::factory()->for($quiz)->create(['order' => 0]);
    Question::factory()->for($category)->create([
        'order' => 0,
        'type' => 'true_false',
        'body' => 'Is water wet?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Fun fact: debated by scientists',
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    app(GameService::class)->start($session);
    $session->refresh();

    Livewire::actingAs($user)
        ->test(HostDashboard::class, ['code' => $session->join_code])
        ->assertSee('Fun fact: debated by scientists');
});

test('host dashboard hides comment when question has none', function () {
    Event::fake();

    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $category = Category::factory()->for($quiz)->create(['order' => 0]);
    Question::factory()->for($category)->create([
        'order' => 0,
        'type' => 'true_false',
        'body' => 'Is water wet?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => null,
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    app(GameService::class)->start($session);
    $session->refresh();

    Livewire::actingAs($user)
        ->test(HostDashboard::class, ['code' => $session->join_code])
        ->assertDontSee('Note:');
});

test('host dashboard does not show comment in lobby phase', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create(['order' => 0]);
    Question::factory()->for($category)->create([
        'order' => 0,
        'type' => 'true_false',
        'body' => 'Is water wet?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Should not appear in lobby',
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    Livewire::actingAs($user)
        ->test(HostDashboard::class, ['code' => $session->join_code])
        ->assertDontSee('Should not appear in lobby');
});

test('host dashboard shows question comment during reviewing phase', function () {
    Event::fake();

    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $category = Category::factory()->for($quiz)->create(['order' => 0]);
    Question::factory()->for($category)->create([
        'order' => 0,
        'type' => 'true_false',
        'body' => 'Is water wet?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Reviewing phase note',
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    app(GameService::class)->start($session);
    app(GameService::class)->finishQuestion($session->fresh());
    $session->refresh();

    Livewire::actingAs($user)
        ->test(HostDashboard::class, ['code' => $session->join_code])
        ->assertSee('Reviewing phase note');
});
