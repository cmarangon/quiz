<?php

use App\Livewire\QuizBuilder;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('user can add a match_pairs question with four text pairs', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the country to its capital')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.text', 'France')
        ->set('questionPairs.0.right.text', 'Paris')
        ->set('questionPairs.1.left.text', 'Japan')
        ->set('questionPairs.1.right.text', 'Tokyo')
        ->set('questionPairs.2.left.text', 'Egypt')
        ->set('questionPairs.2.right.text', 'Cairo')
        ->set('questionPairs.3.left.text', 'Brazil')
        ->set('questionPairs.3.right.text', 'Brasilia')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->where('type', 'match_pairs')->firstOrFail();

    $leftValues = collect($question->options['left'])->pluck('value')->all();
    expect($leftValues)->toBe(['France', 'Japan', 'Egypt', 'Brazil']);

    $rightValues = collect($question->options['right'])->pluck('value')->all();
    expect($rightValues)->toEqualCanonicalizing(['Paris', 'Tokyo', 'Cairo', 'Brasilia']);
    // Right column must be shuffled relative to the entered order so the
    // broadcast never reveals the pairing by position alignment.
    expect($rightValues)->not->toBe(['Paris', 'Tokyo', 'Cairo', 'Brasilia']);

    // correct_answer[leftIndex] = rightIndex must reconstruct the entered pairs.
    $correctAnswer = $question->correct_answer;
    expect($correctAnswer)->toHaveCount(4);
    foreach (['France' => 'Paris', 'Japan' => 'Tokyo', 'Egypt' => 'Cairo', 'Brazil' => 'Brasilia'] as $leftLabel => $rightLabel) {
        $leftIndex = array_search($leftLabel, $leftValues, true);
        expect($rightValues[$correctAnswer[$leftIndex]])->toBe($rightLabel);
    }
});

test('match_pairs question requires text in every pair slot', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the pairs')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.text', 'France')
        ->set('questionPairs.0.right.text', 'Paris')
        // pairs 1-3 left blank
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasErrors('questionPairs.1.left.text');

    expect($category->questions()->count())->toBe(0);
});

test('editing a match_pairs question loads its pairs into the form', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'match_pairs',
        'body' => 'Match the pairs',
        'options' => [
            'left' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'Japan'],
                ['kind' => 'text', 'value' => 'Egypt'],
                ['kind' => 'text', 'value' => 'Brazil'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'Cairo'],
                ['kind' => 'text', 'value' => 'Paris'],
                ['kind' => 'text', 'value' => 'Brasilia'],
                ['kind' => 'text', 'value' => 'Tokyo'],
            ],
        ],
        'correct_answer' => [1, 3, 0, 2],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionType', 'match_pairs')
        ->assertSet('questionPairs.0.left.text', 'France')
        ->assertSet('questionPairs.0.right.text', 'Paris')
        ->assertSet('questionPairs.1.left.text', 'Japan')
        ->assertSet('questionPairs.1.right.text', 'Tokyo')
        ->assertSet('questionPairs.2.left.text', 'Egypt')
        ->assertSet('questionPairs.2.right.text', 'Cairo')
        ->assertSet('questionPairs.3.left.text', 'Brazil')
        ->assertSet('questionPairs.3.right.text', 'Brasilia');
});

test('user can update an existing match_pairs question', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'match_pairs',
        'body' => 'Match the pairs',
        'options' => [
            'left' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'Japan'],
                ['kind' => 'text', 'value' => 'Egypt'],
                ['kind' => 'text', 'value' => 'Brazil'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'Cairo'],
                ['kind' => 'text', 'value' => 'Paris'],
                ['kind' => 'text', 'value' => 'Brasilia'],
                ['kind' => 'text', 'value' => 'Tokyo'],
            ],
        ],
        'correct_answer' => [1, 3, 0, 2],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('questionPairs.0.right.text', 'Berlin')
        ->call('saveQuestion')
        ->assertHasNoErrors()
        ->assertSet('editingQuestionId', null);

    $question->refresh();
    $rightValues = collect($question->options['right'])->pluck('value')->all();
    expect($rightValues)->toContain('Berlin');
    expect($category->questions()->count())->toBe(1);
});
