<?php

namespace App\QuestionTypes;

use App\Contracts\QuestionTypeInterface;
use App\Models\Question;

class TrueFalseType implements QuestionTypeInterface
{
    public function renderSpectatorComponent(): string
    {
        return 'question-types.true-false-spectator';
    }

    public function renderPlayerComponent(): string
    {
        return 'question-types.true-false-player';
    }

    public function validateAnswer(mixed $answer, Question $question): bool
    {
        return (bool) $answer === (bool) $question->correct_answer;
    }

    public function calculatePoints(Question $question, int $timeTakenMs, array $quizSettings): int
    {
        $base = $question->points;
        $timeBonus = $quizSettings['enable_time_bonus'] ?? true;
        if (! $timeBonus) {
            return $base;
        }
        $limitMs = $question->time_limit_seconds * 1000;
        $remaining = max(0, $limitMs - $timeTakenMs);

        return (int) round($base * ($remaining / $limitMs));
    }

    public function validateOptions(array $options): bool
    {
        return count($options) === 2;
    }
}
