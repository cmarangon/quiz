# Match Pairs Question Type — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a fifth question type, `match_pairs`, where the player matches exactly 4 pairs of items (each side independently text or an uploaded image) via tap-to-select, scored all-or-nothing.

**Architecture:** A new `App\QuestionTypes\MatchPairsType` plugs into the existing `QuestionTypeRegistry`/`QuestionTypeInterface` machinery exactly like `OrderingType` does — no changes needed to `SubmitAnswer`, `ScoringService`, or any broadcast event. A new `App\Services\QuestionImageStorage` wraps the `public` disk (first file-upload feature in this app). `App\Livewire\QuizBuilder` gains authoring support (text/image toggle per pair side, `WithFileUploads`). New Blade partials + an Alpine component (`match-pairs.js`) handle the player tap-to-pair UI and the spectator/host display, reusing the existing `question.started`/`question.ended` broadcast flow.

**Tech Stack:** Laravel 12, Livewire 4, Alpine.js, Flux UI, Pest 4, SQLite.

**Spec:** `docs/plans/2026-06-21-match-pairs-design.md`

## Global Constraints

- Exactly 4 pairs (8 items) per `match_pairs` question — no variable count.
- Each side (left/right) of each pair is independently `text` or `image`.
- Images are uploaded files only (no URL field) — `mimes:jpeg,png,webp,gif`, max 2MB.
- Interaction is tap-to-select (no drag-and-drop); explicit "Submit matches" button enabled only once all 4 pairs are locked.
- Scoring is all-or-nothing (no partial credit).
- Only the `right` column is shuffled at save time; `left` keeps the author's entry order. The pairing must never be inferable from the broadcast `options` payload.
- `correct_answer` shape: length-4 array where `correct_answer[leftIndex] = rightIndex`. Submitted answer uses the identical shape.

---

## File Structure

**Created:**
- `app/Services/QuestionImageStorage.php` — store/delete on the `public` disk
- `app/QuestionTypes/MatchPairsType.php` — `QuestionTypeInterface` implementation
- `resources/views/question-types/match-pairs-player.blade.php` — player tap-to-pair UI
- `resources/views/question-types/match-pairs-spectator.blade.php` — spectator/host display
- `resources/js/match-pairs.js` — Alpine component for the player UI
- `tests/Unit/Services/QuestionImageStorageTest.php`
- `tests/Unit/QuestionTypes/MatchPairsTypeTest.php`
- `tests/Feature/MatchPairsGameFlowTest.php`
- `tests/Feature/QuizBuilderMatchPairsTest.php`
- `tests/Unit/Models/QuestionImageCleanupTest.php`

**Modified:**
- `config/quiz.php` — register `match_pairs`
- `database/factories/QuestionFactory.php` — add `matchPairs()` state
- `app/Models/Question.php` — `deleting` hook to clean up uploaded images
- `app/Livewire/PlayerScreen.php` — add `submitMatches()`
- `app/Livewire/QuizBuilder.php` — authoring support
- `resources/views/livewire/quiz-builder.blade.php` — authoring form
- `resources/views/livewire/player-screen.blade.php` — include the new player partial
- `resources/views/livewire/spectator-screen.blade.php` — include the new spectator partial
- `resources/js/app.js` — register the new Alpine component
- `lang/de.json` — German strings
- `deploy.sh` — add `storage:link`
- `tests/Unit/QuestionTypeRegistryTest.php` — assert `match_pairs` is registered

---

## Task 1: Image storage service

**Files:**
- Create: `app/Services/QuestionImageStorage.php`
- Test: `tests/Unit/Services/QuestionImageStorageTest.php`

**Interfaces:**
- Produces: `App\Services\QuestionImageStorage::store(\Illuminate\Http\UploadedFile $file): string` (relative path on the `public` disk), `::delete(string $path): void`. Later tasks (`QuizBuilder`, `Question` model) consume both.

### - [ ] Step 1: Write the failing test

Create `tests/Unit/Services/QuestionImageStorageTest.php`:

```php
<?php

use App\Services\QuestionImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('store puts the file on the public disk under questions/ and returns its path', function () {
    Storage::fake('public');
    $service = new QuestionImageStorage;

    $file = UploadedFile::fake()->image('flag.png');
    $path = $service->store($file);

    expect($path)->toStartWith('questions/');
    Storage::disk('public')->assertExists($path);
});

test('delete removes the stored file', function () {
    Storage::fake('public');
    $service = new QuestionImageStorage;

    $path = $service->store(UploadedFile::fake()->image('flag.png'));
    $service->delete($path);

    Storage::disk('public')->assertMissing($path);
});

test('delete is a no-op when the file is already gone', function () {
    Storage::fake('public');
    $service = new QuestionImageStorage;

    $service->delete('questions/does-not-exist.png');

    // No exception thrown — that's the assertion.
    expect(true)->toBeTrue();
});
```

### - [ ] Step 2: Run it and watch it fail

Run: `vendor/bin/pest tests/Unit/Services/QuestionImageStorageTest.php`
Expected: FAIL with `Class "App\Services\QuestionImageStorage" not found`.

### - [ ] Step 3: Implement the service

Create `app/Services/QuestionImageStorage.php`:

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class QuestionImageStorage
{
    public function store(UploadedFile $file): string
    {
        return $file->store('questions', 'public');
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}
```

### - [ ] Step 4: Run it and watch it pass

Run: `vendor/bin/pest tests/Unit/Services/QuestionImageStorageTest.php`
Expected: all 3 PASS.

### - [ ] Step 5: Commit

```bash
git add app/Services/QuestionImageStorage.php tests/Unit/Services/QuestionImageStorageTest.php
git commit -m "feat: add QuestionImageStorage service for the public disk"
```

---

## Task 2: MatchPairsType

**Files:**
- Create: `app/QuestionTypes/MatchPairsType.php`
- Test: `tests/Unit/QuestionTypes/MatchPairsTypeTest.php`

**Interfaces:**
- Consumes: `App\Models\Question` (existing — `options`, `correct_answer`, `points`, `time_limit_seconds` properties).
- Produces: `App\QuestionTypes\MatchPairsType implements App\Contracts\QuestionTypeInterface`. Submitted-answer shape: length-4 array of ints, `answer[leftIndex] = rightIndex`. `correct_answer` uses the identical shape. Later tasks (`config/quiz.php`, `PlayerScreen::submitMatches`) consume this class by its registered key `match_pairs`.

### - [ ] Step 1: Write the failing tests

Create `tests/Unit/QuestionTypes/MatchPairsTypeTest.php`:

```php
<?php

use App\Models\Question;
use App\QuestionTypes\MatchPairsType;

function matchPairsQuestion(array $overrides = []): Question
{
    return Question::factory()->make(array_merge([
        'category_id' => 1,
        'type' => 'match_pairs',
        'points' => 100,
        'time_limit_seconds' => 30,
        'options' => [
            'left' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'Japan'],
                ['kind' => 'text', 'value' => 'Egypt'],
                ['kind' => 'text', 'value' => 'Brazil'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'Cairo'],
                ['kind' => 'text', 'value' => 'Paris'],
                ['kind' => 'text', 'value' => 'Brasilia'],
                ['kind' => 'text', 'value' => 'Tokyo'],
            ],
        ],
        // left[0] France -> right[1] Paris, left[1] Japan -> right[3] Tokyo,
        // left[2] Egypt -> right[0] Cairo, left[3] Brazil -> right[2] Brasilia.
        'correct_answer' => [1, 3, 0, 2],
    ], $overrides));
}

test('validates an exact match as correct', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer([1, 3, 0, 2], $question))->toBeTrue();
});

test('rejects a swapped pair', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer([1, 3, 2, 0], $question))->toBeFalse();
});

test('rejects a partially correct set of pairs (all-or-nothing)', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    // First two correct, last two swapped.
    expect($type->validateAnswer([1, 3, 2, 0], $question))->toBeFalse();
});

test('rejects an answer of the wrong length', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer([1, 3, 0], $question))->toBeFalse();
});

test('rejects non-array, null, and empty answers', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer('nope', $question))->toBeFalse();
    expect($type->validateAnswer(null, $question))->toBeFalse();
    expect($type->validateAnswer([], $question))->toBeFalse();
});

test('rejects an answer containing a null slot (incomplete pairing)', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->validateAnswer([1, 3, null, 2], $question))->toBeFalse();
});

test('scoreFactor is 1.0 for a correct match and 0.0 otherwise', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->scoreFactor([1, 3, 0, 2], $question))->toBe(1.0);
    expect($type->scoreFactor([1, 3, 2, 0], $question))->toBe(0.0);
});

test('calculatePoints awards full base when time bonus disabled', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion();

    expect($type->calculatePoints($question, 5000, ['enable_time_bonus' => false]))->toBe(100);
});

test('calculatePoints scales with remaining time', function () {
    $type = new MatchPairsType;
    $question = matchPairsQuestion(['time_limit_seconds' => 10]);

    expect($type->calculatePoints($question, 5000, ['enable_time_bonus' => true]))->toBe(50);
});

test('validateOptions accepts a well-formed 4-pair set', function () {
    $type = new MatchPairsType;

    $options = [
        'left' => [
            ['kind' => 'text', 'value' => 'A'],
            ['kind' => 'image', 'value' => 'questions/a.png'],
            ['kind' => 'text', 'value' => 'C'],
            ['kind' => 'text', 'value' => 'D'],
        ],
        'right' => [
            ['kind' => 'text', 'value' => 'W'],
            ['kind' => 'text', 'value' => 'X'],
            ['kind' => 'image', 'value' => 'questions/y.png'],
            ['kind' => 'text', 'value' => 'Z'],
        ],
    ];

    expect($type->validateOptions($options))->toBeTrue();
});

