# Landing Page Design: Dark Immersive Hero

**Date:** 2026-03-18
**Status:** Approved
**Approach:** Dark Immersive Hero (Approach A)

## Context

Replace the default Laravel starter kit welcome page with a visually appealing, playful landing page for the quiz/trivia party game. The page serves a semi-public audience -- people invited to play or spectate.

Two user paths:
- **Admin:** Login button leading to `/login` (or "Dashboard" if authenticated)
- **Spectator:** Browse and join live games directly from the landing page

## Design

### Background

- Near-black base (`#0a0a0a`)
- Animated mesh gradient aurora overlay cycling through theme accent colors (indigo, cyan, pink, orange, emerald) from `config/themes.php`
- Slow, subtle `@keyframes` animation on `background-position` using radial gradients
- Full viewport height (`min-h-screen`)

### Top Bar

- Minimal, no navigation menu
- Single glass pill button in the top-right corner:
  - Unauthenticated: "Admin Login" linking to `/login`
  - Authenticated: "Dashboard" linking to `/dashboard`
- Styled with `backdrop-blur`, semi-transparent border, subtle glow on hover
- `rounded-full` shape

### Hero (Vertically Centered)

- **Logo:** Playful SVG mark (~120-150px) -- stylized question mark combined with a burst/bubble shape. Gradient fill using theme palette colors (purple to cyan to pink).
- **App name:** Large bold display text (48-64px) below the logo. Uses `config('app.name')`. Styled with CSS gradient text effect (`background-clip: text`).
- **Tagline:** Single line of muted text (`text-zinc-400`) beneath the app name. Configurable subtitle.
- **Entrance animation:** Fade-in + slide-up using CSS `@starting-style` (no JS needed).

### Live Games Section (Bottom)

- **Heading:** "Live Games" with a pulsing green dot indicator
- **Game cards:** Horizontal flex row with `gap-4`, each card is a glassmorphism panel:
  - `backdrop-blur`, semi-transparent background, subtle border
  - Quiz name (card title)
  - Player count with icon
  - Game status badge (e.g. "In Lobby", "Playing")
  - Category color accent (thin top border or background tint from theme)
  - "Watch" button linking to `/game/{code}/spectator`
- **Overflow:** Horizontally scrollable on desktop (if >3-4 games), vertical stack on mobile
- **Empty state:** Muted "No live games right now" message with subtle icon
- **Real-time:** Livewire component with polling or Echo for live updates when games start/end

### No Register Link

Registration is not exposed on the landing page. Only login/dashboard access for admins.

## Technical Decisions

- **Standalone Blade template** -- does not use `layouts/app.blade.php` (no sidebar/header)
- **Pure Tailwind CSS 4** -- no external CSS libraries
- **Theme colors** pulled from `config/themes.php` accent values
- **Dark mode only** for this page (no light variant)
- **Responsive:** Mobile-first with `lg:` breakpoints
- **Font:** Instrument Sans (already loaded via Bunny Fonts)
- **Livewire component** for the live games section (enables real-time updates)

## Visual Style

- **Mood:** Playful & energetic
- **Color palette:** Dark base with vibrant gradient accents from existing theme system
- **Cards:** Glassmorphism (frosted glass) with `backdrop-filter: blur()`, subtle borders
- **Border radius:** 12-16px for cards, full-round for buttons
- **Animations:** Subtle -- aurora background loop, entrance fade-in, hover micro-interactions (card lift/glow, button glow)
- **Typography:** Large bold headings, clean body text, gradient text for hero
