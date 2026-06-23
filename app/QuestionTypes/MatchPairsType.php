<?php

namespace App\QuestionTypes;

use App\Contracts\QuestionTypeInterface;
use App\Models\Question;

class MatchPairsType implements QuestionTypeInterface
{
    public function renderSpectatorComponent(): string
    {
        return 'question-types.match-pairs-spectator';
    }

    public function renderPlayerComponent(): string
    {
        return 'question-types.match-pairs-player';
    }

    /**
     * The answer is correct only when every left item is paired with the
     * exact right item recorded at authoring time (all-or-nothing for the MVP).
     */
    public function validateAnswer(mixed $answer, Question $question): bool
    {
        $submitted = $this->normalizePairs($answer);
        $correct = $this->normalizePairs($question->correct_answer);

        if ($submitted === null || $correct === null) {
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
        $limitMs = $question->effectiveTimeLimitSeconds() * 1000;
        $remaining = max(0, $limitMs - $timeTakenMs);

        return (int) round($base * ($remaining / $limitMs));
    }

    /**
     * Options must have a "left" and "right" side, each exactly 4 items, each
     * item a {kind: 'text'|'image', value: non-empty string}.
     */
    public function validateOptions(array $options): bool
    {
        if (! isset($options['left'], $options['right'])) {
            return false;
        }

        return $this->validateSide($options['left']) && $this->validateSide($options['right']);
    }

    private function validateSide(mixed $side): bool
    {
        if (! is_array($side) || count($side) !== 4) {
            return false;
        }

        foreach ($side as $item) {
            if (! is_array($item) || ! isset($item['kind'], $item['value'])) {
                return false;
            }

            if (! in_array($item['kind'], ['text', 'image'], true)) {
                return false;
            }

            if (! is_string($item['value']) || trim($item['value']) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Coerce a value into a length-4 list of ints (leftIndex => rightIndex),
     * or null when it isn't shaped that way. A null slot (an incomplete
     * pairing) fails this check, which is what makes a timed-out partial
     * submission score as "no answer" rather than partial credit.
     *
     * @return array<int, int>|null
     */
    private function normalizePairs(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = [];
        foreach (array_values($value) as $item) {
            if (! is_int($item) && ! (is_string($item) && ctype_digit($item))) {
                return null;
            }

            $normalized[] = (int) $item;
        }

        return count($normalized) === 4 ? $normalized : null;
    }
}
