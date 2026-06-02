<?php

namespace App\QuestionTypes;

use App\Contracts\QuestionTypeInterface;
use App\Models\Question;

class OrderingType implements QuestionTypeInterface
{
    public function renderSpectatorComponent(): string
    {
        return 'question-types.ordering-spectator';
    }

    public function renderPlayerComponent(): string
    {
        return 'question-types.ordering-player';
    }

    /**
     * The answer is correct only when every item is in the exact position of
     * the stored sequence (all-or-nothing for the MVP).
     */
    public function validateAnswer(mixed $answer, Question $question): bool
    {
        $submitted = $this->normalizeSequence($answer);
        $correct = $this->normalizeSequence($question->correct_answer);

        if ($submitted === null || $correct === null) {
            return false;
        }

        if ($submitted === [] || count($submitted) !== count($correct)) {
            return false;
        }

        return $submitted === $correct;
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

    /**
     * Options must contain at least two items, each with a non-empty string
     * label, and all labels must be unique so answers are unambiguous.
     */
    public function validateOptions(array $options): bool
    {
        if (count($options) < 2) {
            return false;
        }

        $labels = [];
        foreach ($options as $option) {
            if (! is_array($option) || ! isset($option['label']) || ! is_string($option['label'])) {
                return false;
            }

            $label = trim($option['label']);
            if ($label === '') {
                return false;
            }

            $labels[] = $label;
        }

        return count($labels) === count(array_unique($labels));
    }

    /**
     * Coerce a value into a sequential list of trimmed string labels, or null
     * when it is not a list of scalar labels.
     *
     * @return array<int, string>|null
     */
    private function normalizeSequence(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = [];
        foreach (array_values($value) as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                return null;
            }

            $normalized[] = trim((string) $item);
        }

        return $normalized;
    }
}
