# Quiz2 — Party Trivia Game

A real-time party trivia game built with Laravel, Livewire, and Laravel Reverb for WebSocket communication.

## Tech Stack

- **Backend:** Laravel 12, Livewire 4, Laravel Fortify
- **Frontend:** Tailwind CSS 4, Flux UI, Vite
- **Real-time:** Laravel Reverb (WebSockets)
- **Database:** SQLite
- **Testing:** Pest

## Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm

## Setup

```bash
composer setup
```

This will install dependencies, generate an app key, run migrations, and build frontend assets.

## Running Locally

The quickest way to start all services at once:

```bash
composer dev
```

This starts the following in parallel:

| Service          | URL                     |
|------------------|-------------------------|
| Laravel server   | http://localhost:8000   |
| Vite dev server  | http://localhost:5173   |
| Queue worker     | (background)            |
| Log viewer (Pail)| (background)            |

You also need to start the **Reverb WebSocket server** separately:

```bash
php artisan reverb:start
```

This runs on `localhost:8080` by default.

### Running Services Individually

```bash
php artisan serve          # Laravel dev server (port 8000)
npm run dev                # Vite dev server (port 5173)
php artisan reverb:start   # WebSocket server (port 8080)
php artisan queue:listen   # Queue worker
```

## Testing

```bash
composer test
```

## Linting

```bash
composer lint        # Auto-fix
composer lint:check  # Check only
```
