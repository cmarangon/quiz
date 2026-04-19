<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class TimeBonusAndStreaksQuizSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'marangon.claudio@gmail.com')->first()
            ?? User::first();

        $quiz = Quiz::create([
            'user_id' => $user->id,
            'title' => 'Ultimate Challenge: Pop Culture',
            'description' => 'The ultimate test! Both time bonus and streak multipliers are active.',
            'visibility' => 'public',
            'settings' => [
                'enable_time_bonus' => true,
                'enable_streaks' => true,
            ],
        ]);

        $category = Category::create([
            'quiz_id' => $quiz->id,
            'name' => 'Pop Culture',
            'slug' => 'pop-culture',
            'order' => 0,
        ]);

        $questions = [
            [
                'body' => 'Which band released the album "Abbey Road"?',
                'options' => [
                    ['label' => 'The Rolling Stones'],
                    ['label' => 'The Beatles'],
                    ['label' => 'Led Zeppelin'],
                    ['label' => 'Pink Floyd'],
                ],
                'correct_answer' => 'The Beatles',
            ],
            [
                'body' => 'What movie franchise features a character named "Darth Vader"?',
                'options' => [
                    ['label' => 'Star Trek'],
                    ['label' => 'Star Wars'],
                    ['label' => 'Battlestar Galactica'],
                    ['label' => 'The Matrix'],
                ],
                'correct_answer' => 'Star Wars',
            ],
            [
                'body' => 'Which video game features a plumber named Mario?',
                'options' => [
                    ['label' => 'Sonic the Hedgehog'],
                    ['label' => 'Super Mario Bros.'],
                    ['label' => 'The Legend of Zelda'],
                    ['label' => 'Pac-Man'],
                ],
                'correct_answer' => 'Super Mario Bros.',
            ],
            [
                'body' => 'Who painted the Mona Lisa?',
                'options' => [
                    ['label' => 'Michelangelo'],
                    ['label' => 'Raphael'],
                    ['label' => 'Leonardo da Vinci'],
                    ['label' => 'Vincent van Gogh'],
                ],
                'correct_answer' => 'Leonardo da Vinci',
            ],
            [
                'body' => 'What TV show features the fictional continent of Westeros?',
                'options' => [
                    ['label' => 'The Witcher'],
                    ['label' => 'Lord of the Rings'],
                    ['label' => 'Game of Thrones'],
                    ['label' => 'Vikings'],
                ],
                'correct_answer' => 'Game of Thrones',
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
                'time_limit_seconds' => 15,
                'order' => $index,
            ]);
        }
    }
}
