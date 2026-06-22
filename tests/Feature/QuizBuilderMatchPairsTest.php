<?php

use App\Livewire\QuizBuilder;
use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('user can add a match_pairs question with an uploaded image pair side', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the flag to its country')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.kind', 'image')
        ->set('questionPairs.0.left.image', UploadedFile::fake()->image('flag.png'))
        ->set('questionPairs.0.right.text', 'France')
        ->set('questionPairs.1.left.text', 'B')
        ->set('questionPairs.1.right.text', 'X')
        ->set('questionPairs.2.left.text', 'C')
        ->set('questionPairs.2.right.text', 'Y')
        ->set('questionPairs.3.left.text', 'D')
        ->set('questionPairs.3.right.text', 'Z')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->where('type', 'match_pairs')->firstOrFail();
    $leftZero = $question->options['left'][0];

    expect($leftZero['kind'])->toBe('image');
    expect($leftZero['value'])->toStartWith('questions/');
    Storage::disk('public')->assertExists($leftZero['value']);
});

test('switching a pair side to image requires an upload', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the pairs')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.kind', 'image')
        // no image uploaded and no existingImage
        ->set('questionPairs.0.right.text', 'France')
        ->set('questionPairs.1.left.text', 'B')
        ->set('questionPairs.1.right.text', 'X')
        ->set('questionPairs.2.left.text', 'C')
        ->set('questionPairs.2.right.text', 'Y')
        ->set('questionPairs.3.left.text', 'D')
        ->set('questionPairs.3.right.text', 'Z')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasErrors('questionPairs.0.left.image');

    expect($category->questions()->count())->toBe(0);
});

test('replacing an image on edit deletes the old file and stores the new one', function () {
    Storage::fake('public');
    $oldPath = Storage::disk('public')->put('questions', UploadedFile::fake()->image('old.png'));

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'match_pairs',
        'body' => 'Match the flags',
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => $oldPath],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
        'correct_answer' => [0, 1, 2, 3],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionPairs.0.left.existingImage', $oldPath)
        ->set('questionPairs.0.left.image', UploadedFile::fake()->image('new.png'))
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question->refresh();
    $newPath = $question->options['left'][0]['value'];

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

test('editing a match_pairs question keeps its existing image without requiring re-upload', function () {
    Storage::fake('public');
    $path = Storage::disk('public')->put('questions', UploadedFile::fake()->image('flag.png'));

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'match_pairs',
        'body' => 'Match the flags',
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => $path],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
        'correct_answer' => [0, 1, 2, 3],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('questionBody', 'Match the flags (updated)')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question->refresh();
    expect($question->options['left'][0]['value'])->toBe($path);
    Storage::disk('public')->assertExists($path);
});

test('saving with the legitimate existingImage populated by editQuestion still works', function () {
    Storage::fake('public');
    $path = Storage::disk('public')->put('questions', UploadedFile::fake()->image('flag.png'));

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = Question::factory()->matchPairs()->create([
        'category_id' => $category->id,
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => $path],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
        'correct_answer' => [0, 1, 2, 3],
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionPairs.0.left.existingImage', $path)
        ->set('questionBody', 'Match the flags (renamed)')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question->refresh();
    expect($question->options['left'][0]['kind'])->toBe('image');
    expect($question->options['left'][0]['value'])->toBe($path);
    expect($question->body)->toBe('Match the flags (renamed)');
});

test('tampering existingImage with a path never associated with this question is rejected', function () {
    Storage::fake('public');
    $ownPath = Storage::disk('public')->put('questions', UploadedFile::fake()->image('flag.png'));
    $foreignPath = Storage::disk('public')->put('questions', UploadedFile::fake()->image('other.png'));

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = Question::factory()->matchPairs()->create([
        'category_id' => $category->id,
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => $ownPath],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
        'correct_answer' => [0, 1, 2, 3],
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionPairs.0.left.existingImage', $ownPath)
        // Simulate a tampered raw Livewire update setting existingImage to a
        // path that was never legitimately produced for this question.
        ->set('questionPairs.0.left.existingImage', $foreignPath)
        ->call('saveQuestion')
        ->assertHasErrors('questionPairs.0.left.image');

    $question->refresh();
    expect($question->options['left'][0]['value'])->toBe($ownPath);
    expect($question->options['left'][0]['value'])->not->toBe($foreignPath);
});

test('a brand new question can never have a legitimate existingImage', function () {
    Storage::fake('public');
    $otherCategory = Category::factory()->create();
    $otherQuestion = Question::factory()->matchPairs()->create([
        'category_id' => $otherCategory->id,
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => Storage::disk('public')->put('questions', UploadedFile::fake()->image('other.png'))],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'text', 'value' => 'France'],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
        'correct_answer' => [0, 1, 2, 3],
    ]);
    $foreignPath = $otherQuestion->options['left'][0]['value'];

    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Match the flag to its country')
        ->set('questionType', 'match_pairs')
        ->set('questionPairs.0.left.kind', 'image')
        ->set('questionPairs.0.left.existingImage', $foreignPath)
        ->set('questionPairs.0.right.text', 'France')
        ->set('questionPairs.1.left.text', 'B')
        ->set('questionPairs.1.right.text', 'X')
        ->set('questionPairs.2.left.text', 'C')
        ->set('questionPairs.2.right.text', 'Y')
        ->set('questionPairs.3.left.text', 'D')
        ->set('questionPairs.3.right.text', 'Z')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasErrors('questionPairs.0.left.image');

    expect($category->questions()->count())->toBe(0);
});
