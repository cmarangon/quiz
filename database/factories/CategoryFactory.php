<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
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
