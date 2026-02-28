<?php

namespace App\Livewire;

use App\Models\GameSession;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.spectator')]
class SpectatorScreen extends Component
{
    public GameSession $session;

    public function mount(string $code): void
    {
        $this->session = GameSession::where('join_code', strtoupper($code))->firstOrFail();
    }

    public function render()
    {
        return view('livewire.spectator-screen', [
            'players' => $this->session->players,
            'playerCount' => $this->session->players()->count(),
        ])->title('Game - ' . $this->session->join_code);
    }
}
