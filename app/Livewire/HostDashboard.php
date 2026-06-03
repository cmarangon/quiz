<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Services\GameService;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class HostDashboard extends Component
{
    public GameSession $session;

    public int $answeredCount = 0;

    public int $totalPlayers = 0;

    public ?int $currentQuestionId = null;

    public int $countdownSeconds = 5;

    public string $phase = 'lobby';

    public string $spectatorUrl = '';

    public string $spectatorQrCodeSvg = '';

    public function mount(string $code): void
    {
        $this->session = GameSession::where('join_code', strtoupper($code))->firstOrFail();

        abort_unless($this->session->host_user_id === Auth::id(), 403);

        $this->spectatorUrl = route('game.spectator', $this->session->join_code);
        $this->spectatorQrCodeSvg = QrCodeService::svg($this->spectatorUrl, 200);

        $this->totalPlayers = $this->session->players()->count();
        $this->phase = match ($this->session->status) {
            'waiting' => 'lobby',
            'playing' => 'playing',
            'reviewing' => 'reviewing',
            'finished' => 'finished',
            default => 'lobby',
        };

        if (in_array($this->session->status, ['playing', 'reviewing'], true)) {
            $this->currentQuestionId = app(GameService::class)->getCurrentQuestion($this->session)?->id;
        }
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
        $this->session->unsetRelation('players');
    }

    public function onPlayerAnswered(array $payload): void
    {
        if (isset($payload['question_id']) && $payload['question_id'] !== $this->currentQuestionId) {
            return;
        }

        $this->answeredCount = $payload['answered_count'];
        $this->totalPlayers = $payload['total_players'];
    }

    public function onQuestionStarted(array $payload): void
    {
        $this->phase = 'playing';
        $this->answeredCount = 0;
        $this->currentQuestionId = $payload['question_id'] ?? null;
    }

    public function onQuestionEnded(array $payload): void
    {
        $this->phase = 'reviewing';
    }

    public function onGameFinished(array $payload): void
    {
        $this->phase = 'finished';
    }

    public function pollPlayers(): void
    {
        $this->totalPlayers = $this->session->players()->count();
        $this->session->unsetRelation('players');
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
        if ($this->phase !== 'playing') {
            return;
        }

        app(GameService::class)->finishQuestion($this->session);
        $this->session->refresh();
        $this->phase = 'reviewing';
    }

    /**
     * Automatically end the question once every player has answered. Driven by a
     * client-side countdown timer. Guarded so a stale timer from a previous
     * question (or a double-fire) can never end the wrong question early.
     */
    public function autoFinishQuestion(int $questionId): void
    {
        if ($this->phase !== 'playing' || $questionId !== $this->currentQuestionId) {
            return;
        }

        if ($this->totalPlayers <= 0 || $this->answeredCount < $this->totalPlayers) {
            return;
        }

        $this->finishQuestion();
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
