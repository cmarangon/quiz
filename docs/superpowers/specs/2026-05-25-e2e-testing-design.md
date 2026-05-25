# End-to-End Browser Testing with Playwright

**Status:** Approved design
**Date:** 2026-05-25
**Owner:** Claudio Marangon

## Goal

Provide an automated browser-driven test that exercises the full multi-screen happy path of the quiz app — host, spectator, and multiple players playing a complete game — so manual verification across several windows and phones is no longer needed before pushing changes.

## Scope

**In scope (v1):**

- One end-to-end test that runs a complete game: lobby → all questions → final scoreboard
- Multi-context test (one host, one spectator, two players) exercising real WebSocket sync via Reverb
- Deterministic test data via a dedicated seeder and isolated SQLite database
- Local execution via `npm run test:e2e`
- Nightly CI run via a separate GitHub Actions workflow with manual dispatch
- Failure artifacts: screenshots, video, trace

**Out of scope (explicitly deferred):**

- Multi-browser matrix (Firefox, WebKit) — Chromium only for v1
- Mobile viewport / device emulation tests
- Visual regression / screenshot diffing
- Test sharding beyond Playwright defaults
- Edge-case scenarios: late joiners, disconnects, time-bonus mode, streaks mode, multi-category quizzes
- Wiring e2e into the per-PR `tests.yml` workflow (nightly-only for v1)

## Tool choice

**Playwright (standalone Node).** Selected over Pest 4 Browser Plugin and Laravel Dusk because:

- Native multi-context support is exactly what this app needs (one test orchestrating 4 isolated browser sessions in parallel against shared backend state)
- Trace viewer is the best-in-class debugging tool for flaky real-time tests
- Mature ecosystem with many examples for WebSocket-driven UIs

Trade-off accepted: tests live in TypeScript outside the Pest suite, separate `npm run test:e2e` command rather than `composer test`.

## Architecture

### Directory layout

```
quiz2/
├── tests/
│   ├── e2e/                                ← NEW
│   │   ├── playwright.config.ts
│   │   ├── fixtures/
│   │   │   └── game.ts                     ← reusable test helpers
│   │   ├── specs/
│   │   │   └── full-game.spec.ts           ← the happy-path test
│   │   └── test-results/                   ← gitignored
│   ├── Feature/                            (existing Pest)
│   └── Unit/                               (existing Pest)
├── database/
│   ├── e2e.sqlite                          ← gitignored, recreated each run
│   └── seeders/
│       └── E2eGameSeeder.php               ← NEW: deterministic user + quiz
├── .env.e2e                                ← NEW: overrides for e2e environment
├── .github/workflows/
│   └── e2e.yml                             ← NEW: nightly + manual dispatch
└── package.json                            ← add Playwright dev dep + scripts
```

### Process model

Playwright's `webServer` configuration boots the full stack before any test runs and tears it down at the end. Boot order:

1. `php artisan migrate:fresh --seed --seeder=E2eGameSeeder --database=e2e --force`
2. `php artisan serve --port=8001 --env=e2e`
3. `php artisan reverb:start --port=8081 --env=e2e`
4. `php artisan queue:work --queue=default --env=e2e` (broadcast events depend on the queue worker)
5. `npm run build` once at startup — built assets are stable and faster than Vite HMR for tests

Ports are deliberately offset from the dev defaults (8000/8080) so `composer dev` and `npm run test:e2e` can run side by side.

### Database isolation

- Separate file: `database/e2e.sqlite`
- `.env.e2e` sets `DB_DATABASE` to the absolute path of that file
- `migrate:fresh --database=e2e` wipes and recreates on every run
- Gitignored

### Browser layout per test

One test, four browser contexts (each context = one isolated browser session, like an incognito window on a different device):

| Context   | Purpose                                              | Auth                       |
|-----------|------------------------------------------------------|----------------------------|
| host      | Drives the game from `/game/{code}/host`             | Logged in as seeded user   |
| spectator | Big-screen view at `/game/{code}/spectator`          | None                       |
| player 1  | Plays as "Alice" at `/game/{code}/play`              | None (nickname only)       |
| player 2  | Plays as "Bob" at `/game/{code}/play`                | None (nickname only)       |

All four contexts hit the same Laravel + Reverb instance, so every WebSocket fan-out and queue-driven broadcast is exercised end-to-end.

## Test scenario: `full-game.spec.ts`

1. **Host setup**
   - Open `/login`, sign in as the seeded host user
   - From the dashboard, start a game from the seeded "E2E Test Quiz"
   - Land on `/game/{code}/host` and capture `{code}` from the URL

2. **Spectator joins**
   - Fresh context opens `/game/{code}/spectator`
   - Assert empty-lobby state visible

3. **Players join (2 in parallel)**
   - Each opens `/join/{code}`, enters nickname ("Alice", "Bob"), submits
   - Lands on `/game/{code}/play`
   - **Assert (WebSocket fan-out check):** host and spectator screens both show both nicknames within the default 10s locator timeout

4. **Host starts the game**
   - Click "Start"
   - Assert all 4 screens transition to the first question

5. **Loop for each question in the seeded quiz**
   - Assert question body visible on every screen
   - Alice picks the correct answer; Bob picks an incorrect answer (deterministic score delta for later assertion)
   - Assert host screen shows "2/2 answered"
   - Host clicks "Reveal" / "Finish question"
   - Assert correct answer highlighted on spectator and player screens; scores update everywhere
   - Host clicks "Next" (skipped on the last question)

6. **End of game**
   - Assert final scoreboard visible on host and spectator
   - Assert `Alice.score > Bob.score` (sanity check on scoring path)
   - Assert players see their final position

### Flakiness defense

