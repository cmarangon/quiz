<?php

namespace App\Livewire;

use App\Actions\SubmitAnswer;
use App\Models\GameSession;
use App\Models\Player;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.game')]
class PlayerScreen extends Component
{
    public GameSession $session;

    public ?Player $player = null;

    public ?array $currentQuestion = null;

    public string $phase = 'waiting';

    public ?array $lastResult = null;

    public array $leaderboard = [];

    public function mount(string $code): void
    {
        $this->session = GameSession::where('join_code', strtoupper($code))->firstOrFail();

        $playerId = request()->query('player_id');
        if ($playerId) {
            $this->player = Player::where('id', $playerId)
                ->where('game_session_id', $this->session->id)
                ->first();
        }

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
            "echo:game.{$this->session->id},.question.started" => 'onQuestionStarted',
            "echo:game.{$this->session->id},.question.ended" => 'onQuestionEnded',
            "echo:game.{$this->session->id},.game.finished" => 'onGameFinished',
        ];
    }

    public function onQuestionStarted(array $payload): void
    {
        $this->phase = 'answering';
        $this->currentQuestion = $payload;
        $this->lastResult = null;
    }

    public function onQuestionEnded(array $payload): void
    {
        $this->phase = 'review';
    }

    public function onGameFinished(array $payload): void
    {
        $this->phase = 'finished';
        $this->leaderboard = $payload['leaderboard'];
    }

    public function submitAnswer(string $answer): void
    {
        if (! $this->player || ! $this->currentQuestion) {
            return;
        }

        $action = app(SubmitAnswer::class);

        $this->lastResult = $action->execute(
            $this->session,
            $this->player,
            $this->currentQuestion['question_id'],
            $answer,
            $this->currentQuestion['time_taken_ms'] ?? 10000,
        );

        $this->phase = 'answered';
    }

    public function render()
    {
        return view('livewire.player-screen')->title('Playing');
    }
}
