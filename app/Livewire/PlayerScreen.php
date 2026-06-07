<?php

namespace App\Livewire;

use App\Actions\SubmitAnswer;
use App\Events\QuestionStarted;
use App\Events\ReactionSent;
use App\Models\GameSession;
use App\Models\Player;
use App\Services\GameService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.game')]
class PlayerScreen extends Component
{
    public GameSession $session;

    public ?Player $player = null;

    public bool $resumeFailed = false;

    public ?array $currentQuestion = null;

    public string $phase = 'waiting';

    public ?string $themeKey = null;

    public ?array $lastResult = null;

    public bool $timedOut = false;

    public ?array $lastGuess = null;

    public mixed $correctAnswer = null;

    public array $leaderboard = [];

    public function mount(string $code): void
    {
        $this->session = GameSession::where('join_code', strtoupper($code))->firstOrFail();

        $playerId = request()->query('player_id');
        if ($playerId) {
            $this->player = Player::where('id', $playerId)
                ->where('game_session_id', $this->session->id)
                ->first();

            // A player_id was supplied but does not belong to this session
            // (game reset, or the code was reused for a new session). Signal
            // the blade to clear the stale localStorage key and bounce to join.
            $this->resumeFailed = $this->player === null;
        }

        if ($this->session->status === 'finished') {
            $this->phase = 'finished';
            $this->leaderboard = $this->session->players()
                ->orderByDesc('score')
                ->get()
                ->map(fn ($p) => ['nickname' => $p->nickname, 'emoji' => $p->emoji, 'score' => $p->score])
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
        $this->themeKey = $payload['theme'] ?? 'default';
        $this->lastResult = null;
        $this->lastGuess = null;
        $this->correctAnswer = null;
        $this->timedOut = false;
    }

    /**
     * Server-authoritative elapsed time for the current question, derived from
     * the broadcast start timestamp (epoch ms) and the server clock. Never
     * trusts a client-supplied duration, so the time limit and time-bonus
     * scoring cannot be gamed from the browser.
     */
    private function elapsedMs(): int
    {
        $startedAt = $this->currentQuestion['started_at'] ?? null;

        if (! $startedAt) {
            return 0;
        }

        return max(0, (int) (now()->getTimestampMs() - $startedAt));
    }

    public function markTimedOut(mixed $answer = null): void
    {
        if ($this->phase !== 'answering' || ! $this->player || ! $this->currentQuestion) {
            return;
        }

        $action = app(SubmitAnswer::class);
        $questionId = $this->currentQuestion['question_id'];
        $hasSelection = ! ($answer === null || $answer === '' || $answer === []);

        try {
            if ($hasSelection) {
                // The clock ran out while the player had a pending selection (a
                // true/false choice, an ordering arrangement, or a dropped map
                // pin). Submit it on their behalf rather than wasting the round.
                // Scoring uses the full time limit so it matches a buzzer-beating
                // manual submit: the time bonus is gone, but a correct answer
                // still earns base points and keeps the player's streak alive.
                if (($this->currentQuestion['type'] ?? null) === 'geo_guesser') {
                    $this->lastGuess = $answer;
                }

                $this->lastResult = $action->execute(
                    $this->session,
                    $this->player,
                    $questionId,
                    $answer,
                    (int) ($this->currentQuestion['time_limit_seconds'] ?? 0) * 1000,
                );
            } else {
                $this->lastResult = $action->timeout($this->session, $this->player, $questionId);
                $this->timedOut = true;
            }
        } catch (\Throwable $e) {
            // Already answered or the round moved on. Only surface the timeout
            // state when the player had nothing selected to fall back on.
            $this->timedOut = $this->timedOut || ! $hasSelection;
        }

        $this->phase = 'answered';
    }

    /**
     * Fallback reconciliation for clients that missed a one-shot broadcast
     * (e.g. their WebSocket subscription wasn't established yet when the host
     * started the game or advanced the question). Driven by wire:poll while the
     * player is in a passive phase.
     */
    public function pollState(): void
    {
        $this->session->refresh();

        if ($this->session->status === 'finished') {
            if ($this->phase !== 'finished') {
                $this->phase = 'finished';
                $this->leaderboard = $this->session->players()
                    ->orderByDesc('score')
                    ->get()
                    ->map(fn ($p) => ['nickname' => $p->nickname, 'emoji' => $p->emoji, 'score' => $p->score])
                    ->toArray();
            }

            return;
        }

        if ($this->session->status !== 'playing') {
            return;
        }

        $question = app(GameService::class)->getCurrentQuestion($this->session);

        if (! $question || ($this->currentQuestion['question_id'] ?? null) === $question->id) {
            return;
        }

        $this->onQuestionStarted((new QuestionStarted($this->session, $question))->broadcastWith());
    }

    public function onQuestionEnded(array $payload): void
    {
        $this->phase = 'review';
        $this->correctAnswer = $payload['correct_answer'] ?? null;
    }

    public function onGameFinished(array $payload): void
    {
        $this->phase = 'finished';
        $this->leaderboard = $payload['leaderboard'];
    }

    /**
     * Broadcast a tapped reaction emoji to the spectator screen during the
     * answer reveal. Guarded server-side on both the review phase and the
     * configured allowlist so a tampered client cannot float arbitrary content
     * onto the TV. Changes no component state, so the Livewire re-render is a
     * no-op. Broadcasting is best-effort: a transport hiccup must never break
     * the player's screen.
     */
    public function react(string $emoji): void
    {
        if ($this->phase !== 'review' || ! $this->player) {
            return;
        }

        if (! in_array($emoji, config('reactions.emojis'), true)) {
            return;
        }

        try {
            broadcast(new ReactionSent($this->session, $emoji));
        } catch (\Throwable $e) {
            // Swallow — see method docblock.
        }
    }

    public function submitAnswer(string $answer): void
    {
        if (! $this->player || ! $this->currentQuestion) {
            return;
        }

        $action = app(SubmitAnswer::class);

        try {
            $this->lastResult = $action->execute(
                $this->session,
                $this->player,
                $this->currentQuestion['question_id'],
                $answer,
                $this->elapsedMs(),
            );
        } catch (\LogicException $e) {
            $this->timedOut = true;
        }

        $this->phase = 'answered';
    }

    public function submitGeoGuess(float $lat, float $lng): void
    {
        if (! $this->player || ! $this->currentQuestion) {
            return;
        }

        $this->lastGuess = ['lat' => $lat, 'lng' => $lng];

        $action = app(SubmitAnswer::class);

        try {
            $this->lastResult = $action->execute(
                $this->session,
                $this->player,
                $this->currentQuestion['question_id'],
                $this->lastGuess,
                $this->elapsedMs(),
            );
        } catch (\LogicException $e) {
            $this->timedOut = true;
        }

        $this->phase = 'answered';
    }

    public function submitOrder(array $order): void
    {
        if (! $this->player || ! $this->currentQuestion) {
            return;
        }

        $action = app(SubmitAnswer::class);

        try {
            $this->lastResult = $action->execute(
                $this->session,
                $this->player,
                $this->currentQuestion['question_id'],
                array_values($order),
                $this->elapsedMs(),
            );
        } catch (\LogicException $e) {
            $this->timedOut = true;
        }

        $this->phase = 'answered';
    }

    public function render()
    {
        return view('livewire.player-screen')->title('Playing');
    }
}
