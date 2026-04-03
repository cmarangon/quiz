<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Claudio Marangon',
            'email' => 'marangon.claudio@gmail.com',
            'password' => bcrypt('linkinpark'),
        ]);

        $this->call([
            NormalQuizSeeder::class,
            TimeBonusQuizSeeder::class,
            StreaksQuizSeeder::class,
            TimeBonusAndStreaksQuizSeeder::class,
        ]);
    }
}
