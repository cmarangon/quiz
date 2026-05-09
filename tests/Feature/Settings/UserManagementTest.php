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
