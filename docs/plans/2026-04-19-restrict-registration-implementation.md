# Restrict Registration & Add User Management — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close public registration and add a `/settings/users` management UI so any authenticated user can list, create, or delete users.

**Architecture:** Remove `Features::registration()` from Fortify config (auto-hides the existing `@if (Route::has('register'))` sign-up link); delete the register view and its Fortify binding; make `DatabaseSeeder` idempotent and env-driven; add a Livewire Volt page under the existing settings layout that delegates to Fortify's already-bound `CreatesNewUsers` action for creation and guards against self-delete.

**Tech Stack:** Laravel 12, Livewire 4 (Volt single-file components), Fortify, Flux UI, Pest 4, SQLite.

**Spec:** `docs/plans/2026-04-19-restrict-registration-design.md`

---

## File Structure

**Modified:**
- `config/fortify.php` — remove `Features::registration()`
- `app/Providers/FortifyServiceProvider.php` — remove `Fortify::registerView(...)` line
- `database/seeders/DatabaseSeeder.php` — idempotent env-driven seeding
- `.env.example` — document `INITIAL_USER_*` vars
- `routes/settings.php` — add `users.index` route
- `resources/views/pages/settings/layout.blade.php` — add Users navlist item
- `tests/Feature/Auth/RegistrationTest.php` — flip to "registration disabled" assertions

**Added:**
- `resources/views/pages/settings/⚡users.blade.php` — Volt component for user management
- `tests/Feature/Settings/UserManagementTest.php` — Pest feature tests
- `tests/Feature/DatabaseSeederTest.php` — seeder idempotency test

**Removed:**
- `resources/views/pages/auth/register.blade.php`

---

## Task 1: Disable public registration

**Files:**
- Modify: `tests/Feature/Auth/RegistrationTest.php`
- Modify: `config/fortify.php`
- Modify: `app/Providers/FortifyServiceProvider.php`
- Remove: `resources/views/pages/auth/register.blade.php`

### - [ ] Step 1: Flip the registration test to assert the routes are disabled

Replace the entire contents of `tests/Feature/Auth/RegistrationTest.php` with:

```php
<?php

test('registration route is disabled', function () {
    expect(Route::has('register'))->toBeFalse();
    expect(Route::has('register.store'))->toBeFalse();
});

test('GET /register returns 404', function () {
    $this->get('/register')->assertNotFound();
});

test('POST /register returns 404', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});
```

### - [ ] Step 2: Run the test — expect it to FAIL

Run: `./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php`

Expected: FAIL. `Route::has('register')` is still `true` because `Features::registration()` is enabled.

### - [ ] Step 3: Remove `Features::registration()` from Fortify config

Edit `config/fortify.php`. Find the `'features'` array (around line 146) and remove the `Features::registration(),` line. The final array:

```php
'features' => [
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
        // 'window' => 0
    ]),
],
```

### - [ ] Step 4: Remove the register view binding

Edit `app/Providers/FortifyServiceProvider.php`. In `configureViews()`, remove this line:

```php
Fortify::registerView(fn () => view('pages::auth.register'));
```

The resulting method:

```php
private function configureViews(): void
{
    Fortify::loginView(fn () => view('pages::auth.login'));
    Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
    Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
    Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
    Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
    Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
}
```

### - [ ] Step 5: Delete the register view file

Run: `rm resources/views/pages/auth/register.blade.php`

### - [ ] Step 6: Run the test — expect PASS

Run: `./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php`

Expected: PASS. The register route no longer exists.

### - [ ] Step 7: Run the full test suite to catch regressions

Run: `composer test`

Expected: PASS. (If any other test references `route('register')` or the register page, flip it to assert disabled behavior using the same pattern.)

### - [ ] Step 8: Manually verify the login page no longer shows a "Sign up" link

Start `composer dev` and open `http://localhost:8000/login`. The "Don't have an account? Sign up" block should be gone because `Route::has('register')` now returns false.

### - [ ] Step 9: Commit

