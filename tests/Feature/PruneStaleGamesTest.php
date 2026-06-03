<?php

use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('prune-stale deletes stale open games and their children', function () {
    $stale = GameSession::factory()->create(['status' => 'waiting']);
    $player = Player::factory()->for($stale, 'gameSession')->create();
    $answer = PlayerAnswer::factory()
        ->for($stale, 'gameSession')
        ->for($player)
        ->create();
    GameSession::query()->whereKey($stale)
        ->update(['updated_at' => now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES + 1)]);

    $fresh = GameSession::factory()->create(['status' => 'playing']);
    $finished = GameSession::factory()->create(['status' => 'finished']);
    GameSession::query()->whereKey($finished)->update(['updated_at' => now()->subDay()]);

    $this->artisan('games:prune-stale')
        ->expectsOutputToContain('Pruned 1 stale game session(s).')
        ->assertSuccessful();

    expect(GameSession::find($stale->id))->toBeNull();
    expect(Player::find($player->id))->toBeNull();
    expect(PlayerAnswer::find($answer->id))->toBeNull();
    expect(GameSession::find($fresh->id))->not->toBeNull();
    expect(GameSession::find($finished->id))->not->toBeNull();
});
