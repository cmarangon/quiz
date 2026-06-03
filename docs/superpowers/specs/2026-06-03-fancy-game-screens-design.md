# Fancy game screens + player emojis — design

**Date:** 2026-06-03
**Status:** Approved (pending spec review)

## Goal

Make three live-game screens more visually engaging and let players express
themselves with a picked emoji prepended to their nickname:

1. **Join screen** — standalone "house style", category-theme independent.
2. **Question result** (player `review` phase) — adopts the active category theme.
3. **Final result** (player `finished` phase) — standalone house style.

Plus: a curated emoji a player must pick when joining, shown everywhere their
nickname appears.

## Decisions (from brainstorming)

- Join + final-result are **category-theme independent** and rendered in a
  per-quiz **house style**.
- Question-result **adheres to the active category theme**.
- Three house styles, **selectable per quiz**: `party-pop`, `game-show`,
  `bright-bouncy`. Default `party-pop`.
- Emoji is shown on: join screen + player's own screen, leaderboards, host
  dashboard, spectator screen.
- Emoji picker: curated grid of ~14 funny emojis, tap to select.
- Picking an emoji is **required** to join (Join button disabled until picked).

## Architecture

### House styles (presentation styles)

- Stored on the quiz in its existing `settings` JSON:
  `settings['presentation_style']` ∈ {`party-pop`, `game-show`,
  `bright-bouncy`}, default `party-pop`. **No migration** for this piece.
- Edited in the Quiz Builder alongside the existing `enable_time_bonus` /
  `enable_streaks` toggles: a 3-way selector, each option with a small visual
  swatch. Wired through `QuizBuilder::mount()` (load) and `QuizBuilder::save()`
  (persist), mirroring the existing boolean settings.
- Read at play time via `$session->quiz->settings['presentation_style']`
  (session references the quiz; settings are not snapshotted). A
  `GameSession::presentationStyle(): string` accessor returns the value with the
  `party-pop` default fallback, so views never repeat the `?? 'party-pop'` logic.
- CSS lives in a new `resources/css/presentation.css`, imported the same way as
  `themes.css`. Each style scoped under a wrapper class:
  `.qz-stage--party-pop`, `.qz-stage--game-show`, `.qz-stage--bright-bouncy`.
  Plain CSS (no Tailwind utilities) so specificity stays predictable — matches
  the `themes.css` convention.
- The join and final-result Blade templates wrap their content in
  `<div class="qz-stage qz-stage--{style}">`.

### Player emoji

- **Migration:** add nullable `string('emoji')` column to `players`.
- `Player::$fillable` gains `emoji`.
- Curated set defined once in `App\Support\PlayerEmojis::all(): array` (~14
  emojis). Single source of truth for the picker UI, join validation, and any
  future use.
- `JoinGame`:
  - New public `string $emoji = ''` property.
  - Picker grid in `join-game.blade.php`; clicking sets `$emoji` via
    `wire:click`, highlights the selection, and the chosen emoji is prepended to
    the nickname preview.
  - Join button disabled (`wire:model` / computed) until `$emoji` is non-empty.
  - `join()` validates `'emoji' => ['required', Rule::in(PlayerEmojis::all())]`
    and persists it on `Player::create([...])`.
- Display: a new Blade component `<x-player-name :player="$player" />` (or a
  small partial accepting `$emoji` + `$nickname`) renders "emoji + nickname"
  consistently. Used in:
  - player's own header (`player-screen.blade.php`),
  - final leaderboard (`player-screen.blade.php` finished phase),
  - host dashboard player list (`host-dashboard.blade.php`),
  - spectator screen (`spectator-screen.blade.php`).
  - Leaderboard data arrays must include `emoji` alongside `nickname`/`score`
    wherever they are built (PlayerScreen, SpectatorScreen, HostDashboard).

### Question-result (review phase) — theme-aware

- One shared partial `resources/views/themes/_player-result.blade.php`, wrapped
  by the active `.qz-theme--{key}` so theme CSS variables tint it.
- Renders: large animated reaction emoji (correct vs. wrong), points value that
  counts up, and a streak flourish when applicable.
- Theme tinting via CSS custom properties: add a small per-theme accent variable
  block in `themes.css` plus shared `.qz-theme .qz-result*` rules that consume
  it (DRY — no 7 separate layouts).
- `geo_guesser` keeps its existing map-based review (unchanged).
- The generic non-themed fallback in `player-screen.blade.php` review phase is
  replaced by the shared partial; the existing red/green boxes are removed.

### Final-result (finished phase)

Rebuilt in the chosen house style (`.qz-stage--{style}` wrapper):

- Animated **podium** for top 3, each showing the player's emoji + nickname.
- Confetti burst on entry (CSS animation, no new JS dependency).
- Player's own score in a large animated count-up card.
- Full leaderboard with emojis; the current player's row highlighted.

## Components & boundaries

| Unit | Responsibility | Depends on |
| --- | --- | --- |
| `PlayerEmojis` support class | Canonical emoji list | — |
| `players.emoji` column + `Player` | Persist per-player emoji | migration |
| `JoinGame` + `join-game.blade.php` | Pick emoji (required) + house-styled join | `PlayerEmojis`, quiz settings |
| `QuizBuilder` + builder blade | Choose presentation style | quiz `settings` |
| `presentation.css` | 3 house styles | — |
| `<x-player-name>` | Consistent emoji+nickname render | — |
| `themes/_player-result.blade.php` | Theme-tinted fancy result | `themes.css` vars |
| final-result section | House-styled podium + leaderboard | `presentation.css`, `<x-player-name>` |

## Data flow

1. Host creates quiz → picks presentation style in builder → saved to
   `quiz.settings['presentation_style']`.
2. Host starts game → `GameSession` created referencing the quiz.
3. Player opens join URL → `JoinGame` reads style via `$session->quiz`, renders
   house-styled join with emoji grid → picks emoji (required) + nickname →
   `join()` persists `emoji` on the `Player`.
4. During play → `review` phase renders the theme-tinted result partial.
5. Game finished → `finished` phase renders house-styled podium + leaderboard,
   emojis pulled from each player/leaderboard entry.

## Error handling / edge cases

- Missing/invalid `presentation_style` → helper falls back to `party-pop`.
- Legacy players with `emoji = null` (pre-migration) → `<x-player-name>` renders
  nickname alone, no broken layout. (New joins always have one.)
- Emoji not in curated set on submit → validation error, same UX as nickname
  errors.
- Unique-nickname logic (`resolveUniqueNickname`) is unaffected; emoji is not
  part of uniqueness.

## Testing

- **Pest (feature):**
  - Joining persists the chosen `emoji`; join fails when `emoji` is empty or not
    in the curated set.
  - Quiz builder saves `presentation_style`; default is `party-pop` when unset.
- **Playwright (e2e):** extend the existing join→play→finish flow to pick an
  emoji at join and assert it appears on the player screen / leaderboard.
- **Manual:** verify the three house styles and the themed result screen in the
  running app (no visual-regression tooling).

## Out of scope (YAGNI)

- Custom/arbitrary emoji input — curated set only.
- Per-category house styles — house style is per quiz, not per category.
- Animated avatars beyond a single static emoji.
- Changing the spectator/host *layouts* beyond adding the emoji to existing
  name displays.
