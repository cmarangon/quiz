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

    public int $answeredCount = 0;
    public int $totalPlayers = 0;
    public string $phase = 'lobby';

    public function mount(string $code): void
    {
        $this->session = GameSession::where('join_code', strtoupper($code))->firstOrFail();

        abort_unless($this->session->host_user_id === Auth::id(), 403);

        $this->totalPlayers = $this->session->players()->count();
        $this->phase = match ($this->session->status) {
            'waiting' => 'lobby',
            'playing' => 'playing',
            'reviewing' => 'reviewing',
            'finished' => 'finished',
            default => 'lobby',
        };
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        return [
            "echo:game.{$this->session->id},.player.joined" => 'onPlayerJoined',
            "echo:game.{$this->session->id},.player.answered" => 'onPlayerAnswered',
            "echo:game.{$this->session->id},.question.ended" => 'onQuestionEnded',
            "echo:game.{$this->session->id},.question.started" => 'onQuestionStarted',
            "echo:game.{$this->session->id},.game.finished" => 'onGameFinished',
        ];
    }

    public function onPlayerJoined(array $payload): void
    {
        $this->totalPlayers = $payload['player_count'];
    }

    public function onPlayerAnswered(array $payload): void
    {
        $this->answeredCount = $payload['answered_count'];
        $this->totalPlayers = $payload['total_players'];
    }

    public function onQuestionStarted(array $payload): void
    {
        $this->phase = 'playing';
        $this->answeredCount = 0;
    }

    public function onQuestionEnded(array $payload): void
    {
        $this->phase = 'reviewing';
    }

    public function onGameFinished(array $payload): void
    {
        $this->phase = 'finished';
    }

    public function startGame(): void
    {
        app(GameService::class)->start($this->session);
        $this->session->refresh();
        $this->phase = 'playing';
        $this->answeredCount = 0;
    }

    public function finishQuestion(): void
    {
        app(GameService::class)->finishQuestion($this->session);
        $this->session->refresh();
    }

    public function nextQuestion(): void
    {
        $result = app(GameService::class)->advanceToNextQuestion($this->session);
        $this->session->refresh();

        if (! $result) {
            $this->phase = 'finished';
        } else {
            $this->phase = 'playing';
            $this->answeredCount = 0;
        }
    }

    public function render()
    {
        return view('livewire.host-dashboard', [
            'players' => $this->session->players,
        ])->title('Host Dashboard');
    }
}
