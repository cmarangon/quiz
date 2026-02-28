<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QuizIndex extends Component
{
    public function render()
    {
        $quizzes = Auth::user()->quizzes()->withCount('categories')->latest()->get();

        return view('livewire.quiz-index', [
            'quizzes' => $quizzes,
        ])->title('My Quizzes');
    }
}
