<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Support\Collection;
use LogicException;

class GameService
{
    public function start(GameSession $session): void
    {
        if ($session->status !== 'waiting') {
            throw new LogicException("Cannot start game in '{$session->status}' status.");
        }

        $firstCategory = $session->quiz->categories()->orderBy('order')->first();

        $session->update([
            'status' => 'playing',
            'current_question_index' => 0,
            'current_category_id' => $firstCategory?->id,
        ]);
    }

    public function getCurrentQuestion(GameSession $session): ?Question
    {
        $questions = $this->getAllQuestionsOrdered($session);

        return $questions->get($session->current_question_index);
    }

    public function finishQuestion(GameSession $session): void
    {
        if ($session->status !== 'playing') {
            throw new LogicException("Cannot finish question in '{$session->status}' status.");
        }

        $session->update(['status' => 'reviewing']);
    }

    public function advanceToNextQuestion(GameSession $session): bool
    {
        if ($session->status !== 'reviewing') {
            throw new LogicException("Cannot advance in '{$session->status}' status.");
        }

        $questions = $this->getAllQuestionsOrdered($session);
        $nextIndex = $session->current_question_index + 1;

        if ($nextIndex >= $questions->count()) {
            $session->update(['status' => 'finished']);

            return false;
        }

        $nextQuestion = $questions->get($nextIndex);

        $session->update([
            'current_question_index' => $nextIndex,
            'current_category_id' => $nextQuestion->category_id,
            'status' => 'playing',
        ]);

        return true;
    }

    private function getAllQuestionsOrdered(GameSession $session): Collection
    {
        return $session->quiz
            ->categories()
            ->orderBy('order')
            ->with(['questions' => fn ($q) => $q->orderBy('order')])
            ->get()
            ->flatMap->questions
            ->values();
    }
}
