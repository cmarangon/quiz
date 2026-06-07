<?php

namespace App\Actions;

use App\Events\PlayerAnswered;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Services\QuestionTypeRegistry;
use App\Services\ScoringService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

class SubmitAnswer
{
    public function __construct(
        private readonly QuestionTypeRegistry $registry,
        private readonly ScoringService $scoring,
    ) {}

    public function execute(
        GameSession $session,
        Player $player,
        int $questionId,
        mixed $answer,
        int $timeTakenMs,
    ): array {
        if ($session->status !== 'playing') {
            throw new LogicException('Game is not in playing state.');
        }

        $existing = PlayerAnswer::where('player_id', $player->id)
            ->where('question_id', $questionId)
            ->exists();

        if ($existing) {
            throw new LogicException('Player already answered this question.');
        }

        $question = $session->quiz->questions()->findOrFail($questionId);

        $gracePeriodMs = 500;
        $timeLimitMs = $question->time_limit_seconds * 1000 + $gracePeriodMs;
        if ($timeTakenMs > $timeLimitMs) {
            throw new LogicException('Answer submitted after time limit.');
        }

        $type = $this->registry->resolve($question->type);

        $isCorrect = $type->validateAnswer($answer, $question);
        $scoreFactor = $type->scoreFactor($answer, $question);
        $pointsEarned = 0;
        $breakdown = null;

        if ($scoreFactor > 0) {
            $breakdown = $this->scoring->breakdown(
                $question,
                $timeTakenMs,
                $player->streak,
                $session->quiz->settings,
                $scoreFactor,
            );
            $pointsEarned = $breakdown['total'];
        }

        // Insert the answer first so the (player_id, question_id) unique
        // constraint rejects a concurrent duplicate before we mutate score or
        // streak, keeping the whole operation atomic.
        try {
            DB::transaction(function () use ($session, $player, $questionId, $answer, $isCorrect, $timeTakenMs, $pointsEarned, $scoreFactor) {
                PlayerAnswer::create([
                    'player_id' => $player->id,
                    'game_session_id' => $session->id,
                    'question_id' => $questionId,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'time_taken_ms' => $timeTakenMs,
                    'points_earned' => $pointsEarned,
                ]);

                if ($scoreFactor > 0) {
                    $player->increment('score', $pointsEarned);
                }

                if ($isCorrect) {
                    $player->increment('streak');
                } else {
                    $player->update(['streak' => 0]);
                }
            });
        } catch (QueryException $e) {
            throw new LogicException('Player already answered this question.', previous: $e);
        }

        $answeredCount = PlayerAnswer::where('game_session_id', $session->id)
            ->where('question_id', $questionId)
            ->count();

        $this->broadcastSafely(fn () => broadcast(new PlayerAnswered($session, $answeredCount, $session->players()->count(), $questionId)));

        return [
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Record a player who ran out of time without answering. Stored as a
     * null-answer row (0 points, streak reset) so the host's answered-count
     * based auto-finish can still complete the round.
     *
     * @return array{is_correct: bool, points_earned: int, timed_out: bool}
     */
    public function timeout(GameSession $session, Player $player, int $questionId): array
    {
        if ($session->status !== 'playing') {
            throw new LogicException('Game is not in playing state.');
        }

        $existing = PlayerAnswer::where('player_id', $player->id)
            ->where('question_id', $questionId)
            ->exists();

        if ($existing) {
            throw new LogicException('Player already answered this question.');
        }

        $question = $session->quiz->questions()->findOrFail($questionId);

        try {
            DB::transaction(function () use ($session, $player, $questionId, $question) {
                PlayerAnswer::create([
                    'player_id' => $player->id,
                    'game_session_id' => $session->id,
                    'question_id' => $questionId,
                    'answer' => null,
                    'is_correct' => false,
                    'time_taken_ms' => $question->time_limit_seconds * 1000,
                    'points_earned' => 0,
                ]);

                $player->update(['streak' => 0]);
            });
        } catch (QueryException $e) {
            throw new LogicException('Player already answered this question.', previous: $e);
        }

        $answeredCount = PlayerAnswer::where('game_session_id', $session->id)
            ->where('question_id', $questionId)
            ->count();

        $this->broadcastSafely(fn () => broadcast(new PlayerAnswered($session, $answeredCount, $session->players()->count(), $questionId)));

        return [
            'is_correct' => false,
            'points_earned' => 0,
            'timed_out' => true,
        ];
    }

    private function broadcastSafely(callable $fn): void
    {
        try {
            $fn();
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed: '.$e->getMessage());
        }
    }
}
