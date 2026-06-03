<?php

namespace Database\Factories;

use App\Models\GameSession;
use App\Models\Player;
use App\Support\PlayerEmojis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory(),
            'nickname' => fake()->userName(),
            'emoji' => fake()->randomElement(PlayerEmojis::all()),
            'score' => 0,
            'streak' => 0,
            'is_connected' => true,
        ];
    }
}
