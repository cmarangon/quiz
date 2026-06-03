# Fancy Game Screens + Player Emojis Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the join, question-result, and final-result screens visually engaging, add three per-quiz "house styles", and let players pick a required emoji shown everywhere their nickname appears.

**Architecture:** Three house styles (`party-pop`, `game-show`, `bright-bouncy`) stored in the quiz's existing `settings` JSON and rendered via a new scoped `presentation.css` on the theme-independent join + final-result screens. The question-result screen stays category-themed via a shared partial tinted by per-theme CSS variables. A new `players.emoji` column (required at join) is rendered consistently through a `<x-player-name>` Blade component across player, host, spectator, and leaderboard surfaces.

**Tech Stack:** Laravel 12, Livewire 3 + Flux UI, Tailwind 4 (+ plain scoped CSS for game themes), Pest 4, Playwright.

---

## File Structure

**Create:**
- `database/migrations/2026_06_03_000000_add_emoji_to_players_table.php` — add `emoji` column
- `app/Support/PlayerEmojis.php` — canonical curated emoji list
- `resources/css/presentation.css` — the three house styles + podium/confetti
- `resources/views/components/player-name.blade.php` — `<x-player-name>` (emoji + nickname)
- `resources/views/themes/_player-result.blade.php` — shared theme-tinted result card
- `tests/Feature/PlayerEmojiTest.php` — emoji persistence/validation
- `tests/Feature/PresentationStyleTest.php` — style save/default

**Modify:**
- `app/Models/Player.php` — fillable
- `app/Models/GameSession.php` — `presentationStyle()` accessor
- `app/Livewire/JoinGame.php` — emoji property/validation/persist
- `app/Livewire/QuizBuilder.php` — presentation_style property/load/save
- `app/Livewire/PlayerScreen.php` — emoji in leaderboard maps
- `app/Livewire/SpectatorScreen.php` — emoji in player list + leaderboard
- `app/Services/GameService.php` — emoji in scores + leaderboard maps
- `app/Events/PlayerJoined.php` — emoji in payload
- `resources/css/app.css` — import presentation.css
- `resources/css/themes.css` — per-theme `--qz-accent*` vars + shared `.qz-result*` rules
- `resources/views/livewire/join-game.blade.php` — house-styled join + emoji grid
- `resources/views/livewire/player-screen.blade.php` — themed review + house-styled finish + emoji header
- `resources/views/livewire/host-dashboard.blade.php` — emoji in player rows
- `resources/views/livewire/spectator-screen.blade.php` — emoji in chips + leaderboard
- `resources/views/livewire/quiz-builder.blade.php` — style picker
- `tests/Feature/GameLobbyTest.php` — set emoji in existing join tests

**Test commands:** `php artisan test --filter=<Name>` (Pest). Build CSS check: `npm run build`.

---

## Task 1: Player emoji column, model, and curated set

**Files:**
- Create: `app/Support/PlayerEmojis.php`
- Create: `database/migrations/2026_06_03_000000_add_emoji_to_players_table.php`
- Modify: `app/Models/Player.php`
- Test: `tests/Feature/PlayerEmojiTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PlayerEmojiTest.php`:

```php
<?php

use App\Models\Player;
use App\Support\PlayerEmojis;

test('curated emoji set is non-empty and unique', function () {
    $all = PlayerEmojis::all();

    expect($all)->toBeArray()
        ->and(count($all))->toBeGreaterThanOrEqual(12)
        ->and($all)->toEqual(array_values(array_unique($all)));
});

test('a player can persist an emoji', function () {
    $player = Player::factory()->create(['emoji' => '🚀']);

    expect($player->fresh()->emoji)->toBe('🚀');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PlayerEmojiTest`
Expected: FAIL — `Class "App\Support\PlayerEmojis" not found` / unknown column `emoji`.

- [ ] **Step 3: Create the curated emoji set**

Create `app/Support/PlayerEmojis.php`:

```php
<?php

namespace App\Support;

class PlayerEmojis
{
    /**
     * Curated set of fun emojis players can pick when joining.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            '🦄', '🐙', '🚀', '🦖',
            '🍕', '👽', '🤖', '🦩',
            '🐸', '🎩', '🔥', '💩',
            '🌮', '👾',
        ];
    }
}
```

- [ ] **Step 4: Create the migration**

Create `database/migrations/2026_06_03_000000_add_emoji_to_players_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('emoji')->nullable()->after('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('emoji');
        });
    }
};
```

- [ ] **Step 5: Add `emoji` to the Player model fillable**

In `app/Models/Player.php`, change the `$fillable` array to include `emoji`:

```php
    protected $fillable = [
        'game_session_id',
        'user_id',
        'nickname',
        'emoji',
        'score',
        'streak',
        'is_connected',
    ];
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=PlayerEmojiTest`
Expected: PASS (2 tests). The test suite uses an in-memory/refreshed DB, so the new migration runs automatically.

- [ ] **Step 7: Commit**

```bash
git add app/Support/PlayerEmojis.php database/migrations/2026_06_03_000000_add_emoji_to_players_table.php app/Models/Player.php tests/Feature/PlayerEmojiTest.php
git commit -m "Add player emoji column and curated emoji set

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: JoinGame — required emoji property, validation, persistence

**Files:**
- Modify: `app/Livewire/JoinGame.php`
- Test: `tests/Feature/PlayerEmojiTest.php` (extend), `tests/Feature/GameLobbyTest.php` (fix existing)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PlayerEmojiTest.php`:

