<?php

use App\Livewire\PlayerScreen;
use App\Models\GameSession;
use App\Models\Player;
use Livewire\Livewire;

test('a valid player_id resolves the player and does not fail resume', function () {
    $session = GameSession::factory()->create();
    $player = Player::factory()->create([
        'game_session_id' => $session->id,
        'nickname' => 'Resumer',
    ]);

    Livewire::withQueryParams(['player_id' => $player->id]);

    Livewire::test(PlayerScreen::class, ['code' => $session->join_code])
        ->assertSet('resumeFailed', false)
        ->assertSee('Resumer');
});

test('a stale player_id from a reset game flags resume failure and creates no player', function () {
    $session = GameSession::factory()->create();

    Livewire::withQueryParams(['player_id' => 999999]);

    Livewire::test(PlayerScreen::class, ['code' => $session->join_code])
        ->assertSet('resumeFailed', true)
        ->assertSet('player', null);

    expect(Player::count())->toBe(0);
});

test('a player_id belonging to another session flags resume failure', function () {
    $session = GameSession::factory()->create();
    $other = GameSession::factory()->create();
    $player = Player::factory()->create(['game_session_id' => $other->id]);

    Livewire::withQueryParams(['player_id' => $player->id]);

    Livewire::test(PlayerScreen::class, ['code' => $session->join_code])
        ->assertSet('resumeFailed', true)
        ->assertSet('player', null);
});

test('no player_id does not flag resume failure', function () {
    $session = GameSession::factory()->create();

    Livewire::test(PlayerScreen::class, ['code' => $session->join_code])
        ->assertSet('resumeFailed', false)
        ->assertSet('player', null);
});
