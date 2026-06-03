# Auto-Expiring Stale Games — Design

**Date:** 2026-06-03
**Status:** Approved, ready for planning

## Problem

The public welcome page (`App\Livewire\WelcomePage`) lists **every** `GameSession`
whose `status` is `waiting`, `playing`, or `reviewing`, newest first, with no age or
owner filter. The Playwright e2e suite runs against production
(`https://quiz.falkenstein.dev`) as `e2e-host@test.local`. Tests that complete a full
game reach `finished` (hidden), but any test that fails partway — or the geo-guesser
test — leaves a session stuck in an open status. These accumulate and clutter the live
frontpage. The same happens to any real game a host abandons.

## Goal

Open games should disappear from the frontpage on their own once they are clearly
abandoned, and their database rows should be reclaimed. No manual intervention.

## Definition of "stale"

A `GameSession` is **stale** when **both** hold:

- `status` is one of `waiting`, `playing`, `reviewing` (an *open* status), and
- `updated_at` is older than **2 hours** (`IDLE_TIMEOUT_MINUTES = 120`).

During real play `updated_at` bumps on every phase change, question advance, and
category change, so an actively-played game always stays fresh. An abandoned lobby or a
game left mid-review keeps its old `updated_at` and ages out after two hours.

## Approach

Two cooperating mechanisms. Cron is probably **not** wired up on the prod Plesk box, so
the frontpage filter — which needs no cron — is the mechanism that actually fixes the
visible problem. The command is hygiene that reclaims DB rows.

### 1. Frontpage freshness filter (instant, no cron)

`WelcomePage::render()` adds a freshness condition so only open **and** fresh games are
listed:

```php
$activeGames = GameSession::with(['quiz', 'players'])
    ->whereIn('status', GameSession::OPEN_STATUSES)
    ->where('updated_at', '>=', now()->subMinutes(GameSession::IDLE_TIMEOUT_MINUTES))
    ->latest()
    ->get();
```

Stale games vanish from the page immediately, regardless of whether any job runs.

### 2. `games:prune-stale` artisan command (reclaims rows)

Hard-deletes stale sessions. The migrations already declare `ON DELETE CASCADE` on
`players.game_session_id`, `player_answers.game_session_id`, and
`player_answers.player_id`, so deleting a session removes its players and answers at the
database level. The command reports how many sessions it deleted.

```php
$count = GameSession::stale()->delete();   // builder delete; DB cascade handles children
$this->info("Pruned {$count} stale game session(s).");
```

`Builder::delete()` issues a single `DELETE FROM game_sessions WHERE ...`; child cleanup
relies on the DB foreign-key cascade, not Eloquent model events (none are needed here).

Registered in `routes/console.php`:

```php
Schedule::command('games:prune-stale')->hourly();
```

This runs automatically only if `* * * * * php artisan schedule:run` is in cron on prod.

### 3. Prune on deploy (fallback for missing cron)

Because cron may not be configured, `deploy.sh` runs the command once per deploy so stale
rows are reclaimed even with no scheduler. Added after migrations, before config caching:

```bash
echo "==> Pruning stale game sessions"
php artisan games:prune-stale
```

## Single source of truth

The threshold and open-status list live on the model so the page and command cannot
drift apart.

`app/Models/GameSession.php`:

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

The frontpage uses the complementary condition (open **and** fresh) directly, as shown
above.

## Files

| File | Change |
| --- | --- |
| `app/Models/GameSession.php` | Add `OPEN_STATUSES`, `IDLE_TIMEOUT_MINUTES`, `scopeStale` |
| `app/Livewire/WelcomePage.php` | Filter query to open + fresh |
| `app/Console/Commands/PruneStaleGames.php` | New `games:prune-stale` command |
| `routes/console.php` | `Schedule::command('games:prune-stale')->hourly()` |
| `deploy.sh` | Run `php artisan games:prune-stale` after migrations |

## Testing (Pest)

- **`tests/Feature/WelcomePageTest.php`** (extend): a fresh open game is listed; an open
  game with `updated_at` older than 2 hours is **not** listed; a `finished` game is not
  listed (existing behaviour).
- **New command test** (e.g. `tests/Feature/PruneStaleGamesTest.php`): seed a stale open
  game with players + answers and a fresh open game; run `games:prune-stale`; assert the
  stale session and its children rows are deleted, the fresh session and a `finished`
  session survive, and the reported count is correct.

Use the existing `WelcomePageTest` and `DashboardCleanupTest` as style references, and
`GameSessionFactory` for setup. Set `updated_at` explicitly when seeding stale rows.

## Out of scope

- Deleting old `finished` games (harmless, preserves real leaderboards/history).
- Targeting only the e2e account (we expire any host's abandoned games).
- A soft-delete / `expired` status (we hard-delete; cascades keep it clean).