- No `sleep()` or arbitrary waits anywhere in tests
- Every WebSocket-driven assertion uses Playwright's auto-waiting locators (`expect(locator).toBeVisible()`, `toHaveText()`) with the default 10s timeout
- `retries: 0` locally (flakes are loud and get fixed), `retries: 1` in CI (one retry catches genuine transients without masking real bugs)
- Trace recorded on first retry — failures in CI come with a full scrubbable timeline

### Selectors

Prefer Playwright's recommended hierarchy: role + accessible name first, visible text second, `data-testid` last.

`data-testid` attributes will be added surgically to Blade templates where role/text is ambiguous (e.g., individual scoreboard rows, the player count indicator). The implementation plan will list each one explicitly; this is a small additive change, not a refactor.

## Test data: `E2eGameSeeder.php`

Deterministic and minimal:

- **User:** email `e2e-host@test.local`, password `password` (both read from env vars in the seeder so they can be overridden in CI)
- **Quiz:** title "E2E Test Quiz", visibility public, time bonus off, streaks off
- **One category** with **3 multiple-choice questions**, known correct answers, 30s time limit each
- Re-runnable: `migrate:fresh --seeder=E2eGameSeeder` wipes and recreates

Three questions keeps the full-game test under ~30s while still exercising the per-question reveal/advance loop multiple times.

## Test helpers: `tests/e2e/fixtures/game.ts`

Small typed helpers so the spec reads as a narrative, not selector wrangling:

- `loginAsHost(page): Promise<void>`
- `startGameFromDashboard(page): Promise<string>` — returns the game code
- `joinAsPlayer(context, code, nickname): Promise<Page>`
- `openSpectator(context, code): Promise<Page>`
- `answerQuestion(playerPage, label): Promise<void>`
- `revealAndAdvance(hostPage, options: { isLast: boolean }): Promise<void>`

The spec itself should be ~40 lines of high-level narrative.

## Environment configuration

`.env.e2e` (minimal overrides, everything else inherited from `.env.example`):

```
APP_ENV=e2e
APP_URL=http://localhost:8001
DB_DATABASE=/absolute/path/to/database/e2e.sqlite
REVERB_HOST=localhost
REVERB_PORT=8081
QUEUE_CONNECTION=database
SESSION_DRIVER=database
BROADCAST_CONNECTION=reverb
E2E_HOST_EMAIL=e2e-host@test.local
E2E_HOST_PASSWORD=password
```

The absolute path will be resolved at config-load time in `playwright.config.ts` using `process.cwd()`.

## package.json scripts

```
"test:e2e":        "playwright test"
"test:e2e:ui":     "playwright test --ui"
"test:e2e:headed": "playwright test --headed"
```

New dev dependencies:

- `@playwright/test`
- `dotenv` (to load `.env.e2e` in `playwright.config.ts`)

## CI: `.github/workflows/e2e.yml`

- Triggers: `schedule` (cron `0 6 * * *` UTC, ~2 AM ET) **and** `workflow_dispatch` (manual)
- Does **not** run on PRs (kept out of the merge-blocking path)
- Reuses the PHP and Node setup steps from `tests.yml`, including the Flux credentials step (`composer config http-basic.composer.fluxui.dev ...`) that uses the same `FLUX_USERNAME` / `FLUX_LICENSE_KEY` secrets
- Adds: `npx playwright install --with-deps chromium`
- Uses GitHub Actions cache for `~/.cache/ms-playwright` to avoid re-downloading browsers
- Runs: `npm run test:e2e`
- On failure: uploads `playwright-report/` and `tests/e2e/test-results/` as workflow artifacts (retained 14 days)

## Failure artifacts

Configured in `playwright.config.ts`:

- `screenshot: 'only-on-failure'`
- `video: 'retain-on-failure'`
- `trace: 'on-first-retry'`

All output goes to `tests/e2e/test-results/` (gitignored).

## .gitignore additions

```
/database/e2e.sqlite
/tests/e2e/test-results/
/tests/e2e/playwright-report/
```

## Acceptance criteria

The implementation is complete when:

1. `npm run test:e2e` runs the full-game test locally and passes reliably 5 runs in a row
2. `npm run test:e2e:headed` shows all 4 browser windows playing the game
3. Inducing a known regression (e.g., temporarily commenting out a broadcast event) causes the test to fail with a useful trace
4. The nightly GitHub Actions workflow runs successfully on a manual `workflow_dispatch` trigger
5. Running `composer dev` and `npm run test:e2e` simultaneously does not conflict (different ports, different DB)

## Risks and mitigations

| Risk                                            | Mitigation                                                                 |
|-------------------------------------------------|----------------------------------------------------------------------------|
| Reverb startup race (tests run before WS ready) | `webServer.url` health-check on a port Reverb only opens once listening    |
| Queue worker not running broadcasts in time     | Default 10s locator timeout absorbs normal latency; CI gets 1 retry        |
| Selectors break when UI changes                 | Keep `data-testid` additions minimal and documented in the plan            |
| Test DB collides with dev DB                    | Separate `database/e2e.sqlite` + separate port + separate `.env.e2e`       |
| CI browser install slow / flaky                 | Cache `~/.cache/ms-playwright` keyed on Playwright version                 |

## Out of scope — explicit follow-up candidates

These are NOT being built now. Listed so they don't sneak into the v1 implementation:

- Late-joiner test (player joins mid-game)
- Disconnect/reconnect test
- Time-bonus quiz mode coverage
- Streaks quiz mode coverage
- Multi-category quiz coverage
- Mobile viewport test
- True/false question type coverage
- Firefox/WebKit matrix
- PR-blocking e2e in `tests.yml`
