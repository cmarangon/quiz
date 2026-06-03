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

    public ?int $addingQuestionToCategoryId = null;

    public string $questionBody = '';

    public string $questionType = 'multiple_choice';

    public array $questionOptions = ['', '', '', ''];

    public string $questionCorrectAnswer = '';

    public string $questionGeoLat = '';

    public string $questionGeoLng = '';

    public int $questionPoints = 10;

    public int $questionTimeLimit = 30;

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

    public function showAddQuestion(int $categoryId): void
    {
        $this->addingQuestionToCategoryId = $categoryId;
        $this->resetQuestionForm();
    }

    public function cancelAddQuestion(): void
    {
        $this->addingQuestionToCategoryId = null;
        $this->resetQuestionForm();
    }

    public function addQuestionOption(): void
    {
        $this->questionOptions[] = '';
    }

    public function removeQuestionOption(int $index): void
    {
        unset($this->questionOptions[$index]);
        $this->questionOptions = array_values($this->questionOptions);
    }

    public function saveQuestion(): void
    {
        $rules = [
            'questionBody' => 'required|min:3',
            'questionType' => 'required|in:multiple_choice,true_false,ordering,geo_guesser',
            'questionPoints' => 'required|integer|min:1',
            'questionTimeLimit' => 'required|integer|min:5',
        ];

        if ($this->questionType === 'multiple_choice' || $this->questionType === 'ordering') {
            $rules['questionOptions'] = 'required|array|min:2';
            $rules['questionOptions.*'] = 'required|string';
        }

        if ($this->questionType === 'ordering') {
            $rules['questionOptions.*'] = 'required|string|distinct';
        }

        if ($this->questionType === 'multiple_choice' || $this->questionType === 'true_false') {
            $rules['questionCorrectAnswer'] = 'required';
        }

        if ($this->questionType === 'geo_guesser') {
            $rules['questionGeoLat'] = 'required|numeric|between:-90,90';
            $rules['questionGeoLng'] = 'required|numeric|between:-180,180';
        }

        $this->validate($rules);

        $category = Category::findOrFail($this->addingQuestionToCategoryId);

        if ($category->quiz_id !== $this->quiz?->id) {
            abort(403);
        }

        [$options, $correctAnswer] = $this->buildQuestionPayload();

        $nextOrder = $category->questions()->max('order') + 1;

        $category->questions()->create([
            'type' => $this->questionType,
            'body' => $this->questionBody,
            'options' => $options,
            'correct_answer' => $correctAnswer,
            'points' => $this->questionPoints,
            'time_limit_seconds' => $this->questionTimeLimit,
            'order' => $nextOrder,
        ]);

        $this->addingQuestionToCategoryId = null;
        $this->resetQuestionForm();
    }

    /**
     * Build the [options, correct_answer] pair for the question being saved.
     *
     * For ordering questions the author enters the items in their correct
     * sequence; we persist that sequence as correct_answer and store the
     * options in a shuffled display order so the broadcast never leaks it.
     *
     * @return array{0: array<int, mixed>, 1: mixed}
     */
    private function buildQuestionPayload(): array
    {
        if ($this->questionType === 'true_false') {
            return [['True', 'False'], $this->questionCorrectAnswer];
        }

        if ($this->questionType === 'geo_guesser') {
            return [[], ['lat' => (float) $this->questionGeoLat, 'lng' => (float) $this->questionGeoLng]];
        }

        if ($this->questionType === 'ordering') {
            $labels = array_values(array_filter(
                array_map('trim', $this->questionOptions),
                fn ($label) => $label !== '',
            ));

            $shuffled = $labels;
            if (count($shuffled) > 1) {
                // Bounded shuffle so a degenerate input (e.g. labels that only
                // differ by whitespace) can never spin forever; fall back to a
                // rotation, which always differs when labels aren't identical.
                for ($attempt = 0; $attempt < 10 && $shuffled === $labels; $attempt++) {
                    shuffle($shuffled);
                }

                if ($shuffled === $labels && count(array_unique($labels)) > 1) {
                    $shuffled[] = array_shift($shuffled);
                }
            }

            $options = array_map(fn ($label) => ['label' => $label], $shuffled);

            return [$options, $labels];
        }

        return [array_values(array_filter($this->questionOptions)), $this->questionCorrectAnswer];
    }

    private function resetQuestionForm(): void
    {
        $this->questionBody = '';
        $this->questionType = 'multiple_choice';
        $this->questionOptions = ['', '', '', ''];
        $this->questionCorrectAnswer = '';
        $this->questionGeoLat = '';
        $this->questionGeoLng = '';
        $this->questionPoints = 10;
        $this->questionTimeLimit = 30;
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
