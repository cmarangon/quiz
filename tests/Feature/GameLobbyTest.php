<?php

use App\Events\PlayerJoined;
use App\Http\Controllers\GameController;
use App\Livewire\HostDashboard;
use App\Livewire\JoinGame;
use App\Livewire\PlayerScreen;
use App\Livewire\SpectatorScreen;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('host can create a game session from a quiz', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->post(route('game.create', $quiz));

    $session = GameSession::where('quiz_id', $quiz->id)->first();

    expect($session)->not->toBeNull();
    expect($session->host_user_id)->toBe($user->id);
    expect($session->status)->toBe('waiting');

    $response->assertRedirect(route('game.host', $session->join_code));
});

test('player can join a game with a nickname', function () {
    Event::fake([PlayerJoined::class]);

    $session = GameSession::factory()->create(['status' => 'waiting']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'TestPlayer')
        ->call('join')
        ->assertRedirectContains('/game/' . $session->join_code . '/play');

    $player = Player::where('game_session_id', $session->id)->first();
    expect($player)->not->toBeNull();
    expect($player->nickname)->toBe('TestPlayer');

    Event::assertDispatched(PlayerJoined::class);
});

test('duplicate nickname gets number appended', function () {
    Event::fake([PlayerJoined::class]);

    $session = GameSession::factory()->create(['status' => 'waiting']);
    Player::factory()->create([
        'game_session_id' => $session->id,
        'nickname' => 'Alex',
    ]);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'Alex')
        ->call('join');

    $players = Player::where('game_session_id', $session->id)->pluck('nickname')->toArray();
    expect($players)->toContain('Alex');
    expect($players)->toContain('Alex 2');
});

test('player cannot join a game that is already playing', function () {
    $session = GameSession::factory()->create(['status' => 'playing']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'TestPlayer')
        ->call('join')
        ->assertHasErrors(['nickname']);
});

test('non-host cannot access host dashboard', function () {
    $host = User::factory()->create();
    $otherUser = User::factory()->create();
    $session = GameSession::factory()->create(['host_user_id' => $host->id]);

    $this->actingAs($otherUser)
        ->get(route('game.host', $session->join_code))
        ->assertStatus(403);
});

test('spectator page is accessible without auth', function () {
    $session = GameSession::factory()->create();

    $this->get(route('game.spectator', $session->join_code))
        ->assertStatus(200);
});
