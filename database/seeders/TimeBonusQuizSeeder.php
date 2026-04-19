<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class TimeBonusQuizSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'marangon.claudio@gmail.com')->first()
            ?? User::first();

        $quiz = Quiz::create([
            'user_id' => $user->id,
            'title' => 'Speed Round: Science',
            'description' => 'Answer fast to earn bonus points! Time bonus is enabled.',
            'visibility' => 'public',
            'settings' => [
                'enable_time_bonus' => true,
                'enable_streaks' => false,
            ],
        ]);

        $category = Category::create([
            'quiz_id' => $quiz->id,
            'name' => 'Science',
            'slug' => 'science',
            'order' => 0,
        ]);

        $questions = [
            [
                'body' => 'What gas do plants absorb from the atmosphere?',
                'options' => [
                    ['label' => 'Oxygen'],
                    ['label' => 'Nitrogen'],
                    ['label' => 'Carbon Dioxide'],
                    ['label' => 'Hydrogen'],
                ],
                'correct_answer' => 'Carbon Dioxide',
            ],
            [
                'body' => 'What is the speed of light approximately in km/s?',
                'options' => [
                    ['label' => '150,000 km/s'],
                    ['label' => '300,000 km/s'],
                    ['label' => '450,000 km/s'],
                    ['label' => '600,000 km/s'],
                ],
                'correct_answer' => '300,000 km/s',
            ],
            [
                'body' => 'What is the hardest natural substance on Earth?',
                'options' => [
                    ['label' => 'Gold'],
                    ['label' => 'Iron'],
                    ['label' => 'Diamond'],
                    ['label' => 'Platinum'],
                ],
                'correct_answer' => 'Diamond',
            ],
            [
                'body' => 'How many bones are in the adult human body?',
                'options' => [
                    ['label' => '186'],
                    ['label' => '206'],
                    ['label' => '226'],
                    ['label' => '246'],
                ],
                'correct_answer' => '206',
            ],
            [
                'body' => 'What planet has the most moons?',
                'options' => [
                    ['label' => 'Jupiter'],
                    ['label' => 'Saturn'],
                    ['label' => 'Uranus'],
                    ['label' => 'Neptune'],
                ],
                'correct_answer' => 'Saturn',
            ],
        ];

        foreach ($questions as $index => $q) {
            Question::create([
                'category_id' => $category->id,
                'type' => 'multiple_choice',
                'body' => $q['body'],
                'options' => $q['options'],
                'correct_answer' => $q['correct_answer'],
                'points' => 10,
                'time_limit_seconds' => 20,
                'order' => $index,
            ]);
        }
    }
}
