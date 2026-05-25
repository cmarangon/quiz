# Quiz, Session & History Cleanup — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let dashboard users delete their own quizzes, manually end stale open hosted game sessions, and bulk-delete finished hosted games and player history entries — all guarded by Flux confirmation modals.

**Architecture:** Two existing Livewire components (`App\Livewire\Dashboard`, `App\Livewire\QuizIndex`) become stateful. Each gains `confirm*` methods that populate a `$pendingAction` / `$pendingId` pair driving a single shared Flux modal, plus action methods that do `findOrFail` + ownership check + `delete()` / `update()` / `whereIn(...)->delete()`. No new database columns: existing `cascadeOnDelete` foreign keys (quiz → categories → questions; game_session → players → player_answers; quiz → game_sessions) do the fanout.

**Tech Stack:** Laravel 12, Livewire 4, Flux UI, Pest 4, SQLite.

**Spec:** `docs/plans/2026-05-25-cleanup-actions-design.md`

---

## File Structure

**Modified:**
- `app/Livewire/Dashboard.php` — convert to stateful component with confirm/action methods and selection arrays
- `app/Livewire/QuizIndex.php` — add `confirmDeleteQuiz` / `deleteQuiz`
- `resources/views/livewire/dashboard.blade.php` — checkboxes, bulk-action bars, Delete/End buttons, shared modal
- `resources/views/livewire/quiz-index.blade.php` — Delete button + shared modal
- `lang/de.json` — add German translations

**Added:**
- `tests/Feature/DashboardCleanupTest.php` — Pest tests for delete/end/clear flows

---

## Task 1: Delete a quiz from the Dashboard

**Files:**
- Modify: `app/Livewire/Dashboard.php`
- Modify: `resources/views/livewire/dashboard.blade.php`
- Create: `tests/Feature/DashboardCleanupTest.php`

### - [ ] Step 1: Write the failing test for deleting one's own quiz

Create `tests/Feature/DashboardCleanupTest.php` with this content:

```php
<?php

use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('user can delete own quiz from dashboard', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create();
    $question = Question::factory()->for($category)->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmDeleteQuiz', $quiz->id)
        ->assertSet('pendingAction', 'delete-quiz')
        ->assertSet('pendingId', $quiz->id)
        ->call('deleteQuiz');

    expect(Quiz::find($quiz->id))->toBeNull();
    expect(Category::find($category->id))->toBeNull();
    expect(Question::find($question->id))->toBeNull();
});
```

### - [ ] Step 2: Run the test and watch it fail

Run: `vendor/bin/pest --filter='user can delete own quiz from dashboard'`
Expected: FAIL with `Method [confirmDeleteQuiz] does not exist on component`.

### - [ ] Step 3: Add the state and methods to Dashboard.php

Replace the entire contents of `app/Livewire/Dashboard.php` with:

```php
<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    /** @var array<int> */
    public array $selectedSessionIds = [];

    /** @var array<int> */
    public array $selectedPlayerIds = [];

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

    public function render()
    {
        $user = Auth::user();

        $quizzes = $user->quizzes()
            ->withCount('categories')
            ->latest()
            ->get();

        $hostedSessions = GameSession::where('host_user_id', $user->id)
            ->with('quiz')
            ->withCount('players')
            ->latest()
            ->limit(10)
            ->get();

        $playerEntries = Player::where('user_id', $user->id)
            ->with(['gameSession.quiz'])
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.dashboard', [
            'quizzes' => $quizzes,
            'hostedSessions' => $hostedSessions,
            'playerEntries' => $playerEntries,
        ])->title('Dashboard');
    }
}
```

### - [ ] Step 4: Run the test again and watch it pass

Run: `vendor/bin/pest --filter='user can delete own quiz from dashboard'`
Expected: PASS.

### - [ ] Step 5: Write the failing authorization test

Append to `tests/Feature/DashboardCleanupTest.php`:

```php
test('user cannot delete another users quiz', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherQuiz = Quiz::factory()->for($other)->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmDeleteQuiz', $otherQuiz->id)
        ->call('deleteQuiz')
        ->assertStatus(403);

    expect(Quiz::find($otherQuiz->id))->not->toBeNull();
});
```

### - [ ] Step 6: Run it to verify it already passes

Run: `vendor/bin/pest --filter='user cannot delete another users quiz'`
Expected: PASS (the `abort_unless($quiz->user_id === Auth::id(), 403)` already guards this).

