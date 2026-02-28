<?php

namespace Database\Factories;

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerAnswer>
 */
class PlayerAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'game_session_id' => GameSession::factory(),
            'question_id' => Question::factory(),
            'answer' => 'Option A',
            'is_correct' => false,
            'time_taken_ms' => fake()->numberBetween(1000, 30000),
            'points_earned' => 0,
        ];
    }
}
