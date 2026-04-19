# Quiz2

A real-time, Kahoot-ish party trivia game. One host, a bunch of players, a big screen, and six capital letters standing between your friends and glory.

Built on Laravel 12, Livewire 4, and Reverb, because spinning up a WebSocket stack shouldn't feel like a second job.

## What it does

- **Host** a game from your browser — pick a quiz, hit start, read the questions out loud like you're on television.
- **Players** join from their phones with a 6-character code (or a QR code, because 2008 called and wants its manual entry back).
- **Spectators** get a big-screen view — scores, questions, drama — no login required.
- **Question types:** multiple choice and true/false. More welcome; PRs even more welcome.
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

## License

MIT. Play nice.