```bash
git add config/fortify.php app/Providers/FortifyServiceProvider.php tests/Feature/Auth/RegistrationTest.php
git rm resources/views/pages/auth/register.blade.php
git commit -m "feat: disable public registration

Removes Features::registration() from Fortify config, deletes the
register view and its binding, and flips the registration test to
assert the routes no longer exist. The login page's sign-up link is
already gated by Route::has('register') so it auto-hides.

Refs #7"
```

---

## Task 2: Make `DatabaseSeeder` idempotent and env-driven

**Files:**
- Create: `tests/Feature/DatabaseSeederTest.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `.env.example`

### - [ ] Step 1: Write the failing seeder test

Create `tests/Feature/DatabaseSeederTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeder creates one initial user when users table is empty', function () {
    config()->set('app.initial_user.name', 'Seed Admin');
    config()->set('app.initial_user.email', 'seed@example.com');
    config()->set('app.initial_user.password', 'secret123');

    expect(User::count())->toBe(0);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1);
    $user = User::first();
    expect($user->name)->toBe('Seed Admin');
    expect($user->email)->toBe('seed@example.com');
    expect($user->email_verified_at)->not->toBeNull();
});

test('seeder is idempotent when users already exist', function () {
    User::factory()->create();

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1);
});
```

The test uses `config()` rather than `env()` directly because Laravel's `env()` helper only reads from `.env` at boot; we route the seeder through the config cache so tests can override values cleanly. Task step 3 wires that up.

Note: this test deliberately runs only `DatabaseSeeder` (not its nested quiz seeders), because those depend on a user existing and would make the test brittle. Step 3's implementation guards the nested calls similarly.

### - [ ] Step 2: Run the test — expect FAIL

Run: `./vendor/bin/pest tests/Feature/DatabaseSeederTest.php`

Expected: FAIL. Current seeder hard-codes a specific user and always calls the nested quiz seeders, so the first test gets the wrong name/email and the second test creates a second user.

### - [ ] Step 3: Rewrite `DatabaseSeeder` to be idempotent and env-driven

Replace the contents of `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() > 0) {
            return;
        }

        User::create([
            'name' => config('app.initial_user.name', env('INITIAL_USER_NAME', 'Admin')),
            'email' => config('app.initial_user.email', env('INITIAL_USER_EMAIL', 'admin@example.com')),
            'password' => config('app.initial_user.password', env('INITIAL_USER_PASSWORD', 'password')),
            'email_verified_at' => now(),
        ]);

        $this->call([
            NormalQuizSeeder::class,
            TimeBonusQuizSeeder::class,
            StreaksQuizSeeder::class,
            TimeBonusAndStreaksQuizSeeder::class,
        ]);
    }
}
```

Key changes:
- Early return if any users already exist → idempotent on `db:seed` re-runs.
- Reads credentials from `config()` first, falling back to `env()`. Tests set `config()` values; production reads `env()` via `.env`.
- The nested quiz seeders run only on a fresh database (inside the `count() === 0` branch) so they don't re-seed duplicate quizzes on every `db:seed`. This is also a bug fix — the old seeder would create duplicate quiz data on every seed run.

### - [ ] Step 4: Run the seeder test — expect PASS

Run: `./vendor/bin/pest tests/Feature/DatabaseSeederTest.php`

Expected: PASS.

### - [ ] Step 5: Document env vars in `.env.example`

Append to `.env.example`:

```
INITIAL_USER_NAME="Admin"
INITIAL_USER_EMAIL="admin@example.com"
INITIAL_USER_PASSWORD="password"
```

Place these after `VITE_APP_NAME="${APP_NAME}"` (the last existing line).

### - [ ] Step 6: Run full test suite

Run: `composer test`

Expected: PASS.

### - [ ] Step 7: Commit

```bash
git add database/seeders/DatabaseSeeder.php tests/Feature/DatabaseSeederTest.php .env.example
git commit -m "feat: make DatabaseSeeder idempotent and env-driven

Seeder now creates a single initial user from INITIAL_USER_* env
vars, and no-ops when users already exist. Nested quiz seeders only
run on a fresh install, fixing a duplicate-data bug on repeated
db:seed runs.

