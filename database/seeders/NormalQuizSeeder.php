<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class NormalQuizSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'marangon.claudio@gmail.com')->first()
            ?? User::first();

        $quiz = Quiz::create([
            'user_id' => $user->id,
            'title' => 'General Knowledge',
            'description' => 'A classic general knowledge quiz with no time bonus or streaks.',
            'visibility' => 'public',
            'settings' => [
                'enable_time_bonus' => false,
                'enable_streaks' => false,
            ],
        ]);

        $category = Category::create([
            'quiz_id' => $quiz->id,
            'name' => 'General Knowledge',
            'slug' => 'general-knowledge',
            'order' => 0,
        ]);

        $questions = [
            [
                'body' => 'What is the capital of France?',
                'options' => [
                    ['label' => 'Berlin'],
                    ['label' => 'Madrid'],
                    ['label' => 'Paris'],
                    ['label' => 'Rome'],
                ],
                'correct_answer' => 'Paris',
            ],
            [
                'body' => 'Which planet is known as the Red Planet?',
                'options' => [
                    ['label' => 'Venus'],
                    ['label' => 'Mars'],
                    ['label' => 'Jupiter'],
                    ['label' => 'Saturn'],
                ],
                'correct_answer' => 'Mars',
            ],
            [
                'body' => 'What is the largest ocean on Earth?',
                'options' => [
                    ['label' => 'Atlantic Ocean'],
                    ['label' => 'Indian Ocean'],
                    ['label' => 'Arctic Ocean'],
                    ['label' => 'Pacific Ocean'],
                ],
                'correct_answer' => 'Pacific Ocean',
            ],
            [
                'body' => 'Who wrote "Romeo and Juliet"?',
                'options' => [
                    ['label' => 'Charles Dickens'],
                    ['label' => 'William Shakespeare'],
                    ['label' => 'Jane Austen'],
                    ['label' => 'Mark Twain'],
                ],
                'correct_answer' => 'William Shakespeare',
            ],
            [
                'body' => 'What is the chemical symbol for water?',
                'options' => [
                    ['label' => 'CO2'],
                    ['label' => 'H2O'],
                    ['label' => 'NaCl'],
                    ['label' => 'O2'],
                ],
                'correct_answer' => 'H2O',
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
                'time_limit_seconds' => 30,
                'order' => $index,
            ]);
        }
    }
}
