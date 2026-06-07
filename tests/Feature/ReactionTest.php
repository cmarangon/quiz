<?php

use App\Events\ReactionSent;
use App\Livewire\PlayerScreen;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create();
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    Question::factory()->for($this->category)->create(['order' => 0]);
    $this->session = GameSession::factory()
        ->for($this->quiz)
        ->for($this->user, 'host')
        ->create(['status' => 'playing']);
    $this->player = Player::factory()
        ->for($this->session, 'gameSession')
        ->create(['nickname' => 'Alex']);
});

test('reacting during review broadcasts ReactionSent', function () {
    Event::fake([ReactionSent::class]);
    Livewire::withQueryParams(['player_id' => $this->player->id]);

    Livewire::test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->set('phase', 'review')
        ->call('react', '🔥');

    Event::assertDispatched(ReactionSent::class, function (ReactionSent $e) {
        return $e->emoji === '🔥' && $e->session->id === $this->session->id;
    });
});

test('reacting outside review broadcasts nothing', function () {
    Event::fake([ReactionSent::class]);
    Livewire::withQueryParams(['player_id' => $this->player->id]);

    Livewire::test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->set('phase', 'answering')
        ->call('react', '🔥');

    Event::assertNotDispatched(ReactionSent::class);
});

test('reacting with a non-allowlisted emoji broadcasts nothing', function () {
    Event::fake([ReactionSent::class]);
    Livewire::withQueryParams(['player_id' => $this->player->id]);

    Livewire::test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->set('phase', 'review')
        ->call('react', '💀');

    Event::assertNotDispatched(ReactionSent::class);
});

test('reacting without a player broadcasts nothing', function () {
    Event::fake([ReactionSent::class]);

    // No player_id query param: an unjoined/spectator client.
    Livewire::test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->set('phase', 'review')
        ->call('react', '🔥');

    Event::assertNotDispatched(ReactionSent::class);
});

test('ReactionSent broadcasts the contract the spectator JS depends on', function () {
    $event = new ReactionSent($this->session, '🔥');

    expect($event->broadcastAs())->toBe('reaction.sent');
    expect($event->broadcastWith())->toBe(['emoji' => '🔥']);
    expect($event->broadcastOn()[0]->name)->toBe('game.'.$this->session->id);
});