Refs #7"
```

---

## Task 3: Add `/settings/users` route with an authenticated access test

**Files:**
- Create: `tests/Feature/Settings/UserManagementTest.php`
- Create: `resources/views/pages/settings/⚡users.blade.php` (stub)
- Modify: `routes/settings.php`
- Modify: `resources/views/pages/settings/layout.blade.php`

### - [ ] Step 1: Write the failing access tests

Create `tests/Feature/Settings/UserManagementTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the user management page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('users.index'))->assertOk();
});
```

### - [ ] Step 2: Run the test — expect FAIL

Run: `./vendor/bin/pest tests/Feature/Settings/UserManagementTest.php`

Expected: FAIL. `route('users.index')` raises "Route [users.index] not defined."

### - [ ] Step 3: Add the route

Edit `routes/settings.php`. Inside the existing `Route::middleware(['auth', 'verified'])->group(...)` block (below the appearance route), add:

```php
Route::livewire('settings/users', 'pages::settings.users')->name('users.index');
```

### - [ ] Step 4: Create a stub Volt component

Create `resources/views/pages/settings/⚡users.blade.php` with this exact content. Volt single-file components (see `⚡profile.blade.php` for reference) use an anonymous class declaration followed directly by Blade markup — no `render()` method. The file gets fully replaced in Task 4, 5, and 6; don't worry about the empty body.

```blade
<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Users')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Users')" :subheading="__('Manage who can access the app')">
        <div class="my-6 w-full space-y-6">
            {{-- Populated in later tasks --}}
        </div>
    </x-pages::settings.layout>
</section>
```

### - [ ] Step 5: Add the sidebar nav link

Edit `resources/views/pages/settings/layout.blade.php`. Add a new `flux:navlist.item` after the Appearance item (line 9). The updated navlist block:

```blade
<flux:navlist aria-label="{{ __('Settings') }}">
    <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
    <flux:navlist.item :href="route('user-password.edit')" wire:navigate>{{ __('Password') }}</flux:navlist.item>
    @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
        <flux:navlist.item :href="route('two-factor.show')" wire:navigate>{{ __('Two-factor auth') }}</flux:navlist.item>
    @endif
    <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
    <flux:navlist.item :href="route('users.index')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
</flux:navlist>
```

### - [ ] Step 6: Run the test — expect PASS

Run: `./vendor/bin/pest tests/Feature/Settings/UserManagementTest.php`

Expected: PASS. Route exists, component renders.

### - [ ] Step 7: Commit

```bash
git add routes/settings.php resources/views/pages/settings/⚡users.blade.php resources/views/pages/settings/layout.blade.php tests/Feature/Settings/UserManagementTest.php
git commit -m "feat: add /settings/users route and page scaffold

Adds the user management settings page behind auth+verified middleware,
with a sidebar link in the settings layout. Page is a stub in this
commit — listing, creation, and deletion land in following commits.

