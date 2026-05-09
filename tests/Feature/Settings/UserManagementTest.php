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
