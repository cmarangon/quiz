<?php

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the user management page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('users.index'))->assertOk();
});

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

    expect(Quiz::find($quiz->id))->toBeNull();
});