test('validateOptions rejects missing left or right keys', function () {
    $type = new MatchPairsType;

    expect($type->validateOptions(['left' => [['kind' => 'text', 'value' => 'A']]]))->toBeFalse();
});

test('validateOptions rejects a side with fewer than four items', function () {
    $type = new MatchPairsType;

    $side = [['kind' => 'text', 'value' => 'A'], ['kind' => 'text', 'value' => 'B']];
    expect($type->validateOptions(['left' => $side, 'right' => $side]))->toBeFalse();
});

test('validateOptions rejects an invalid kind', function () {
    $type = new MatchPairsType;

    $bad = [
        ['kind' => 'video', 'value' => 'A'],
        ['kind' => 'text', 'value' => 'B'],
        ['kind' => 'text', 'value' => 'C'],
        ['kind' => 'text', 'value' => 'D'],
    ];
    $ok = [
        ['kind' => 'text', 'value' => 'W'],
        ['kind' => 'text', 'value' => 'X'],
        ['kind' => 'text', 'value' => 'Y'],
        ['kind' => 'text', 'value' => 'Z'],
    ];

    expect($type->validateOptions(['left' => $bad, 'right' => $ok]))->toBeFalse();
});

test('validateOptions rejects an empty value', function () {
    $type = new MatchPairsType;

    $bad = [
        ['kind' => 'text', 'value' => ''],
        ['kind' => 'text', 'value' => 'B'],
        ['kind' => 'text', 'value' => 'C'],
        ['kind' => 'text', 'value' => 'D'],
    ];
    $ok = [
        ['kind' => 'text', 'value' => 'W'],
        ['kind' => 'text', 'value' => 'X'],
        ['kind' => 'text', 'value' => 'Y'],
        ['kind' => 'text', 'value' => 'Z'],
    ];

    expect($type->validateOptions(['left' => $bad, 'right' => $ok]))->toBeFalse();
});

test('returns correct livewire component names', function () {
    $type = new MatchPairsType;

    expect($type->renderSpectatorComponent())->toBe('question-types.match-pairs-spectator');
    expect($type->renderPlayerComponent())->toBe('question-types.match-pairs-player');
});
```

### - [ ] Step 2: Run it and watch it fail

Run: `vendor/bin/pest tests/Unit/QuestionTypes/MatchPairsTypeTest.php`
Expected: FAIL with `Class "App\QuestionTypes\MatchPairsType" not found`.

### - [ ] Step 3: Implement MatchPairsType

Create `app/QuestionTypes/MatchPairsType.php`:

```php
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
        $limitMs = $question->time_limit_seconds * 1000;
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
```

### - [ ] Step 4: Run it and watch it pass

Run: `vendor/bin/pest tests/Unit/QuestionTypes/MatchPairsTypeTest.php`
Expected: all 15 PASS.

### - [ ] Step 5: Commit

```bash
git add app/QuestionTypes/MatchPairsType.php tests/Unit/QuestionTypes/MatchPairsTypeTest.php
git commit -m "feat: add MatchPairsType question-type implementation"
```

---

## Task 3: Register the type, factory state, registry test

**Files:**
- Modify: `config/quiz.php`
- Modify: `database/factories/QuestionFactory.php`
- Modify: `tests/Unit/QuestionTypeRegistryTest.php`

**Interfaces:**
- Produces: `Question::factory()->matchPairs()` state, used by Task 4's feature test and Task 6's authoring tests. Registered type key `'match_pairs'` resolvable via `App\Services\QuestionTypeRegistry::resolve('match_pairs')`.

### - [ ] Step 1: Write the failing registry test

In `tests/Unit/QuestionTypeRegistryTest.php`, change the third test's assertion (the `toContain` list) to:

```php
test('registry lists all registered types', function () {
    $registry = app(QuestionTypeRegistry::class);
    expect($registry->registered())->toContain('multiple_choice', 'true_false', 'geo_guesser', 'match_pairs');
});
```

### - [ ] Step 2: Run it and watch it fail

Run: `vendor/bin/pest tests/Unit/QuestionTypeRegistryTest.php`
Expected: FAIL — `match_pairs` not in the registered list.

### - [ ] Step 3: Register the type in config/quiz.php

In `config/quiz.php`, add the import and the config entry:

```php
<?php

use App\QuestionTypes\GeoGuesserType;
use App\QuestionTypes\MatchPairsType;
use App\QuestionTypes\MultipleChoiceType;
use App\QuestionTypes\OrderingType;
use App\QuestionTypes\TrueFalseType;

return [
    'question_types' => [
        'multiple_choice' => MultipleChoiceType::class,
        'true_false' => TrueFalseType::class,
        'geo_guesser' => GeoGuesserType::class,
        'ordering' => OrderingType::class,
        'match_pairs' => MatchPairsType::class,
    ],

    'geo_guesser' => [
        // Guesses within this radius (km) of the correct location earn full
        // points and count as correct. Beyond it, points decay linearly to
        // zero at max_distance_km. Both can be overridden per question via
        // the question's `options`.
        'threshold_km' => (float) env('QUIZ_GEO_THRESHOLD_KM', 50),
        'max_distance_km' => (float) env('QUIZ_GEO_MAX_DISTANCE_KM', 2000),
    ],
];
```

### - [ ] Step 4: Run the registry test and watch it pass

Run: `vendor/bin/pest tests/Unit/QuestionTypeRegistryTest.php`
Expected: all PASS.

### - [ ] Step 5: Add the factory state

In `database/factories/QuestionFactory.php`, add this method after `ordering()`:

```php
    public function matchPairs(): static
    {
        return $this->state(fn () => [
            'type' => 'match_pairs',
            'body' => 'Match each country to its capital.',
            'options' => [
                'left' => [
                    ['kind' => 'text', 'value' => 'France'],
                    ['kind' => 'text', 'value' => 'Japan'],
                    ['kind' => 'text', 'value' => 'Egypt'],
                    ['kind' => 'text', 'value' => 'Brazil'],
                ],
                // Shuffled relative to "left" so the broadcast display order
                // never reveals the correct pairing.
                'right' => [
                    ['kind' => 'text', 'value' => 'Cairo'],
                    ['kind' => 'text', 'value' => 'Paris'],
                    ['kind' => 'text', 'value' => 'Brasilia'],
                    ['kind' => 'text', 'value' => 'Tokyo'],
                ],
            ],
            // correct_answer[leftIndex] = rightIndex
            'correct_answer' => [1, 3, 0, 2],
        ]);
    }
```

### - [ ] Step 6: Verify the factory state works in tinker-equivalent test

Run: `vendor/bin/pest --filter='registry lists all registered types'` (re-run as a sanity check, no new test needed yet — the factory state is exercised by Task 4)
Expected: PASS.

### - [ ] Step 7: Commit

```bash
git add config/quiz.php database/factories/QuestionFactory.php tests/Unit/QuestionTypeRegistryTest.php
git commit -m "feat: register match_pairs question type"
```

---

## Task 4: PlayerScreen submission + game-flow test

**Files:**
- Modify: `app/Livewire/PlayerScreen.php`
- Create: `tests/Feature/MatchPairsGameFlowTest.php`

**Interfaces:**
- Consumes: `App\Actions\SubmitAnswer::execute()` (existing, unchanged), `Question::factory()->matchPairs()` (Task 3).
- Produces: `App\Livewire\PlayerScreen::submitMatches(array $pairs): void`, called by the player Alpine component built in Task 8 via `$wire.submitMatches(...)`.

### - [ ] Step 1: Write the failing feature test

Create `tests/Feature/MatchPairsGameFlowTest.php`:

```php
<?php

use App\Actions\SubmitAnswer;
use App\Livewire\PlayerScreen;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    Event::fake();

    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    $this->question = Question::factory()->for($this->category)->matchPairs()->create([
        'order' => 0,
        'points' => 100,
    ]);
    $this->session = GameSession::factory()->for($this->quiz)->for($this->user, 'host')->create(['status' => 'waiting']);
    $this->player = Player::factory()->for($this->session, 'gameSession')->create(['nickname' => 'Matcher']);

    app(GameService::class)->start($this->session);
    $this->action = app(SubmitAnswer::class);
});

test('a fully correct match earns full points and increments streak', function () {
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        [1, 3, 0, 2],
        5000,
    );

    expect($result['is_correct'])->toBeTrue();
    expect($result['points_earned'])->toBe(100);
    expect($this->player->fresh()->score)->toBe(100);
    expect($this->player->fresh()->streak)->toBe(1);
});

test('an incorrect match earns zero points and resets streak', function () {
    $this->player->update(['streak' => 3]);

    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        [3, 1, 0, 2],
        5000,
    );

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->score)->toBe(0);
    expect($this->player->fresh()->streak)->toBe(0);
});

test('a partially correct match (3 of 4 pairs) earns zero points (all-or-nothing)', function () {
    // left[0]->1 and left[1]->3 correct, left[2] and left[3] swapped.
    $result = $this->action->execute(
        $this->session->fresh(),
        $this->player,
        $this->question->id,
        [1, 3, 2, 0],
        5000,
    );

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->score)->toBe(0);
});

test('PlayerScreen::submitMatches delegates to SubmitAnswer and advances the phase', function () {
    Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->assertSet('phase', 'answering')
        ->call('submitMatches', [1, 3, 0, 2])
        ->assertSet('phase', 'answered');

    expect($this->player->fresh()->score)->toBe(100);
});
```

### - [ ] Step 2: Run it and watch it fail

Run: `vendor/bin/pest tests/Feature/MatchPairsGameFlowTest.php`
Expected: the first three tests FAIL because `Question::factory()->matchPairs()` doesn't exist yet if Task 3 wasn't completed first — confirm Task 3 is done, then re-run. With Task 3 done, the first three PASS and the fourth FAILS with `Method [submitMatches] does not exist`.

### - [ ] Step 3: Add submitMatches to PlayerScreen

In `app/Livewire/PlayerScreen.php`, add this method directly after `submitOrder()`:

```php
    public function submitMatches(array $pairs): void
    {
        if (! $this->player || ! $this->currentQuestion) {
            return;
        }

        $action = app(SubmitAnswer::class);

        try {
            $this->lastResult = $action->execute(
                $this->session,
                $this->player,
                $this->currentQuestion['question_id'],
                array_values($pairs),
                $this->elapsedMs(),
            );
        } catch (\LogicException $e) {
            $this->timedOut = true;
        }

        $this->phase = 'answered';
    }
