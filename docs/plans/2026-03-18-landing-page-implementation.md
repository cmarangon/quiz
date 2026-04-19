# Landing Page Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the default Laravel welcome page with a dark, immersive landing page featuring an animated aurora background, playful SVG logo, and live game cards for spectators.

**Architecture:** A new Livewire component (`WelcomePage`) replaces the static `welcome.blade.php` view. The component queries active game sessions and renders them as glassmorphism cards. The route changes from `Route::view` to a Livewire component route. Custom CSS keyframes for the aurora animation go in `app.css`.

**Tech Stack:** Laravel 12, Livewire 4, Tailwind CSS 4, Pest (testing)

---

### Task 1: Create the Livewire WelcomePage Component (Backend)

**Files:**
- Create: `app/Livewire/WelcomePage.php`
- Test: `tests/Feature/WelcomePageTest.php`

**Step 1: Write the failing tests**

Create `tests/Feature/WelcomePageTest.php`:

```php
<?php

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use App\Models\User;

test('welcome page returns successful response', function () {
    $response = $this->get(route('home'));
    $response->assertOk();
});

test('welcome page shows admin login link for guests', function () {
    $this->get(route('home'))
        ->assertSee('Admin Login')
        ->assertDontSee('Dashboard');
});

test('welcome page shows dashboard link for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertSee('Dashboard')
        ->assertDontSee('Admin Login');
});

test('welcome page shows active game sessions', function () {
    $quiz = Quiz::factory()->create(['title' => 'Fun Trivia Night']);
    $session = GameSession::factory()->for($quiz)->create(['status' => 'waiting']);
    Player::factory()->for($session, 'gameSession')->count(3)->create();

    $this->get(route('home'))
        ->assertSee('Fun Trivia Night')
        ->assertSee('3 players');
});

test('welcome page does not show finished game sessions', function () {
    $quiz = Quiz::factory()->create(['title' => 'Old Game']);
    GameSession::factory()->for($quiz)->create(['status' => 'finished']);

    $this->get(route('home'))
        ->assertDontSee('Old Game');
});

test('welcome page shows empty state when no active games', function () {
    $this->get(route('home'))
        ->assertSee('No live games right now');
});

test('welcome page shows watch link for active games', function () {
    $session = GameSession::factory()->create(['status' => 'waiting', 'join_code' => 'ABC123']);

    $this->get(route('home'))
        ->assertSee(route('game.spectator', 'ABC123'));
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/WelcomePageTest.php`
Expected: Most tests FAIL (the first one may pass since the existing welcome view still works)

**Step 3: Create the Livewire component**

Create `app/Livewire/WelcomePage.php`:

```php
<?php

namespace App\Livewire;

use App\Models\GameSession;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.welcome')]
class WelcomePage extends Component
{
    public function render()
    {
        $activeGames = GameSession::with(['quiz', 'players'])
            ->whereIn('status', ['waiting', 'playing', 'reviewing'])
            ->latest()
            ->get();

        return view('livewire.welcome-page', [
            'activeGames' => $activeGames,
        ]);
    }
}
```

**Step 4: Update the route**

Modify `routes/web.php` line 13 -- change:
```php
Route::view('/', 'welcome')->name('home');
```
to:
```php
Route::get('/', \App\Livewire\WelcomePage::class)->name('home');
```

Add the import at the top with the other Livewire imports:
```php
use App\Livewire\WelcomePage;
```

And use the short form:
```php
Route::get('/', WelcomePage::class)->name('home');
```

**Step 5: Create minimal layout and view stubs (so tests pass)**

Create `resources/views/layouts/welcome.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Quiz') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#0a0a0a] text-white min-h-screen antialiased">
    {{ $slot }}
</body>
</html>
```

Create `resources/views/livewire/welcome-page.blade.php`:

```blade
<div>
    {{-- Top bar --}}
    <header class="fixed top-0 right-0 p-6 z-50">
        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
        @else
            <a href="{{ route('login') }}">Admin Login</a>
        @endauth
    </header>

    {{-- Hero placeholder --}}
    <main class="flex flex-col items-center justify-center min-h-screen">
        <h1>{{ config('app.name', 'Quiz') }}</h1>
    </main>

    {{-- Live games --}}
    <section class="px-6 pb-12">
        @if($activeGames->isEmpty())
            <p>No live games right now</p>
        @else
            @foreach($activeGames as $game)
                <div>
                    <span>{{ $game->quiz->title }}</span>
                    <span>{{ $game->players->count() }} players</span>
                    <a href="{{ route('game.spectator', $game->join_code) }}">Watch</a>
                </div>
            @endforeach
        @endif
    </section>
</div>
```

**Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/WelcomePageTest.php`
Expected: ALL PASS

**Step 7: Commit**

```bash
git add app/Livewire/WelcomePage.php resources/views/livewire/welcome-page.blade.php resources/views/layouts/welcome.blade.php routes/web.php tests/Feature/WelcomePageTest.php
git commit -m "feat: add WelcomePage Livewire component with live game cards"
```

---

### Task 2: Add Aurora Background Animation CSS

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/views/layouts/welcome.blade.php`

**Step 1: Add aurora keyframes and utility classes to `resources/css/app.css`**

Append to the end of `resources/css/app.css`:

```css
/* Landing page aurora background */
@keyframes aurora {
    0%, 100% {
        background-position: 0% 50%;
    }
    25% {
        background-position: 50% 0%;
    }
    50% {
        background-position: 100% 50%;
    }
    75% {
        background-position: 50% 100%;
    }
}

.aurora-bg {
    background:
        radial-gradient(ellipse at 20% 50%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(6, 182, 212, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 40% 80%, rgba(244, 63, 94, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 70% 60%, rgba(251, 146, 60, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 30%, rgba(52, 211, 153, 0.1) 0%, transparent 50%);
    background-size: 200% 200%;
    animation: aurora 20s ease-in-out infinite;
}
```

**Step 2: Apply aurora class to the layout body**

In `resources/views/layouts/welcome.blade.php`, update the `<body>` tag:

```html
<body class="bg-[#0a0a0a] text-white min-h-screen antialiased aurora-bg">
```

**Step 3: Verify visually**

Run: `composer dev` (or `php artisan serve` + `npm run dev`)
Visit `http://localhost:8000` and confirm the subtle animated gradient aurora is visible against the dark background.

**Step 4: Commit**

```bash
git add resources/css/app.css resources/views/layouts/welcome.blade.php
git commit -m "feat: add animated aurora mesh gradient background"
```

---

### Task 3: Design the Playful SVG Logo

**Files:**
- Create: `resources/views/components/landing-logo.blade.php`
- Modify: `resources/views/livewire/welcome-page.blade.php`

**Step 1: Create the SVG logo component**

Create `resources/views/components/landing-logo.blade.php`:

```blade
@props(['class' => 'w-32 h-32'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="logo-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#8B5CF6" />
            <stop offset="50%" style="stop-color:#06B6D4" />
            <stop offset="100%" style="stop-color:#F43F5E" />
        </linearGradient>
    </defs>
    {{-- Burst/starburst shape behind --}}
    <g opacity="0.3">
        <path d="M100 10 L115 75 L170 30 L125 85 L190 100 L125 115 L170 170 L115 125 L100 190 L85 125 L30 170 L75 115 L10 100 L75 85 L30 30 L85 75 Z" fill="url(#logo-gradient)" />
    </g>
    {{-- Speech bubble body --}}
    <rect x="45" y="40" width="110" height="90" rx="20" fill="url(#logo-gradient)" />
    {{-- Speech bubble tail --}}
    <polygon points="70,130 90,130 75,155" fill="url(#logo-gradient)" />
    {{-- Question mark --}}
    <text x="100" y="115" text-anchor="middle" fill="white" font-size="72" font-weight="bold" font-family="ui-sans-serif, system-ui, sans-serif">?</text>
</svg>
```

**Step 2: Update the hero section in welcome-page.blade.php**

Replace the hero `<main>` placeholder with:

```blade
<main class="flex flex-col items-center justify-center min-h-screen px-6">
    {{-- Logo --}}
    <div class="opacity-0 translate-y-6 animate-[fadeInUp_0.8s_ease-out_forwards]">
        <x-landing-logo class="w-32 h-32 lg:w-40 lg:h-40 drop-shadow-[0_0_30px_rgba(139,92,246,0.3)]" />
    </div>

    {{-- App name --}}
    <h1 class="mt-6 text-5xl lg:text-7xl font-bold bg-gradient-to-r from-purple-400 via-cyan-400 to-pink-400 bg-clip-text text-transparent opacity-0 translate-y-6 animate-[fadeInUp_0.8s_ease-out_0.2s_forwards]">
        {{ config('app.name', 'Quiz') }}
    </h1>

    {{-- Tagline --}}
    <p class="mt-4 text-lg text-zinc-400 opacity-0 translate-y-6 animate-[fadeInUp_0.8s_ease-out_0.4s_forwards]">
        Real-time party trivia
    </p>
</main>
```