Refs #7"
```

---

## Task 4: List users on the management page

**Files:**
- Modify: `tests/Feature/Settings/UserManagementTest.php`
- Modify: `resources/views/pages/settings/⚡users.blade.php`

### - [ ] Step 1: Write the failing list test

Add to `tests/Feature/Settings/UserManagementTest.php`:

```php
test('the page lists all users', function () {
    $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

    $this->actingAs($alice);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('Alice')
        ->assertSee('alice@example.com')
        ->assertSee('Bob')
        ->assertSee('bob@example.com');
});
```

### - [ ] Step 2: Run the test — expect FAIL

Run: `./vendor/bin/pest tests/Feature/Settings/UserManagementTest.php --filter="lists all users"`

Expected: FAIL. The stub renders nothing, so neither name is present.

### - [ ] Step 3: Add user listing to the Volt component

Replace `resources/views/pages/settings/⚡users.blade.php` with:

```blade
<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'users' => User::latest()->paginate(15),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Users')" :subheading="__('Manage who can access the app')">
        <div class="my-6 w-full space-y-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left">
                            <th class="py-2 pr-4">{{ __('Name') }}</th>
                            <th class="py-2 pr-4">{{ __('Email') }}</th>
                            <th class="py-2 pr-4">{{ __('Created') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="user-{{ $user->id }}">
                                <td class="py-2 pr-4">
                                    {{ $user->name }}
                                    @if ($user->id === Auth::id())
                                        <flux:badge size="sm" class="ms-2">{{ __('You') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $user->email }}</td>
                                <td class="py-2 pr-4">{{ $user->created_at->format('Y-m-d') }}</td>
                                <td class="py-2 text-right"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                {{ $users->links() }}
            </div>
        </div>
    </x-pages::settings.layout>
</section>
```

The Volt component exposes data to the view via a `with()` method (see Livewire docs). Using `paginate(15)` alongside `WithPagination` gives us paging for free. The trailing empty `<td>` is where the Delete button lands in Task 6.

### - [ ] Step 4: Run the test — expect PASS

Run: `./vendor/bin/pest tests/Feature/Settings/UserManagementTest.php --filter="lists all users"`

Expected: PASS.

### - [ ] Step 5: Run the full suite

Run: `composer test`

Expected: PASS.

### - [ ] Step 6: Commit

```bash
git add resources/views/pages/settings/⚡users.blade.php tests/Feature/Settings/UserManagementTest.php
git commit -m "feat: list users on /settings/users page

Renders a paginated table of all users (name, email, created date)
with a \"You\" badge on the current user's row. Delete column is
empty in this commit — populated in the next one.

Refs #7"
```

---

## Task 5: Create new users via the management page

**Files:**
- Modify: `tests/Feature/Settings/UserManagementTest.php`
- Modify: `resources/views/pages/settings/⚡users.blade.php`

### - [ ] Step 1: Write the failing create tests

Add to `tests/Feature/Settings/UserManagementTest.php`:

```php
use Livewire\Livewire;

test('authenticated user can create a new user', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    Livewire::test('pages::settings.users')
        ->set('name', 'New Person')
        ->set('email', 'new@example.com')
        ->set('password', 'password1234')
        ->set('password_confirmation', 'password1234')
        ->call('createUser')
        ->assertHasNoErrors();

    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
});

test('create form clears after success', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::settings.users')
        ->set('name', 'New Person')
        ->set('email', 'clear@example.com')
        ->set('password', 'password1234')
        ->set('password_confirmation', 'password1234')
        ->call('createUser');

    $component
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');
});

test('duplicate email is rejected', function () {
    $this->actingAs(User::factory()->create());
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test('pages::settings.users')
        ->set('name', 'Duplicate')
        ->set('email', 'taken@example.com')
        ->set('password', 'password1234')
        ->set('password_confirmation', 'password1234')
        ->call('createUser')
        ->assertHasErrors(['email']);

    expect(User::where('email', 'taken@example.com')->count())->toBe(1);
});