```

### - [ ] Step 4: Run the full test file and watch it pass

Run: `vendor/bin/pest tests/Feature/MatchPairsGameFlowTest.php`
Expected: all 4 PASS.

### - [ ] Step 5: Commit

```bash
git add app/Livewire/PlayerScreen.php tests/Feature/MatchPairsGameFlowTest.php
git commit -m "feat: wire match_pairs submission through PlayerScreen"
```

---

## Task 5: Clean up uploaded images when a match_pairs question is deleted

**Files:**
- Modify: `app/Models/Question.php`
- Create: `tests/Unit/Models/QuestionImageCleanupTest.php`

**Interfaces:**
- Consumes: `App\Services\QuestionImageStorage::delete()` (Task 1).
- Produces: a `deleting` model event on `Question` that's a no-op for every type except `match_pairs`. Task 7 (image upload in `QuizBuilder`) relies on this existing so that `deleteQuestion()` never orphans files.

### - [ ] Step 1: Write the failing test

Create `tests/Unit/Models/QuestionImageCleanupTest.php`:

```php
<?php

use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\Storage;

test('deleting a match_pairs question removes its uploaded images', function () {
    Storage::fake('public');

    $leftImagePath = Storage::disk('public')->put('questions', UploadedFileStub());
    $rightImagePath = Storage::disk('public')->put('questions', UploadedFileStub());

    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->matchPairs()->create([
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => $leftImagePath],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'image', 'value' => $rightImagePath],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
    ]);

    $question->delete();

    Storage::disk('public')->assertMissing($leftImagePath);
    Storage::disk('public')->assertMissing($rightImagePath);
});

test('deleting a question of another type does not touch the public disk', function () {
    Storage::fake('public');

    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->create();

    $question->delete();

    // No files were ever written; this just asserts no exception is thrown
    // for a type with no "options.left"/"options.right" shape.
    expect(Question::find($question->id))->toBeNull();
});

function UploadedFileStub(): \Illuminate\Http\UploadedFile
{
    return \Illuminate\Http\UploadedFile::fake()->image('flag.png');
}
```

### - [ ] Step 2: Run it and watch it fail

Run: `vendor/bin/pest tests/Unit/Models/QuestionImageCleanupTest.php`
Expected: FAIL — the images still exist after delete (no cleanup hook yet).

### - [ ] Step 3: Add the deleting hook to Question

In `app/Models/Question.php`, add the `App\Services\QuestionImageStorage` import and a `booted()` method:

```php
<?php

namespace App\Models;

