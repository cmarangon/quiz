# Quiz, Session & History Cleanup — Design

**Date:** 2026-05-25
**Branch:** `feature/cleanup-actions`

## Problem

The dashboard surfaces quizzes, hosted games, and game history but offers no way to remove anything. Three gaps:

1. A user who created a quiz cannot delete it — even quizzes that were never played and are clearly throwaway drafts pile up in *My Quizzes*.
2. Hosted games that get abandoned mid-play (host closed the tab, network dropped, players left) stay forever in `waiting` / `playing` / `reviewing` status, cluttering the *Hosted Games* section and making it look like there are live games when there aren't.
3. Old finished hosted games and player history entries accumulate with no way to trim them.

## Decisions

- **Delete any quiz.** No new "draft" status. Owners can delete any of their quizzes; the cascade already handles categories, questions, sessions, players, and player answers.
- **Manually close (not delete) stale open sessions.** The host gets an `End` action per row for any hosted session still in `waiting` / `playing` / `reviewing`. Action sets `status = 'finished'`. Players and answers are preserved so history stays meaningful.
- **Per-section bulk clear with row selection.** Each of *Hosted Games* and *My Game History* gets per-row checkboxes plus a `Delete selected` button above the table. Checkboxes only render on finished sessions (open sessions need to be ended first).
- **Confirmation modals for everything destructive** using `flux:modal`, matching `pages/settings/⚡users.blade.php`. A single shared modal per Livewire component, driven by a `$pendingAction` discriminator.
- **No new database columns.** Existing `cascadeOnDelete` chains do the fanout work.
- **No scheduled jobs.** Cleanup is user-driven only. (An auto-prune scheduler is an obvious follow-up but out of scope here.)
- **No broadcast on manual end.** Closing a stale session is host-side bookkeeping; clients on a truly stale session are already gone. If we ever see a real "spectator still watching a dead game" problem we'll add a `GameFinished` broadcast in a follow-up.

## Architecture

### Livewire components

**`App\Livewire\Dashboard`** — becomes stateful. Public state:

```php
public array $selectedSessionIds = [];
public array $selectedPlayerIds = [];
public ?string $pendingAction = null; // 'delete-quiz' | 'end-session' | 'clear-sessions' | 'clear-players'
public ?int $pendingId = null;
```

Methods (all guard authorization via `findOrFail` + `abort_unless(... === Auth::id(), 403)`):

| Method                       | Effect                                                                                                       |
|------------------------------|--------------------------------------------------------------------------------------------------------------|
| `confirmDeleteQuiz(int $id)` | sets `$pendingAction = 'delete-quiz'`, `$pendingId = $id`                                                    |
| `confirmEndSession(int $id)` | sets `$pendingAction = 'end-session'`, `$pendingId = $id`                                                    |
| `confirmClearSessions()`     | sets `$pendingAction = 'clear-sessions'`                                                                     |
| `confirmClearPlayerEntries()`| sets `$pendingAction = 'clear-players'`                                                                      |
| `deleteQuiz()`               | `Quiz::findOrFail($pendingId)` → ownership check → `delete()` (cascade)                                      |
| `endSession()`               | `GameSession::findOrFail($pendingId)` → host check → `update(['status' => 'finished'])`                      |
| `clearSessions()`            | `GameSession::whereIn('id', $selectedSessionIds)->where('host_user_id', Auth::id())->where('status', 'finished')->delete()` |
| `clearPlayerEntries()`       | `Player::whereIn('id', $selectedPlayerIds)->where('user_id', Auth::id())->delete()`                          |

The submit handler on the shared modal dispatches to the right action method based on `$pendingAction` and is a no-op for an unknown value (defense in depth).

After each action: reset `$pendingAction` / `$pendingId` / the relevant selection array, then `dispatch('quiz-deleted')` / `'game-ended'` / `'history-cleared'` for the existing `<x-action-message>` toast pattern.

**`App\Livewire\QuizIndex`** — becomes stateful. Gains the same `confirmDeleteQuiz` / `deleteQuiz` pair and its own `pendingId` so the action is also available from `/quizzes`.

### Database

No migrations. Existing FK chains:

- `quizzes` → `categories` → `questions` (cascade)
- `game_sessions` → `players` → `player_answers` (cascade)
- `quizzes` → `game_sessions` (cascade) — so deleting a quiz also wipes its history. That's intentional: a quiz the owner is throwing away takes its game history with it.

### Authorization

Following the codebase's existing pattern (e.g. `HostDashboard.php:26`), each action does an inline `abort_unless($model->user_id === Auth::id(), 403)` after `findOrFail`. The bulk-clear methods scope the query directly by `Auth::id()`, so unrelated ids passed in the selection array are silently filtered out by the `where` clause and never deleted.

## UI

### My Quizzes (on Dashboard and `/quizzes`)