test('mismatched passwords are rejected', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.users')
        ->set('name', 'Bob')
        ->set('email', 'bob-new@example.com')
        ->set('password', 'password1234')
        ->set('password_confirmation', 'different')
        ->call('createUser')
        ->assertHasErrors(['password']);

    expect(User::where('email', 'bob-new@example.com')->exists())->toBeFalse();
});
```

### - [ ] Step 2: Run the tests — expect FAIL

Run: `./vendor/bin/pest tests/Feature/Settings/UserManagementTest.php`

Expected: FAIL. `createUser` doesn't exist; the public form fields don't exist.

### - [ ] Step 3: Add create form state and action to the Volt component

Replace the contents of `resources/views/pages/settings/⚡users.blade.php` with:

```blade
<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function createUser(CreatesNewUsers $creator): void
    {
        $creator->create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset(['name', 'email', 'password', 'password_confirmation']);

        $this->dispatch('user-created');
    }

    public function with(): array
    {
        return [
            'users' => User::latest()->paginate(15),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Users')" :subheading="__('Manage who can access the app')">
        <div class="my-6 w-full space-y-8">
            <form wire:submit="createUser" class="space-y-4">
                <flux:heading size="sm">{{ __('Add a user') }}</flux:heading>

                <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="off" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />
                <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" />
                <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" data-test="create-user-button">
                        {{ __('Create user') }}
                    </flux:button>

                    <x-action-message class="me-3" on="user-created">
                        {{ __('User created.') }}
                    </x-action-message>
                </div>
            </form>

            <flux:separator variant="subtle" />

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left">
                            <th class="py-2 pr-4">{{ __('Name') }}</th>
                            <th class="py-2 pr-4">{{ __('Email') }}</th>
                            <th class="py-2 pr-4">{{ __('Created') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="user-{{ $user->id }}">
                                <td class="py-2 pr-4">
                                    {{ $user->name }}
                                    @if ($user->id === Auth::id())
                                        <flux:badge size="sm" class="ms-2">{{ __('You') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $user->email }}</td>
                                <td class="py-2 pr-4">{{ $user->created_at->format('Y-m-d') }}</td>
                                <td class="py-2 text-right">
                                    {{-- Delete button lands in Task 6 --}}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                {{ $users->links() }}
            </div>
        </div>
    </x-pages::settings.layout>
</section>
```

Key points:
- `createUser` takes `CreatesNewUsers` via method injection — Livewire resolves it from the container, which is bound to `App\Actions\Fortify\CreateNewUser` by `FortifyServiceProvider::configureActions()`. That action runs the `ProfileValidationRules` + `PasswordValidationRules` validation internally; a `ValidationException` bubbles out and Livewire surfaces errors against the matching field names.
- `$this->reset([...])` clears form fields on success.
- `dispatch('user-created')` drives the `x-action-message` flash confirmation, matching the pattern in `⚡profile.blade.php`.

### - [ ] Step 4: Run the tests — expect PASS

Run: `./vendor/bin/pest tests/Feature/Settings/UserManagementTest.php`

Expected: PASS for all four new tests plus the three earlier tests (guests-redirected, auth-can-view, lists-all-users).

### - [ ] Step 5: Manual smoke test

Start `composer dev`, log in with the seeded initial user, navigate to `/settings/users`, and create a new user. Confirm:
- The table shows the new user.
- The form clears.
- The "User created." message flashes.
- Attempting to create a duplicate email shows a field error.

### - [ ] Step 6: Commit

```bash
git add resources/views/pages/settings/⚡users.blade.php tests/Feature/Settings/UserManagementTest.php
git commit -m "feat: add user creation form to /settings/users

Delegates creation to Fortify's CreatesNewUsers action so validation
rules stay shared with the old registration flow. Successful submission
clears the form and flashes a confirmation; duplicate-email and
password-mismatch errors are surfaced against the right fields.

Refs #7"
```

---

## Task 6: Delete users (with self-delete guard)

**Files:**
- Modify: `tests/Feature/Settings/UserManagementTest.php`
- Modify: `resources/views/pages/settings/⚡users.blade.php`

### - [ ] Step 1: Write the failing delete tests

Add to `tests/Feature/Settings/UserManagementTest.php`:

```php
test('authenticated user can delete another user', function () {
    $actor = User::factory()->create();
    $victim = User::factory()->create(['email' => 'victim@example.com']);

    $this->actingAs($actor);

    Livewire::test('pages::settings.users')
        ->call('confirmDelete', $victim->id)
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect(User::find($victim->id))->toBeNull();
});

test('user cannot delete themselves', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    Livewire::test('pages::settings.users')
        ->call('confirmDelete', $actor->id)
        ->call('deleteUser')
        ->assertForbidden();

    expect(User::find($actor->id))->not->toBeNull();
});

test('deleting a user cascades to their quizzes', function () {
    $actor = User::factory()->create();
    $victim = User::factory()->create();
    $quiz = $victim->quizzes()->create(['title' => 'Doomed Quiz']);

    $this->actingAs($actor);

    Livewire::test('pages::settings.users')
        ->call('confirmDelete', $victim->id)
        ->call('deleteUser');

    expect(\App\Models\Quiz::find($quiz->id))->toBeNull();
});
```

### - [ ] Step 2: Run the tests — expect FAIL

Run: `./vendor/bin/pest tests/Feature/Settings/UserManagementTest.php --filter="delete"`

Expected: FAIL. `confirmDelete` and `deleteUser` methods don't exist.

### - [ ] Step 3: Add delete state, methods, button, and modal to the Volt component

Replace the contents of `resources/views/pages/settings/⚡users.blade.php` with:

```blade
<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public ?int $userIdToDelete = null;

    public function createUser(CreatesNewUsers $creator): void
    {
        $creator->create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset(['name', 'email', 'password', 'password_confirmation']);

        $this->dispatch('user-created');
    }

    public function confirmDelete(int $userId): void
    {
        $this->userIdToDelete = $userId;
    }

    public function deleteUser(): void
    {
        abort_if($this->userIdToDelete === null, 400);
        abort_if($this->userIdToDelete === Auth::id(), 403);

        User::findOrFail($this->userIdToDelete)->delete();

        $this->userIdToDelete = null;

        $this->dispatch('user-deleted');
    }

    public function with(): array
    {
        return [
            'users' => User::latest()->paginate(15),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Users')" :subheading="__('Manage who can access the app')">
        <div class="my-6 w-full space-y-8">
            <form wire:submit="createUser" class="space-y-4">
                <flux:heading size="sm">{{ __('Add a user') }}</flux:heading>

                <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="off" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />
                <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" />
                <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" data-test="create-user-button">
                        {{ __('Create user') }}
                    </flux:button>

                    <x-action-message class="me-3" on="user-created">
                        {{ __('User created.') }}
                    </x-action-message>
                </div>
            </form>

            <flux:separator variant="subtle" />

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left">
                            <th class="py-2 pr-4">{{ __('Name') }}</th>
                            <th class="py-2 pr-4">{{ __('Email') }}</th>
                            <th class="py-2 pr-4">{{ __('Created') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="user-{{ $user->id }}">
                                <td class="py-2 pr-4">
                                    {{ $user->name }}
                                    @if ($user->id === Auth::id())
                                        <flux:badge size="sm" class="ms-2">{{ __('You') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $user->email }}</td>
                                <td class="py-2 pr-4">{{ $user->created_at->format('Y-m-d') }}</td>
                                <td class="py-2 text-right">
                                    @if ($user->id !== Auth::id())
                                        <flux:modal.trigger name="confirm-user-delete">
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="confirmDelete({{ $user->id }})"
                                                data-test="delete-user-{{ $user->id }}"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                {{ $users->links() }}

                <x-action-message class="mt-2" on="user-deleted">
                    {{ __('User deleted.') }}
                </x-action-message>
            </div>
        </div>

        <flux:modal name="confirm-user-delete" focusable class="max-w-lg">
            <form wire:submit="deleteUser" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete this user?') }}</flux:heading>

                    <flux:subheading>
                        {{ __('Deleting this user also removes all of their quizzes and hosted games. This cannot be undone.') }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                        {{ __('Delete user') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    </x-pages::settings.layout>
</section>
```

Key points:
- Two-step flow: `confirmDelete($id)` sets `$userIdToDelete`, the Flux modal trigger opens `confirm-user-delete`, the modal form submits `deleteUser`.
- Backend guard: `abort_if($this->userIdToDelete === Auth::id(), 403)` — the UI also hides the delete button on the self-row (belt + suspenders).
- Cascade is handled by the existing foreign-key constraints (`quizzes.user_id` → cascadeOnDelete, etc.). The "cascades to quizzes" test exercises this to lock the behavior in.

### - [ ] Step 4: Run the tests — expect PASS

Run: `./vendor/bin/pest tests/Feature/Settings/UserManagementTest.php`

Expected: PASS for all tests in the file.

### - [ ] Step 5: Manual smoke test

Via `composer dev`:
- Log in as the seeded admin.
- Go to `/settings/users`.
- Create a test user.
- Click Delete on the test user, confirm in modal — they disappear.
- Confirm there's no Delete button on your own row.
- Try `Livewire::test(...)->call('deleteUser')` with `userIdToDelete` set to your own id via the test suite (already covered by "cannot delete themselves" test).

### - [ ] Step 6: Run full suite

Run: `composer test`

Expected: PASS.

### - [ ] Step 7: Commit

```bash
git add resources/views/pages/settings/⚡users.blade.php tests/Feature/Settings/UserManagementTest.php
git commit -m "feat: add user deletion with self-delete guard

Each row gets a Delete button (hidden on the current user's row) that
opens a Flux confirmation modal. Backend refuses self-deletion with
403 as a second line of defense. Cascade to quizzes and hosted games
is handled by existing foreign-key constraints; test added to lock
that behavior in.

Refs #7"
```

---

## Task 7: Add German translations

**Files:**
- Modify: `lang/de.json`

### - [ ] Step 1: Add German translations

Open `lang/de.json`. For each key below, check whether it already exists in the file (grep for the key): if it does, skip it (do not duplicate — JSON doesn't allow duplicate keys and Laravel's translator ignores later ones). If it doesn't, add it as a new entry. Append new entries just before the closing `}` of the JSON object, one per line, comma-separated per standard JSON rules.

Candidate keys:

```json
"Users": "Benutzer",
"Manage who can access the app": "Verwalte, wer Zugriff auf die App hat",
"Add a user": "Benutzer hinzufügen",
"Name": "Name",
"Email": "E-Mail",
"Password": "Passwort",
"Confirm password": "Passwort bestätigen",
"Create user": "Benutzer erstellen",
"User created.": "Benutzer erstellt.",
"User deleted.": "Benutzer gelöscht.",
"Delete": "Löschen",
"Delete this user?": "Diesen Benutzer löschen?",
"Delete user": "Benutzer löschen",
"Deleting this user also removes all of their quizzes and hosted games. This cannot be undone.": "Wenn du diesen Benutzer löschst, werden auch alle seine Quizze und Spiele entfernt. Das kann nicht rückgängig gemacht werden.",
"Cancel": "Abbrechen",
"Created": "Erstellt",
"You": "Du"
```

Likely already-present keys (verify before adding): `"Name"`, `"Email"`, `"Password"`, `"Cancel"`, `"Delete"`.

### - [ ] Step 2: Verify JSON is valid

Run: `php -r "json_decode(file_get_contents('lang/de.json'), false, 512, JSON_THROW_ON_ERROR); echo 'OK';"`

Expected: `OK` (no JSON parse error).

### - [ ] Step 3: Manual check — switch locale

Via `composer dev`:
- Navigate to `/locale/de`.
- Go to `/settings/users`.
- Confirm the heading, subheading, form labels, table headers, and delete modal copy all render in German.

### - [ ] Step 4: Commit

```bash
git add lang/de.json
git commit -m "feat: translate user management page to German

Closes the last piece of issue #7.

Refs #7"
```

---

## Final Verification

### - [ ] Step 1: Run the full suite one more time

Run: `composer test`

Expected: PASS. Pint lint pass followed by a green Pest run with all the new tests included.

### - [ ] Step 2: End-to-end manual flow

- Fresh install: `rm database/database.sqlite && php artisan migrate --seed`
- Confirm the seeder created a single user from your `.env` values.
- Log in as that user.
- `/register` → 404.
- `/settings/users` → list, create, delete all work.
- Create a user, log out, log in as that new user — works.
- Delete a user who had quizzes — their quizzes are gone.

### - [ ] Step 3: Push the branch and open a PR

```bash
git push -u origin HEAD
gh pr create --title "Restrict registration and add user management (#7)" --body "$(cat <<'EOF'
## Summary

- Disables public registration: `Features::registration()` removed from Fortify, register view deleted, `/register` returns 404
- Adds `/settings/users` — authenticated users can list, create, and delete other users (self-delete blocked)
- Makes `DatabaseSeeder` idempotent and env-driven via `INITIAL_USER_*` vars so fresh installs still have a way in

## Test plan
- [ ] `composer test` passes
- [ ] `/register` returns 404
- [ ] Login page no longer shows a sign-up link
- [ ] Fresh install (`migrate --seed`) creates exactly one user from env vars
- [ ] `/settings/users` renders list, creation works, deletion works, self-delete blocked
- [ ] Locale switch shows German copy correctly

Closes #7

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
