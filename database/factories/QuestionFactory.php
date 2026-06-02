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
            'body' => fake()->sentence().'?',
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

    public function geoGuesser(): static
    {
        return $this->state(fn () => [
            'type' => 'geo_guesser',
            'body' => 'Where is '.fake()->city().'?',
            'options' => [
                'zoom' => 2,
                'center' => ['lat' => 20.0, 'lng' => 0.0],
            ],
            'correct_answer' => [
                'lat' => fake()->latitude(),
                'lng' => fake()->longitude(),
            ],
        ]);
    }

    public function ordering(): static
    {
        return $this->state(fn () => [
            'type' => 'ordering',
            'body' => 'Put the steps in the correct order.',
            // Stored display order is intentionally shuffled so the broadcast
            // never reveals the correct sequence.
            'options' => [
                ['label' => 'Step B'],
                ['label' => 'Step D'],
                ['label' => 'Step A'],
                ['label' => 'Step C'],
            ],
            'correct_answer' => ['Step A', 'Step B', 'Step C', 'Step D'],
        ]);
    }
}