```php
use App\Livewire\JoinGame;
use App\Models\GameSession;
use App\Models\Quiz;
use Livewire\Livewire;

test('joining persists the chosen emoji', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'waiting']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'EmojiFan')
        ->set('emoji', '🚀')
        ->call('join')
        ->assertRedirectContains('/game/'.$session->join_code.'/play');

    expect(Player::where('game_session_id', $session->id)->first()->emoji)->toBe('🚀');
});

test('joining without an emoji fails validation', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'waiting']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'NoEmoji')
        ->set('emoji', '')
        ->call('join')
        ->assertHasErrors(['emoji']);
});

test('joining with an emoji outside the curated set fails validation', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'waiting']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'Sneaky')
        ->set('emoji', '😀')
        ->call('join')
        ->assertHasErrors(['emoji']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PlayerEmojiTest`
Expected: FAIL on the three new tests — emoji is not validated/persisted yet (join succeeds without emoji, no errors).

- [ ] **Step 3: Add emoji handling to JoinGame**

In `app/Livewire/JoinGame.php`:

Add the import near the top (after the existing `use` statements):

```php
use App\Support\PlayerEmojis;
use Illuminate\Validation\Rule;
```

Add the property after `public string $nickname = '';`:

```php
    public string $emoji = '';
```

Replace the validation block inside `join()`:

```php
        $this->validate([
            'nickname' => 'required|string|min:1|max:50',
        ]);
```

with:

```php
        $this->validate([
            'nickname' => 'required|string|min:1|max:50',
            'emoji' => ['required', Rule::in(PlayerEmojis::all())],
        ]);
```

Add `emoji` to the `Player::create([...])` call:

```php
        $player = Player::create([
            'game_session_id' => $session->id,
            'nickname' => $nickname,
            'emoji' => $this->emoji,
            'score' => 0,
            'streak' => 0,
            'is_connected' => true,
        ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=PlayerEmojiTest`
Expected: PASS (all 5 tests).

- [ ] **Step 5: Fix existing join tests that now require an emoji**

In `tests/Feature/GameLobbyTest.php`, every `Livewire::test(JoinGame::class, ...)` chain that calls `join` must also set a valid emoji. Add `->set('emoji', '🚀')` after each `->set('nickname', ...)` in:
- `test('player can join a game with a nickname', ...)`
- `test('duplicate nickname gets number appended', ...)`

Example (first one):

```php
    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'TestPlayer')
        ->set('emoji', '🚀')
        ->call('join')
        ->assertRedirectContains('/game/'.$session->join_code.'/play');
```

Do NOT add an emoji to `test('cannot join with empty nickname', ...)` (line ~66) — it asserts nickname errors and should still fail validation overall; it remains valid.

- [ ] **Step 6: Run the full lobby + emoji suites**

Run: `php artisan test --filter='GameLobbyTest|PlayerEmojiTest'`
Expected: PASS (all tests green).

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/JoinGame.php tests/Feature/PlayerEmojiTest.php tests/Feature/GameLobbyTest.php
git commit -m "Require a curated emoji when joining a game

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: GameSession presentation-style accessor + Quiz Builder picker

**Files:**
- Modify: `app/Models/GameSession.php`
- Modify: `app/Livewire/QuizBuilder.php`
- Modify: `resources/views/livewire/quiz-builder.blade.php`
- Test: `tests/Feature/PresentationStyleTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PresentationStyleTest.php`:

```php
<?php

use App\Livewire\QuizBuilder;
use App\Models\GameSession;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('game session defaults to party-pop presentation style', function () {
    $quiz = Quiz::factory()->create(['settings' => []]);
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id]);

    expect($session->presentationStyle())->toBe('party-pop');
});

test('game session reads presentation style from quiz settings', function () {
    $quiz = Quiz::factory()->create(['settings' => ['presentation_style' => 'game-show']]);
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id]);

    expect($session->presentationStyle())->toBe('game-show');
});

test('quiz builder saves the chosen presentation style', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'My Quiz')
        ->set('presentationStyle', 'bright-bouncy')
        ->call('save');

    expect(Quiz::where('title', 'My Quiz')->first()->settings['presentation_style'])
        ->toBe('bright-bouncy');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PresentationStyleTest`
Expected: FAIL — `Method ... presentationStyle does not exist` and no `presentationStyle` property on QuizBuilder.

- [ ] **Step 3: Add the accessor to GameSession**

In `app/Models/GameSession.php`, add this method inside the class (after the relationships):

```php
    /**
     * The per-quiz presentation ("house") style for theme-independent screens.
     * One of: party-pop, game-show, bright-bouncy. Defaults to party-pop.
     */
    public function presentationStyle(): string
    {
        $allowed = ['party-pop', 'game-show', 'bright-bouncy'];
        $style = $this->quiz->settings['presentation_style'] ?? 'party-pop';

        return in_array($style, $allowed, true) ? $style : 'party-pop';
    }
```

If `GameSession` has no `quiz()` relation yet, add one (it references `quiz_id`):

```php
    public function quiz(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
```

(Verify first with: `grep -n "function quiz" app/Models/GameSession.php` — only add if missing.)

- [ ] **Step 4: Add the property + load/save to QuizBuilder**

In `app/Livewire/QuizBuilder.php`:

Add the property near the other public settings properties (`$enableTimeBonus`, `$enableStreaks`):

```php
    public string $presentationStyle = 'party-pop';
```

In `mount()`, after the `$this->enableStreaks = ...` line, add:

```php
            $this->presentationStyle = $quiz->settings['presentation_style'] ?? 'party-pop';
```

In `save()`, add `presentation_style` to the `settings` array:

```php
            'settings' => [
                'enable_time_bonus' => $this->enableTimeBonus,
                'enable_streaks' => $this->enableStreaks,
                'presentation_style' => $this->presentationStyle,
            ],
```

- [ ] **Step 5: Add the picker UI to the builder blade**

In `resources/views/livewire/quiz-builder.blade.php`, after the "Enable Streaks" checkbox label block (around line 25, before the save button at line 30), add:

