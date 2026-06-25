# Admin Question Comment Field — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional free-text `comment` column to `questions`, editable in the Quiz Builder form and displayed (read-only) on the Host Dashboard during play.

**Architecture:** New nullable DB column → fillable model property → QuizBuilder Livewire component stores/loads it → quiz-builder view shows textarea in form and inline note in question list → HostDashboard passes current question to its view → host-dashboard view renders a note panel in playing/reviewing phases.

**Tech Stack:** Laravel 12, Livewire, Pest, Flux UI components.

## Global Constraints

- PHP ≥ 8.2
- Comment field is nullable, no length limit, no validation rule.
- Never broadcast or render comment on player or spectator screens.
- Follow existing migration naming: `YYYY_MM_DD_HHMMSS_<description>.php`.
- Tests use Pest syntax (`test('…', function () { … })`).
- All Livewire tests use `Livewire::actingAs($user)->test(…)`.

---

### Task 1: Migration and Model

**Files:**
- Create: `database/migrations/2026_06_25_000000_add_comment_to_questions_table.php`
- Modify: `app/Models/Question.php`
- Test: `tests/Feature/QuestionCommentMigrationTest.php`

**Interfaces:**
- Produces: `Question::$fillable` includes `'comment'`; `Question->comment` is `string|null`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/QuestionCommentMigrationTest.php`:

```php
<?php

use App\Models\Category;
use App\Models\Question;

test('comment column accepts null', function () {
    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->create(['comment' => null]);

    expect($question->fresh()->comment)->toBeNull();
});

