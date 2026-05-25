<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    /** @var array<int> */
    public array $selectedSessionIds = [];

    /** @var array<int> */
    public array $selectedPlayerIds = [];

    public ?string $pendingAction = null;

    public ?int $pendingId = null;

    public function confirmDeleteQuiz(int $id): void
    {
        $this->pendingAction = 'delete-quiz';
        $this->pendingId = $id;
    }

    public function deleteQuiz(): void
    {
        abort_unless($this->pendingAction === 'delete-quiz' && $this->pendingId !== null, 400);

        $quiz = Quiz::findOrFail($this->pendingId);
        abort_unless($quiz->user_id === Auth::id(), 403);

        $quiz->delete();

        $this->pendingAction = null;
        $this->pendingId = null;
        $this->dispatch('quiz-deleted');
    }

    public function confirmEndSession(int $id): void
    {
        $this->pendingAction = 'end-session';
        $this->pendingId = $id;
    }

    public function endSession(): void
    {
        abort_unless($this->pendingAction === 'end-session' && $this->pendingId !== null, 400);

        $session = GameSession::findOrFail($this->pendingId);
        abort_unless($session->host_user_id === Auth::id(), 403);
        abort_unless(in_array($session->status, ['waiting', 'playing', 'reviewing'], true), 400);

        $session->update(['status' => 'finished']);

        $this->pendingAction = null;
        $this->pendingId = null;
        $this->dispatch('game-ended');
    }

    public function confirmClearSessions(): void
    {
        $this->pendingAction = 'clear-sessions';
    }

    public function clearSessions(): void
    {
        abort_unless($this->pendingAction === 'clear-sessions', 400);

        GameSession::whereIn('id', $this->selectedSessionIds)
            ->where('host_user_id', Auth::id())
            ->where('status', 'finished')
            ->delete();

        $this->selectedSessionIds = [];
        $this->pendingAction = null;
        $this->dispatch('history-cleared');
    }

    public function runPendingAction(): void
    {
        match ($this->pendingAction) {
            'delete-quiz' => $this->deleteQuiz(),
            'end-session' => $this->endSession(),
            'clear-sessions' => $this->clearSessions(),
            'clear-players' => $this->clearPlayerEntries(),
            default => null,
        };
    }

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