### - [ ] Step 7: Wire the Delete button + shared modal into the dashboard view

Replace the entire contents of `resources/views/livewire/dashboard.blade.php` with:

```blade
<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    {{-- My Quizzes --}}
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('My Quizzes') }}</h2>
            <a href="{{ route('quizzes.create') }}" class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">+ {{ __('New Quiz') }}</a>
        </div>

        @if($quizzes->isEmpty())
            <div class="rounded-xl border border-neutral-200 p-6 text-center text-gray-500 dark:border-neutral-700 dark:text-gray-400">
                {{ __('No quizzes yet.') }} <a href="{{ route('quizzes.create') }}" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Create your first quiz') }}</a>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Title') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Categories') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($quizzes as $quiz)
                            <tr wire:key="quiz-{{ $quiz->id }}">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $quiz->title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $quiz->categories_count }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <a href="{{ route('quizzes.edit', $quiz) }}" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('Edit') }}</a>
                                    <form action="{{ route('game.create', $quiz) }}" method="POST" class="ml-3 inline">
                                        @csrf
                                        <button type="submit" class="rounded bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-500">{{ __('Play') }}</button>
                                    </form>
                                    <flux:modal.trigger name="confirm-action">
                                        <flux:button size="sm" variant="danger" class="ml-3"
                                                     wire:click="confirmDeleteQuiz({{ $quiz->id }})"
                                                     data-test="delete-quiz-{{ $quiz->id }}">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Hosted Games --}}
    <div>
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Hosted Games') }}</h2>

        @if($hostedSessions->isEmpty())
            <div class="rounded-xl border border-neutral-200 p-6 text-center text-gray-500 dark:border-neutral-700 dark:text-gray-400">
                {{ __('No hosted games yet.') }}
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Quiz') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Players') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($hostedSessions as $session)
                            <tr wire:key="session-{{ $session->id }}">
                                <td class="px-4 py-3">
                                    @if($session->status === 'finished')
                                        <flux:checkbox wire:model.live="selectedSessionIds" value="{{ $session->id }}"
                                                       data-test="select-session-{{ $session->id }}" />
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $session->quiz->title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $session->players_count }} {{ __('players') }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                        @if($session->status === 'finished') bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300
                                        @elseif($session->status === 'playing') bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300
                                        @else bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300
                                        @endif">
                                        {{ ucfirst($session->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $session->created_at->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    @if($session->status !== 'finished')
                                        <flux:modal.trigger name="confirm-action">
                                            <flux:button size="sm" variant="danger"
                                                         wire:click="confirmEndSession({{ $session->id }})"
                                                         data-test="end-session-{{ $session->id }}">
                                                {{ __('End') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(count($selectedSessionIds) > 0)
                <div class="mt-2 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-neutral-800">
                    <span class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __(':count selected', ['count' => count($selectedSessionIds)]) }}
                    </span>
                    <flux:modal.trigger name="confirm-action">
                        <flux:button size="sm" variant="danger"
                                     wire:click="confirmClearSessions"
                                     data-test="clear-sessions">
                            {{ __('Delete selected') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif
        @endif

        <x-action-message class="mt-2" on="game-ended">{{ __('Game ended.') }}</x-action-message>
        <x-action-message class="mt-2" on="history-cleared">{{ __('History cleared.') }}</x-action-message>
    </div>

    {{-- My Game History --}}
    <div>
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('My Game History') }}</h2>

        @if($playerEntries->isEmpty())
            <div class="rounded-xl border border-neutral-200 p-6 text-center text-gray-500 dark:border-neutral-700 dark:text-gray-400">
                {{ __("You haven't played any games yet.") }}
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Quiz') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Score') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($playerEntries as $entry)
                            <tr wire:key="player-{{ $entry->id }}">
                                <td class="px-4 py-3">
                                    <flux:checkbox wire:model.live="selectedPlayerIds" value="{{ $entry->id }}"
                                                   data-test="select-player-{{ $entry->id }}" />
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $entry->gameSession->quiz->title }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $entry->score }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $entry->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(count($selectedPlayerIds) > 0)
                <div class="mt-2 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-neutral-800">
                    <span class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __(':count selected', ['count' => count($selectedPlayerIds)]) }}
                    </span>
                    <flux:modal.trigger name="confirm-action">
                        <flux:button size="sm" variant="danger"
                                     wire:click="confirmClearPlayerEntries"
                                     data-test="clear-players">
                            {{ __('Delete selected') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif
        @endif
    </div>

    <x-action-message on="quiz-deleted">{{ __('Quiz deleted.') }}</x-action-message>

    {{-- Shared confirmation modal --}}
    <flux:modal name="confirm-action" focusable class="max-w-lg" data-test="confirm-action">
        <form wire:submit="runPendingAction" class="space-y-6">
            <div>
                @if($pendingAction === 'delete-quiz')
                    <flux:heading size="lg">{{ __('Delete this quiz?') }}</flux:heading>
                    <flux:subheading>{{ __('All categories, questions and past game sessions will be removed.') }}</flux:subheading>
                @elseif($pendingAction === 'end-session')
                    <flux:heading size="lg">{{ __('End this game?') }}</flux:heading>
                    <flux:subheading>{{ __('Its status will be set to finished.') }}</flux:subheading>
                @elseif($pendingAction === 'clear-sessions')
                    <flux:heading size="lg">{{ __('Delete :count hosted games?', ['count' => count($selectedSessionIds)]) }}</flux:heading>
                @elseif($pendingAction === 'clear-players')
                    <flux:heading size="lg">{{ __('Delete :count entries from your game history?', ['count' => count($selectedPlayerIds)]) }}</flux:heading>
                @endif
            </div>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" data-test="confirm-pending-action">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
```

