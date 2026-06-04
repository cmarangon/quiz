<?php

namespace App\Services;

use App\Events\CategoryChanged;
use App\Events\GameFinished;
use App\Events\QuestionEnded;
use App\Events\QuestionStarted;
use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
            'current_question_started_at' => now(),
        ]);

        $session->refresh();
        $question = $this->getCurrentQuestion($session);

        $this->broadcastSafely(fn () => $firstCategory && broadcast(new CategoryChanged($session, $firstCategory)));

        $this->broadcastSafely(fn () => $question && broadcast(new QuestionStarted($session, $question)));
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

        $question = $this->getCurrentQuestion($session);

        $session->update(['status' => 'reviewing']);

        if ($question) {
            $scores = $session->players()->orderByDesc('score')->get()->map(fn ($p) => [
                'nickname' => $p->nickname,
                'emoji' => $p->emoji,
                'score' => $p->score,
            ])->toArray();

            $this->broadcastSafely(fn () => broadcast(new QuestionEnded($session, $question, $scores)));
        }
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

            $leaderboard = $session->players()->orderByDesc('score')->get()->map(fn ($p) => [
                'nickname' => $p->nickname,
                'emoji' => $p->emoji,
                'score' => $p->score,
            ])->toArray();

            $this->broadcastSafely(fn () => broadcast(new GameFinished($session, $leaderboard)));

            return false;
        }

        $prevCategoryId = $session->current_category_id;
        $nextQuestion = $questions->get($nextIndex);

        $session->update([
            'current_question_index' => $nextIndex,
            'current_category_id' => $nextQuestion->category_id,
            'current_question_started_at' => now(),
            'status' => 'playing',
        ]);

        $session->refresh();

        if ($nextQuestion->category_id !== $prevCategoryId) {
            $this->broadcastSafely(fn () => broadcast(new CategoryChanged($session, $nextQuestion->category)));
        }

        $this->broadcastSafely(fn () => broadcast(new QuestionStarted($session, $nextQuestion)));

        return true;
    }

    private function broadcastSafely(callable $fn): void
    {
        try {
            $fn();
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed: '.$e->getMessage());
        }
    }

    private function getAllQuestionsOrdered(GameSession $session): Collection
    {
        return $session->quiz
            ->categories()
            ->orderBy('order')
            ->with(['questions' => fn ($q) => $q->orderBy('order')])
            ->get()
            ->flatMap(fn ($category) => $category->questions
                ->each(fn ($question) => $question->setRelation('category', $category)))
            ->values();
    }
}
