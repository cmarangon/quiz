# Quiz

[![Tests](https://github.com/cmarangon/quiz/actions/workflows/tests.yml/badge.svg)](https://github.com/cmarangon/quiz/actions/workflows/tests.yml)

A real-time, Kahoot-ish party trivia game. One host, a bunch of players, a big screen, and six capital letters standing between your friends and glory.

Built on Laravel 12, Livewire 4, and Reverb, because spinning up a WebSocket stack shouldn't feel like a second job.

## What it does

- **Host** a game from your browser — pick a quiz, hit start, read the questions out loud like you're on television.
- **Players** join from their phones with a 6-character code (or a QR code, because 2008 called and wants its manual entry back).
- **Spectators** get a big-screen view — scores, questions, drama — no login required.
- **Question types:** multiple choice, true/false, ordering (sort the options into the right sequence), and geo-guesser (drop a pin on the map — closer guesses score higher). More welcome; PRs even more welcome.
- **Languages:** English and German (`/locale/en`, `/locale/de`).
- **Live everything:** Reverb pushes joins, answers, reveals, and category changes to every screen in real time.

## Stack

| Layer       | Tooling                                  |
|-------------|------------------------------------------|
| Backend     | Laravel 12, Livewire 4, Fortify          |
| Frontend    | Livewire + Flux UI, Tailwind CSS 4, Vite |
| Real-time   | Laravel Reverb (WebSockets)              |
| Database    | SQLite (swap if you feel fancy)          |
| Tests       | Pest 4                                   |
| Style       | Laravel Pint                             |

## Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm

## Setup

```bash
composer setup
```

Installs PHP + JS deps, copies `.env`, generates an app key, runs migrations, and builds the frontend. One command, zero yak shaving.

## Running it

Start the main show:

```bash
composer dev
```

That boots the Laravel server, the queue worker, the log tailer (Pail), and Vite — all color-coded, all in one terminal.

Reverb runs separately, because WebSockets like their own room:

```bash
php artisan reverb:start
```

| Service          | URL                     |
|------------------|-------------------------|
| App              | http://localhost:8000   |
| Vite HMR         | http://localhost:5173   |
| Reverb           | ws://localhost:8080     |

### One service at a time

```bash
php artisan serve          # app (8000)
npm run dev                # vite (5173)
php artisan reverb:start   # websockets (8080)
php artisan queue:listen   # jobs
```

## Routes worth knowing

| Route                          | Who it's for                       |
|--------------------------------|------------------------------------|
| `/`                            | Landing page                       |
| `/dashboard`                   | Authed host home                   |
| `/quizzes`, `/quizzes/create`  | Quiz management + inline builder   |
| `/game/{code}/host`            | Host control panel                 |
| `/game/{code}/spectator`       | Big-screen view (no auth)          |
| `/game/{code}/play`            | Player view on phones              |
| `/join/{code}`                 | Join entry point                   |

## Testing

```bash
composer test        # lints, then runs the Pest suite
composer lint        # Pint auto-fix
composer lint:check  # Pint in CI mode
```

## Ideas / TODO

A running wishlist — PRs welcome. Roughly ordered by impact-to-effort within each group.

### 🎉 Funnier / more party energy

- [ ] **Sound & music** — lobby music, a speeding-up countdown tick, a drumroll before the reveal, a triumphant leaderboard sting. Spectator screen only (it's the "TV"), so phones stay quiet.
- [ ] **Funnier nicknames** — auto-generate options ("Captain Wrong", "0 Points Joey") on top of the existing emoji avatars.
- [x] **Reaction emojis** — let players tap 😂😱🔥 during reveal and float them across the spectator screen (reuses the existing Reverb channel).
- [ ] **Snarky reveal commentary** on the spectator screen, driven by the answer distribution ("France is not in South America").
- [ ] **Double-or-nothing final question** — players wager points before seeing it.
- [ ] **Louder streak callouts** — surface the streaks we already track ("🔥 5 in a row!", personal "ON FIRE" state).

### 🧩 Better game / new content

- [ ] **More question types** — image/audio, "closest number" (Price-is-Right), type-the-answer (fuzzy match), buzzer/fastest-finger. The `QuestionTypeRegistry` + `QuestionTypeInterface` architecture is built for this.
- [x] **Answer-distribution bar on reveal** — show how many players picked each option (data already lives in `PlayerAnswer`).
- [ ] **Podium animation** — top-3 reveal (3rd → 2nd → 1st) on the spectator screen.
- [ ] **AI-generated quizzes** — "generate a 10-question quiz about ___" button in the QuizBuilder.

### 🪄 Easier to use

- [x] **Player reconnect/resume** — persist `player_id` to `localStorage` and auto-rejoin after a phone lock or refresh.
- [ ] **Host escape hatches** — skip question, extend timer, kick player.
- [ ] **Sample quiz library** — seed a few ready-to-play quizzes so the first run is instant fun.
- [ ] **Shareable join link** — copy button + native share for `/join/{code}`.
- [ ] **Post-game recap** — a shareable stat card ("7/10, fastest answer 1.2s, longest streak 4").

## License

MIT. Play nice.
