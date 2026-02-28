<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        $quizzes = $user->quizzes()
            ->withCount('categories')
            ->latest()
            ->get();

        $hostedSessions = GameSession::where('host_user_id', $user->id)
            ->with('quiz')
            ->withCount('players')
            ->latest()
            ->limit(10)
            ->get();

        $playerEntries = Player::where('user_id', $user->id)
            ->with(['gameSession.quiz'])
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.dashboard', [
            'quizzes' => $quizzes,
            'hostedSessions' => $hostedSessions,
            'playerEntries' => $playerEntries,
        ])->title('Dashboard');
    }
}
