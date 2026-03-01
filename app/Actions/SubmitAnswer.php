<?php

namespace App\Actions;

use App\Events\PlayerAnswered;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Services\QuestionTypeRegistry;
use App\Services\ScoringService;
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
        $pointsEarned = 0;

        if ($isCorrect) {
            $pointsEarned = $this->scoring->calculate(
                $question,
                $timeTakenMs,
                $player->streak,
                $session->quiz->settings,
            );
            $player->increment('score', $pointsEarned);
            $player->increment('streak');
        } else {
            $player->update(['streak' => 0]);
        }

        PlayerAnswer::create([
            'player_id' => $player->id,
            'game_session_id' => $session->id,
            'question_id' => $questionId,
            'answer' => $answer,
            'is_correct' => $isCorrect,
            'time_taken_ms' => $timeTakenMs,
            'points_earned' => $pointsEarned,
        ]);

        $answeredCount = PlayerAnswer::where('game_session_id', $session->id)
            ->where('question_id', $questionId)
            ->count();

        broadcast(new PlayerAnswered($session, $answeredCount, $session->players()->count()));

        return [
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
        ];
    }
}