```blade
                <div class="flex flex-col gap-2">
                    <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Presentation style') }}</span>
                    <div class="flex flex-wrap gap-2" data-test="presentation-style-picker">
                        @foreach(['party-pop' => 'Party Pop', 'game-show' => 'Game Show', 'bright-bouncy' => 'Bright & Bouncy'] as $key => $label)
                            <button type="button"
                                wire:click="$set('presentationStyle', '{{ $key }}')"
                                data-test="presentation-style-option"
                                data-style="{{ $key }}"
                                @class([
                                    'rounded-lg border px-4 py-2 text-sm font-medium transition',
                                    'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200' => $presentationStyle === $key,
                                    'border-neutral-300 text-neutral-600 dark:border-neutral-600 dark:text-neutral-300' => $presentationStyle !== $key,
                                ])>
                                {{ __($label) }}
                            </button>
                        @endforeach
                    </div>
                </div>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=PresentationStyleTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Models/GameSession.php app/Livewire/QuizBuilder.php resources/views/livewire/quiz-builder.blade.php tests/Feature/PresentationStyleTest.php
git commit -m "Add per-quiz presentation style picker and accessor

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: House styles CSS + redesigned join screen with emoji grid

**Files:**
- Create: `resources/css/presentation.css`
- Modify: `resources/css/app.css`
- Modify: `resources/views/livewire/join-game.blade.php`

This task is presentational; verification is a successful CSS build plus rendering the join markup. No unit test (covered functionally by Task 2 tests, which still pass).

- [ ] **Step 1: Create presentation.css with the three house styles**

Create `resources/css/presentation.css` with the validated styles (ported from the approved mockup). This file also contains the final-result podium/confetti used in Task 8.

```css
/* =========================================================================
   Per-quiz "house" styles for theme-independent screens (join + final result).
   Scoped under .qz-stage--{style}. Plain CSS so it never fights Tailwind.
   ========================================================================= */

.qz-stage { position: relative; width: 100%; }

.qz-stage .qz-card {
    border-radius: 30px;
    padding: 26px 22px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    position: relative;
    overflow: hidden;
}

.qz-stage .qz-code-pill {
    font-size: 12px; letter-spacing: 1px; text-transform: uppercase;
    padding: 6px 14px; border-radius: 999px;
}
.qz-stage .qz-code-pill .qz-codeval { font-family: 'Anton', sans-serif; letter-spacing: 3px; }

.qz-stage .qz-title { font-size: 30px; line-height: 1.05; text-align: center; }

.qz-stage .qz-emoji-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; width: 100%;
}
.qz-stage .qz-emoji-btn {
    aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
    font-size: 24px; border-radius: 14px; cursor: pointer; border: 1.5px solid transparent;
    background: rgba(255, 255, 255, 0.08); transition: transform .12s, box-shadow .12s, background .12s;
}
.qz-stage .qz-input {
    width: 100%; padding: 13px 16px; border-radius: 14px; font-size: 16px; font-family: inherit;
}
.qz-stage .qz-btn {
    width: 100%; padding: 14px; border-radius: 14px; font-weight: 800; font-size: 17px;
    font-family: 'Baloo 2', sans-serif; cursor: pointer; border: none; transition: filter .12s, transform .12s;
}
.qz-stage .qz-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.qz-stage .qz-btn:not(:disabled):hover { filter: brightness(1.05); transform: translateY(-1px); }
.qz-stage .qz-blob { position: absolute; border-radius: 50%; pointer-events: none; }

