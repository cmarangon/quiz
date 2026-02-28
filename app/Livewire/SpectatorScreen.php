<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Services\QrCodeService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.spectator')]
class SpectatorScreen extends Component
{
    public GameSession $session;

    public ?array $currentTheme = null;
    public ?array $currentQuestion = null;
    public int $answeredCount = 0;
    public int $totalPlayers = 0;
    public array $scores = [];
    public array $leaderboard = [];
    public string $phase = 'lobby';
    public mixed $correctAnswer = null;

    /** @var list<string> */
    public array $playerNames = [];

    public string $quizTitle = '';

    public string $joinUrl = '';

    public string $qrCodeSvg = '';

    public function mount(string $code): void
    {
        $this->session = GameSession::where('join_code', strtoupper($code))->firstOrFail();
        $this->session->loadMissing('quiz');
        $this->quizTitle = $this->session->quiz->title ?? '';
        $this->joinUrl = route('game.join', $this->session->join_code);
        $this->qrCodeSvg = QrCodeService::svg($this->joinUrl, 250);
        $this->loadPlayers();
        $this->totalPlayers = $this->session->players()->count();

        if ($this->session->status === 'finished') {
            $this->phase = 'finished';
            $this->leaderboard = $this->session->players()
                ->orderByDesc('score')
                ->get()
                ->map(fn ($p) => ['nickname' => $p->nickname, 'score' => $p->score])
                ->toArray();
        }
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        return [
            "echo:game.{$this->session->id},.player.joined" => 'onPlayerJoined',
            "echo:game.{$this->session->id},.category.changed" => 'onCategoryChanged',
            "echo:game.{$this->session->id},.question.started" => 'onQuestionStarted',
            "echo:game.{$this->session->id},.player.answered" => 'onPlayerAnswered',
            "echo:game.{$this->session->id},.question.ended" => 'onQuestionEnded',
            "echo:game.{$this->session->id},.game.finished" => 'onGameFinished',
        ];
    }

    public function onPlayerJoined(array $payload): void
    {
        $this->totalPlayers = $payload['player_count'];
        $this->loadPlayers();
    }

    private function loadPlayers(): void
    {
        $this->playerNames = $this->session->players()
            ->pluck('nickname')
            ->toArray();
    }

    public function onCategoryChanged(array $payload): void
    {
        $this->phase = 'category-intro';
        $theme = $payload['theme'] ?? 'default';
        $this->currentTheme = config("themes.{$theme}", config('themes.default'));
        $this->currentTheme['name'] = $payload['name'] ?? '';
        $this->currentTheme['key'] = $theme;
    }

    public function onQuestionStarted(array $payload): void
    {
        $this->phase = 'question';
        $this->currentQuestion = $payload;
        $this->answeredCount = 0;
        $this->correctAnswer = null;
    }

    public function onPlayerAnswered(array $payload): void
    {
        $this->answeredCount = $payload['answered_count'];
        $this->totalPlayers = $payload['total_players'];
    }

    public function onQuestionEnded(array $payload): void
    {
        $this->phase = 'review';
        $this->correctAnswer = $payload['correct_answer'];
        $this->scores = $payload['scores'];
    }

    public function onGameFinished(array $payload): void
    {
        $this->phase = 'finished';
        $this->leaderboard = $payload['leaderboard'];
    }

    public function render()
    {
        return view('livewire.spectator-screen', [
            'players' => $this->session->players,
            'playerCount' => $this->totalPlayers,
        ])->title('Game - ' . $this->session->join_code);
    }
}
