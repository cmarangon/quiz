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
        $answerBool = $this->toBool($answer);
        $correctBool = $this->toBool($question->correct_answer);

        return $answerBool !== null && $answerBool === $correctBool;
    }

    /**
     * Normalise an answer to a strict boolean. Answers travel as the option
     * labels "True"/"False" (and the correct answer is persisted the same way),
     * so a plain (bool) cast is wrong — every non-empty string is truthy. Returns
     * null for values that don't clearly map to true/false.
     */
    private function toBool(mixed $value): ?bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function scoreFactor(mixed $answer, Question $question): float
    {
        return $this->validateAnswer($answer, $question) ? 1.0 : 0.0;
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
