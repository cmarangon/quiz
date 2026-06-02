<?php

namespace App\Contracts;

use App\Models\Question;

interface QuestionTypeInterface
{
    public function renderSpectatorComponent(): string;

    public function renderPlayerComponent(): string;

    public function validateAnswer(mixed $answer, Question $question): bool;

    /**
     * Fraction of the question's points the answer earns, in the range [0, 1].
     *
     * Exact-match types return 1.0 for a correct answer and 0.0 otherwise.
     * Distance-based types (e.g. geo guesser) return a partial factor.
     */
    public function scoreFactor(mixed $answer, Question $question): float;

    public function calculatePoints(Question $question, int $timeTakenMs, array $quizSettings): int;

    public function validateOptions(array $options): bool;
}
