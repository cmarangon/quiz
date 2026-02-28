<?php

namespace Database\Factories;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'name' => fake()->word(),
            'slug' => fake()->slug(1),
            'theme' => 'default',
            'description' => fake()->sentence(),
            'order' => 0,
        ];
    }
}
