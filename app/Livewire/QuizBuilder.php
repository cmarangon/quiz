<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\QuestionImageStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class QuizBuilder extends Component
{
    use WithFileUploads;

    public ?Quiz $quiz = null;

    public string $title = '';

    public string $description = '';

    public bool $enableTimeBonus = true;

    public bool $enableStreaks = true;

    public string $presentationStyle = 'party-pop';

    public string $newCategoryName = '';

    public string $newCategoryTheme = '';

    public ?int $addingQuestionToCategoryId = null;

    public ?int $editingQuestionId = null;

    public string $questionBody = '';

    public string $questionType = 'multiple_choice';

    public array $questionOptions = ['', '', '', ''];

    public string $questionCorrectAnswer = '';

    public string $questionGeoLat = '';

    public string $questionGeoLng = '';

    public string $questionGeoThresholdKm = '';

    public string $questionGeoMaxDistanceKm = '';

    public array $questionPairs = [];

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
            $this->presentationStyle = $quiz->settings['presentation_style'] ?? 'party-pop';
        }
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|min:3',
            'presentationStyle' => ['required', Rule::in(['party-pop', 'game-show', 'bright-bouncy'])],
        ]);

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'settings' => [
                'enable_time_bonus' => $this->enableTimeBonus,
                'enable_streaks' => $this->enableStreaks,
                'presentation_style' => $this->presentationStyle,
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
            'newCategoryTheme' => ['required', Rule::in($this->selectableThemeKeys())],
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
        $this->newCategoryTheme = '';
    }

    /**
     * Styled themes a user may pick. Excludes the 'default' fallback so a
     * category can't be silently created without a real theme.
     *
     * @return list<string>
     */
    private function selectableThemeKeys(): array
    {
        return array_values(array_filter(
            array_keys(config('themes', [])),
            fn (string $key) => $key !== 'default',
        ));
    }

    public function showAddQuestion(int $categoryId): void
    {
        $this->editingQuestionId = null;
        $this->addingQuestionToCategoryId = $categoryId;
        $this->resetQuestionForm();
    }

    public function editQuestion(int $questionId): void
    {
        $question = Question::findOrFail($questionId);
        $category = $question->category;

        if ($category->quiz_id !== $this->quiz?->id) {
            abort(403);
        }

        $this->resetQuestionForm();
        $this->addingQuestionToCategoryId = null;
        $this->editingQuestionId = $question->id;

        $this->questionBody = $question->body;
        $this->questionType = $question->type;
        $this->questionPoints = $question->points;
        $this->questionTimeLimit = $question->time_limit_seconds;

        if ($question->type === 'multiple_choice') {
            $options = $question->options ?: [];
            $this->questionOptions = count($options) >= 2 ? $options : ['', ''];
            $this->questionCorrectAnswer = is_scalar($question->correct_answer) ? (string) $question->correct_answer : '';
        } elseif ($question->type === 'true_false') {
            $this->questionCorrectAnswer = is_scalar($question->correct_answer) ? (string) $question->correct_answer : '';
        } elseif ($question->type === 'ordering') {
            $labels = is_array($question->correct_answer) ? $question->correct_answer : [];
            $this->questionOptions = count($labels) >= 2 ? $labels : ['', ''];
        } elseif ($question->type === 'geo_guesser') {
            $this->questionGeoLat = (string) ($question->correct_answer['lat'] ?? '');
            $this->questionGeoLng = (string) ($question->correct_answer['lng'] ?? '');

            $options = $question->options ?? [];
            $this->questionGeoThresholdKm = isset($options['threshold_km']) ? (string) $options['threshold_km'] : '';
            $this->questionGeoMaxDistanceKm = isset($options['max_distance_km']) ? (string) $options['max_distance_km'] : '';
        } elseif ($question->type === 'match_pairs') {
            $options = $question->options ?? [];
            $left = $options['left'] ?? [];
            $right = $options['right'] ?? [];
            $correctAnswer = is_array($question->correct_answer) ? $question->correct_answer : [];

            $pairs = [];
            foreach ($left as $index => $leftItem) {
                $rightIndex = $correctAnswer[$index] ?? null;
                $rightItem = $rightIndex !== null ? ($right[$rightIndex] ?? null) : null;

                $pairs[] = [
                    'left' => $this->pairFormState($leftItem),
                    'right' => $this->pairFormState($rightItem ?? ['kind' => 'text', 'value' => '']),
                ];
            }

            $this->questionPairs = count($pairs) === 4 ? $pairs : $this->defaultQuestionPairs();
        }
    }

    public function deleteQuestion(int $questionId): void
    {
        $question = Question::findOrFail($questionId);
        $category = $question->category;

        if ($category->quiz_id !== $this->quiz?->id) {
            abort(403);
        }

        $question->delete();

        if ($this->editingQuestionId === $questionId) {
            $this->editingQuestionId = null;
            $this->resetQuestionForm();
        }
    }

    public function cancelAddQuestion(): void
    {
        $this->addingQuestionToCategoryId = null;
        $this->editingQuestionId = null;
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
            'questionType' => 'required|in:multiple_choice,true_false,ordering,geo_guesser,match_pairs',
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

            if ($this->questionGeoThresholdKm !== '') {
                $rules['questionGeoThresholdKm'] = 'numeric|min:0';
            }

            if ($this->questionGeoMaxDistanceKm !== '') {
                $rules['questionGeoMaxDistanceKm'] = 'numeric|gt:0';
            }
        }

        if ($this->questionType === 'match_pairs') {
            $rules['questionPairs'] = 'required|array|size:4';
            foreach (range(0, 3) as $i) {
                $rules["questionPairs.$i.left.kind"] = 'required|in:text,image';
                $rules["questionPairs.$i.right.kind"] = 'required|in:text,image';
                $rules["questionPairs.$i.left.image"] = 'nullable|image|mimes:jpeg,png,webp,gif|max:2048';
                $rules["questionPairs.$i.right.image"] = 'nullable|image|mimes:jpeg,png,webp,gif|max:2048';
            }
        }

        $this->validate($rules);

        if ($this->questionType === 'geo_guesser'
            && $this->questionGeoThresholdKm !== ''
            && $this->questionGeoMaxDistanceKm !== ''
            && (float) $this->questionGeoThresholdKm >= (float) $this->questionGeoMaxDistanceKm) {
            $this->addError('questionGeoThresholdKm', __('The threshold must be less than the max distance.'));

            return;
        }

        if ($this->questionType === 'match_pairs' && ! $this->validateQuestionPairsContent()) {
            return;
        }

        [$options, $correctAnswer] = $this->buildQuestionPayload();

        if ($this->editingQuestionId) {
            $question = Question::findOrFail($this->editingQuestionId);
            $category = $question->category;

            if ($category->quiz_id !== $this->quiz?->id) {
                abort(403);
            }

            if ($question->type === 'match_pairs') {
                $this->cleanupReplacedImages($question->options ?? [], $options);
            }

            $question->update([
                'type' => $this->questionType,
                'body' => $this->questionBody,
                'options' => $options,
                'correct_answer' => $correctAnswer,
                'points' => $this->questionPoints,
                'time_limit_seconds' => $this->questionTimeLimit,
            ]);
        } else {
            $category = Category::findOrFail($this->addingQuestionToCategoryId);

            if ($category->quiz_id !== $this->quiz?->id) {
                abort(403);
            }

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
        }

        $this->addingQuestionToCategoryId = null;
        $this->editingQuestionId = null;
        $this->resetQuestionForm();
    }

    /**
     * Manual cross-field check for each pair slot's content, mirroring the
     * geo_guesser threshold/max-distance check above: structural shape is
     * covered by $this->validate(), but "does this slot actually have content
     * for its chosen kind" depends on a sibling field, so it's checked here.
     */
    private function validateQuestionPairsContent(): bool
    {
        foreach ($this->questionPairs as $index => $pair) {
            foreach (['left', 'right'] as $side) {
                $kind = $pair[$side]['kind'] ?? 'text';

                if ($kind === 'text' && trim($pair[$side]['text'] ?? '') === '') {
                    $this->addError("questionPairs.$index.$side.text", __('Enter text or switch to an image.'));

                    return false;
                }

                if ($kind === 'image' && ! ($pair[$side]['image'] ?? null) && ! ($pair[$side]['existingImage'] ?? null)) {
                    $this->addError("questionPairs.$index.$side.image", __('Upload an image or switch to text.'));

                    return false;
                }
            }
        }

        return true;
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
        if ($this->questionType === 'match_pairs') {
            $left = [];
            $right = [];
            foreach ($this->questionPairs as $pair) {
                $left[] = $this->resolvePairItem($pair['left']);
                $right[] = $this->resolvePairItem($pair['right']);
            }

            // Shuffle a permutation of right-side *positions* rather than the
            // values themselves, so two pairs with identical-looking values
            // can never be confused when computing correct_answer below.
            $original = range(0, count($right) - 1);
            $positions = $original;
            if (count($positions) > 1) {
                for ($attempt = 0; $attempt < 10 && $positions === $original; $attempt++) {
                    shuffle($positions);
                }
                if ($positions === $original) {
                    $positions[] = array_shift($positions);
                }
            }

            $shuffledRight = array_map(fn ($originalIndex) => $right[$originalIndex], $positions);

            // correct_answer[leftIndex] = position of left[leftIndex]'s match
            // within the shuffled right array.
            $correctAnswer = [];
            foreach ($positions as $newPosition => $originalIndex) {
                $correctAnswer[$originalIndex] = $newPosition;
            }
            ksort($correctAnswer);

            return [['left' => $left, 'right' => $shuffledRight], array_values($correctAnswer)];
        }

        if ($this->questionType === 'true_false') {
            return [['True', 'False'], $this->questionCorrectAnswer];
        }

        if ($this->questionType === 'geo_guesser') {
            $options = [];

            if ($this->questionGeoThresholdKm !== '') {
                $options['threshold_km'] = (float) $this->questionGeoThresholdKm;
            }

            if ($this->questionGeoMaxDistanceKm !== '') {
                $options['max_distance_km'] = (float) $this->questionGeoMaxDistanceKm;
            }

            return [$options, ['lat' => (float) $this->questionGeoLat, 'lng' => (float) $this->questionGeoLng]];
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

    /**
     * Resolve one pair slot's stored {kind, value} from its form state.
     */
    private function resolvePairItem(array $item): array
    {
        if (($item['kind'] ?? 'text') === 'image') {
            $path = $item['image']
                ? app(QuestionImageStorage::class)->store($item['image'])
                : ($item['existingImage'] ?? '');

            return ['kind' => 'image', 'value' => $path];
        }

        return ['kind' => 'text', 'value' => trim($item['text'] ?? '')];
    }

    /**
     * @return array<int, array{left: array, right: array}>
     */
    private function defaultQuestionPairs(): array
    {
        $emptySlot = ['kind' => 'text', 'text' => '', 'image' => null, 'existingImage' => null];

        return array_fill(0, 4, ['left' => $emptySlot, 'right' => $emptySlot]);
    }

    /**
     * Reconstruct a pair slot's form state from its stored {kind, value}.
     */
    private function pairFormState(array $item): array
    {
        if (($item['kind'] ?? 'text') === 'image') {
            return ['kind' => 'image', 'text' => '', 'image' => null, 'existingImage' => $item['value'] ?? null];
        }

        return ['kind' => 'text', 'text' => $item['value'] ?? '', 'image' => null, 'existingImage' => null];
    }

    /**
     * @return list<string>
     */
    private function imagePaths(array $options): array
    {
        $paths = [];
        foreach (['left', 'right'] as $side) {
            foreach ($options[$side] ?? [] as $item) {
                if (($item['kind'] ?? null) === 'image' && ! empty($item['value'])) {
                    $paths[] = $item['value'];
                }
            }
        }

        return $paths;
    }

    /**
     * Delete any image that was on the question before this save but isn't
     * referenced by the freshly-built options anymore (the author replaced it
     * or switched that slot back to text).
     */
    private function cleanupReplacedImages(array $oldOptions, array $newOptions): void
    {
        $storage = app(QuestionImageStorage::class);
        $removed = array_diff($this->imagePaths($oldOptions), $this->imagePaths($newOptions));

        foreach ($removed as $path) {
            $storage->delete($path);
        }
    }

    private function resetQuestionForm(): void
    {
        $this->questionBody = '';
        $this->questionType = 'multiple_choice';
        $this->questionOptions = ['', '', '', ''];
        $this->questionCorrectAnswer = '';
        $this->questionGeoLat = '';
        $this->questionGeoLng = '';
        $this->questionGeoThresholdKm = '';
        $this->questionGeoMaxDistanceKm = '';
        $this->questionPairs = $this->defaultQuestionPairs();
        $this->questionPoints = 10;
        $this->questionTimeLimit = 30;
    }

    public function render()
    {
        $categories = $this->quiz ? $this->quiz->categories()->with('questions')->get() : collect();
        $themeKeys = $this->selectableThemeKeys();
        $questionTypes = array_keys(config('quiz.question_types', []));

        return view('livewire.quiz-builder', [
            'categories' => $categories,
            'themeKeys' => $themeKeys,
            'questionTypes' => $questionTypes,
        ])->title($this->quiz ? 'Edit Quiz' : 'Create Quiz');
    }
}
