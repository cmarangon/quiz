<?php

use App\Livewire\QuizBuilder;
use App\Livewire\QuizIndex;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\User;
use Livewire\Livewire;

test('guest cannot access quiz index', function () {
    $this->get('/quizzes')->assertRedirect('/login');
});

test('authenticated user sees their quizzes', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id, 'title' => 'My Awesome Quiz']);

    Livewire::actingAs($user)
        ->test(QuizIndex::class)
        ->assertSee('My Awesome Quiz');
});

test('user can create a quiz', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'New Quiz Title')
        ->set('description', 'A quiz description')
        ->call('save')
        ->assertHasNoErrors();

    expect(Quiz::where('title', 'New Quiz Title')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('quiz title is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

test('user can add a category to a quiz', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->set('newCategoryName', 'Science')
        ->set('newCategoryTheme', 'science')
        ->call('addCategory')
        ->assertHasNoErrors();

    expect($quiz->categories()->where('name', 'Science')->exists())->toBeTrue();
});

test('user can add a question to a category', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'What is 2+2?')
        ->set('questionType', 'multiple_choice')
        ->set('questionOptions', ['3', '4', '5', '6'])
        ->set('questionCorrectAnswer', '4')
        ->set('questionPoints', 10)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    expect($category->questions()->where('body', 'What is 2+2?')->exists())->toBeTrue();
});

test('user cannot edit another users quiz', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($otherUser)
        ->get("/quizzes/{$quiz->id}/edit")
        ->assertStatus(403);
});

test('user can add an ordering question with a shuffled display order', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Order the planets from the sun')
        ->set('questionType', 'ordering')
        ->set('questionOptions', ['Mercury', 'Venus', 'Earth', 'Mars'])
        ->set('questionPoints', 10)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->where('body', 'Order the planets from the sun')->firstOrFail();

    expect($question->type)->toBe('ordering');
    // The entered order is the correct answer.
    expect($question->correct_answer)->toBe(['Mercury', 'Venus', 'Earth', 'Mars']);
    // Options carry the same labels but in a different (shuffled) display order
    // so the broadcast never reveals the correct sequence.
    $displayLabels = collect($question->options)->pluck('label')->all();
    expect($displayLabels)->not->toBe(['Mercury', 'Venus', 'Earth', 'Mars']);
    sort($displayLabels);
    expect($displayLabels)->toBe(['Earth', 'Mars', 'Mercury', 'Venus']);
});

test('ordering question rejects duplicate item labels', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Order these steps')
        ->set('questionType', 'ordering')
        ->set('questionOptions', ['Step A', 'Step A'])
        ->set('questionPoints', 10)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasErrors('questionOptions.*');

    expect($category->questions()->count())->toBe(0);
});

test('ordering question does not require a separate correct answer field', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Order these steps')
        ->set('questionType', 'ordering')
        ->set('questionOptions', ['First', 'Second', 'Third'])
        ->set('questionCorrectAnswer', '')
        ->set('questionPoints', 10)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    expect($category->questions()->where('type', 'ordering')->count())->toBe(1);
});

test('user can add a geo guesser question with coordinates', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Where is the Eiffel Tower?')
        ->set('questionType', 'geo_guesser')
        ->set('questionGeoLat', '48.8584')
        ->set('questionGeoLng', '2.2945')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->where('type', 'geo_guesser')->firstOrFail();
    expect($question->correct_answer)->toBe(['lat' => 48.8584, 'lng' => 2.2945]);
});

test('geo guesser question requires valid coordinates', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('showAddQuestion', $category->id)
        ->set('questionBody', 'Where is the Eiffel Tower?')
        ->set('questionType', 'geo_guesser')
        ->set('questionGeoLat', '200')
        ->set('questionGeoLng', '')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasErrors(['questionGeoLat', 'questionGeoLng']);

    expect($category->questions()->count())->toBe(0);
});