**Step 3: Add the fadeInUp keyframe to `resources/css/app.css`**

```css
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(1.5rem);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

**Step 4: Verify visually**

Visit `http://localhost:8000` and confirm the logo, gradient text, and staggered fade-in animations render correctly.

**Step 5: Commit**

```bash
git add resources/views/components/landing-logo.blade.php resources/views/livewire/welcome-page.blade.php resources/css/app.css
git commit -m "feat: add playful SVG logo and hero section with animations"
```

---

### Task 4: Style the Admin Login Glass Pill Button

**Files:**
- Modify: `resources/views/livewire/welcome-page.blade.php`

**Step 1: Write a test for the login link**

The tests from Task 1 already cover the auth/guest link behavior. No new tests needed.

**Step 2: Style the top bar header**

Replace the `<header>` in `welcome-page.blade.php` with:

```blade
<header class="fixed top-0 right-0 p-6 z-50">
    @auth
        <a href="{{ route('dashboard') }}"
           class="px-5 py-2 rounded-full text-sm font-medium text-white/80 backdrop-blur-md bg-white/5 border border-white/10 hover:bg-white/10 hover:border-white/20 hover:text-white hover:shadow-[0_0_15px_rgba(139,92,246,0.2)] transition-all duration-300">
            Dashboard
        </a>
    @else
        <a href="{{ route('login') }}"
           class="px-5 py-2 rounded-full text-sm font-medium text-white/80 backdrop-blur-md bg-white/5 border border-white/10 hover:bg-white/10 hover:border-white/20 hover:text-white hover:shadow-[0_0_15px_rgba(139,92,246,0.2)] transition-all duration-300">
            Admin Login
        </a>
    @endauth
</header>
```

**Step 3: Run existing tests to confirm nothing broke**

Run: `php artisan test tests/Feature/WelcomePageTest.php`
Expected: ALL PASS

**Step 4: Verify visually**

Confirm the glass pill button renders in the top-right with blur, border, and hover glow.

**Step 5: Commit**

```bash
git add resources/views/livewire/welcome-page.blade.php
git commit -m "feat: style admin login as glassmorphism pill button"
```

---

### Task 5: Style the Live Games Section with Glassmorphism Cards

**Files:**
- Modify: `resources/views/livewire/welcome-page.blade.php`

**Step 1: Replace the live games section**

Replace the `<section>` in `welcome-page.blade.php` with:

```blade
{{-- Live Games Section --}}
<section class="fixed bottom-0 inset-x-0 p-6 lg:p-8 z-40">
    {{-- Section header --}}
    <div class="flex items-center gap-2 mb-4">
        @if($activeGames->isNotEmpty())
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span>
        @endif
        <h2 class="text-lg font-semibold text-white/90">Live Games</h2>
    </div>

    @if($activeGames->isEmpty())
        <div class="flex items-center justify-center py-8 rounded-2xl backdrop-blur-md bg-white/5 border border-white/10">
            <p class="text-zinc-500 text-sm">No live games right now</p>
        </div>
    @else
        <div class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory scrollbar-hide">
            @foreach($activeGames as $game)
                @php
                    $categoryType = $game->quiz->categories->first()?->type ?? 'default';
                    $theme = config("themes.{$categoryType}", config('themes.default'));
                @endphp
                <div class="flex-shrink-0 w-72 snap-start rounded-2xl backdrop-blur-md bg-white/5 border border-white/10 p-5 hover:bg-white/10 hover:border-white/20 hover:shadow-[0_0_20px_rgba(139,92,246,0.15)] hover:-translate-y-1 transition-all duration-300"
                     style="border-top: 2px solid;">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-white truncate mr-2">{{ $game->quiz->title }}</h3>
                        <span class="flex-shrink-0 px-2 py-0.5 text-xs rounded-full
                            {{ $game->status === 'waiting' ? 'bg-emerald-500/20 text-emerald-400' : '' }}
                            {{ $game->status === 'playing' ? 'bg-amber-500/20 text-amber-400' : '' }}
                            {{ $game->status === 'reviewing' ? 'bg-blue-500/20 text-blue-400' : '' }}">
                            {{ $game->status === 'waiting' ? 'In Lobby' : ucfirst($game->status) }}
                        </span>
                    </div>

                    <div class="flex items-center text-sm text-zinc-400 mb-4">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ $game->players->count() }} players
                    </div>

                    <a href="{{ route('game.spectator', $game->join_code) }}"
                       class="block w-full text-center px-4 py-2 rounded-xl text-sm font-medium bg-white/10 text-white/80 border border-white/10 hover:bg-white/15 hover:text-white transition-all duration-200">
                        Watch
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</section>
```