Note: the modal's submit calls a single `runPendingAction()` dispatcher we add in Task 2's first step (and reuse for end/clear). For now the test calls `deleteQuiz()` directly so the unwired modal doesn't block.

### - [ ] Step 8: Re-run the two existing tests to confirm view changes didn't break them

Run: `vendor/bin/pest tests/Feature/DashboardCleanupTest.php tests/Feature/DashboardTest.php`
Expected: all PASS.

### - [ ] Step 9: Commit

```bash
git add app/Livewire/Dashboard.php resources/views/livewire/dashboard.blade.php tests/Feature/DashboardCleanupTest.php
git commit -m "feat: delete quiz from dashboard with confirm modal"
```

---

## Task 2: End a stale open hosted game

**Files:**
- Modify: `app/Livewire/Dashboard.php`
- Modify: `tests/Feature/DashboardCleanupTest.php`

### - [ ] Step 1: Write failing tests for end-session

Append to `tests/Feature/DashboardCleanupTest.php`:

```php
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;

test('host can end a waiting session', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'waiting']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->assertSet('pendingAction', 'end-session')
        ->assertSet('pendingId', $session->id)
        ->call('endSession');

    expect($session->fresh()->status)->toBe('finished');
});

test('host can end a playing session', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'playing']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->call('endSession');

    expect($session->fresh()->status)->toBe('finished');
});

test('ending a session preserves players and answers', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'playing']);
    $player = Player::factory()->for($session, 'gameSession')->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->call('endSession');

    expect(Player::find($player->id))->not->toBeNull();
});

test('non-host cannot end a session', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();
    $session = GameSession::factory()->for($quiz)->for($owner, 'host')->create(['status' => 'waiting']);

    Livewire::actingAs($other)
        ->test(Dashboard::class)
        ->call('confirmEndSession', $session->id)
        ->call('endSession')
        ->assertStatus(403);

    expect($session->fresh()->status)->toBe('waiting');
});
```

### - [ ] Step 2: Run them and watch them fail

Run: `vendor/bin/pest --filter='end'`
Expected: FAIL with `Method [confirmEndSession] does not exist`.

### - [ ] Step 3: Add the end-session methods and the `runPendingAction` dispatcher

In `app/Livewire/Dashboard.php`, add a `use App\Models\GameSession;` import (after the `use App\Models\Player;` line), then add these methods to the class (place them after `deleteQuiz()` and before `render()`):

```php
public function confirmEndSession(int $id): void
{
    $this->pendingAction = 'end-session';
    $this->pendingId = $id;
}

public function endSession(): void
{
    abort_unless($this->pendingAction === 'end-session' && $this->pendingId !== null, 400);

    $session = GameSession::findOrFail($this->pendingId);
    abort_unless($session->host_user_id === Auth::id(), 403);

    $session->update(['status' => 'finished']);

    $this->pendingAction = null;
    $this->pendingId = null;
    $this->dispatch('game-ended');
}

public function runPendingAction(): void
{
    match ($this->pendingAction) {
        'delete-quiz' => $this->deleteQuiz(),
        'end-session' => $this->endSession(),
        'clear-sessions' => $this->clearSessions(),
        'clear-players' => $this->clearPlayerEntries(),
        default => null,
    };
}
```

