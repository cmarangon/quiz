# Auto-Expiring Stale Games Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Open games that sit idle for more than 2 hours stop showing on the public welcome page and have their database rows reclaimed automatically.

**Architecture:** A single source of truth on the `GameSession` model (open-status list, idle-timeout constant, `stale` query scope) is consumed by two mechanisms: the welcome page filters its query to open-and-fresh games (works with no cron), and a `games:prune-stale` artisan command hard-deletes stale games (DB foreign-key cascades remove their players and answers). The command is scheduled hourly and also run once per deploy.

**Tech Stack:** Laravel 12, Livewire (welcome page), Pest 4 for tests, SQLite test DB with foreign-key constraints enabled.

**Spec:** `docs/superpowers/specs/2026-06-03-auto-expire-stale-games-design.md`

---

## File Structure

| File | Responsibility |
| --- | --- |
| `app/Models/GameSession.php` | Owns `OPEN_STATUSES`, `IDLE_TIMEOUT_MINUTES`, and `scopeStale` — the shared definition of "open" and "stale" |
| `app/Livewire/WelcomePage.php` | Lists only open + fresh games |
| `app/Console/Commands/PruneStaleGames.php` | Deletes stale games, reports count |
| `routes/console.php` | Schedules the command hourly |
| `deploy.sh` | Runs the command once per deploy (cron-independent fallback) |
| `tests/Unit/GameSessionScopeTest.php` | Tests `scopeStale` selects the right rows |
| `tests/Feature/WelcomePageTest.php` | Tests stale games are hidden, fresh shown |
| `tests/Feature/PruneStaleGamesTest.php` | Tests the command deletes stale games + children, keeps the rest |

---

## Task 1: Model constants and `stale` scope

**Files:**
- Modify: `app/Models/GameSession.php`
- Test: `tests/Unit/GameSessionScopeTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/GameSessionScopeTest.php`:

```php
<?php

use App\Models\GameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stale scope selects open games idle past the timeout', function () {
    $staleWaiting = GameSession::factory()->create(['status' => 'waiting']);
    GameSession::query()->whereKey($staleWaiting)
        ->update(['updated_at' => now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES + 1)]);

    $freshWaiting = GameSession::factory()->create(['status' => 'waiting']);

    $staleFinished = GameSession::factory()->create(['status' => 'finished']);
    GameSession::query()->whereKey($staleFinished)
        ->update(['updated_at' => now()->subDay()]);

    $ids = GameSession::stale()->pluck('id');

    expect($ids)->toContain($staleWaiting->id)
        ->not->toContain($freshWaiting->id)
        ->not->toContain($staleFinished->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/GameSessionScopeTest.php`
Expected: FAIL — `Call to undefined method ...::stale()` (and `IDLE_TIMEOUT_MINUTES` undefined).

- [ ] **Step 3: Add the constants and scope**

In `app/Models/GameSession.php`, add the `Builder` import near the other `use` statements:

```php
use Illuminate\Database\Eloquent\Builder;
```

Inside the class, directly after `use HasFactory;`, add:

```php
public const OPEN_STATUSES = ['waiting', 'playing', 'reviewing'];

public const IDLE_TIMEOUT_MINUTES = 120;

public function scopeStale(Builder $query): Builder
{
    return $query
        ->whereIn('status', self::OPEN_STATUSES)
        ->where('updated_at', '<', now()->subMinutes(self::IDLE_TIMEOUT_MINUTES));
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/GameSessionScopeTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/GameSession.php tests/Unit/GameSessionScopeTest.php
git commit -m "Add stale scope and timeout constants to GameSession"
```

---

## Task 2: Welcome page hides stale games

**Files:**
- Modify: `app/Livewire/WelcomePage.php`
- Test: `tests/Feature/WelcomePageTest.php:55` (add tests near the finished-games test)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/WelcomePageTest.php`:

```php
test('welcome page does not show stale open game sessions', function () {
    $quiz = Quiz::factory()->create(['title' => 'Abandoned Lobby']);
    $session = GameSession::factory()->for($quiz)->create(['status' => 'waiting']);
    GameSession::query()->whereKey($session)
        ->update(['updated_at' => now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES + 1)]);

    $this->get(route('home'))
        ->assertDontSee('Abandoned Lobby');
});

test('welcome page still shows recently updated open games', function () {
    $quiz = Quiz::factory()->create(['title' => 'Live Right Now']);
    GameSession::factory()->for($quiz)->create(['status' => 'playing']);

    $this->get(route('home'))
        ->assertSee('Live Right Now');
});
```

- [ ] **Step 2: Run tests to verify the stale one fails**

Run: `php artisan test tests/Feature/WelcomePageTest.php`
Expected: `does not show stale open game sessions` FAILS (stale game is currently listed); the others PASS.

- [ ] **Step 3: Add the freshness filter**

In `app/Livewire/WelcomePage.php`, replace the query in `render()`:

```php
$activeGames = GameSession::with(['quiz', 'players'])
    ->whereIn('status', GameSession::OPEN_STATUSES)
    ->where('updated_at', '>=', now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES))
    ->latest()
    ->get();
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/WelcomePageTest.php`
Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/WelcomePage.php tests/Feature/WelcomePageTest.php
git commit -m "Hide stale open games from the welcome page"
```

