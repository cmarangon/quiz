<?php

namespace App\Livewire;

use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QuizIndex extends Component
{
    public ?string $pendingAction = null;

    public ?int $pendingId = null;

    public function confirmDeleteQuiz(int $id): void
    {
        $this->pendingAction = 'delete-quiz';
        $this->pendingId = $id;
    }

    public function deleteQuiz(): void
    {
        abort_unless($this->pendingAction === 'delete-quiz' && $this->pendingId !== null, 400);

        $quiz = Quiz::findOrFail($this->pendingId);
        abort_unless($quiz->user_id === Auth::id(), 403);

        $quiz->delete();

        $this->pendingAction = null;
        $this->pendingId = null;
        $this->dispatch('quiz-deleted');
    }

    public function runPendingAction(): void
    {
        if ($this->pendingAction === 'delete-quiz') {
            $this->deleteQuiz();
        }
    }

    public function render()
    {
        $quizzes = Auth::user()->quizzes()->withCount('categories')->latest()->get();

        return view('livewire.quiz-index', [
            'quizzes' => $quizzes,
        ])->title('My Quizzes');
    }
}