Note: `clearSessions` and `clearPlayerEntries` are added in Tasks 3 and 4 respectively. PHP won't error here because `match` only evaluates the matched arm; until those methods exist, the `clear-*` arms are unreachable in tests. We add them now to avoid re-editing the dispatcher later.

### - [ ] Step 4: Run the end-session tests and watch them pass

Run: `vendor/bin/pest --filter='end'`
Expected: all four PASS.

### - [ ] Step 5: Commit

```bash
git add app/Livewire/Dashboard.php tests/Feature/DashboardCleanupTest.php
git commit -m "feat: end stale open hosted sessions from dashboard"
```

---

## Task 3: Bulk clear finished hosted sessions

**Files:**
- Modify: `app/Livewire/Dashboard.php`
- Modify: `tests/Feature/DashboardCleanupTest.php`

### - [ ] Step 1: Write failing test for bulk-clear

Append to `tests/Feature/DashboardCleanupTest.php`:

```php
test('user can bulk-clear selected finished sessions', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $s1 = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'finished']);
    $s2 = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'finished']);
    $kept = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'finished']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('selectedSessionIds', [$s1->id, $s2->id])
        ->call('confirmClearSessions')
        ->assertSet('pendingAction', 'clear-sessions')
        ->call('clearSessions');

    expect(GameSession::find($s1->id))->toBeNull();
    expect(GameSession::find($s2->id))->toBeNull();
    expect(GameSession::find($kept->id))->not->toBeNull();
});

test('bulk-clear silently skips open sessions if their ids leak in', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $openSession = GameSession::factory()->for($quiz)->for($user, 'host')->create(['status' => 'playing']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('selectedSessionIds', [$openSession->id])
        ->call('confirmClearSessions')
        ->call('clearSessions');

    expect(GameSession::find($openSession->id))->not->toBeNull();
});

test('bulk-clear silently skips sessions hosted by other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherQuiz = Quiz::factory()->for($other)->create();
    $otherSession = GameSession::factory()->for($otherQuiz)->for($other, 'host')->create(['status' => 'finished']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('selectedSessionIds', [$otherSession->id])
        ->call('confirmClearSessions')
        ->call('clearSessions');

    expect(GameSession::find($otherSession->id))->not->toBeNull();
});
```

### - [ ] Step 2: Run them and watch them fail

Run: `vendor/bin/pest --filter='bulk-clear|clear-sessions'`
Expected: FAIL with `Method [confirmClearSessions] does not exist`.

### - [ ] Step 3: Add bulk-clear methods to Dashboard.php

In `app/Livewire/Dashboard.php`, add these two methods after `endSession()`:

```php
public function confirmClearSessions(): void
{
    $this->pendingAction = 'clear-sessions';
}

public function clearSessions(): void
{
    abort_unless($this->pendingAction === 'clear-sessions', 400);

    GameSession::whereIn('id', $this->selectedSessionIds)
        ->where('host_user_id', Auth::id())
        ->where('status', 'finished')
        ->delete();

    $this->selectedSessionIds = [];
    $this->pendingAction = null;
    $this->dispatch('history-cleared');
}
```

### - [ ] Step 4: Run the bulk-clear tests and watch them pass

Run: `vendor/bin/pest --filter='bulk-clear|clear-sessions'`
Expected: all three PASS.

### - [ ] Step 5: Commit

```bash
git add app/Livewire/Dashboard.php tests/Feature/DashboardCleanupTest.php
git commit -m "feat: bulk-clear finished hosted sessions from dashboard"
```

---

## Task 4: Bulk clear player history entries

**Files:**
- Modify: `app/Livewire/Dashboard.php`
- Modify: `tests/Feature/DashboardCleanupTest.php`

### - [ ] Step 1: Write failing test for player-entry clear

Append to `tests/Feature/DashboardCleanupTest.php`:

