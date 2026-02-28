<?php

namespace App\Contracts;

use App\Models\Question;

interface QuestionTypeInterface
{
    public function renderSpectatorComponent(): string;
    public function renderPlayerComponent(): string;
    public function validateAnswer(mixed $answer, Question $question): bool;
    public function calculatePoints(Question $question, int $timeTakenMs, array $quizSettings): int;
    public function validateOptions(array $options): bool;
}
