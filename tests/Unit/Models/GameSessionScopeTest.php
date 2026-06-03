<?php

use App\Models\GameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stale scope selects open games idle past the timeout', function () {
    $staleWaiting = GameSession::factory()->create(['status' => 'waiting']);
    GameSession::query()->whereKey($staleWaiting)
        ->update(['updated_at' => now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES + 1)]);

    $stalePlaying = GameSession::factory()->create(['status' => 'playing']);
    GameSession::query()->whereKey($stalePlaying)
        ->update(['updated_at' => now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES + 1)]);

    $staleReviewing = GameSession::factory()->create(['status' => 'reviewing']);
    GameSession::query()->whereKey($staleReviewing)
        ->update(['updated_at' => now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES + 1)]);

    $freshWaiting = GameSession::factory()->create(['status' => 'waiting']);

    $staleFinished = GameSession::factory()->create(['status' => 'finished']);
    GameSession::query()->whereKey($staleFinished)
        ->update(['updated_at' => now()->subDay()]);

    $ids = GameSession::stale()->pluck('id');

    expect($ids)->toContain($staleWaiting->id)
        ->toContain($stalePlaying->id)
        ->toContain($staleReviewing->id)
        ->not->toContain($freshWaiting->id)
        ->not->toContain($staleFinished->id);
});
