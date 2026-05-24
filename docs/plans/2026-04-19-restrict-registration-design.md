# Restrict Registration & Add User Management — Design

**Issue:** [#7 — Restrict registration](https://github.com/cmarangon/quiz/issues/7)
**Date:** 2026-04-19

## Problem

Anyone can register on the app today via `/register` and immediately gain full access to host quizzes and create games. We want registration to be closed: the only way for a new user to exist is for an existing (authenticated) user to create them from a backend user-management screen.

## Decisions

- **No roles.** Any authenticated user can manage other users. The app stays single-tier; the security boundary is simply "logged in or not."
- **Registration is removed, not hidden.** Disable Fortify's registration feature so `/register` returns 404. Strip links to it from the login page.
- **Seed an initial user** via `DatabaseSeeder` from env vars so fresh installs still have a way in.
- **Minimal management UI.** List users, create (name + email + password), delete. No edit, no password reset, no invite emails — those can come later if needed.
- **UI lives under `/settings/users`** using the existing settings layout and Volt single-file component convention.

## Architecture

### 1. Disable public registration

**`config/fortify.php`** — remove `Features::registration()` from the `features` array:

```php
'features' => [
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]),
],
```

Dropping this entry un-registers Fortify's `GET/POST /register` route, so the backend refuses registration attempts with a 404.

**No view edits needed for the login page.** `login.blade.php:52` already wraps the "Sign up" link in `@if (Route::has('register'))`, so disabling the Fortify feature auto-hides the link. A repo-wide grep for `route('register')` confirms that's the only reference.

**`resources/views/pages/auth/register.blade.php`** — delete this file. Fortify no longer routes to it, and keeping it around is just dead code.

### 2. Seed initial user

**`database/seeders/DatabaseSeeder.php`** creates one user from env vars, but only if the `users` table is empty — so re-running `db:seed` on an installed app never clobbers real credentials:

```php
public function run(): void
{
    if (User::count() > 0) {
        return;
    }

    User::create([
        'name' => env('INITIAL_USER_NAME', 'Admin'),
        'email' => env('INITIAL_USER_EMAIL', 'admin@example.com'),
        'password' => env('INITIAL_USER_PASSWORD', 'password'),
        'email_verified_at' => now(),
    ]);
}
```

The `User` model already casts `password` to `hashed`, so passing a plain-text value is correct — it gets hashed on save.

**`.env.example`** adds documented placeholders:

```
INITIAL_USER_NAME="Admin"
INITIAL_USER_EMAIL="admin@example.com"
INITIAL_USER_PASSWORD="password"
```

The install flow (`composer setup`, which already runs `migrate --seed`) continues to work unchanged.

### 3. User management UI

**Route** — `routes/settings.php`, inside the existing `auth`+`verified` middleware group:

```php
Route::livewire('settings/users', 'pages::settings.users')->name('users.index');
```

**Component** — new Volt single-file component at `resources/views/pages/settings/⚡users.blade.php`, following the pattern of `⚡profile.blade.php`. Uses `WithPagination`.

**State:**

- Create form: `$name`, `$email`, `$password`, `$password_confirmation`
- Delete modal: `$confirmingUserDeletion` (bool), `$userIdToDelete` (int|null)

**Methods:**

- `createUser()` — delegates to Fortify's `CreatesNewUsers` contract, which internally runs `ProfileValidationRules::profileRules()` + `PasswordValidationRules::passwordRules()`:

  ```php
  app(\Laravel\Fortify\Contracts\CreatesNewUsers::class)->create([
      'name' => $this->name,
      'email' => $this->email,
      'password' => $this->password,
      'password_confirmation' => $this->password_confirmation,
  ]);
  ```

  This reuses the exact same `CreateNewUser` action Fortify used for public registration, so validation rules live in one place. Livewire catches the `ValidationException` thrown by the action and surfaces errors against the form fields automatically — no explicit `$this->validate(...)` call in the component. On success, resets form fields and dispatches a `user-created` event for the flash message.

- `confirmDelete($id)` — sets `$userIdToDelete = $id` and `$confirmingUserDeletion = true`.

- `deleteUser()` — `abort_if($this->userIdToDelete === Auth::id(), 403)`, then `User::findOrFail($this->userIdToDelete)->delete()`. Resets modal state.

**View:**

- `@include('partials.settings-heading')` for consistency with other settings pages.
- `<x-pages::settings.layout heading="Users" subheading="Manage who can access the app">`.
- Create form: `flux:input` for name/email/password/password_confirmation + `flux:button` submit. Mirrors the markup style of `⚡profile.blade.php`.
- User table: name, email, created_at, delete button. "You" badge next to the current user's row; delete button hidden for that row (belt-and-suspenders with the 403 in the backend method).
- Delete confirmation modal: mirrors `⚡delete-user-modal.blade.php`. Copy warns: "Deleting this user also removes all of their quizzes and hosted games."
- Pagination via Livewire's default pagination view.

**Sidebar nav** — `resources/views/pages/settings/layout.blade.php`, add a new `flux:navlist.item` after Appearance:

```blade
<flux:navlist.item :href="route('users.index')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
```

**i18n** — new strings ("Users", "Manage who can access the app", modal copy, etc.) go into both `lang/en.json` and `lang/de.json` following existing conventions.

### 4. Cascade behavior (no changes needed)

The existing schema already handles user deletion correctly:

- `quizzes.user_id` → `cascadeOnDelete` (also cascades to categories, questions)
- `game_sessions.host_user_id` → `cascadeOnDelete` (also cascades to players, answers)
- `players.user_id` → `nullOnDelete` (history preserved, anonymized when a user who was only a player gets deleted)

No migration needed. The modal copy above surfaces the quizzes/games cascade to the user doing the deletion.

## Testing

Pest feature tests (`tests/Feature/Settings/UserManagementTest.php` and `tests/Feature/Auth/RegistrationTest.php` updates):

- `GET /register` returns 404 (registration disabled).
- `POST /register` returns 404.
- `GET /settings/users` as guest → redirects to login.
- `GET /settings/users` as authenticated user → renders, lists existing users.
- Create user with valid data → user exists in DB, form clears.
- Create user with duplicate email → validation error, no user created.
- Delete another user → user gone from DB.
- Delete self → 403, user still exists.
- Seeder: running `php artisan db:seed` twice → one user (idempotent).

## Out of scope

- Roles or admin flag — explicitly declined.
- Edit user (name/email) — not in minimum viable scope.
- Password reset by admin — users can still use the public "forgot password" flow.
- Invite-by-email — deferred; admin shares credentials out-of-band.
- Search / filtering in the user list — pagination is enough for now.

## Files touched

**Modified:**
- `config/fortify.php` — remove `Features::registration()`
- `resources/views/pages/settings/layout.blade.php` — add Users navlist item
- `routes/settings.php` — add `users.index` route
- `database/seeders/DatabaseSeeder.php` — seed initial user
- `.env.example` — document `INITIAL_USER_*` vars
- `lang/en.json`, `lang/de.json` — new strings
- `tests/Feature/Auth/RegistrationTest.php` — flip existing tests to assert registration is disabled (404)

**Added:**
- `resources/views/pages/settings/⚡users.blade.php`
- `tests/Feature/Settings/UserManagementTest.php`

**Removed:**
- `resources/views/pages/auth/register.blade.php`