```php
test('user can bulk-clear selected player history entries', function () {
    $user = User::factory()->create();
    $session = GameSession::factory()->create(['status' => 'finished']);
    $p1 = Player::factory()->for($session, 'gameSession')->create(['user_id' => $user->id]);
    $p2 = Player::factory()->for($session, 'gameSession')->create(['user_id' => $user->id]);
    $kept = Player::factory()->for($session, 'gameSession')->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('selectedPlayerIds', [$p1->id, $p2->id])
        ->call('confirmClearPlayerEntries')
        ->assertSet('pendingAction', 'clear-players')
        ->call('clearPlayerEntries');

    expect(Player::find($p1->id))->toBeNull();
    expect(Player::find($p2->id))->toBeNull();
    expect(Player::find($kept->id))->not->toBeNull();
});

test('bulk-clear player entries silently skips entries belonging to other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = GameSession::factory()->create(['status' => 'finished']);
    $otherEntry = Player::factory()->for($session, 'gameSession')->create(['user_id' => $other->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('selectedPlayerIds', [$otherEntry->id])
        ->call('confirmClearPlayerEntries')
        ->call('clearPlayerEntries');

    expect(Player::find($otherEntry->id))->not->toBeNull();
});
```

### - [ ] Step 2: Run them and watch them fail

Run: `vendor/bin/pest --filter='player history|clear player'`
Expected: FAIL with `Method [confirmClearPlayerEntries] does not exist`.

### - [ ] Step 3: Add player-clear methods to Dashboard.php

In `app/Livewire/Dashboard.php`, add these methods after `clearSessions()`:

```php
public function confirmClearPlayerEntries(): void
{
    $this->pendingAction = 'clear-players';
}

public function clearPlayerEntries(): void
{
    abort_unless($this->pendingAction === 'clear-players', 400);

    Player::whereIn('id', $this->selectedPlayerIds)
        ->where('user_id', Auth::id())
        ->delete();

    $this->selectedPlayerIds = [];
    $this->pendingAction = null;
    $this->dispatch('history-cleared');
}
```

### - [ ] Step 4: Run the tests and watch them pass

Run: `vendor/bin/pest --filter='player history|clear player'`
Expected: both PASS.

### - [ ] Step 5: Commit

```bash
git add app/Livewire/Dashboard.php tests/Feature/DashboardCleanupTest.php
git commit -m "feat: bulk-clear player history entries from dashboard"
```

---

## Task 5: Defense-in-depth on the dispatcher

**Files:**
- Modify: `tests/Feature/DashboardCleanupTest.php`

### - [ ] Step 1: Write the failing test for dispatcher safety

Append to `tests/Feature/DashboardCleanupTest.php`:

```php
test('runPendingAction is a no-op when pendingAction is null', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('runPendingAction');

    expect(Quiz::find($quiz->id))->not->toBeNull();
});

test('calling deleteQuiz with mismatched pendingAction aborts 400', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('confirmEndSession', 9999)
        ->call('deleteQuiz')
        ->assertStatus(400);

    expect(Quiz::find($quiz->id))->not->toBeNull();
});
```

### - [ ] Step 2: Run them — they should already pass

