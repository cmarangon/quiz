<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameSession>
 */
class GameSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'host_user_id' => User::factory(),
            'join_code' => strtoupper(fake()->bothify('???###')),
            'status' => 'waiting',
            'current_question_index' => 0,
        ];
    }
}