---

## Task 3: `games:prune-stale` command

**Files:**
- Create: `app/Console/Commands/PruneStaleGames.php`
- Test: `tests/Feature/PruneStaleGamesTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PruneStaleGamesTest.php`:

```php
<?php

use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('prune-stale deletes stale open games and their children', function () {
    $stale = GameSession::factory()->create(['status' => 'waiting']);
    $player = Player::factory()->for($stale, 'gameSession')->create();
    $answer = PlayerAnswer::factory()
        ->for($stale, 'gameSession')
        ->for($player)
        ->create();
    GameSession::query()->whereKey($stale)
        ->update(['updated_at' => now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES + 1)]);

    $fresh = GameSession::factory()->create(['status' => 'playing']);
    $finished = GameSession::factory()->create(['status' => 'finished']);
    GameSession::query()->whereKey($finished)->update(['updated_at' => now()->subDay()]);

    $this->artisan('games:prune-stale')
        ->expectsOutputToContain('Pruned 1 stale game session(s).')
        ->assertSuccessful();

    expect(GameSession::find($stale->id))->toBeNull();
    expect(Player::find($player->id))->toBeNull();
    expect(PlayerAnswer::find($answer->id))->toBeNull();
    expect(GameSession::find($fresh->id))->not->toBeNull();
    expect(GameSession::find($finished->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PruneStaleGamesTest.php`
Expected: FAIL — command `games:prune-stale` is not defined.

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/PruneStaleGames.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use Illuminate\Console\Command;

class PruneStaleGames extends Command
{
    protected $signature = 'games:prune-stale';

    protected $description = 'Delete open game sessions that have been idle past the timeout';

    public function handle(): int
    {
        $count = GameSession::stale()->delete();

        $this->info("Pruned {$count} stale game session(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/PruneStaleGamesTest.php`
Expected: PASS.

> Note: child deletion relies on the database `ON DELETE CASCADE` foreign keys. Laravel's SQLite test connection enables foreign-key constraints by default, so the cascade is exercised by this test.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/PruneStaleGames.php tests/Feature/PruneStaleGamesTest.php
git commit -m "Add games:prune-stale command"
```

---

## Task 4: Schedule the command hourly

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Add the schedule entry**

In `routes/console.php`, add the import below the existing `use` lines:

```php
use Illuminate\Support\Facades\Schedule;
```

At the end of the file add:

```php
Schedule::command('games:prune-stale')->hourly();
```

- [ ] **Step 2: Verify the command is scheduled**

Run: `php artisan schedule:list`
Expected: output includes `games:prune-stale` running hourly.

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test`
Expected: all tests PASS (no regressions).

- [ ] **Step 4: Commit**

```bash
git add routes/console.php
git commit -m "Schedule games:prune-stale hourly"
```

---

## Task 5: Prune on deploy

**Files:**
- Modify: `deploy.sh`

- [ ] **Step 1: Add the prune step after migrations**

In `deploy.sh`, immediately after the `php artisan migrate --force` block and before `echo "==> Caching configuration"`, insert:

```bash
echo "==> Pruning stale game sessions"
php artisan games:prune-stale
```

- [ ] **Step 2: Verify the script is still valid bash**

Run: `bash -n deploy.sh`
Expected: no output, exit code 0.

- [ ] **Step 3: Commit**

```bash
git add deploy.sh
git commit -m "Prune stale games on deploy"
```

---

## Final Verification

- [ ] Run the full suite: `php artisan test` — all green.
- [ ] `php artisan schedule:list` shows `games:prune-stale` hourly.
- [ ] Manual sanity: `php artisan games:prune-stale` prints a "Pruned N stale game session(s)." line and exits 0.

---

## Notes for the implementer

- **Why query-builder `update` to age rows in tests:** Eloquent's `save()` always refreshes `updated_at`, so to create a genuinely-stale row you must set `updated_at` via `GameSession::query()->whereKey(...)->update([...])`, which does not touch timestamps automatically.
- **Why builder `delete()` in the command (not a model loop):** a single `DELETE FROM game_sessions WHERE ...` is cheaper and child rows are removed by the DB foreign-key cascade declared in the migrations. No Eloquent model events are needed.
- **Single source of truth:** the timeout and status list live only on `GameSession`. If the window ever changes, change `IDLE_TIMEOUT_MINUTES` once — the page, scope, and command all follow.
```
