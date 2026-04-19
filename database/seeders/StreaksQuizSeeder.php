<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class StreaksQuizSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'marangon.claudio@gmail.com')->first()
            ?? User::first();

        $quiz = Quiz::create([
            'user_id' => $user->id,
            'title' => 'History Streak Challenge',
            'description' => 'Build your streak! Consecutive correct answers earn multiplied points.',
            'visibility' => 'public',
            'settings' => [
                'enable_time_bonus' => false,
                'enable_streaks' => true,
            ],
        ]);

        $category = Category::create([
            'quiz_id' => $quiz->id,
            'name' => 'History',
            'slug' => 'history',
            'order' => 0,
        ]);

        $questions = [
            [
                'body' => 'In what year did World War II end?',
                'options' => [
                    ['label' => '1943'],
                    ['label' => '1944'],
                    ['label' => '1945'],
                    ['label' => '1946'],
                ],
                'correct_answer' => '1945',
            ],
            [
                'body' => 'Who was the first President of the United States?',
                'options' => [
                    ['label' => 'Thomas Jefferson'],
                    ['label' => 'George Washington'],
                    ['label' => 'John Adams'],
                    ['label' => 'Benjamin Franklin'],
                ],
                'correct_answer' => 'George Washington',
            ],
            [
                'body' => 'Which ancient civilization built the pyramids of Giza?',
                'options' => [
                    ['label' => 'Roman'],
                    ['label' => 'Greek'],
                    ['label' => 'Egyptian'],
                    ['label' => 'Mesopotamian'],
                ],
                'correct_answer' => 'Egyptian',
            ],
            [
                'body' => 'The Berlin Wall fell in which year?',
                'options' => [
                    ['label' => '1987'],
                    ['label' => '1988'],
                    ['label' => '1989'],
                    ['label' => '1990'],
                ],
                'correct_answer' => '1989',
            ],
            [
                'body' => 'Who discovered penicillin?',
                'options' => [
                    ['label' => 'Marie Curie'],
                    ['label' => 'Alexander Fleming'],
                    ['label' => 'Louis Pasteur'],
                    ['label' => 'Joseph Lister'],
                ],
                'correct_answer' => 'Alexander Fleming',
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