use App\Services\QuestionImageStorage;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'type',
        'body',
        'options',
        'correct_answer',
        'points',
        'time_limit_seconds',
        'order',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Question $question) {
            if ($question->type !== 'match_pairs') {
                return;
            }

            $storage = app(QuestionImageStorage::class);

            foreach (['left', 'right'] as $side) {
                foreach ($question->options[$side] ?? [] as $item) {
                    if (($item['kind'] ?? null) === 'image' && ! empty($item['value'])) {
                        $storage->delete($item['value']);
                    }
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_answer' => 'json',
            'points' => 'integer',
            'time_limit_seconds' => 'integer',
            'order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

### - [ ] Step 4: Run it and watch it pass

Run: `vendor/bin/pest tests/Unit/Models/QuestionImageCleanupTest.php`
Expected: both PASS.

### - [ ] Step 5: Re-run the existing QuizCrudTest delete tests to confirm no regression

Run: `vendor/bin/pest tests/Feature/QuizCrudTest.php --filter=delete`
Expected: all PASS (those questions are `multiple_choice`, so the hook's early `return` keeps them untouched).

### - [ ] Step 6: Commit

```bash
git add app/Models/Question.php tests/Unit/Models/QuestionImageCleanupTest.php
git commit -m "feat: delete uploaded images when a match_pairs question is removed"
```

---

## Task 6: QuizBuilder authoring — text pairs

**Files:**
- Modify: `app/Livewire/QuizBuilder.php`
- Modify: `resources/views/livewire/quiz-builder.blade.php`
- Create: `tests/Feature/QuizBuilderMatchPairsTest.php`

**Interfaces:**
- Produces: `$questionPairs` public array property shaped `[{left: {kind, text, image, existingImage}, right: {...}}, ...]` (4 entries). `resolvePairItem(array $item): array` and `pairFormState(array $item): array` private helpers — Task 7 extends both with image-kind branches; their text-kind behavior must not change.

This task handles only `kind === 'text'` pairs end-to-end (save, edit, shuffle, validation). Task 7 adds the image-kind branch on top of the exact same methods.

### - [ ] Step 1: Write the failing tests

Create `tests/Feature/QuizBuilderMatchPairsTest.php`:

```php
<?php

use App\Livewire\QuizBuilder;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('user can add a match_pairs question with four text pairs', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the country to its capital')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.text', 'France')
        ->set('questionPairs.0.right.text', 'Paris')
        ->set('questionPairs.1.left.text', 'Japan')
        ->set('questionPairs.1.right.text', 'Tokyo')
        ->set('questionPairs.2.left.text', 'Egypt')
        ->set('questionPairs.2.right.text', 'Cairo')
        ->set('questionPairs.3.left.text', 'Brazil')
        ->set('questionPairs.3.right.text', 'Brasilia')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->where('type', 'match_pairs')->firstOrFail();

    $leftValues = collect($question->options['left'])->pluck('value')->all();
    expect($leftValues)->toBe(['France', 'Japan', 'Egypt', 'Brazil']);

    $rightValues = collect($question->options['right'])->pluck('value')->all();
    expect($rightValues)->toEqualCanonicalizing(['Paris', 'Tokyo', 'Cairo', 'Brasilia']);
    // Right column must be shuffled relative to the entered order so the
    // broadcast never reveals the pairing by position alignment.
    expect($rightValues)->not->toBe(['Paris', 'Tokyo', 'Cairo', 'Brasilia']);

    // correct_answer[leftIndex] = rightIndex must reconstruct the entered pairs.
    $correctAnswer = $question->correct_answer;
    expect($correctAnswer)->toHaveCount(4);
    foreach (['France' => 'Paris', 'Japan' => 'Tokyo', 'Egypt' => 'Cairo', 'Brazil' => 'Brasilia'] as $leftLabel => $rightLabel) {
        $leftIndex = array_search($leftLabel, $leftValues, true);
        expect($rightValues[$correctAnswer[$leftIndex]])->toBe($rightLabel);
    }
});

test('match_pairs question requires text in every pair slot', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the pairs')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.text', 'France')
        ->set('questionPairs.0.right.text', 'Paris')
        // pairs 1-3 left blank
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasErrors('questionPairs.1.left.text');

    expect($category->questions()->count())->toBe(0);
});

test('editing a match_pairs question loads its pairs into the form', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'match_pairs',
        'body' => 'Match the pairs',
        'options' => [
            'left' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'Japan'],
                ['kind' => 'text', 'value' => 'Egypt'],
                ['kind' => 'text', 'value' => 'Brazil'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'Cairo'],
                ['kind' => 'text', 'value' => 'Paris'],
                ['kind' => 'text', 'value' => 'Brasilia'],
                ['kind' => 'text', 'value' => 'Tokyo'],
            ],
        ],
        'correct_answer' => [1, 3, 0, 2],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionType', 'match_pairs')
        ->assertSet('questionPairs.0.left.text', 'France')
        ->assertSet('questionPairs.0.right.text', 'Paris')
        ->assertSet('questionPairs.1.left.text', 'Japan')
        ->assertSet('questionPairs.1.right.text', 'Tokyo')
        ->assertSet('questionPairs.2.left.text', 'Egypt')
        ->assertSet('questionPairs.2.right.text', 'Cairo')
        ->assertSet('questionPairs.3.left.text', 'Brazil')
        ->assertSet('questionPairs.3.right.text', 'Brasilia');
});

test('user can update an existing match_pairs question', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'match_pairs',
        'body' => 'Match the pairs',
        'options' => [
            'left' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'Japan'],
                ['kind' => 'text', 'value' => 'Egypt'],
                ['kind' => 'text', 'value' => 'Brazil'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'Cairo'],
                ['kind' => 'text', 'value' => 'Paris'],
                ['kind' => 'text', 'value' => 'Brasilia'],
                ['kind' => 'text', 'value' => 'Tokyo'],
            ],
        ],
        'correct_answer' => [1, 3, 0, 2],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('questionPairs.0.right.text', 'Berlin')
        ->call('saveQuestion')
        ->assertHasNoErrors()
        ->assertSet('editingQuestionId', null);

    $question->refresh();
    $rightValues = collect($question->options['right'])->pluck('value')->all();
    expect($rightValues)->toContain('Berlin');
    expect($category->questions()->count())->toBe(1);
});
```

### - [ ] Step 2: Run it and watch it fail

Run: `vendor/bin/pest tests/Feature/QuizBuilderMatchPairsTest.php`
Expected: FAIL — `questionPairs` doesn't exist on the component yet.

### - [ ] Step 3: Add the questionPairs property and default-state helper

In `app/Livewire/QuizBuilder.php`, add the property after `public string $questionGeoMaxDistanceKm = '';`:

```php
    public array $questionPairs = [];
```

Add this private method right before `private function resetQuestionForm(): void`:

```php
    /**
     * @return array<int, array{left: array, right: array}>
     */
    private function defaultQuestionPairs(): array
    {
        $emptySlot = ['kind' => 'text', 'text' => '', 'image' => null, 'existingImage' => null];

        return array_fill(0, 4, ['left' => $emptySlot, 'right' => $emptySlot]);
    }
```

### - [ ] Step 4: Reset questionPairs in resetQuestionForm()

In `app/Livewire/QuizBuilder.php`, add a line to `resetQuestionForm()`:

```php
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
```

### - [ ] Step 5: Add match_pairs to the allowed type list and add its validation + content check

In `saveQuestion()`, change the `questionType` rule and add a `match_pairs`-specific block. The method becomes:

```php
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
            }
        }

        return true;
    }
```

### - [ ] Step 6: Add the buildQuestionPayload() match_pairs branch and resolvePairItem()

In `app/Livewire/QuizBuilder.php`, add a `match_pairs` branch to `buildQuestionPayload()` (insert it as the first `if` in the method, before the `true_false` check — order among the `if`s doesn't matter since they're mutually exclusive on `$this->questionType`, but keep it readable):

```php
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
     * Text-only for now — Task 7 adds the image-kind branch here.
     */
    private function resolvePairItem(array $item): array
    {
        return ['kind' => 'text', 'value' => trim($item['text'] ?? '')];
    }
```

### - [ ] Step 7: Add the match_pairs branch to editQuestion()

In `app/Livewire/QuizBuilder.php`, add an `elseif` branch to `editQuestion()` (after the `geo_guesser` branch):

```php
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
```

Then add the `pairFormState()` helper right after `defaultQuestionPairs()`:

```php
    /**
     * Reconstruct a pair slot's form state from its stored {kind, value}.
     * Text-only for now — Task 7 adds the image-kind branch here.
     */
    private function pairFormState(array $item): array
    {
        return ['kind' => 'text', 'text' => $item['value'] ?? '', 'image' => null, 'existingImage' => null];
    }
```

### - [ ] Step 8: Add the type option and pair-entry form to the Blade view

In `resources/views/livewire/quiz-builder.blade.php`, add the dropdown option (inside the existing `<select wire:model.live="questionType">`):

```blade
                                        <select wire:model.live="questionType" class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white">
                                            <option value="multiple_choice">{{ __('Multiple Choice') }}</option>
                                            <option value="true_false">{{ __('True / False') }}</option>
                                            <option value="ordering">{{ __('Ordering') }}</option>
                                            <option value="geo_guesser">{{ __('Geo Guesser') }}</option>
                                            <option value="match_pairs">{{ __('Match Pairs') }}</option>
                                        </select>
```

Then add the pair-entry section, right after the `@if($questionType === 'geo_guesser') ... @endif` block and before the form's save/cancel buttons:

```blade
                                @if($questionType === 'match_pairs')
                                <div data-test="match-pairs-form">
                                    <label class="mb-1 block text-sm font-medium dark:text-neutral-300">{{ __('Pairs (left matches right)') }}</label>
                                    <div class="space-y-2">
                                        @foreach($questionPairs as $index => $pair)
                                            <div class="flex gap-2" wire:key="match-pair-{{ $index }}">
                                                <flux:input wire:model.live.debounce.300ms="questionPairs.{{ $index }}.left.text" :placeholder="__('Left :n', ['n' => $index + 1])" size="sm" class="flex-1" />
                                                <flux:input wire:model.live.debounce.300ms="questionPairs.{{ $index }}.right.text" :placeholder="__('Right :n', ['n' => $index + 1])" size="sm" class="flex-1" />
                                            </div>
                                            @error("questionPairs.$index.left.text") <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                            @error("questionPairs.$index.right.text") <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                        @endforeach
                                    </div>
                                    @error('questionPairs') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                </div>
                                @endif
```

### - [ ] Step 9: Run the tests and watch them pass

Run: `vendor/bin/pest tests/Feature/QuizBuilderMatchPairsTest.php`
Expected: all 4 PASS.

### - [ ] Step 10: Re-run the full QuizBuilder test file to confirm no regression

Run: `vendor/bin/pest tests/Feature/QuizCrudTest.php`
Expected: all PASS.

### - [ ] Step 11: Commit

```bash
git add app/Livewire/QuizBuilder.php resources/views/livewire/quiz-builder.blade.php tests/Feature/QuizBuilderMatchPairsTest.php
git commit -m "feat: author match_pairs questions with text pairs in QuizBuilder"
```

---

## Task 7: QuizBuilder authoring — image pairs

**Files:**
- Modify: `app/Livewire/QuizBuilder.php`
- Modify: `resources/views/livewire/quiz-builder.blade.php`
- Modify: `tests/Feature/QuizBuilderMatchPairsTest.php`

**Interfaces:**
- Consumes: `App\Services\QuestionImageStorage::store()`/`::delete()` (Task 1).
- Produces: full image-kind support on the same `resolvePairItem()`/`pairFormState()`/`validateQuestionPairsContent()` methods Task 6 introduced — their text-kind behavior is unchanged by this task.

### - [ ] Step 1: Write the failing tests

Append to `tests/Feature/QuizBuilderMatchPairsTest.php` (add `use Illuminate\Http\UploadedFile;` and `use Illuminate\Support\Facades\Storage;` to the `use` block at the top of the file):

```php
test('user can add a match_pairs question with an uploaded image pair side', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the flag to its country')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.kind', 'image')
        ->set('questionPairs.0.left.image', UploadedFile::fake()->image('flag.png'))
        ->set('questionPairs.0.right.text', 'France')
        ->set('questionPairs.1.left.text', 'B')
        ->set('questionPairs.1.right.text', 'X')
        ->set('questionPairs.2.left.text', 'C')
        ->set('questionPairs.2.right.text', 'Y')
        ->set('questionPairs.3.left.text', 'D')
        ->set('questionPairs.3.right.text', 'Z')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->where('type', 'match_pairs')->firstOrFail();
    $leftZero = $question->options['left'][0];

    expect($leftZero['kind'])->toBe('image');
    expect($leftZero['value'])->toStartWith('questions/');
    Storage::disk('public')->assertExists($leftZero['value']);
});

test('switching a pair side to image requires an upload', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the pairs')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.kind', 'image')
        // no image uploaded and no existingImage
        ->set('questionPairs.0.right.text', 'France')
        ->set('questionPairs.1.left.text', 'B')
        ->set('questionPairs.1.right.text', 'X')
        ->set('questionPairs.2.left.text', 'C')
        ->set('questionPairs.2.right.text', 'Y')
        ->set('questionPairs.3.left.text', 'D')
        ->set('questionPairs.3.right.text', 'Z')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasErrors('questionPairs.0.left.image');

    expect($category->questions()->count())->toBe(0);
});

test('replacing an image on edit deletes the old file and stores the new one', function () {
    Storage::fake('public');
    $oldPath = Storage::disk('public')->put('questions', UploadedFile::fake()->image('old.png'));

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'match_pairs',
        'body' => 'Match the flags',
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => $oldPath],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
        'correct_answer' => [0, 1, 2, 3],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionPairs.0.left.existingImage', $oldPath)
        ->set('questionPairs.0.left.image', UploadedFile::fake()->image('new.png'))
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question->refresh();
    $newPath = $question->options['left'][0]['value'];

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

test('editing a match_pairs question keeps its existing image without requiring re-upload', function () {
    Storage::fake('public');
    $path = Storage::disk('public')->put('questions', UploadedFile::fake()->image('flag.png'));

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'match_pairs',
        'body' => 'Match the flags',
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => $path],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
        'correct_answer' => [0, 1, 2, 3],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('questionBody', 'Match the flags (updated)')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question->refresh();
    expect($question->options['left'][0]['value'])->toBe($path);
    Storage::disk('public')->assertExists($path);
});
```

### - [ ] Step 2: Run them and watch them fail

Run: `vendor/bin/pest tests/Feature/QuizBuilderMatchPairsTest.php`
Expected: the first new test FAILs because the saved item's `kind` is always `'text'` (Task 6's `resolvePairItem` ignores `kind`); the "requires an upload" test fails because nothing currently blocks an empty image; the replace/keep tests fail because `pairFormState` never sets `existingImage`.

### - [ ] Step 3: Add the WithFileUploads trait and QuestionImageStorage import

In `app/Livewire/QuizBuilder.php`, update the imports and class declaration:

```php
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

    // ...existing properties unchanged...
```

(Leave every existing property and method below the `use WithFileUploads;` line exactly as-is — this step only adds the trait and the one import.)

### - [ ] Step 4: Extend resolvePairItem() and pairFormState() with the image branch

Replace the two helper methods added in Task 6 with:

```php
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
     * Reconstruct a pair slot's form state from its stored {kind, value}.
     */
    private function pairFormState(array $item): array
    {
        if (($item['kind'] ?? 'text') === 'image') {
            return ['kind' => 'image', 'text' => '', 'image' => null, 'existingImage' => $item['value'] ?? null];
        }

        return ['kind' => 'text', 'text' => $item['value'] ?? '', 'image' => null, 'existingImage' => null];
    }
```

### - [ ] Step 5: Extend validateQuestionPairsContent() and the saveQuestion() rules

Replace `validateQuestionPairsContent()` with:

```php
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
```

In `saveQuestion()`, extend the `match_pairs` rules block to also validate any uploaded file's shape:

```php
        if ($this->questionType === 'match_pairs') {
            $rules['questionPairs'] = 'required|array|size:4';
            foreach (range(0, 3) as $i) {
                $rules["questionPairs.$i.left.kind"] = 'required|in:text,image';
                $rules["questionPairs.$i.right.kind"] = 'required|in:text,image';
                $rules["questionPairs.$i.left.image"] = 'nullable|image|mimes:jpeg,png,webp,gif|max:2048';
                $rules["questionPairs.$i.right.image"] = 'nullable|image|mimes:jpeg,png,webp,gif|max:2048';
            }
        }
```

### - [ ] Step 6: Add cleanup-on-replace and wire it into the update path

Add these two private methods to `app/Livewire/QuizBuilder.php` (anywhere among the other private helpers, e.g. after `pairFormState()`):

```php
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
```

In `saveQuestion()`, call it right before `$question->update(...)` in the `editingQuestionId` branch:

```php
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
```

(The `else` branch — creating a new question — is unchanged.)

### - [ ] Step 7: Replace the pair-entry Blade section with the kind-toggle version

In `resources/views/livewire/quiz-builder.blade.php`, replace the `@if($questionType === 'match_pairs') ... @endif` block added in Task 6 with:

```blade
                                @if($questionType === 'match_pairs')
                                <div data-test="match-pairs-form" class="space-y-2">
                                    <label class="mb-1 block text-sm font-medium dark:text-neutral-300">{{ __('Pairs (left matches right)') }}</label>
                                    @foreach($questionPairs as $index => $pair)
                                        <div class="grid grid-cols-2 gap-2" wire:key="match-pair-{{ $index }}">
                                            @foreach(['left', 'right'] as $side)
                                                <div class="space-y-1 rounded-md border border-neutral-200 p-2 dark:border-neutral-700">
                                                    <div class="flex gap-1">
                                                        <flux:button type="button" size="xs" :variant="$pair[$side]['kind'] === 'text' ? 'primary' : 'ghost'"
                                                                      wire:click="$set('questionPairs.{{ $index }}.{{ $side }}.kind', 'text')"
                                                                      data-test="match-pair-kind-text-{{ $index }}-{{ $side }}">
                                                            {{ __('Text') }}
                                                        </flux:button>
                                                        <flux:button type="button" size="xs" :variant="$pair[$side]['kind'] === 'image' ? 'primary' : 'ghost'"
                                                                      wire:click="$set('questionPairs.{{ $index }}.{{ $side }}.kind', 'image')"
                                                                      data-test="match-pair-kind-image-{{ $index }}-{{ $side }}">
                                                            {{ __('Image') }}
                                                        </flux:button>
                                                    </div>

                                                    @if($pair[$side]['kind'] === 'image')
                                                        @if($pair[$side]['existingImage'] && ! $pair[$side]['image'])
                                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($pair[$side]['existingImage']) }}"
                                                                 class="h-12 w-12 rounded object-cover" alt="" />
                                                        @endif
                                                        <input type="file"
                                                               wire:model="questionPairs.{{ $index }}.{{ $side }}.image"
                                                               data-test="match-pair-image-{{ $index }}-{{ $side }}"
                                                               class="block text-xs" />
                                                        @error("questionPairs.$index.$side.image") <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                                    @else
                                                        <flux:input wire:model.live.debounce.300ms="questionPairs.{{ $index }}.{{ $side }}.text"
                                                                    :placeholder="$side === 'left' ? __('Left :n', ['n' => $index + 1]) : __('Right :n', ['n' => $index + 1])"
                                                                    size="sm" />
                                                        @error("questionPairs.$index.$side.text") <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                    @error('questionPairs') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                                </div>
                                @endif
```

### - [ ] Step 8: Run the full match_pairs builder test file and watch it pass

Run: `vendor/bin/pest tests/Feature/QuizBuilderMatchPairsTest.php`
Expected: all 8 tests PASS.

### - [ ] Step 9: Re-run QuizCrudTest and the image-cleanup test to confirm no regression

Run: `vendor/bin/pest tests/Feature/QuizCrudTest.php tests/Unit/Models/QuestionImageCleanupTest.php`
Expected: all PASS.

### - [ ] Step 10: Commit

```bash
git add app/Livewire/QuizBuilder.php resources/views/livewire/quiz-builder.blade.php tests/Feature/QuizBuilderMatchPairsTest.php
git commit -m "feat: support uploaded images per pair side in QuizBuilder"
```

---

## Task 8: Player tap-to-pair UI

**Files:**
- Create: `resources/js/match-pairs.js`
- Create: `resources/views/question-types/match-pairs-player.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/views/livewire/player-screen.blade.php`
- Modify: `tests/Feature/MatchPairsGameFlowTest.php`

**Interfaces:**
- Consumes: `App\Livewire\PlayerScreen::submitMatches(array $pairs)` (Task 4), the `answer-provider` / `questionTimer` contract already established by `resources/js/question-timer.js` and `resources/js/ordering.js`.
- Produces: Alpine component `matchPairs(config)` registered as `Alpine.data('matchPairs', ...)`, consumed by the new Blade partial.

### - [ ] Step 1: Write the Alpine component

Create `resources/js/match-pairs.js`:

```js
/**
 * Alpine component backing the match-pairs question's tap-to-pair UI.
 *
 * config: { left: [{kind, value}], right: [{kind, value}] }  // value is text
 * or a fully-resolved image URL — resolved server-side in the Blade partial.
 *
 * Tap a left item, then a right item, to lock a pair. Tapping either side of
 * an already-locked pair unlocks it so the player can redo it. No
 * drag-and-drop: this needs to work reliably on a phone.
 */
export function matchPairs(config) {
    return {
        left: [],
        right: [],
        pairs: [],
        selectedLeft: null,
        submitted: false,

        init() {
            this.left = Array.from(config.left || []);
            this.right = Array.from(config.right || []);
            this.pairs = this.left.map(() => null);

            // Hand the current (possibly incomplete) pairing to the countdown
            // timer so it can be auto-submitted if the player runs out of time.
            this.$dispatch('answer-provider', { provider: () => Array.from(this.pairs) });
        },

        isComplete() {
            return this.pairs.length > 0 && this.pairs.every((v) => v !== null);
        },

        pairedWith(leftIndex) {
            return this.pairs[leftIndex];
        },

        rightUsedAt(rightIndex) {
            return this.pairs.findIndex((v) => v === rightIndex);
        },

        tapLeft(index) {
            if (this.submitted) {
                return;
            }

            if (this.pairs[index] !== null) {
                this.pairs[index] = null;
                this.selectedLeft = null;
                return;
            }

            this.selectedLeft = index;
        },

        tapRight(index) {
            if (this.submitted) {
                return;
            }

            const usedAt = this.rightUsedAt(index);
            if (usedAt !== -1) {
                this.pairs[usedAt] = null;
                if (usedAt === this.selectedLeft) {
                    this.selectedLeft = null;
                }
                return;
            }

            if (this.selectedLeft === null) {
                return;
            }

            this.pairs[this.selectedLeft] = index;
            this.selectedLeft = null;
        },

        colorFor(leftIndex) {
            const palette = ['a', 'b', 'c', 'd'];
            return palette[leftIndex % palette.length];
        },

        submit() {
            if (this.submitted || ! this.isComplete()) {
                return;
            }
            this.submitted = true;
            this.$wire.submitMatches(Array.from(this.pairs));
        },
    };
}

export function registerMatchPairs(Alpine) {
    Alpine.data('matchPairs', matchPairs);
}
```

### - [ ] Step 2: Register the component in app.js

In `resources/js/app.js`, add the import and registration call:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { registerChoiceAnswer } from './choice-answer';
import { registerGeoMap } from './geo-map';
import { registerGeoPicker } from './geo-picker';
import { registerMatchPairs } from './match-pairs';
import { registerOrdering } from './ordering';
import { registerQuestionTimer } from './question-timer';
import { registerReactionBar, registerReactionFloat } from './reactions';
import { registerPlayerSession } from './player-session';

window.Pusher = Pusher;

document.addEventListener('alpine:init', () => {
    registerChoiceAnswer(window.Alpine);
    registerGeoMap(window.Alpine);
    registerGeoPicker(window.Alpine);
    registerMatchPairs(window.Alpine);
    registerOrdering(window.Alpine);
    registerQuestionTimer(window.Alpine);
    registerReactionBar(window.Alpine);
    registerReactionFloat(window.Alpine);
    registerPlayerSession(window.Alpine);
});
```

(Only the `registerMatchPairs` import line and its call inside `alpine:init` are new — everything else in the file is unchanged.)

### - [ ] Step 3: Create the player Blade partial

Create `resources/views/question-types/match-pairs-player.blade.php`:

```blade
@php
    $resolveItems = fn ($items) => collect($items)->map(fn ($item) => [
        'kind' => $item['kind'] ?? 'text',
        'value' => ($item['kind'] ?? 'text') === 'image'
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($item['value'])
            : $item['value'],
    ])->values()->all();

    $leftItems = $resolveItems($currentQuestion['options']['left'] ?? []);
    $rightItems = $resolveItems($currentQuestion['options']['right'] ?? []);

    $isThemed = ($themeKey ?? null)
        && $themeKey !== 'default'
        && array_key_exists($themeKey, config('themes', []));
    $themeEmoji = $isThemed ? config('themes.'.$themeKey.'.emoji') : null;
    $themeBadge = $isThemed ? config('themes.'.$themeKey.'.badge') : null;
@endphp

@if($isThemed)
    <div
        wire:key="match-pairs-player-{{ $currentQuestion['question_id'] ?? 'q' }}"
        x-data="matchPairs(@js(['left' => $leftItems, 'right' => $rightItems]))"
        class="qz-theme qz-theme--{{ $themeKey }} qz-player w-full"
    >
        @include('themes._deco')

        <div class="space-y-5">
            @if(! empty($currentQuestion['category_name']))
                <div class="flex justify-center">
                    <span class="qz-badge">{{ $themeBadge }} {{ $currentQuestion['category_name'] }}</span>
                </div>
            @endif

            @if($currentQuestion['body'] ?? null)
                <div class="qz-question">
                    <span class="qz-emoji">{{ $themeEmoji }}</span>
                    <h2>{{ $currentQuestion['body'] }}</h2>
                </div>
            @endif

            <p class="qz-hint">{{ __('Tap a card on the left, then its match on the right.') }}</p>

            <div class="grid grid-cols-2 gap-3" data-test="match-pairs-columns">
                <ul class="qz-orderlist" data-test="match-pairs-left">
                    <template x-for="(item, index) in left" :key="'left-' + index">
                        <li
                            x-on:click="tapLeft(index)"
                            data-test="match-pairs-left-item"
                            class="qz-option qz-order"
                            x-bind:class="pairedWith(index) !== null ? colorFor(index) : ''"
                        >
                            <img x-show="item.kind === 'image'" x-bind:src="item.value" class="h-16 w-16 rounded object-cover" alt="" />
                            <span x-show="item.kind === 'text'" x-text="item.value" class="qz-order__label"></span>
                        </li>
                    </template>
                </ul>
                <ul class="qz-orderlist" data-test="match-pairs-right">
                    <template x-for="(item, index) in right" :key="'right-' + index">
                        <li
                            x-on:click="tapRight(index)"
                            data-test="match-pairs-right-item"
                            class="qz-option qz-order"
                            x-bind:class="rightUsedAt(index) !== -1 ? colorFor(rightUsedAt(index)) : ''"
                        >
                            <img x-show="item.kind === 'image'" x-bind:src="item.value" class="h-16 w-16 rounded object-cover" alt="" />
                            <span x-show="item.kind === 'text'" x-text="item.value" class="qz-order__label"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <button
                type="button"
                x-on:click="submit()"
                x-bind:disabled="submitted || ! isComplete() || (typeof expired !== 'undefined' && expired)"
                data-test="match-pairs-submit"
                class="qz-cta"
            >
                {{ __('Submit matches') }}
            </button>
        </div>
    </div>
@else
    <div
        wire:key="match-pairs-player-{{ $currentQuestion['question_id'] ?? 'q' }}"
        x-data="matchPairs(@js(['left' => $leftItems, 'right' => $rightItems]))"
        class="w-full space-y-4"
    >
        @if($currentQuestion['body'] ?? null)
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $currentQuestion['body'] }}</h2>
        @endif

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Tap a card on the left, then its match on the right.') }}
        </p>

        <div class="grid grid-cols-2 gap-3" data-test="match-pairs-columns">
            <ul class="space-y-2" data-test="match-pairs-left">
                <template x-for="(item, index) in left" :key="'left-' + index">
                    <li
                        x-on:click="tapLeft(index)"
                        data-test="match-pairs-left-item"
                        class="flex items-center justify-center rounded-xl border border-zinc-200 bg-white p-3 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                        x-bind:class="pairedWith(index) !== null ? 'ring-2 ring-blue-500' : ''"
                    >
                        <img x-show="item.kind === 'image'" x-bind:src="item.value" class="h-16 w-16 rounded object-cover" alt="" />
                        <span x-show="item.kind === 'text'" x-text="item.value" class="font-medium text-zinc-900 dark:text-white"></span>
                    </li>
                </template>
            </ul>
            <ul class="space-y-2" data-test="match-pairs-right">
                <template x-for="(item, index) in right" :key="'right-' + index">
                    <li
                        x-on:click="tapRight(index)"
                        data-test="match-pairs-right-item"
                        class="flex items-center justify-center rounded-xl border border-zinc-200 bg-white p-3 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                        x-bind:class="rightUsedAt(index) !== -1 ? 'ring-2 ring-blue-500' : ''"
                    >
                        <img x-show="item.kind === 'image'" x-bind:src="item.value" class="h-16 w-16 rounded object-cover" alt="" />
                        <span x-show="item.kind === 'text'" x-text="item.value" class="font-medium text-zinc-900 dark:text-white"></span>
                    </li>
                </template>
            </ul>
        </div>

        <button
            type="button"
            x-on:click="submit()"
            x-bind:disabled="submitted || ! isComplete() || (typeof expired !== 'undefined' && expired)"
            data-test="match-pairs-submit"
            class="w-full rounded-xl bg-blue-600 px-4 py-3 text-lg font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('Submit matches') }}
        </button>
    </div>
@endif
```

### - [ ] Step 4: Wire the partial into player-screen.blade.php

In `resources/views/livewire/player-screen.blade.php`, add a `match_pairs` branch to the answering-phase type switch, **before** the generic `@elseif($currentQuestion && ! empty($currentQuestion['options']))` fallback (it must come first — a `match_pairs` question also has non-empty `options`, so the generic branch would otherwise swallow it):

```blade
                @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
                    @include('question-types.geo-guesser-player')
                @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'ordering')
                    @include('question-types.ordering-player')
                @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'match_pairs')
                    @include('question-types.match-pairs-player')
                @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'true_false')
                    @include('question-types.true-false-player')
                @elseif($currentQuestion && ! empty($currentQuestion['options']))