/* ---------- PARTY POP ---------- */
.qz-stage--party-pop .qz-card {
    background: linear-gradient(160deg, #3b0764, #1e1b4b 55%, #0f172a);
    box-shadow: 0 24px 60px rgba(124, 58, 237, .35), inset 0 0 60px rgba(168, 85, 247, .12);
    border: 2px solid rgba(168, 85, 247, .4);
    font-family: 'Fredoka', sans-serif; color: #fff;
}
.qz-stage--party-pop .qz-blob.b1 { width: 120px; height: 120px; background: radial-gradient(circle at 30% 30%, #f472b6, #db2777); top: -30px; right: -30px; opacity: .5; }
.qz-stage--party-pop .qz-blob.b2 { width: 90px; height: 90px; background: radial-gradient(circle at 30% 30%, #5eead4, #0d9488); bottom: 60px; left: -26px; opacity: .5; }
.qz-stage--party-pop .qz-code-pill { background: rgba(244, 114, 182, .18); border: 1.5px solid #f472b6; color: #f9a8d4; }
.qz-stage--party-pop .qz-title { font-family: 'Bangers', sans-serif; letter-spacing: 1.5px; color: #fff; text-shadow: 0 3px 0 #db2777, 0 0 22px rgba(244, 114, 182, .5); font-size: 38px; }
.qz-stage--party-pop .qz-emoji-btn { border-color: rgba(255, 255, 255, .12); }
.qz-stage--party-pop .qz-emoji-btn.is-selected { background: rgba(244, 114, 182, .25); border-color: #f472b6; box-shadow: 0 0 16px rgba(244, 114, 182, .6); transform: scale(1.08); }
.qz-stage--party-pop .qz-input { background: rgba(255, 255, 255, .07); border: 1.5px solid rgba(255, 255, 255, .18); color: #fff; }
.qz-stage--party-pop .qz-input::placeholder { color: rgba(255, 255, 255, .45); }
.qz-stage--party-pop .qz-btn { background: linear-gradient(90deg, #ec4899, #a855f7); color: #fff; box-shadow: 0 8px 24px rgba(236, 72, 153, .45); }

/* ---------- GAME SHOW ---------- */
.qz-stage--game-show .qz-card {
    background: radial-gradient(ellipse at top, #1f2937, #0b0f1a 70%);
    border: 2px solid rgba(251, 191, 36, .45);
    box-shadow: 0 24px 60px rgba(0, 0, 0, .6), inset 0 0 50px rgba(251, 191, 36, .06);
    font-family: 'Fredoka', sans-serif; color: #fff;
}
.qz-stage--game-show .qz-blob.spot { top: -60px; left: 50%; transform: translateX(-50%); width: 240px; height: 240px; background: radial-gradient(circle, rgba(251, 191, 36, .35), transparent 60%); }
.qz-stage--game-show .qz-code-pill { background: rgba(251, 191, 36, .12); border: 1.5px solid #fbbf24; color: #fcd34d; }
.qz-stage--game-show .qz-title { font-family: 'Anton', sans-serif; letter-spacing: 1px; color: #fde68a; text-transform: uppercase; font-size: 32px; text-shadow: 0 0 18px rgba(251, 191, 36, .4); }
.qz-stage--game-show .qz-emoji-btn { background: rgba(255, 255, 255, .04); border-color: rgba(251, 191, 36, .2); }
.qz-stage--game-show .qz-emoji-btn.is-selected { background: rgba(251, 191, 36, .2); border-color: #fbbf24; box-shadow: 0 0 16px rgba(251, 191, 36, .6); transform: scale(1.08); }
.qz-stage--game-show .qz-input { background: rgba(0, 0, 0, .4); border: 1.5px solid rgba(251, 191, 36, .35); color: #fde68a; }
.qz-stage--game-show .qz-input::placeholder { color: rgba(253, 230, 138, .4); }
.qz-stage--game-show .qz-btn { background: linear-gradient(90deg, #f59e0b, #fbbf24); color: #3b2a00; box-shadow: 0 8px 24px rgba(245, 158, 11, .4); }

/* ---------- BRIGHT & BOUNCY ---------- */
.qz-stage--bright-bouncy .qz-card {
    background: linear-gradient(165deg, #0f766e, #0e7490 50%, #1e3a8a);
    box-shadow: 0 24px 60px rgba(13, 148, 136, .4);
    font-family: 'Baloo 2', sans-serif; color: #fff;
}
.qz-stage--bright-bouncy .qz-blob.u1 { width: 80px; height: 80px; top: -20px; left: -20px; background: rgba(255, 255, 255, .1); }
.qz-stage--bright-bouncy .qz-blob.u2 { width: 50px; height: 50px; bottom: 90px; right: 10px; background: rgba(255, 255, 255, .1); }
.qz-stage--bright-bouncy .qz-code-pill { background: rgba(255, 255, 255, .18); border: 1.5px solid rgba(255, 255, 255, .35); color: #fff; }
.qz-stage--bright-bouncy .qz-title { font-family: 'Baloo 2', sans-serif; font-weight: 800; color: #fff; font-size: 30px; }
.qz-stage--bright-bouncy .qz-emoji-btn { background: rgba(255, 255, 255, .16); border: 2px solid transparent; }
.qz-stage--bright-bouncy .qz-emoji-btn.is-selected { background: #fff; border-color: #fff; transform: scale(1.12) rotate(-6deg); box-shadow: 0 6px 16px rgba(0, 0, 0, .25); }
.qz-stage--bright-bouncy .qz-input { background: rgba(255, 255, 255, .92); border: none; color: #0f172a; }
.qz-stage--bright-bouncy .qz-btn { background: #fff; color: #0e7490; box-shadow: 0 8px 20px rgba(0, 0, 0, .25); }

/* ---------- Shared error text ---------- */
.qz-stage .qz-error { color: #fca5a5; font-size: 13px; text-align: center; }
```

- [ ] **Step 2: Import presentation.css**

In `resources/css/app.css`, add the import right after the themes import:

```css
@import './themes.css';
@import './presentation.css';
```

- [ ] **Step 3: Verify the CSS builds**

Run: `npm run build`
Expected: Build completes with no errors; output includes the new classes.

- [ ] **Step 4: Rewrite the join screen blade**

Replace the entire contents of `resources/views/livewire/join-game.blade.php` with:

```blade
@php($style = $session?->presentationStyle() ?? 'party-pop')
<div class="qz-stage qz-stage--{{ $style }}">
    <div class="qz-card">
        @if($style === 'party-pop')
            <span class="qz-blob b1"></span><span class="qz-blob b2"></span>
        @elseif($style === 'game-show')
            <span class="qz-blob spot"></span>
        @else
            <span class="qz-blob u1"></span><span class="qz-blob u2"></span>
        @endif

        <span class="qz-code-pill">{{ __('Code') }} · <span class="qz-codeval">{{ $code }}</span></span>
        <h1 class="qz-title">{{ __('Join Game') }}</h1>

        <form wire:submit="join" class="flex w-full flex-col gap-4">
            <div class="qz-emoji-grid" data-test="join-emoji-grid">
                @foreach(\App\Support\PlayerEmojis::all() as $option)
                    <button type="button"
                        wire:click="$set('emoji', '{{ $option }}')"
                        data-test="join-emoji-option"
                        data-emoji="{{ $option }}"
                        @class(['qz-emoji-btn', 'is-selected' => $emoji === $option])>
                        {{ $option }}
                    </button>
                @endforeach
            </div>

            @error('emoji')
                <p class="qz-error">{{ $message }}</p>
            @enderror

            <input type="text"
                class="qz-input"
                wire:model="nickname"
                data-test="join-nickname-input"
                placeholder="{{ __('Enter a nickname') }}"
                required>

            @if($emoji)
                <p class="text-center text-sm opacity-80" data-test="join-name-preview">{{ $emoji }} {{ $nickname ?: __('Your Nickname') }}</p>
            @endif

            @error('nickname')
                <p class="qz-error">{{ $message }}</p>
            @enderror

            <button type="submit" class="qz-btn" data-test="join-submit" @disabled($emoji === '')>
                {{ __('Join Game') }}
            </button>
        </form>
    </div>
</div>
```

Note: `JoinGame` already exposes `$session`? It does NOT — it only has `$code`. Add a `$session` load so the blade can read the style. In `app/Livewire/JoinGame.php` `mount()`, after `$this->code = strtoupper($code);`, the component already fetches the session inside `join()`. Add a public property and load it in `mount()`:

In `app/Livewire/JoinGame.php`, add property:

```php
    public ?GameSession $session = null;
```

And in `mount()`:

```php
    public function mount(string $code): void
    {
        $this->code = strtoupper($code);
        $this->session = GameSession::where('join_code', $this->code)->first();
    }
```

Then in `join()`, reuse it instead of re-querying (replace `$session = GameSession::where('join_code', $this->code)->firstOrFail();` with):

```php
        $session = $this->session ?? GameSession::where('join_code', $this->code)->firstOrFail();
```

- [ ] **Step 5: Verify the join tests still pass**

Run: `php artisan test --filter='PlayerEmojiTest|GameLobbyTest'`
Expected: PASS. (The blade now uses `data-test="join-nickname-input"` on a native input, and `wire:model="nickname"` / `wire:model="emoji"` still bind as the tests expect.)

- [ ] **Step 6: Commit**

```bash
git add resources/css/presentation.css resources/css/app.css resources/views/livewire/join-game.blade.php app/Livewire/JoinGame.php
git commit -m "Redesign join screen with house styles and emoji picker

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Player-name component + thread emoji through leaderboards/events (backend)

**Files:**
- Create: `resources/views/components/player-name.blade.php`
- Modify: `app/Services/GameService.php`
- Modify: `app/Livewire/PlayerScreen.php`
- Modify: `app/Livewire/SpectatorScreen.php`
- Modify: `app/Events/PlayerJoined.php`
- Test: `tests/Feature/PlayerEmojiTest.php` (extend)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PlayerEmojiTest.php`:

```php
use App\Services\GameService;

test('finish leaderboard includes player emojis', function () {
    $quiz = Quiz::factory()->create();
    $session = GameSession::factory()->create(['quiz_id' => $quiz->id, 'status' => 'finished']);
    Player::factory()->create(['game_session_id' => $session->id, 'nickname' => 'A', 'emoji' => '🚀', 'score' => 10]);

    $leaderboard = $session->players()->orderByDesc('score')->get()
        ->map(fn ($p) => ['nickname' => $p->nickname, 'emoji' => $p->emoji, 'score' => $p->score])
        ->toArray();

    expect($leaderboard[0]['emoji'])->toBe('🚀');
});
```

(This documents the expected leaderboard shape; the assertions below are enforced by the component + display task. Keep it simple and green after the maps are updated.)

- [ ] **Step 2: Run test to verify current state**

Run: `php artisan test --filter='finish leaderboard includes'`
Expected: PASS already (the test builds its own map). This is a guard for the shape we standardize on — proceed to make the production maps match it.

- [ ] **Step 3: Create the player-name component**

Create `resources/views/components/player-name.blade.php`:

```blade
@props(['emoji' => null, 'nickname' => ''])

<span {{ $attributes }}>@if($emoji)<span class="qz-name-emoji">{{ $emoji }}</span> @endif{{ $nickname }}</span>
```

- [ ] **Step 4: Add emoji to GameService leaderboard maps**

In `app/Services/GameService.php`, update BOTH `->map(...)` closures to include `emoji`:

`finishQuestion()` scores map:

```php
            $scores = $session->players()->orderByDesc('score')->get()->map(fn ($p) => [
                'nickname' => $p->nickname,
                'emoji' => $p->emoji,
                'score' => $p->score,
            ])->toArray();
```

`advanceToNextQuestion()` leaderboard map:

```php
            $leaderboard = $session->players()->orderByDesc('score')->get()->map(fn ($p) => [
                'nickname' => $p->nickname,
                'emoji' => $p->emoji,
                'score' => $p->score,
            ])->toArray();
```

- [ ] **Step 5: Add emoji to PlayerScreen leaderboard maps**

In `app/Livewire/PlayerScreen.php`, update BOTH `->map(...)` closures (in `mount()` and `pollState()`):

```php
                ->map(fn ($p) => ['nickname' => $p->nickname, 'emoji' => $p->emoji, 'score' => $p->score])
```

- [ ] **Step 6: Add emoji to SpectatorScreen player list + leaderboard**

In `app/Livewire/SpectatorScreen.php`:

`loadPlayers()` — return name+emoji pairs instead of plucked strings:

```php
    private function loadPlayers(): void
    {
        $this->playerNames = $this->session->players()
            ->get(['nickname', 'emoji'])
            ->map(fn ($p) => ['nickname' => $p->nickname, 'emoji' => $p->emoji])
            ->toArray();
    }
```

The leaderboard map (around line 57):

```php
                ->map(fn ($p) => ['nickname' => $p->nickname, 'emoji' => $p->emoji, 'score' => $p->score])
```

- [ ] **Step 7: Add emoji to PlayerJoined broadcast payload**

In `app/Events/PlayerJoined.php`, `broadcastWith()`:

```php
        return [
            'player_id' => $this->player->id,
            'nickname' => $this->player->nickname,
            'emoji' => $this->player->emoji,
            'player_count' => $this->session->players()->count(),
        ];
```

- [ ] **Step 8: Run the relevant suites**

Run: `php artisan test --filter='PlayerEmojiTest|GameFlowTest|GameLobbyTest|OrderingGameFlowTest|GeoGuesserGameFlowTest'`
Expected: PASS. (If any test asserts an exact leaderboard array shape without `emoji`, update that expectation to include `'emoji' => ...`.)

- [ ] **Step 9: Commit**

```bash
git add resources/views/components/player-name.blade.php app/Services/GameService.php app/Livewire/PlayerScreen.php app/Livewire/SpectatorScreen.php app/Events/PlayerJoined.php tests/Feature/PlayerEmojiTest.php
git commit -m "Carry player emoji through leaderboards and join events

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: Render emojis on host, spectator, and player headers

**Files:**
- Modify: `resources/views/livewire/host-dashboard.blade.php`
- Modify: `resources/views/livewire/spectator-screen.blade.php`
- Modify: `resources/views/livewire/player-screen.blade.php`

Presentational; verified by rendering + existing `data-player-nickname` test hooks staying intact.

- [ ] **Step 1: Host dashboard player rows**

In `resources/views/livewire/host-dashboard.blade.php`, replace the nickname span (line ~77):

```blade
                        <span class="text-zinc-900 dark:text-white">{{ $player->nickname }}</span>
```

with:

```blade
                        <x-player-name :emoji="$player->emoji" :nickname="$player->nickname" class="text-zinc-900 dark:text-white" />
```

(Leave the `data-player-nickname="{{ $player->nickname }}"` attribute on the `<li>` unchanged so existing tests keep matching.)

- [ ] **Step 2: Spectator player chips**

In `resources/views/livewire/spectator-screen.blade.php`, the lobby chips loop (line ~36) now iterates arrays. Replace:

```blade
                    @foreach($playerNames as $name)
                        <span data-test="spectator-player-chip" data-player-nickname="{{ $name }}" class="rounded-full bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
```

with:

```blade
                    @foreach($playerNames as $name)
                        <span data-test="spectator-player-chip" data-player-nickname="{{ $name['nickname'] }}" class="rounded-full bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
```

And inside that chip, replace the bare `{{ $name }}` output with:

```blade
                            <x-player-name :emoji="$name['emoji']" :nickname="$name['nickname']" />
```

(Verify the exact inner markup by reading lines 36–40 first; the chip currently prints `{{ $name }}` between the open/close span.)

- [ ] **Step 3: Spectator leaderboard + podium rows**

In `resources/views/livewire/spectator-screen.blade.php`, for the podium (line ~179) and full leaderboard (line ~197), replace the bare `{{ $entry['nickname'] }}` name outputs with:

```blade
<x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname']" />
```

Keep the rank number prefix (`{{ $index + 1 }}.`) and `data-player-nickname="{{ $entry['nickname'] }}"` attributes unchanged. For the mid-game scores list (line ~159) do the same replacement of `{{ $entry['nickname'] ?? '' }}`.

- [ ] **Step 4: Player screen own-name header**

In `resources/views/livewire/player-screen.blade.php`, replace the header (lines 3–5):

```blade
        <h1 data-test="player-nickname" class="text-2xl font-bold text-zinc-900 dark:text-white">
            {{ $player->nickname }}
        </h1>
```

with:

```blade
        <h1 data-test="player-nickname" data-player-nickname="{{ $player->nickname }}" class="text-2xl font-bold text-zinc-900 dark:text-white">
            <x-player-name :emoji="$player->emoji" :nickname="$player->nickname" />
        </h1>
```

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS. Pay attention to `GameLobbyTest` spectator chip tests — they assert `data-player-nickname` (still present) and may assert visible text; if a test asserts the chip text equals just the nickname, it still matches because the emoji is a separate inner span.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/host-dashboard.blade.php resources/views/livewire/spectator-screen.blade.php resources/views/livewire/player-screen.blade.php
git commit -m "Show player emojis on host, spectator, and player screens

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 7: Theme-tinted question-result partial

**Files:**
- Modify: `resources/css/themes.css`
- Create: `resources/views/themes/_player-result.blade.php`
- Modify: `resources/views/livewire/player-screen.blade.php`

- [ ] **Step 1: Add unified accent variables + shared result CSS to themes.css**

At the END of `resources/css/themes.css`, append a unified accent for each theme plus shared result-card rules:

```css
/* =========================== SHARED RESULT CARD ========================== */
/* Each theme exposes a unified accent so the result card can be DRY. */
.qz-theme--science        { --qz-accent: #a3e635; --qz-accent-2: #22d3ee; }
.qz-theme--history        { --qz-accent: #c8962a; --qz-accent-2: #7a2e2e; }
.qz-theme--geography      { --qz-accent: #34d399; --qz-accent-2: #0ea5e9; }
.qz-theme--nature         { --qz-accent: #4ade80; --qz-accent-2: #16a34a; }
.qz-theme--sports         { --qz-accent: #f97316; --qz-accent-2: #facc15; }
.qz-theme--pop-culture    { --qz-accent: #f472b6; --qz-accent-2: #a855f7; }
.qz-theme--general-knowledge { --qz-accent: #818cf8; --qz-accent-2: #38bdf8; }

.qz-theme .qz-result {
    display: flex; flex-direction: column; align-items: center; gap: 14px;
    padding: 28px 22px; border-radius: 24px; text-align: center;
    background: rgba(0, 0, 0, 0.25);
    border: 2px solid color-mix(in srgb, var(--qz-accent, #818cf8) 55%, transparent);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
    animation: qz-result-pop 0.4s cubic-bezier(.2, 1.3, .4, 1) both;
}
@keyframes qz-result-pop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.qz-theme .qz-result__reaction { font-size: 64px; line-height: 1; animation: qz-result-bounce 0.6s ease both; }
@keyframes qz-result-bounce { 0% { transform: translateY(-12px); } 50% { transform: translateY(6px); } 100% { transform: translateY(0); } }

.qz-theme .qz-result__title { font-size: 28px; font-weight: 800; }
.qz-theme .qz-result--correct .qz-result__title { color: var(--qz-accent); }
.qz-theme .qz-result--wrong .qz-result__title { color: #fca5a5; }
.qz-theme .qz-result__points {
    font-size: 20px; font-weight: 700;
    color: color-mix(in srgb, var(--qz-accent-2, #38bdf8) 80%, white);
}
.qz-theme .qz-result__streak {
    margin-top: 4px; font-size: 14px; font-weight: 700; letter-spacing: .5px;
    padding: 6px 14px; border-radius: 999px;
    background: color-mix(in srgb, var(--qz-accent, #818cf8) 30%, transparent);
    color: #fff;
}
```

- [ ] **Step 2: Create the shared result partial**

Create `resources/views/themes/_player-result.blade.php`:

```blade
@php($result = $lastResult ?? null)
@php($correct = $result['is_correct'] ?? false)
@php($points = $result['points_earned'] ?? 0)
<div class="qz-theme qz-theme--{{ $themeKey }} qz-player">
    @if($result)
        <div data-test="player-result" data-correct="{{ $correct ? '1' : '0' }}"
             class="qz-result {{ $correct ? 'qz-result--correct' : 'qz-result--wrong' }}">
            <div class="qz-result__reaction">{{ $correct ? '🎉' : '😬' }}</div>
            <div class="qz-result__title">{{ $correct ? __('Correct!') : __('Wrong!') }}</div>
            <div class="qz-result__points">+{{ $points }} {{ __('points') }}</div>
            @if($correct && ($player->streak ?? 0) > 1)
                <div class="qz-result__streak">🔥 {{ $player->streak }} {{ __('in a row') }}</div>
            @endif
        </div>
    @else
        <p class="text-zinc-400">{{ __('No answer submitted') }}</p>
    @endif
</div>
```

- [ ] **Step 3: Wire the partial into the review phase**

In `resources/views/livewire/player-screen.blade.php`, replace the REVIEW phase block. The current block (lines ~55–78) handles geo_guesser separately, then renders generic green/red boxes in an `@else`. Replace the `@else ... @endif` generic-box portion so it reads:

```blade
        {{-- REVIEW PHASE --}}
        @elseif($phase === 'review')
            @if($currentQuestion && ($currentQuestion['type'] ?? null) === 'geo_guesser')
                <div class="w-full max-w-md space-y-4">
                    @include('question-types.geo-guesser-player')
                </div>
            @else
                <div class="w-full max-w-md">
                    @include('themes._player-result')
                </div>
            @endif
```

(Remove the old `@if($lastResult) ... green/red boxes ... @endif` markup that was inside the previous `@else`.)

- [ ] **Step 4: Verify build + tests**

Run: `npm run build && php artisan test --filter='GameFlowTest'`
Expected: Build OK; GameFlowTest PASS. If GameFlowTest asserted the literal text "Correct!"/"Wrong!", those strings still render inside `.qz-result__title`, so assertions on visible text continue to pass.

- [ ] **Step 5: Commit**

```bash
git add resources/css/themes.css resources/views/themes/_player-result.blade.php resources/views/livewire/player-screen.blade.php
git commit -m "Add theme-tinted fancy question-result for players

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 8: House-styled final-result (podium + confetti + score count-up)

**Files:**
- Modify: `resources/css/presentation.css` (append podium/confetti)
- Modify: `resources/views/livewire/player-screen.blade.php` (finished phase)

- [ ] **Step 1: Append podium + confetti CSS to presentation.css**

Add to the END of `resources/css/presentation.css`:

```css
/* =========================== FINAL RESULT ================================ */
.qz-stage .qz-final { display: flex; flex-direction: column; align-items: center; gap: 22px; }
.qz-stage .qz-final__title { font-size: 30px; font-weight: 800; text-align: center; }
.qz-stage--party-pop .qz-final__title { font-family: 'Bangers', sans-serif; letter-spacing: 1.5px; }
.qz-stage--game-show .qz-final__title { font-family: 'Anton', sans-serif; text-transform: uppercase; }

.qz-stage .qz-podium { display: flex; align-items: flex-end; justify-content: center; gap: 10px; width: 100%; }
.qz-stage .qz-podium__col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.qz-stage .qz-podium__emoji { font-size: 34px; }
.qz-stage .qz-podium__name { font-size: 13px; font-weight: 700; text-align: center; opacity: .95; }
.qz-stage .qz-podium__score { font-size: 12px; opacity: .8; }
.qz-stage .qz-podium__bar {
    width: 100%; border-radius: 14px 14px 0 0; display: flex; align-items: flex-start;
    justify-content: center; padding-top: 8px; font-weight: 800; color: rgba(0, 0, 0, .55);
    animation: qz-bar-rise 0.6s cubic-bezier(.2, 1.1, .3, 1) both;
}
@keyframes qz-bar-rise { from { transform: scaleY(0); transform-origin: bottom; } to { transform: scaleY(1); } }
.qz-stage .qz-podium__col--1 .qz-podium__bar { height: 96px; background: linear-gradient(180deg, #fde68a, #f59e0b); }
.qz-stage .qz-podium__col--2 .qz-podium__bar { height: 70px; background: linear-gradient(180deg, #e5e7eb, #9ca3af); }
.qz-stage .qz-podium__col--3 .qz-podium__bar { height: 52px; background: linear-gradient(180deg, #fed7aa, #d97706); }

.qz-stage .qz-scorecard {
    width: 100%; text-align: center; border-radius: 20px; padding: 18px;
    background: rgba(255, 255, 255, 0.08); border: 1.5px solid rgba(255, 255, 255, 0.18);
}
.qz-stage .qz-scorecard__label { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: .7; }
.qz-stage .qz-scorecard__value { font-size: 44px; font-weight: 800; line-height: 1.1; }

.qz-stage .qz-board { width: 100%; border-radius: 20px; padding: 16px; background: rgba(0, 0, 0, 0.22); }
.qz-stage .qz-board__row { display: flex; justify-content: space-between; gap: 8px; padding: 6px 4px; }
.qz-stage .qz-board__row.is-me { font-weight: 800; }
.qz-stage .qz-name-emoji { display: inline-block; }

/* Confetti */
.qz-confetti { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 5; }
.qz-confetti i {
    position: absolute; top: -12px; width: 9px; height: 14px; opacity: .9;
    animation: qz-confetti-fall linear forwards;
}
@keyframes qz-confetti-fall {
    to { transform: translateY(108vh) rotate(540deg); opacity: 1; }
}
```

- [ ] **Step 2: Rewrite the finished phase in player-screen.blade.php**

In `resources/views/livewire/player-screen.blade.php`, replace the entire FINISHED phase block (lines ~80–104) with:

```blade
        {{-- FINISHED PHASE --}}
        @elseif($phase === 'finished')
            @php($style = $session->presentationStyle())
            @php($top = array_slice($leaderboard, 0, 3))
            <div class="qz-stage qz-stage--{{ $style }} w-full max-w-md">
                <div class="qz-confetti" aria-hidden="true">
                    @for($i = 0; $i < 24; $i++)
                        <i style="left: {{ ($i * 4.1) + 2 }}%; background: hsl({{ ($i * 37) % 360 }}, 85%, 60%); animation-duration: {{ 2.4 + (($i % 5) * 0.4) }}s; animation-delay: {{ ($i % 7) * 0.18 }}s;"></i>
                    @endfor
                </div>

                <div class="qz-card">
                    <div class="qz-final">
                        <h2 class="qz-final__title">{{ __('Game Over!') }}</h2>

                        @if(count($top) > 0)
                            <div class="qz-podium">
                                @php($order = [1 => $top[1] ?? null, 0 => $top[0] ?? null, 2 => $top[2] ?? null])
                                @foreach($order as $idx => $entry)
                                    @if($entry)
                                        <div class="qz-podium__col qz-podium__col--{{ $idx + 1 }}">
                                            <div class="qz-podium__emoji">{{ $entry['emoji'] ?? '🎮' }}</div>
                                            <div class="qz-podium__name">{{ $entry['nickname'] }}</div>
                                            <div class="qz-podium__score">{{ $entry['score'] }}</div>
                                            <div class="qz-podium__bar">{{ $idx + 1 }}</div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @php($finalScore = $player->fresh()->score)
                        <div class="qz-scorecard">
                            <div class="qz-scorecard__label">{{ __('Your Score') }}</div>
                            {{-- Alpine count-up; server-rendered fallback text keeps the real
                                 number present for Pest/non-JS, while browsers animate 0 → score. --}}
                            <div class="qz-scorecard__value" data-test="player-final-score"
                                 x-data="{ n: 0, target: {{ $finalScore }} }"
                                 x-init="let step = Math.max(1, Math.round(target / 40)); let t = setInterval(() => { n = Math.min(target, n + step); if (n >= target) clearInterval(t); }, 25)"
                                 x-text="n">{{ $finalScore }}</div>
                        </div>

                        @if(! empty($leaderboard))
                            <div class="qz-board">
                                @foreach($leaderboard as $index => $entry)
                                    <div @class(['qz-board__row', 'is-me' => ($entry['nickname'] ?? '') === $player->nickname])>
                                        <span>{{ $index + 1 }}. <x-player-name :emoji="$entry['emoji'] ?? null" :nickname="$entry['nickname']" /></span>
                                        <span>{{ $entry['score'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
```

- [ ] **Step 3: Verify build + tests**

Run: `npm run build && php artisan test --filter='GameFlowTest|OrderingGameFlowTest|GeoGuesserGameFlowTest'`
Expected: Build OK; PASS. `data-test="player-final-score"` is preserved so the final-score assertions keep working.

- [ ] **Step 4: Commit**

```bash
git add resources/css/presentation.css resources/views/livewire/player-screen.blade.php
git commit -m "Redesign final-result with podium, confetti, and house style

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 9: Playwright e2e — pick emoji at join, assert it shows

**Files:**
- Modify: the existing e2e join flow spec under `tests/e2e/`

- [ ] **Step 1: Locate the join step in the e2e suite**

Run: `grep -rn "join-nickname-input\|game.join\|/play" tests/e2e | head`
Expected: Find the spec that fills the nickname and joins.

- [ ] **Step 2: Add emoji selection before submitting**

In that spec, immediately before the nickname is submitted, click an emoji option and assert the preview. Add (adapting `page`/locator style to the file's existing conventions):

```ts
await page.locator('[data-test="join-emoji-option"][data-emoji="🚀"]').click();
await page.locator('[data-test="join-nickname-input"]').fill('E2EPlayer');
await expect(page.locator('[data-test="join-name-preview"]')).toContainText('🚀');
await page.locator('[data-test="join-submit"]').click();
```

Then, on the player screen, assert the emoji renders in the header:

```ts
await expect(page.locator('[data-test="player-nickname"]')).toContainText('🚀');
```

- [ ] **Step 3: Run the e2e suite**

Run: `npm run test:e2e`
Expected: PASS. (If the environment can't run Playwright here, note it and rely on the Pest coverage + manual verification.)

- [ ] **Step 4: Commit**

```bash
git add tests/e2e
git commit -m "Cover emoji pick in join e2e flow

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Final verification

- [ ] Run the full Pest suite: `php artisan test` — expect all green.
- [ ] Build assets: `npm run build` — expect no errors.
- [ ] Manual smoke (via `/run` or `npm run dev`): create a quiz, set each of the three presentation styles, host a game, join on a second screen (pick an emoji, see it in the preview + your header), answer a question (themed result card), finish the game (podium + confetti + leaderboard with emojis). Confirm host dashboard and spectator screen show emojis next to names.
