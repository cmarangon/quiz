<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eGameSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('E2E_HOST_EMAIL', 'e2e-host@test.local');
        $password = env('E2E_HOST_PASSWORD', 'password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'E2E Host',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $this->seedMultipleChoiceQuiz($user);
        $this->seedGeoQuiz($user);
    }

    private function seedMultipleChoiceQuiz(User $user): void
    {
        $quizTitle = env('E2E_QUIZ_TITLE', 'E2E Test Quiz');

        $quiz = Quiz::firstOrCreate(
            ['user_id' => $user->id, 'title' => $quizTitle],
            [
                'description' => 'Deterministic quiz used by the Playwright suite.',
                'visibility' => 'public',
                'settings' => [
                    'enable_time_bonus' => false,
                    'enable_streaks' => false,
                ],
            ],
        );

        if (! $quiz->wasRecentlyCreated) {
            return;
        }

        $category = Category::create([
            'quiz_id' => $quiz->id,
            'name' => 'General',
            'slug' => 'general',
            'order' => 0,
        ]);

        $questions = [
            [
                'body' => 'What is 2 + 2?',
                'options' => [
                    ['label' => '3'],
                    ['label' => '4'],
                    ['label' => '5'],
                    ['label' => '22'],
                ],
                'correct_answer' => '4',
            ],
            [
                'body' => 'What color is the sky on a clear day?',
                'options' => [
                    ['label' => 'Green'],
                    ['label' => 'Blue'],
                    ['label' => 'Red'],
                    ['label' => 'Yellow'],
                ],
                'correct_answer' => 'Blue',
            ],
            [
                'body' => 'How many sides does a triangle have?',
                'options' => [
                    ['label' => '2'],
                    ['label' => '3'],
                    ['label' => '4'],
                    ['label' => '5'],
                ],
                'correct_answer' => '3',
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

    private function seedGeoQuiz(User $user): void
    {
        $quizTitle = env('E2E_GEO_QUIZ_TITLE', 'E2E Geo Quiz');

        $quiz = Quiz::firstOrCreate(
            ['user_id' => $user->id, 'title' => $quizTitle],
            [
                'description' => 'Deterministic geo-guesser quiz used by the Playwright suite.',
                'visibility' => 'public',
                'settings' => [
                    'enable_time_bonus' => false,
                    'enable_streaks' => false,
                ],
            ],
        );

        if (! $quiz->wasRecentlyCreated) {
            return;
        }

        $category = Category::create([
            'quiz_id' => $quiz->id,
            'name' => 'Geography',
            'slug' => 'geography',
            'order' => 0,
        ]);

        Question::create([
            'category_id' => $category->id,
            'type' => 'geo_guesser',
            'body' => 'Where is the Eiffel Tower?',
            'options' => [
                'zoom' => 2,
                'center' => ['lat' => 30.0, 'lng' => 10.0],
                'threshold_km' => 0,
                // Wide radius keeps the smoke test deterministic: any reasonable
                // pin earns a non-zero score.
                'max_distance_km' => 20000,
            ],
            'correct_answer' => ['lat' => 48.8584, 'lng' => 2.2945],
            'points' => 100,
            'time_limit_seconds' => 30,
            'order' => 0,
        ]);
    }
}
