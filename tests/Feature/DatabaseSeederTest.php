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
