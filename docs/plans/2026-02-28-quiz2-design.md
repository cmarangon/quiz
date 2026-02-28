# Quiz2 — Party Trivia Game Design

## Overview

A party trivia game where a host controls the game flow, a spectator screen (TV) displays questions and scores for the room, and players answer on their phones. Built with Laravel, Livewire, and Laravel Reverb for real-time communication.

## Tech Stack

- **Backend:** Laravel (latest) with Livewire starter kit
- **Real-time:** Laravel Broadcasting with Reverb WebSocket server
- **Auth:** Laravel's built-in authentication via Livewire starter kit
- **Frontend:** Blade + Livewire components, Tailwind CSS

## Architecture: Hybrid Server-Authoritative

The server owns all game state (scores, timer, current question). The host sends explicit commands (start, next question, skip, pause) which the server validates before broadcasting to all clients. This gives server-side reliability with host-controlled pacing.

## Data Model

### User
Laravel's built-in user model. Email/password login.

### Quiz
- `id`, `user_id` (creator), `title`, `description`, `visibility` (public/private)
- `settings` (JSON): `enable_time_bonus` (bool, default true), `enable_streaks` (bool, default true)
- Belongs to User. Has many Categories.

### Category
- `id`, `quiz_id`, `name`, `slug`, `theme` (string key), `description`, `order` (int)
- Belongs to Quiz. Has many Questions.
- The `theme` field maps to a config entry defining visual properties for the spectator screen.

### Question
- `id`, `category_id`, `type` (string, maps to registered question type class)
- `body` (question text), `options` (JSON), `correct_answer` (JSON)
- `points` (int, default 10), `time_limit_seconds` (int, default 30), `order` (int)
- Belongs to Category.

### GameSession
- `id`, `quiz_id`, `host_user_id`, `join_code` (6-char alphanumeric, unique)
- `status` enum: waiting, playing, reviewing, finished
- `current_question_index`, `current_category_id`, `settings` (JSON)
- Belongs to Quiz and User (host). Has many Players.

### Player
- `id`, `game_session_id`, `user_id` (nullable, for logged-in players), `nickname`
- `score` (int), `streak` (int)
- Belongs to GameSession.

### PlayerAnswer
- `id`, `player_id`, `game_session_id`, `question_id`
- `answer` (JSON), `is_correct` (bool), `time_taken_ms` (int), `points_earned` (int)

## Three Screen Roles

### Host
The game master. Controls game flow (start, next question, skip, pause). Sees a dashboard with player management, answer counts, and admin controls. Requires authentication and quiz ownership. Route: `/game/{code}/host`.

### Spectator
The big TV screen everyone watches. Display only, no interaction needed. Shows:
- **Lobby:** Join code in large text + QR code encoding the join URL + live player list
- **Playing:** Category theme, question text, answer options, timer countdown, "X/Y answered" progress
- **Reviewing:** Correct answer reveal, leaderboard with animations
- **Finished:** Final podium and results

No auth required. Route: `/game/{code}/spectator`.

