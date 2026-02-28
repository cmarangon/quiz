<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'type' => 'multiple_choice',
            'body' => fake()->sentence() . '?',
            'options' => [
                ['label' => 'Option A'],
                ['label' => 'Option B'],
                ['label' => 'Option C'],
                ['label' => 'Option D'],
            ],
            'correct_answer' => 'Option A',
            'points' => 10,
            'time_limit_seconds' => 30,
            'order' => 0,
        ];
    }
}