test('comment column persists a string value', function () {
    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->create(['comment' => 'Check source: Wikipedia']);

    expect($question->fresh()->comment)->toBe('Check source: Wikipedia');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/QuestionCommentMigrationTest.php
```

Expected: FAIL — "Unknown column 'comment'" or mass-assignment error.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_25_000000_add_comment_to_questions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
```

- [ ] **Step 4: Add `comment` to `Question::$fillable`**

In `app/Models/Question.php`, update `$fillable`:

```php
protected $fillable = [
    'category_id',
    'type',
    'body',
    'options',
    'correct_answer',
    'points',
    'time_limit_seconds',
    'order',
    'comment',
];
```

- [ ] **Step 5: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Feature/QuestionCommentMigrationTest.php
```

Expected: PASS (both tests green).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_25_000000_add_comment_to_questions_table.php \
        app/Models/Question.php \
        tests/Feature/QuestionCommentMigrationTest.php
git commit -m "feat: add comment column to questions table"
```

---

### Task 2: Quiz Builder — Save and Load Comment

**Files:**
- Modify: `app/Livewire/QuizBuilder.php`
- Test: `tests/Feature/QuizBuilderTest.php` (append tests)

**Interfaces:**
- Consumes: `Question->comment` (nullable string) from Task 1.
- Produces: `QuizBuilder::$questionComment` (public string property); `saveQuestion()` writes comment; `editQuestion()` loads comment; `resetQuestionForm()` clears comment.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/QuizBuilderTest.php`:

```php
test('saving a new question persists its comment', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();

    Livewire::actingAs($user)
        ->test(\App\Livewire\QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is 2+2?')
        ->set('questionType', 'true_false')
        ->set('questionCorrectAnswer', 'True')
        ->set('questionComment', 'Source: basic arithmetic')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->firstOrFail();
    expect($question->comment)->toBe('Source: basic arithmetic');
});

test('saving a question with blank comment stores null', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();

    Livewire::actingAs($user)
        ->test(\App\Livewire\QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is 2+2?')
        ->set('questionType', 'true_false')
        ->set('questionCorrectAnswer', 'True')
        ->set('questionComment', '')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->firstOrFail();
    expect($question->comment)->toBeNull();
});

test('editing a question loads its existing comment', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();
    $question = \App\Models\Question::factory()->for($category)->create([
        'type' => 'true_false',
        'body' => 'Is the sky blue?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Remember to mention clouds',
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionComment', 'Remember to mention clouds');
});

test('updating a question overwrites its comment', function () {
    $user = User::factory()->create();
    $quiz = \App\Models\Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();
    $question = \App\Models\Question::factory()->for($category)->create([
        'type' => 'true_false',
        'body' => 'Is the sky blue?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Old note',
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('questionComment', 'New note')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    expect($question->fresh()->comment)->toBe('New note');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/QuizBuilderTest.php --filter="comment"
```

Expected: FAIL — "Property [$questionComment] not found on component".

- [ ] **Step 3: Add `$questionComment` property to `QuizBuilder`**

In `app/Livewire/QuizBuilder.php`, add the property after `public string $questionTimeLimit = '';`:

```php
public string $questionComment = '';
```

- [ ] **Step 4: Load comment in `editQuestion()`**

In `QuizBuilder::editQuestion()`, after the `$this->questionTimeLimit = …` line, add:

```php
$this->questionComment = $question->comment ?? '';
```

- [ ] **Step 5: Save comment in `saveQuestion()`**

In `QuizBuilder::saveQuestion()`, in the `$question->update([…])` call (editing path), add:

```php
'comment' => $this->questionComment ?: null,
```

In the `$category->questions()->create([…])` call (create path), add:

```php
'comment' => $this->questionComment ?: null,
```

- [ ] **Step 6: Reset comment in `resetQuestionForm()`**

In `QuizBuilder::resetQuestionForm()`, add:

```php
$this->questionComment = '';
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
php artisan test tests/Feature/QuizBuilderTest.php --filter="comment"
```

Expected: PASS (all four new tests green).

- [ ] **Step 8: Run the full test suite to check for regressions**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/QuizBuilder.php tests/Feature/QuizBuilderTest.php
git commit -m "feat: save and load admin comment in QuizBuilder"
```

---

### Task 3: Quiz Builder — View

**Files:**
- Modify: `resources/views/livewire/quiz-builder.blade.php`

**Interfaces:**
- Consumes: `$questionComment` (Livewire public property from Task 2); `$question->comment` (nullable string from Task 1).

This task is UI-only; no new tests are needed (behaviour is covered by Task 2's Livewire assertions). Manual verification steps are listed instead.

- [ ] **Step 1: Add textarea to the question form**

In `resources/views/livewire/quiz-builder.blade.php`, inside the question form panel (the dashed-border `<div>` that contains `wire:model="questionBody"`), insert the comment textarea immediately after the `questionBody` block (the `<div>` that ends with `@error('questionBody') … @enderror`):

```blade
<div>
    <flux:textarea wire:model="questionComment" :label="__('Admin note (private)')" :placeholder="__('Optional note visible only to you')" rows="2" />
</div>
```

- [ ] **Step 2: Show comment in the question list**

In `quiz-builder.blade.php`, inside the `@foreach($category->questions as $question)` loop, the question body is currently displayed as:

```blade
<span>{{ $question->body }} ({{ $question->points }} {{ __('pts') }}, {{ $question->effectiveTimeLimitSeconds() }}s{{ $question->time_limit_seconds === null ? ', '.__('Default') : '' }})</span>
```

Replace that `<span>` with:

```blade
<span class="flex flex-col">
    <span>{{ $question->body }} ({{ $question->points }} {{ __('pts') }}, {{ $question->effectiveTimeLimitSeconds() }}s{{ $question->time_limit_seconds === null ? ', '.__('Default') : '' }})</span>
    @if($question->comment)
        <span class="text-xs italic text-neutral-400 dark:text-neutral-500">{{ $question->comment }}</span>
    @endif
</span>
```

- [ ] **Step 3: Manually verify in the browser**

Start the dev server (`php artisan serve`) and open the Quiz Builder for an existing quiz.

Check:
1. The question form shows an "Admin note (private)" textarea.
2. Saving a question with a note shows the note in italics under the question body.
3. Saving a question with a blank note shows nothing under the question body.
4. Editing a question pre-fills the textarea with the saved note.
5. Cancelling and re-opening the form clears the textarea.

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/quiz-builder.blade.php
git commit -m "feat: show admin comment textarea and note in quiz builder view"
```

---

### Task 4: Host Dashboard — Show Comment During Play

**Files:**
- Modify: `app/Livewire/HostDashboard.php`
- Modify: `resources/views/livewire/host-dashboard.blade.php`
- Test: `tests/Feature/HostDashboardCommentTest.php`

**Interfaces:**
- Consumes: `Question->comment` (nullable string from Task 1); `$this->currentQuestionId` and `$this->phase` (existing HostDashboard properties).
- Produces: `$currentQuestion` variable passed to the view (nullable `Question`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/HostDashboardCommentTest.php`:

```php
<?php

use App\Livewire\HostDashboard;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('host dashboard shows question comment during playing phase', function () {
    Event::fake();

    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $category = Category::factory()->for($quiz)->create(['order' => 0]);
    Question::factory()->for($category)->create([
        'order' => 0,
        'type' => 'true_false',
        'body' => 'Is water wet?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Fun fact: debated by scientists',
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    app(GameService::class)->start($session);
    $session->refresh();

    Livewire::actingAs($user)
        ->test(HostDashboard::class, ['code' => $session->join_code])
        ->assertSee('Fun fact: debated by scientists');
});

test('host dashboard hides comment when question has none', function () {
    Event::fake();

    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $category = Category::factory()->for($quiz)->create(['order' => 0]);
    Question::factory()->for($category)->create([
        'order' => 0,
        'type' => 'true_false',
        'body' => 'Is water wet?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => null,
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    app(GameService::class)->start($session);
    $session->refresh();

    Livewire::actingAs($user)
        ->test(HostDashboard::class, ['code' => $session->join_code])
        ->assertDontSee('Note:');
});

test('host dashboard does not show comment in lobby phase', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create(['order' => 0]);
    Question::factory()->for($category)->create([
        'order' => 0,
        'type' => 'true_false',
        'body' => 'Is water wet?',
        'options' => ['True', 'False'],
        'correct_answer' => 'True',
        'comment' => 'Should not appear in lobby',
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    Livewire::actingAs($user)
        ->test(HostDashboard::class, ['code' => $session->join_code])
        ->assertDontSee('Should not appear in lobby');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/HostDashboardCommentTest.php
```

Expected: FAIL — comment text not found in rendered HTML.

- [ ] **Step 3: Pass `$currentQuestion` from `HostDashboard::render()`**

In `app/Livewire/HostDashboard.php`, update the `render()` method:

```php
public function render()
{
    $currentQuestion = ($this->currentQuestionId && in_array($this->phase, ['playing', 'reviewing'], true))
        ? \App\Models\Question::find($this->currentQuestionId)
        : null;

    return view('livewire.host-dashboard', [
        'players' => $this->session->players,
        'currentQuestion' => $currentQuestion,
    ])->title('Host Dashboard');
}
```

- [ ] **Step 4: Add the note panel to the host dashboard view**

In `resources/views/livewire/host-dashboard.blade.php`, insert this block directly before the `{{-- Answer Progress --}}` section (the `@if($phase === 'playing')` block at line 67):

```blade
{{-- Admin note for host only --}}
@if(isset($currentQuestion) && $currentQuestion?->comment)
    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ __('Note:') }}</p>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $currentQuestion->comment }}</p>
    </div>
@endif
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/HostDashboardCommentTest.php
```

Expected: PASS (all three tests green).

- [ ] **Step 6: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/HostDashboard.php \
        resources/views/livewire/host-dashboard.blade.php \
        tests/Feature/HostDashboardCommentTest.php
git commit -m "feat: show admin question comment on host dashboard during play"
```