### Player
The phone screen. Minimal UI optimized for fast tapping:
- **Lobby:** Waiting state after joining
- **Playing:** Answer buttons only (color-coded, no question text — it's on the TV)
- **Reviewing:** Personal result (correct/wrong, points earned, streak)
- **Finished:** Personal stats (rank, score, accuracy, best streak)

No auth required (join code + nickname). Optionally linked to a User account for persistent stats. Route: `/game/{code}/play`. QR/join landing: `/join/{code}`.

## Game Flow

### Lobby Phase (status: waiting)
1. Host creates a game from a Quiz, server generates a 6-char join code
2. Spectator screen shows join code + QR code prominently
3. Players scan QR or visit URL, enter nickname
4. Server broadcasts `PlayerJoined` to host and spectator
5. Host clicks "Start Game"

### Playing Phase (status: playing)
1. Server sets `current_category_id`, broadcasts `CategoryChanged` with theme data
2. Server broadcasts `QuestionStarted` with question body, options, time limit (NOT the correct answer)
3. Players see answer buttons on phones and tap their answer
4. Server records answer, broadcasts `PlayerAnswered` (count only) to host and spectator
5. Timer expires OR all players answered, server moves to review

### Review Phase (status: reviewing)
1. Server broadcasts `QuestionEnded` with correct answer, per-player results, updated scores
2. Spectator shows correct answer, breakdown, leaderboard
3. Host clicks "Next Question" — if same category, back to Playing step 2; if new category, back to Playing step 1

### Finished Phase (status: finished)
1. Server broadcasts `GameFinished` with final leaderboard
2. Spectator shows podium/final results
3. Player phones show personal stats
4. Results saved to database

## Broadcasting Events & Channels

### Channels
- `game.{sessionId}` — Public channel for spectator + player events
- `game.{sessionId}.host` — Private channel for host-only data
- `game.{sessionId}.player.{playerId}` — Private channel for personal results

### Events
| Event | Direction | Payload |
|---|---|---|
| `PlayerJoined` | Server → Host, Spectator | player name, total count |
| `CategoryChanged` | Server → All | category name, theme key |
| `QuestionStarted` | Server → All | question body, options, time limit (spectator gets full data, players get option labels/colors only) |
| `PlayerAnswered` | Server → Host, Spectator | answer count |
| `QuestionEnded` | Server → All | correct answer, scores (spectator gets full reveal, players get personal result) |
| `GameFinished` | Server → All | final leaderboard |

## Pluggable Question Types (Strategy Pattern)

### QuestionType Interface
- `renderSpectatorComponent(): string` — Livewire component name for spectator screen
- `renderPlayerComponent(): string` — Livewire component name for player phone
- `validateAnswer(mixed $answer, Question $question): bool` — Check correctness
- `calculatePoints(Question $question, int $timeTakenMs): int` — Score with time bonus
- `validateOptions(array $options): bool` — Validate question structure on create/edit

### Registration
Config-driven via `config/quiz.php`:
```php
'question_types' => [
    'multiple_choice' => MultipleChoiceType::class,
    'true_false' => TrueFalseType::class,
],
```

A `QuestionTypeRegistry` service (bound in a service provider) resolves type classes by key.

### Shipped Types
- **MultipleChoice** — 4 options, pick one
- **TrueFalse** — Two buttons

### Adding New Types
Create the class implementing the interface, create Livewire components for spectator + player, add entry to config. No existing code changes.

## Scoring System

### Base Scoring
Each question has a configurable `points` value (default 10).

### Time Bonus (optional per quiz)
Formula: `base_points * (time_remaining / time_limit)`. Answering instantly gets full points, answering at the buzzer gets near zero. Disabled: every correct answer gets flat `base_points`.

### Streaks (optional per quiz)
Consecutive correct answers build a streak:
- 1-2 correct: 1x multiplier
- 3-4 correct: 1.5x multiplier
- 5+ correct: 2x multiplier
- Wrong answer resets streak to 0

### Final Score Per Question
`round(base_points * time_factor * streak_multiplier)`

Quiz-level `settings` control `enable_time_bonus` and `enable_streaks` (both default true).

## Category Theming

Each Category has a `theme` string mapping to `config/themes.php`:
```php
'science' => [
    'gradient' => 'from-indigo-900 to-purple-900',
    'accent' => 'cyan-400',
    'icon' => 'beaker',
    'background_pattern' => 'molecules',
],
```

- Spectator applies full theme (gradient, patterns, animations)
- Players get simplified theme (accent color swap)
- Category transitions include an interstitial on spectator showing category name + icon
- Adding a new theme: add a config entry, no code changes

## Routes

| Route | Purpose | Auth |
|---|---|---|
| `/dashboard` | User's quizzes, game history, stats | Required |
| `/quizzes/{quiz}/edit` | Quiz builder (categories, questions) | Required (owner) |
| `/game/create/{quiz}` | Start a new game session | Required (owner) |
| `/game/{code}/host` | Host control panel | Required (owner) |
| `/game/{code}/spectator` | Spectator TV display | None |
| `/game/{code}/play` | Player phone screen | None |
| `/join/{code}` | QR code landing, nickname entry | None |

## Key Livewire Components

- `QuizBuilder` — Create/edit quizzes, categories, questions
- `GameLobby` — Host lobby controls + start button
- `SpectatorScreen` — TV display, listens to broadcast events
- `PlayerScreen` — Phone UI, listens to relevant events
- `HostDashboard` — Game controls (next, skip, pause)
- `Leaderboard` — Reused on spectator and final results

## Error Handling & Edge Cases

### Player Disconnects
- Reverb detects presence leave, server marks player as disconnected
- Player stays in the game; reconnecting (same browser session) rejoins at current question
- Missed questions score 0, streak resets
- Host sees "disconnected" indicator

### Host Disconnects
- Game pauses automatically, spectator shows "Waiting for host..."
- Host reconnects → game resumes
- 5-minute timeout → game ends, partial results saved

### Late Joiners
- Players can only join during lobby phase (status: waiting)
- Once game starts, join code stops accepting new players
- Spectator hides QR code once playing

### Timer
- Server is authority on timing
- Answers arriving after server timer expires are rejected
- 500ms grace period for network latency

### Duplicate Nicknames
- Append a number if taken (e.g. "Alex" → "Alex 2")

## Testing Strategy

### Unit Tests
- Question type classes: validation, scoring, time bonus, streak multipliers
- Scoring service: all combinations of time bonus/streaks on/off
- Game session state machine: valid/invalid transitions

### Feature Tests
- Quiz CRUD: create, add categories/questions, edit, delete
- Game lifecycle: create session → join → start → answer → review → finish
- Broadcasting: correct events dispatched at each phase
- Auth: host ownership, public spectator/player routes
- Player answers: scoring, late rejection, disconnect handling

### Browser Tests (Dusk)
- Full game flow end-to-end
- Spectator screen renders through all phases
- QR code links to correct join URL
