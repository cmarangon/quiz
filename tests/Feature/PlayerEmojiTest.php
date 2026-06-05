<?php

use App\Livewire\JoinGame;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use App\Support\PlayerEmojis;
use Livewire\Livewire;

test('curated emoji set is non-empty and unique', function () {
    $all = PlayerEmojis::all();

    expect($all)->toBeArray()
        ->and(count($all))->toBeGreaterThanOrEqual(12)
        ->and($all)->toEqual(array_values(array_unique($all)));
});

test('a player can persist an emoji', function () {
    $player = Player::factory()->create(['emoji' => '🚀']);

    expect($player->fresh()->emoji)->toBe('🚀');
});

test('joining persists the chosen emoji', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'waiting']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'EmojiFan')
        ->set('emoji', '🚀')
        ->call('join')
        ->assertRedirectContains('/game/'.$session->join_code.'/play');

    expect(Player::where('game_session_id', $session->id)->first()->emoji)->toBe('🚀');
});

test('joining without an emoji fails validation', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'waiting']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'NoEmoji')
        ->set('emoji', '')
        ->call('join')
        ->assertHasErrors(['emoji']);
});

test('joining with an emoji outside the curated set fails validation', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'waiting']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'Sneaky')
        ->set('emoji', '😀')
        ->call('join')
        ->assertHasErrors(['emoji']);
});

test('lewd list is non-empty and unique', function () {
    $lewd = PlayerEmojis::lewd();

    expect($lewd)->toBeArray()
        ->and(count($lewd))->toBeGreaterThanOrEqual(1)
        ->and($lewd)->toEqual(array_values(array_unique($lewd)));
});

test('surprise me sets a lewd emoji that passes validation', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'waiting']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->call('surpriseMe')
        ->tap(fn ($c) => expect(PlayerEmojis::lewd())->toContain($c->get('emoji')))
        ->set('nickname', 'Cheeky')
        ->call('join')
        ->assertHasNoErrors()
        ->assertRedirectContains('/game/'.$session->join_code.'/play');

    expect(PlayerEmojis::lewd())
        ->toContain(Player::where('game_session_id', $session->id)->first()->emoji);
});

test('finish leaderboard includes player emojis', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'finished']);
    Player::factory()->create(['game_session_id' => $session->id, 'nickname' => 'A', 'emoji' => '🚀', 'score' => 10]);

    $leaderboard = $session->players()->orderByDesc('score')->get()
        ->map(fn ($p) => ['nickname' => $p->nickname, 'emoji' => $p->emoji, 'score' => $p->score])
        ->toArray();

    expect($leaderboard[0]['emoji'])->toBe('🚀');
});