```

(Everything after this `@elseif` chain, and the rest of the file, is unchanged.)

### - [ ] Step 5: Write a rendering smoke test

Append to `tests/Feature/MatchPairsGameFlowTest.php`:

```php
test('player screen renders the match-pairs tap-to-pair UI during the answering phase', function () {
    Livewire::withQueryParams(['player_id' => $this->player->id])
        ->test(PlayerScreen::class, ['code' => $this->session->join_code])
        ->call('pollState')
        ->assertSet('phase', 'answering')
        ->assertSeeHtml('data-test="match-pairs-left-item"')
        ->assertSeeHtml('data-test="match-pairs-right-item"')
        ->assertSeeHtml('data-test="match-pairs-submit"');
});
```

### - [ ] Step 6: Run it and watch it pass

Run: `vendor/bin/pest tests/Feature/MatchPairsGameFlowTest.php`
Expected: all 5 tests PASS.

### - [ ] Step 7: Re-run the full player-screen-related feature tests to confirm no regression

Run: `vendor/bin/pest tests/Feature/GameFlowTest.php tests/Feature/OrderingGameFlowTest.php tests/Feature/QuestionTimerTest.php`
Expected: all PASS — the new `@elseif` branch only activates for `match_pairs`, so every other type's rendering path is untouched.

### - [ ] Step 8: Commit

```bash
git add resources/js/match-pairs.js resources/js/app.js resources/views/question-types/match-pairs-player.blade.php resources/views/livewire/player-screen.blade.php tests/Feature/MatchPairsGameFlowTest.php
git commit -m "feat: add player tap-to-pair UI for match_pairs questions"
```

---

## Task 9: Spectator/host view

**Files:**
- Create: `resources/views/question-types/match-pairs-spectator.blade.php`
- Modify: `resources/views/livewire/spectator-screen.blade.php`
- Modify: `tests/Feature/MatchPairsGameFlowTest.php`

**Interfaces:**
- Consumes: the same broadcast payload as the player partial (`$currentQuestion['options']['left'|'right']`), plus the existing spectator-screen variables `$phase`, `$themeKey`, `$answeredCount`, `$totalPlayers`, `$correctAnswer`, `$scores` — all already populated generically by `App\Livewire\SpectatorScreen` for every question type, no changes needed there.

### - [ ] Step 1: Create the spectator Blade partial

Create `resources/views/question-types/match-pairs-spectator.blade.php`:

```blade
@php
    $resolveItems = fn ($items) => collect($items)->map(fn ($item) => [
        'kind' => $item['kind'] ?? 'text',
        'value' => ($item['kind'] ?? 'text') === 'image'
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($item['value'])
            : $item['value'],
    ])->values()->all();

    $leftItems = $resolveItems($currentQuestion['options']['left'] ?? []);
    $rightItems = $resolveItems($currentQuestion['options']['right'] ?? []);

    $isThemed = ($themeKey ?? null)
        && $themeKey !== 'default'
        && array_key_exists($themeKey, config('themes', []));
    $themeEmoji = $isThemed ? config('themes.'.$themeKey.'.emoji') : null;

    $correctPairs = [];
    if (($phase ?? null) === 'review' && is_array($correctAnswer ?? null)) {
        foreach ($correctAnswer as $leftIndex => $rightIndex) {
            $correctPairs[] = ['left' => $leftItems[$leftIndex] ?? null, 'right' => $rightItems[$rightIndex] ?? null];
        }
    }
