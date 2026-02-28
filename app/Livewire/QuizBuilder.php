<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QuizBuilder extends Component
{
    public ?Quiz $quiz = null;

    public string $title = '';
    public string $description = '';
    public bool $enableTimeBonus = true;
    public bool $enableStreaks = true;

    public string $newCategoryName = '';
    public string $newCategoryTheme = 'default';

    public function mount(?Quiz $quiz = null): void
    {
        if ($quiz && $quiz->exists) {
            if ($quiz->user_id !== Auth::id()) {
                abort(403);
            }

            $this->quiz = $quiz;
            $this->title = $quiz->title;
            $this->description = $quiz->description ?? '';
            $this->enableTimeBonus = $quiz->settings['enable_time_bonus'] ?? true;
            $this->enableStreaks = $quiz->settings['enable_streaks'] ?? true;
        }
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|min:3',
        ]);

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'settings' => [
                'enable_time_bonus' => $this->enableTimeBonus,
                'enable_streaks' => $this->enableStreaks,
            ],
        ];

        if ($this->quiz) {
            $this->quiz->update($data);
        } else {
            $this->quiz = Auth::user()->quizzes()->create($data);
        }
    }

    public function addCategory(): void
    {
        $this->validate([
            'newCategoryName' => 'required',
        ]);

        if (! $this->quiz) {
            return;
        }

        $nextOrder = $this->quiz->categories()->max('order') + 1;

        $this->quiz->categories()->create([
            'name' => $this->newCategoryName,
            'slug' => Str::slug($this->newCategoryName),
            'theme' => $this->newCategoryTheme,
            'order' => $nextOrder,
        ]);

        $this->newCategoryName = '';
        $this->newCategoryTheme = 'default';
    }

    public function addQuestion(int $categoryId, array $questionData): void
    {
        $category = Category::findOrFail($categoryId);

        if ($category->quiz_id !== $this->quiz?->id) {
            abort(403);
        }

        $nextOrder = $category->questions()->max('order') + 1;

        $category->questions()->create([
            'type' => $questionData['type'] ?? 'multiple_choice',
            'body' => $questionData['body'],
            'options' => $questionData['options'] ?? [],
            'correct_answer' => $questionData['correct_answer'],
            'points' => $questionData['points'] ?? 10,
            'time_limit_seconds' => $questionData['time_limit_seconds'] ?? 30,
            'order' => $nextOrder,
        ]);
    }

    public function render()
    {
        $categories = $this->quiz ? $this->quiz->categories()->with('questions')->get() : collect();
        $themeKeys = array_keys(config('themes', []));
        $questionTypes = array_keys(config('quiz.question_types', []));

        return view('livewire.quiz-builder', [
            'categories' => $categories,
            'themeKeys' => $themeKeys,
            'questionTypes' => $questionTypes,
        ])->title($this->quiz ? 'Edit Quiz' : 'Create Quiz');
    }
}