Run: `vendor/bin/pest --filter='runPendingAction|mismatched'`
Expected: both PASS (the dispatcher's `default => null` arm and the `abort_unless($this->pendingAction === ...)` guards already cover this).

### - [ ] Step 3: Commit

```bash
git add tests/Feature/DashboardCleanupTest.php
git commit -m "test: cover dispatcher no-op and mismatched pendingAction"
```

---

## Task 6: Delete quiz from /quizzes page

**Files:**
- Modify: `app/Livewire/QuizIndex.php`
- Modify: `resources/views/livewire/quiz-index.blade.php`
- Modify: `tests/Feature/DashboardCleanupTest.php`

### - [ ] Step 1: Write the failing test

Append to `tests/Feature/DashboardCleanupTest.php`:

```php
use App\Livewire\QuizIndex;

test('user can delete own quiz from quiz index', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(QuizIndex::class)
        ->call('confirmDeleteQuiz', $quiz->id)
        ->assertSet('pendingAction', 'delete-quiz')
        ->assertSet('pendingId', $quiz->id)
        ->call('deleteQuiz');

    expect(Quiz::find($quiz->id))->toBeNull();
});

test('user cannot delete another users quiz from quiz index', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherQuiz = Quiz::factory()->for($other)->create();

    Livewire::actingAs($user)
        ->test(QuizIndex::class)
        ->call('confirmDeleteQuiz', $otherQuiz->id)
        ->call('deleteQuiz')
        ->assertStatus(403);

    expect(Quiz::find($otherQuiz->id))->not->toBeNull();
});
```

### - [ ] Step 2: Run them and watch them fail

Run: `vendor/bin/pest --filter='quiz index'`
Expected: FAIL with `Method [confirmDeleteQuiz] does not exist`.

### - [ ] Step 3: Make QuizIndex.php stateful

Replace the entire contents of `app/Livewire/QuizIndex.php` with:

```php
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
```

### - [ ] Step 4: Run the new tests and watch them pass

Run: `vendor/bin/pest --filter='quiz index'`
Expected: both PASS.

### - [ ] Step 5: Add the Delete button + modal to the view

Replace the entire contents of `resources/views/livewire/quiz-index.blade.php` with:

```blade
<div class="mx-auto max-w-4xl p-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold dark:text-white">{{ __('My Quizzes') }}</h1>
        <a href="{{ route('quizzes.create') }}" wire:navigate>
            <flux:button variant="primary" icon="plus">
                {{ __('Create Quiz') }}
            </flux:button>
        </a>
    </div>

    @if($quizzes->isEmpty())
        <div class="rounded-lg border border-neutral-200 p-8 text-center dark:border-neutral-700">
            <p class="text-neutral-500 dark:text-neutral-400">{{ __('You have no quizzes yet. Create your first one!') }}</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($quizzes as $quiz)
                <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
                     wire:key="quiz-{{ $quiz->id }}">
                    <div>
                        <h2 class="text-lg font-semibold dark:text-white">{{ $quiz->title }}</h2>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $quiz->categories_count }} {{ trans_choice('{1} category|[2,*] categories', $quiz->categories_count) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('quizzes.edit', $quiz) }}" wire:navigate>
                            <flux:button variant="ghost" icon="pencil" size="sm">
                                {{ __('Edit') }}
                            </flux:button>
                        </a>
                        <flux:modal.trigger name="confirm-action">
                            <flux:button variant="danger" size="sm"
                                         wire:click="confirmDeleteQuiz({{ $quiz->id }})"
                                         data-test="delete-quiz-{{ $quiz->id }}">
                                {{ __('Delete') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-action-message class="mt-4" on="quiz-deleted">{{ __('Quiz deleted.') }}</x-action-message>

    <flux:modal name="confirm-action" focusable class="max-w-lg" data-test="confirm-action">
        <form wire:submit="runPendingAction" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this quiz?') }}</flux:heading>
                <flux:subheading>{{ __('All categories, questions and past game sessions will be removed.') }}</flux:subheading>
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" data-test="confirm-pending-action">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
```

### - [ ] Step 6: Re-run the existing QuizCrudTest to make sure the view changes didn't break it

Run: `vendor/bin/pest tests/Feature/QuizCrudTest.php`
Expected: all PASS.

### - [ ] Step 7: Commit

```bash
git add app/Livewire/QuizIndex.php resources/views/livewire/quiz-index.blade.php tests/Feature/DashboardCleanupTest.php
git commit -m "feat: delete quiz from /quizzes index page"
```

---

## Task 7: German translations

**Files:**
- Modify: `lang/de.json`

### - [ ] Step 1: Add the new translation keys

Open `lang/de.json` and add the following entries (preserve the existing JSON structure — these go anywhere inside the top-level object; keep valid JSON):

```json
"Delete": "Löschen",
"End": "Beenden",
"Delete this quiz?": "Dieses Quiz löschen?",
"All categories, questions and past game sessions will be removed.": "Alle Kategorien, Fragen und vergangenen Spielsitzungen werden entfernt.",
"End this game?": "Dieses Spiel beenden?",
"Its status will be set to finished.": "Der Status wird auf beendet gesetzt.",
"Delete :count hosted games?": "':count' gehostete Spiele löschen?",
"Delete :count entries from your game history?": "':count' Einträge aus deinem Spielverlauf löschen?",
"Delete selected": "Auswahl löschen",
":count selected": "':count' ausgewählt",
"Cancel": "Abbrechen",
"Quiz deleted.": "Quiz gelöscht.",
"Game ended.": "Spiel beendet.",
"History cleared.": "Verlauf gelöscht."
```

If any of these keys already exist in `lang/de.json` (e.g. "Cancel"), skip the duplicates — JSON cannot have duplicate keys.

### - [ ] Step 2: Validate the JSON is still well-formed

Run: `php -r 'json_decode(file_get_contents("lang/de.json"), false, 512, JSON_THROW_ON_ERROR); echo "OK\n";'`
Expected: `OK`

### - [ ] Step 3: Smoke-check that German rendering picks up "Löschen"

Run: `vendor/bin/pest tests/Feature/DashboardCleanupTest.php tests/Feature/DashboardTest.php tests/Feature/QuizCrudTest.php`
Expected: all PASS (translations aren't asserted in tests, this just confirms nothing regressed).

### - [ ] Step 4: Commit

```bash
git add lang/de.json
git commit -m "feat: translate cleanup actions to German"
```

---

## Task 8: Final verification

### - [ ] Step 1: Run the full suite via composer test (lints + pest)

Run: `composer test`
Expected: Pint passes (no formatting diffs), all Pest tests PASS.

### - [ ] Step 2: If Pint fixes anything, commit the fix

Run: `git status`
If there are modifications from Pint:

```bash
git add -u
git commit -m "style: pint auto-fix"
```

### - [ ] Step 3: Manual UI smoke (optional but recommended)

Boot the app:

```bash
composer dev
```

Visit `http://localhost:8000/dashboard` while logged in. Confirm:

- "Delete" button on each quiz row opens a confirm modal; confirming removes the row.
- A `waiting`-status hosted game shows an "End" button (not a checkbox); clicking opens a modal; confirming flips the status to "Finished".
- A `finished`-status hosted game shows a checkbox; ticking it surfaces the "X selected / Delete selected" bar above the table; confirming deletes only the ticked rows.
- The same checkbox+bar pattern works on "My Game History".
- Switch to German via `/locale/de` and confirm the new strings render in German.

### - [ ] Step 4: Push and open the PR

```bash
git push -u origin feature/cleanup-actions
gh pr create --title "Cleanup actions: delete quiz, end stale session, clear history" --body "$(cat <<'EOF'
## Summary
- Owners can delete their own quizzes from both the Dashboard and `/quizzes` (cascades to categories, questions and past game sessions).
- Hosts can manually end a stale `waiting` / `playing` / `reviewing` hosted session — status flips to `finished`, players and answers are preserved.
- Per-section bulk-clear with row checkboxes on `Hosted Games` (finished only) and `My Game History`.
- All destructive actions go through a shared Flux confirmation modal; new German strings added.

Spec: `docs/plans/2026-05-25-cleanup-actions-design.md`
Plan: `docs/plans/2026-05-25-cleanup-actions-implementation.md`

## Test plan
- [ ] `composer test` is green
- [ ] `/dashboard` delete-quiz removes the row + cascades
- [ ] `/dashboard` end-session flips a waiting/playing session to finished, players survive
- [ ] `/dashboard` bulk-clear deletes only ticked finished hosted sessions; open sessions unaffected even if their ids leak in
- [ ] `/dashboard` bulk-clear deletes only the user's own player history rows
- [ ] `/quizzes` delete-quiz works the same as Dashboard
- [ ] German locale renders the new strings (`/locale/de`)

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-review notes

- **Spec coverage** — all four buckets (delete-quiz on Dashboard + /quizzes, end-session, clear-sessions, clear-players) get a task; modal, authorization, i18n, tests all covered.
- **Type/method consistency** — `pendingAction` values (`delete-quiz`, `end-session`, `clear-sessions`, `clear-players`), method names (`confirmDeleteQuiz`/`deleteQuiz`, `confirmEndSession`/`endSession`, `confirmClearSessions`/`clearSessions`, `confirmClearPlayerEntries`/`clearPlayerEntries`), and selection array names (`selectedSessionIds`, `selectedPlayerIds`) are identical across spec and plan.
- **Cascade reliance** — Task 1 asserts cascade via `expect(Category::find($category->id))->toBeNull()` and `expect(Question::find($question->id))->toBeNull()`, confirming the no-migration decision works as intended.
- **Dispatcher ordering** — `runPendingAction` is added in Task 2 referencing `clearSessions` / `clearPlayerEntries` before they exist; Task 2 Step 3 calls this out explicitly and tests in Task 2 don't exercise those arms, so PHP is happy until Tasks 3 and 4 fill them in.