Action cell adds a `Delete` button next to `Edit`:

```blade
<flux:modal.trigger name="confirm-action">
    <flux:button size="sm" variant="danger"
                 wire:click="confirmDeleteQuiz({{ $quiz->id }})"
                 data-test="delete-quiz-{{ $quiz->id }}">
        {{ __('Delete') }}
    </flux:button>
</flux:modal.trigger>
```

### Hosted Games (Dashboard)

- Header gets a leading empty `<th>` for the checkbox column.
- Per row:
  - `status === 'finished'` → `<flux:checkbox wire:model.live="selectedSessionIds" value="{{ $session->id }}">`
  - otherwise → `End` danger button calling `confirmEndSession($session->id)`
- Above the table, a bulk-action bar shows `{{ count($selectedSessionIds) }} {{ __('selected') }}` and a `Delete selected` button (disabled when count is zero) that calls `confirmClearSessions()`.

### My Game History (Dashboard)

Same checkbox + bulk-action bar pattern, bound to `$selectedPlayerIds`. No per-row "end" — these are always finished.

### Shared confirmation modal

One `<flux:modal name="confirm-action">` at the bottom of each Livewire view, body switched on `$pendingAction`:

| `$pendingAction`   | Heading                                            | Submit calls        |
|--------------------|----------------------------------------------------|---------------------|
| `delete-quiz`      | "Delete this quiz?"                                | `deleteQuiz`        |
| `end-session`      | "End this game?"                                   | `endSession`        |
| `clear-sessions`   | "Delete :count hosted games?"                      | `clearSessions`     |
| `clear-players`    | "Delete :count entries from your game history?"    | `clearPlayerEntries`|

Each modal includes the cancel button (`flux:modal.close`) and a danger submit. The submit handler in the component reads `$pendingAction` and dispatches to the right method.

### Test attributes

`delete-quiz-{id}`, `end-session-{id}`, `clear-sessions`, `clear-players`, `confirm-action`, `confirm-delete-quiz`, `confirm-end-session`, `confirm-clear-sessions`, `confirm-clear-players`.

## i18n

New strings added to `lang/de.json` (also flowed through `__()` in views so the English source is the key):

| English                                                       | German                                              |
|---------------------------------------------------------------|-----------------------------------------------------|
| "Delete"                                                      | "Löschen"                                           |
| "End"                                                         | "Beenden"                                           |
| "Delete this quiz?"                                           | "Dieses Quiz löschen?"                              |
| "All categories, questions and past game sessions will be removed." | "Alle Kategorien, Fragen und vergangenen Spielsitzungen werden entfernt." |
| "End this game?"                                              | "Dieses Spiel beenden?"                             |
| "Its status will be set to finished."                         | "Der Status wird auf beendet gesetzt."              |
| "Delete :count hosted games?"                                 | "':count' gehostete Spiele löschen?"                |
| "Delete :count entries from your game history?"               | "':count' Einträge aus deinem Spielverlauf löschen?"|
| "Delete selected"                                             | "Auswahl löschen"                                   |
| ":count selected"                                             | "':count' ausgewählt"                               |
| "Cancel"                                                      | "Abbrechen"                                         |
| "Quiz deleted."                                               | "Quiz gelöscht."                                    |
| "Game ended."                                                 | "Spiel beendet."                                    |
| "History cleared."                                            | "Verlauf gelöscht."                                 |

## Testing

New file `tests/Feature/DashboardCleanupTest.php` (Pest), ~11 tests:

**Delete quiz**
- user can delete own quiz from Dashboard → row gone, categories/questions/sessions cascade-deleted
- user can delete own quiz from QuizIndex
- user cannot delete another user's quiz (`confirmDeleteQuiz($otherQuiz->id)` → 403)

**End hosted session**
- host can end a `waiting` / `playing` / `reviewing` session → status flipped to `finished`
- ending preserves players and player answers
- non-host cannot end a session → 403

**Clear hosted sessions**
- selecting finished sessions and calling `clearSessions` removes only those rows
- passing an open (waiting/playing) session id in the selection is silently filtered out by the `status=finished` guard — open sessions survive

**Clear player history**
- selecting player entries and calling `clearPlayerEntries` removes only those rows
- a user cannot delete another user's player entries (id in the selection belongs to another user → filtered out by the `user_id` scope; rows survive)

**Modal / state**
- calling a `confirm*` method sets `$pendingAction` and `$pendingId` correctly; submit with a mismatched `$pendingAction` is a no-op

Run via `composer test` (Pint then Pest).

## Out of scope

- Scheduled / auto-prune of old finished sessions. Could be a Laravel scheduled job in a follow-up.
- "Undo" toasts for accidental deletes.
- Broadcasting a `GameFinished` event when the host manually ends a stale session.
- Soft deletes — none of the involved tables have `deleted_at`, and adding them would be scope creep.