@endphp

@if($isThemed)
    <div
        wire:key="match-pairs-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        class="qz-theme qz-theme--{{ $themeKey }} qz-spectator {{ ($phase ?? null) === 'review' ? 'qz-review' : '' }} w-full"
    >
        @include('themes._deco')

        <div class="mx-auto w-full space-y-8">
            <div class="qz-question">
                <span class="qz-emoji">{{ $themeEmoji }}</span>
                <h2 data-test="spectator-question-body">{{ $currentQuestion['body'] ?? '' }}</h2>
            </div>

            @if(($phase ?? null) === 'review')
                <div class="space-y-3" data-test="match-pairs-correct-pairs">
                    @foreach($correctPairs as $pair)
                        <div class="flex items-center justify-center gap-4">
                            <span class="qz-option qz-order is-correct">
                                @if(($pair['left']['kind'] ?? null) === 'image')
                                    <img src="{{ $pair['left']['value'] }}" class="h-16 w-16 rounded object-cover" alt="" />
                                @else
                                    <span class="qz-order__label">{{ $pair['left']['value'] ?? '' }}</span>
                                @endif
                            </span>
                            <span aria-hidden="true">&#8596;</span>
                            <span class="qz-option qz-order is-correct">
                                @if(($pair['right']['kind'] ?? null) === 'image')
                                    <img src="{{ $pair['right']['value'] }}" class="h-16 w-16 rounded object-cover" alt="" />
                                @else
                                    <span class="qz-order__label">{{ $pair['right']['value'] ?? '' }}</span>
                                @endif
                            </span>
                            <span class="ml-auto">&#10003;</span>
                        </div>
                    @endforeach
                </div>

                @if(! empty($scores ?? []))
                    <div class="qz-question" style="text-align:left">
                        <h3 class="qz-qlabel" style="margin-bottom:10px">{{ __('Leaderboard') }}</h3>
                        <ol class="space-y-2">
                            @foreach($scores as $entry)
                                <li class="flex justify-between">
                                    <span>{{ $entry['nickname'] ?? '' }}</span>
                                    <span style="font-weight:700">{{ $entry['score'] ?? 0 }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            @else
                <div class="grid grid-cols-2 gap-3" data-test="match-pairs-columns">
                    <ul class="qz-orderlist">
                        @foreach($leftItems as $item)
                            <li class="qz-option qz-order">
                                @if($item['kind'] === 'image')
                                    <img src="{{ $item['value'] }}" class="h-16 w-16 rounded object-cover" alt="" />
                                @else
                                    <span class="qz-order__label">{{ $item['value'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <ul class="qz-orderlist">
                        @foreach($rightItems as $item)
                            <li class="qz-option qz-order">
                                @if($item['kind'] === 'image')
                                    <img src="{{ $item['value'] }}" class="h-16 w-16 rounded object-cover" alt="" />
                                @else
                                    <span class="qz-order__label">{{ $item['value'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="qz-hint">{{ __('Players are matching pairs...') }}</p>

                <div class="qz-meta">
                    <span>{{ __(':answered / :total answered', ['answered' => $answeredCount ?? 0, 'total' => $totalPlayers ?? 0]) }}</span>
                    <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
                </div>
            @endif
        </div>
    </div>
@else
    <div
        wire:key="match-pairs-spectator-{{ $currentQuestion['question_id'] ?? 'q' }}-{{ $phase }}"
        class="w-full space-y-8"
    >
        <div class="text-center">
            <h2 data-test="spectator-question-body" class="text-[clamp(2.5rem,5vw,4.5rem)] leading-tight font-bold text-zinc-900 dark:text-white">
                {{ $currentQuestion['body'] ?? '' }}
            </h2>
        </div>

        @if(($phase ?? null) === 'review')
            <p class="text-center text-3xl font-semibold text-green-600 dark:text-green-400">
                {{ __('Correct pairs:') }}
            </p>
            <div class="mx-auto max-w-3xl space-y-4" data-test="match-pairs-correct-pairs">
                @foreach($correctPairs as $pair)
                    <div class="flex items-center justify-center gap-4 rounded-xl border-2 border-green-500 bg-green-100 p-6 dark:bg-green-900/40">
                        <span class="flex items-center justify-center text-2xl font-semibold text-green-900 dark:text-green-200">
                            @if(($pair['left']['kind'] ?? null) === 'image')
                                <img src="{{ $pair['left']['value'] }}" class="h-20 w-20 rounded object-cover" alt="" />
                            @else
                                {{ $pair['left']['value'] ?? '' }}
                            @endif
                        </span>
                        <span class="text-green-600 dark:text-green-400" aria-hidden="true">&#8596;</span>
                        <span class="flex items-center justify-center text-2xl font-semibold text-green-900 dark:text-green-200">
                            @if(($pair['right']['kind'] ?? null) === 'image')
                                <img src="{{ $pair['right']['value'] }}" class="h-20 w-20 rounded object-cover" alt="" />
                            @else
                                {{ $pair['right']['value'] ?? '' }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

            @if(! empty($scores ?? []))
                <div class="mx-auto max-w-3xl rounded-xl border border-zinc-200 dark:border-zinc-700 p-8">
                    <h3 class="text-3xl font-semibold text-zinc-900 dark:text-white mb-6">{{ __('Leaderboard') }}</h3>
                    <ol class="space-y-4">
                        @foreach($scores as $entry)
                            <li class="flex justify-between text-2xl text-zinc-700 dark:text-zinc-300">
                                <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname'] ?? ''" />
                                <span class="font-bold">{{ $entry['score'] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        @else
            <div class="mx-auto grid max-w-4xl grid-cols-2 gap-4" data-test="match-pairs-columns">
                <ul class="space-y-4">
                    @foreach($leftItems as $item)
                        <li class="flex items-center justify-center rounded-xl border-2 border-zinc-200 bg-white p-6 text-center text-3xl font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            @if($item['kind'] === 'image')
                                <img src="{{ $item['value'] }}" class="h-24 w-24 rounded object-cover" alt="" />
                            @else
                                {{ $item['value'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                <ul class="space-y-4">
                    @foreach($rightItems as $item)
                        <li class="flex items-center justify-center rounded-xl border-2 border-zinc-200 bg-white p-6 text-center text-3xl font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            @if($item['kind'] === 'image')
                                <img src="{{ $item['value'] }}" class="h-24 w-24 rounded object-cover" alt="" />
                            @else
                                {{ $item['value'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="text-center text-2xl text-zinc-500 dark:text-zinc-400">
                {{ __('Players are matching pairs...') }}
            </p>

            <div class="mx-auto flex max-w-3xl items-center justify-between text-2xl text-zinc-500 dark:text-zinc-400">
                <span>{{ __(':answered / :total answered', ['answered' => $answeredCount ?? 0, 'total' => $totalPlayers ?? 0]) }}</span>
                <span>{{ $currentQuestion['time_limit_seconds'] ?? 30 }}s</span>
            </div>
        @endif
    </div>
@endif
```

### - [ ] Step 2: Wire it into the QUESTION PHASE switch

In `resources/views/livewire/spectator-screen.blade.php`, find this exact block (inside the `@elseif($phase === 'question')` section):

```blade
        @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
            @include('question-types.geo-guesser-spectator')
        @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'ordering')
            @include('question-types.ordering-spectator')
        @elseif($currentQuestion && ! empty($currentQuestion['options']) && in_array($themeKey, ['science', 'history', 'pop-culture', 'general-knowledge', 'geography', 'nature', 'sports', 'crime'], true))
            @include('themes.'.$themeKey.'.spectator-question')
        @elseif($currentQuestion)
```

Replace it with (adding the `match_pairs` branch right after `ordering`):

```blade
        @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
            @include('question-types.geo-guesser-spectator')
        @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'ordering')
            @include('question-types.ordering-spectator')
        @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'match_pairs')
            @include('question-types.match-pairs-spectator')
        @elseif($currentQuestion && ! empty($currentQuestion['options']) && in_array($themeKey, ['science', 'history', 'pop-culture', 'general-knowledge', 'geography', 'nature', 'sports', 'crime'], true))
            @include('themes.'.$themeKey.'.spectator-question')
        @elseif($currentQuestion)
```

### - [ ] Step 3: Wire it into the REVIEW PHASE switch

In the same file, find this exact block (inside the `@elseif($phase === 'review')` section):

```blade
        @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
            @include('question-types.geo-guesser-spectator')
        @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'ordering')
            @include('question-types.ordering-spectator')
        @elseif($currentQuestion && ! empty($currentQuestion['options']) && in_array($themeKey, ['science', 'history', 'pop-culture', 'general-knowledge', 'geography', 'nature', 'sports', 'crime'], true))
            @include('themes.'.$themeKey.'.spectator-review')
```

Replace it with:

```blade
        @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
            @include('question-types.geo-guesser-spectator')
        @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'ordering')
            @include('question-types.ordering-spectator')
        @elseif($currentQuestion && ($currentQuestion['type'] ?? null) === 'match_pairs')
            @include('question-types.match-pairs-spectator')
        @elseif($currentQuestion && ! empty($currentQuestion['options']) && in_array($themeKey, ['science', 'history', 'pop-culture', 'general-knowledge', 'geography', 'nature', 'sports', 'crime'], true))
            @include('themes.'.$themeKey.'.spectator-review')
```

(The rest of both blocks — including the `x-answer-distribution` component used only by the themed/generic branches — is unchanged; `match_pairs`, like `ordering` and `geo_guesser`, fully owns its own partial for both phases.)

### - [ ] Step 4: Write rendering smoke tests for both phases

`App\Livewire\SpectatorScreen` has no `pollState` fallback — it's driven entirely by `onQuestionStarted(array $payload)` / `onQuestionEnded(array $payload)` listener methods (see `app/Livewire/SpectatorScreen.php:124-153`), which is exactly how the existing `tests/Feature/CategoryThemeTest.php` exercises it (`->call('onQuestionStarted', $payload)`). Follow the same pattern here instead of polling.

Append to `tests/Feature/MatchPairsGameFlowTest.php` (add `use App\Livewire\SpectatorScreen;` to the `use` block at the top):

```php
test('spectator screen renders both columns during the question phase', function () {
    Livewire::test(SpectatorScreen::class, ['code' => $this->session->join_code])
        ->call('onQuestionStarted', [
            'question_id' => $this->question->id,
            'question_index' => 0,
            'body' => $this->question->body,
            'type' => 'match_pairs',
            'category_name' => null,
            'theme' => 'default',
            'time_limit_seconds' => $this->question->time_limit_seconds,
            'options' => $this->question->options,
        ])
        ->assertSet('phase', 'question')
        ->assertSeeHtml('data-test="match-pairs-columns"');
});

test('spectator screen renders the correct pairs during the review phase', function () {
    Livewire::test(SpectatorScreen::class, ['code' => $this->session->join_code])
        ->call('onQuestionStarted', [
            'question_id' => $this->question->id,
            'question_index' => 0,
            'body' => $this->question->body,
            'type' => 'match_pairs',
            'category_name' => null,
            'theme' => 'default',
            'time_limit_seconds' => $this->question->time_limit_seconds,
            'options' => $this->question->options,
        ])
        ->call('onQuestionEnded', [
            'question_id' => $this->question->id,
            'correct_answer' => $this->question->correct_answer,
            'scores' => [['nickname' => 'Matcher', 'score' => 100]],
            'guesses' => [],
            'distribution' => [],
        ])
        ->assertSet('phase', 'review')
        ->assertSeeHtml('data-test="match-pairs-correct-pairs"');
});
```

### - [ ] Step 5: Run them and watch them pass

Run: `vendor/bin/pest tests/Feature/MatchPairsGameFlowTest.php`
Expected: all 7 tests PASS.

### - [ ] Step 6: Re-run the full spectator-related feature tests to confirm no regression

Run: `vendor/bin/pest tests/Feature/GameFlowTest.php tests/Feature/OrderingGameFlowTest.php tests/Feature/CategoryThemeTest.php`
Expected: all PASS.

### - [ ] Step 7: Commit

```bash
git add resources/views/question-types/match-pairs-spectator.blade.php resources/views/livewire/spectator-screen.blade.php tests/Feature/MatchPairsGameFlowTest.php
git commit -m "feat: add spectator/host view for match_pairs questions"
```

---

## Task 10: German translations

**Files:**
- Modify: `lang/de.json`

None of the following keys currently exist in `lang/de.json` (verified by grep before writing this task), so there's no duplicate-key risk.

### - [ ] Step 1: Add the new translation keys

Open `lang/de.json` and add the following entries anywhere inside the top-level object (keep the file valid JSON — add a comma after the preceding entry):

```json
"Match Pairs": "Paare zuordnen",
"Pairs (left matches right)": "Paare (links passt zu rechts)",
"Left :n": "Links :n",
"Right :n": "Rechts :n",
"Text": "Text",
"Image": "Bild",
"Enter text or switch to an image.": "Text eingeben oder zu einem Bild wechseln.",
"Upload an image or switch to text.": "Bild hochladen oder zu Text wechseln.",
"Tap a card on the left, then its match on the right.": "Tippe eine Karte links an, dann ihre Übereinstimmung rechts.",
"Submit matches": "Zuordnung abschicken",
"Players are matching pairs...": "Spieler ordnen Paare zu...",
"Correct pairs:": "Richtige Paare:"
```

### - [ ] Step 2: Validate the JSON is still well-formed

Run: `php -r 'json_decode(file_get_contents("lang/de.json"), false, 512, JSON_THROW_ON_ERROR); echo "OK\n";'`
Expected: `OK`

### - [ ] Step 3: Re-run the full match_pairs test suite to confirm nothing regressed

Run: `vendor/bin/pest tests/Unit/QuestionTypes/MatchPairsTypeTest.php tests/Feature/MatchPairsGameFlowTest.php tests/Feature/QuizBuilderMatchPairsTest.php`
Expected: all PASS (translations aren't asserted in these tests, this just confirms nothing else broke).

### - [ ] Step 4: Commit

```bash
git add lang/de.json
git commit -m "feat: translate match_pairs UI strings to German"
```

---

## Task 11: Deploy script — storage:link

**Files:**
- Modify: `deploy.sh`

**Note:** This is the first feature in the app that writes to the `public` disk. Per the project's known Falkenstein production quirks ([[prod-host-falkenstein-quiz]] memory): nginx workers run as `www-data`, PHP-FPM runs as user `quiz`. If nginx serves `/storage/*` directly (typical for a symlinked Laravel `public` disk), files written by PHP-FPM (`quiz`) must be readable by `www-data`. This step only adds the symlink — verify file permissions manually on the first prod deploy after this ships; don't blind-`chmod` in this script without having seen the actual nginx config.

### - [ ] Step 1: Add the storage:link step

In `deploy.sh`, add a new step right after `composer install` and before `npm ci`:

```bash
echo "==> Entering maintenance mode"
php artisan down --retry=60

echo "==> Pulling latest code"
git pull origin "$BRANCH"

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Linking public storage"
php artisan storage:link

echo "==> Installing and building frontend assets"
npm ci
npm run build
```

(`php artisan storage:link` is idempotent — Laravel's command checks for an existing symlink and skips silently if it's already there, so this is safe to run on every deploy.)

### - [ ] Step 2: Verify the script is still valid bash

Run: `bash -n deploy.sh`
Expected: no output (exit code 0 means the syntax is valid).

### - [ ] Step 3: Commit

```bash
git add deploy.sh
git commit -m "feat: link public storage during deploy for uploaded question images"
```

---

## Task 12: Final verification

### - [ ] Step 1: Run the full suite via composer test

Run: `composer test`
Expected: Pint passes (no formatting diffs), all Pest tests PASS (existing suite + every test added in Tasks 1-10).

### - [ ] Step 2: If Pint fixes anything, commit the fix

Run: `git status`
If there are modifications from Pint:

```bash
git add -u
git commit -m "style: pint auto-fix"
```

### - [ ] Step 3: Build frontend assets to catch any JS syntax error

Run: `npm run build`
Expected: build succeeds with no errors (catches a typo in `match-pairs.js` or `app.js` that Pest can't see, since Pest never executes the bundled JS).

### - [ ] Step 4: Manual UI smoke test

Boot the app:

```bash
composer dev
```

As a logged-in user, visit a quiz's edit page (`/quizzes/{quiz}/edit`) and confirm:

- The question type dropdown offers "Match Pairs".
- Adding a `match_pairs` question shows 4 pair rows, each with a Text/Image toggle on both sides.
- Switching a side to "Image" shows a file input; uploading a small image and saving succeeds; re-opening the edit form shows the uploaded image's thumbnail without requiring re-upload.
- Saving with a pair slot left empty shows a validation error on that exact slot.

Then host a game including that question and join as a player in a second browser/incognito window:

- The player screen shows two columns (left fixed order, right shuffled) with text/image items rendered correctly.
- Tapping a left item then a right item locks the pair (visually distinguished); tapping either side of a locked pair unlocks it.
- "Submit matches" stays disabled until all 4 pairs are locked.
- The spectator/big-screen view shows both columns while the question is live, and the correct pairs (connected, with a checkmark) once the host ends the question.
- A player who lets the timer run out with some pairs locked does not get the round wasted (their partial pairing is auto-submitted per the existing `answer-provider` timeout contract) — confirm via the host's answered-count ticking up.

### - [ ] Step 5: Push and open the PR

```bash
git push -u origin feature/match-pairs
gh pr create --title "Add match_pairs question type (tap-to-pair, text/image, all-or-nothing)" --body "$(cat <<'EOF'
## Summary
- New question type `match_pairs`: exactly 4 pairs, each side independently text or an uploaded image, scored all-or-nothing.
- Tap-to-select pairing on the player screen (no drag-and-drop); explicit "Submit matches" button enabled once all 4 pairs are locked.
- First image-upload feature in the app — new `QuestionImageStorage` service wraps the `public` disk; uploaded files are cleaned up on question delete and on image replacement during edit.
- Only the right column is shuffled at save time so the broadcast `options` payload never reveals the correct pairing.
- `deploy.sh` now runs `storage:link`; first prod deploy after this ships needs a manual permissions check (`www-data` vs `quiz` user — see the Falkenstein quirks notes).

Spec: `docs/plans/2026-06-21-match-pairs-design.md`
Plan: `docs/plans/2026-06-21-match-pairs-implementation.md`

## Test plan
- [ ] `composer test` is green
- [ ] `npm run build` succeeds
- [ ] Author a `match_pairs` question with a mix of text and image pairs in the quiz builder; edit it and confirm the existing image isn't lost
- [ ] Host a game with that question; play it as a player in a second window — tap-to-pair, lock/unlock, submit-when-ready all work
- [ ] Spectator screen shows both columns live, then the correct pairs on review
- [ ] Letting the timer run out with a partial pairing still submits something (doesn't waste the round)
- [ ] After deploy, manually verify uploaded images are readable by nginx (`www-data`) on the Falkenstein host

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-review notes

- **Spec coverage** — every section of `docs/plans/2026-06-21-match-pairs-design.md` maps to a task: data model (Tasks 2-3), authoring incl. images (Tasks 6-7), player UI (Task 8), spectator UI (Task 9), deployment (Task 11), i18n (Task 10), testing strategy (a test file per task throughout).
- **Type/method consistency** — `correct_answer[leftIndex] = rightIndex` and the identical submitted-answer shape are used consistently from `MatchPairsType` (Task 2) through `QuizBuilder::buildQuestionPayload()` (Task 6) and the Alpine `pairs` array (Task 8). `resolvePairItem()`/`pairFormState()` are introduced text-only in Task 6 and extended (not renamed or restructured) in Task 7, so no later task references a stale signature.
- **Testing pattern correctness** — Task 9's spectator tests were corrected mid-plan after verifying `SpectatorScreen` has no `pollState` method and is driven purely by `onQuestionStarted`/`onQuestionEnded`; the corrected tests match the existing `CategoryThemeTest.php` pattern exactly, including `QuestionEnded::broadcastWith()`'s real key names (`scores`, `guesses`, `distribution` are required, not optional, in `onQuestionEnded`).
- **No image CSS additions** — both Blade partials reuse existing `qz-*` classes (themed) or plain Tailwind utilities (non-themed) for image sizing (`h-16 w-16 rounded object-cover`), so no `themes.css` edits were needed, keeping this plan's file list accurate.
- **Cascade/cleanup reliance** — Task 5's `Question::booted()` hook is type-gated (`if ($question->type !== 'match_pairs') return;`), so it's a guaranteed no-op for every other question type; Task 7's `cleanupReplacedImages()` only fires on the edit path for `match_pairs`, leaving every other type's `saveQuestion()` behavior untouched.
