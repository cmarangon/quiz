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
