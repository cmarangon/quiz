<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Services\GameService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class HostDashboard extends Component
{
    public GameSession $session;

    public function mount(string $code): void
    {
        $this->session = GameSession::where('join_code', strtoupper($code))->firstOrFail();

        abort_unless($this->session->host_user_id === Auth::id(), 403);
    }

    public function startGame(): void
    {
        app(GameService::class)->start($this->session);
        $this->session->refresh();
    }

    public function nextQuestion(): void
    {
        app(GameService::class)->advanceToNextQuestion($this->session);
        $this->session->refresh();
    }

    public function render()
    {
        return view('livewire.host-dashboard', [
            'players' => $this->session->players,
        ])->title('Host Dashboard');
    }
}
