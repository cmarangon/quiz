<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
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

    public function matchPairs(): static
    {
        return $this->state(fn () => [
            'type' => 'match_pairs',
            'body' => 'Match each country to its capital.',
            'options' => [
                'left' => [
                    ['kind' => 'text', 'value' => 'France'],
                    ['kind' => 'text', 'value' => 'Japan'],
                    ['kind' => 'text', 'value' => 'Egypt'],
                    ['kind' => 'text', 'value' => 'Brazil'],
                ],
                // Shuffled relative to "left" so the broadcast display order
                // never reveals the correct pairing.
                'right' => [
                    ['kind' => 'text', 'value' => 'Cairo'],
                    ['kind' => 'text', 'value' => 'Paris'],
                    ['kind' => 'text', 'value' => 'Brasilia'],
                    ['kind' => 'text', 'value' => 'Tokyo'],
                ],
            ],
            // correct_answer[leftIndex] = rightIndex
            'correct_answer' => [1, 3, 0, 2],
        ]);
    }
}