**Step 2: Add scrollbar-hide utility to `resources/css/app.css`**

```css
/* Hide scrollbar for horizontal game cards */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
```

**Step 3: Adjust the hero `min-h-screen` to account for the bottom section**

In the `<main>` hero section, change `min-h-screen` to `min-h-[70vh]` or use padding-bottom so the hero content doesn't hide behind the fixed bottom section:

```blade
<main class="flex flex-col items-center justify-center min-h-screen pb-48 px-6">
```

**Step 4: Run tests**

Run: `php artisan test tests/Feature/WelcomePageTest.php`
Expected: ALL PASS

**Step 5: Verify visually**

Visit `http://localhost:8000`. Create a game session in the database to see the cards render. Confirm:
- Glassmorphism cards with blur and border
- Status badges with correct colors
- Hover lift effect
- Horizontal scroll on overflow
- Empty state when no games
- Pulsing green dot

**Step 6: Commit**

```bash
git add resources/views/livewire/welcome-page.blade.php resources/css/app.css
git commit -m "feat: style live games section with glassmorphism cards"
```

---

### Task 6: Add Livewire Polling for Real-Time Updates

**Files:**
- Modify: `resources/views/livewire/welcome-page.blade.php`

**Step 1: Add polling directive**

At the top of `welcome-page.blade.php`, change:

```blade
<div>
```

to:

```blade
<div wire:poll.5s>
```

This makes Livewire re-render the component every 5 seconds, picking up new/finished game sessions automatically.

**Step 2: Run tests**

Run: `php artisan test tests/Feature/WelcomePageTest.php`
Expected: ALL PASS

**Step 3: Commit**

```bash
git add resources/views/livewire/welcome-page.blade.php
git commit -m "feat: add 5-second Livewire polling for live game updates"
```

---

### Task 7: Clean Up Old Welcome Page and Update Tests

**Files:**
- Delete: `resources/views/welcome.blade.php`
- Modify: `tests/Feature/ExampleTest.php`

**Step 1: Delete the old welcome.blade.php**

Run: `rm resources/views/welcome.blade.php`

The old file is the giant Laravel starter kit template. It's fully replaced by the Livewire component now.

**Step 2: Update ExampleTest.php**

The existing `tests/Feature/ExampleTest.php` tests the home route. It should still pass since the route name didn't change, but verify:

Run: `php artisan test tests/Feature/ExampleTest.php`
Expected: PASS

**Step 3: Run the full test suite**

Run: `php artisan test`
Expected: ALL PASS. If any test references the old `welcome` view, fix the reference.

**Step 4: Commit**

```bash
git rm resources/views/welcome.blade.php
git add tests/Feature/ExampleTest.php
git commit -m "chore: remove old Laravel starter kit welcome page"
```

---

### Task 8: Final Visual Polish and Responsive Testing

**Files:**
- Possibly tweak: `resources/views/livewire/welcome-page.blade.php`
- Possibly tweak: `resources/css/app.css`

**Step 1: Test on mobile viewport**

Open browser devtools, test at 375px width. Verify:
- Logo scales down appropriately
- Text is readable and not overflowing
- Game cards stack or scroll properly
- Admin login button is accessible

**Step 2: Test with game data**

Use `php artisan tinker` to create test game sessions:

```php
$quiz = \App\Models\Quiz::factory()->create(['title' => 'Movie Trivia']);
$session = \App\Models\GameSession::factory()->for($quiz)->create(['status' => 'waiting']);
\App\Models\Player::factory()->for($session, 'gameSession')->count(4)->create();

$quiz2 = \App\Models\Quiz::factory()->create(['title' => 'Science Bowl']);
$session2 = \App\Models\GameSession::factory()->for($quiz2)->create(['status' => 'playing']);
\App\Models\Player::factory()->for($session2, 'gameSession')->count(7)->create();
```

**Step 3: Fix any visual issues found**

Adjust spacing, font sizes, or card widths as needed.

**Step 4: Run full test suite one final time**

Run: `php artisan test`
Expected: ALL PASS

**Step 5: Commit any final tweaks**

```bash
git add -A
git commit -m "fix: landing page responsive polish"
```
