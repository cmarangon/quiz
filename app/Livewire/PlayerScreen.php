<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Models\Player;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.game')]
class PlayerScreen extends Component
{
    public GameSession $session;
    public ?Player $player = null;

    public function mount(string $code): void
    {
        $this->session = GameSession::where('join_code', strtoupper($code))->firstOrFail();

        $playerId = request()->query('player_id');
        if ($playerId) {
            $this->player = Player::where('id', $playerId)
                ->where('game_session_id', $this->session->id)
                ->first();
        }
    }

    public function render()
    {
        return view('livewire.player-screen')->title('Playing');
    }
}
