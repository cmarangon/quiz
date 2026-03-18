<?php

namespace App\Livewire;

use App\Models\GameSession;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.welcome')]
class WelcomePage extends Component
{
    public function render()
    {
        $activeGames = GameSession::with(['quiz', 'players'])
            ->whereIn('status', ['waiting', 'playing', 'reviewing'])
            ->latest()
            ->get();

        return view('livewire.welcome-page', [
            'activeGames' => $activeGames,
        ]);
    }
}
