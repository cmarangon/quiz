<?php

use App\Livewire\HostDashboard;
use App\Livewire\PlayerScreen;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Carbon;
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

test('the lobby roster marks a stale player as reconnecting and reconciles is_connected', function () {
    Carbon::setTestNow('2026-06-07 12:00:00');

    $user = User::factory()->create();
    $session = GameSession::factory()->create([
        'host_user_id' => $user->id,
        'status' => 'waiting',
    ]);

    $online = Player::factory()->create([
        'game_session_id' => $session->id,
        'nickname' => 'Fresh',
        'last_seen_at' => now(),
        'is_connected' => true,
    ]);
    $stale = Player::factory()->create([
        'game_session_id' => $session->id,
        'nickname' => 'Dropped',
        'last_seen_at' => now()->subSeconds(Player::PRESENCE_THRESHOLD_SECONDS + 5),
        'is_connected' => true,
    ]);

    Livewire::actingAs($user)
        ->test(HostDashboard::class, ['code' => $session->join_code])
        ->call('pollPlayers')
        ->assertSeeHtml('data-player-nickname="Dropped" data-connected="false"')
        ->assertSeeHtml('data-player-nickname="Fresh" data-connected="true"');

    expect($stale->refresh()->is_connected)->toBeFalse();
    expect($online->refresh()->is_connected)->toBeTrue();
});
