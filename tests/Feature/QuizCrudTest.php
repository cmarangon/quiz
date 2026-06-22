<?php

use App\Livewire\QuizBuilder;
use App\Livewire\QuizIndex;
use App\Models\Category;
use App\Models\Question;
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

test('user can disable the scoreboard when creating a quiz', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'No Scoreboard Quiz')
        ->set('showScoreboard', false)
        ->call('save')
        ->assertHasNoErrors();

    $quiz = Quiz::where('title', 'No Scoreboard Quiz')->firstOrFail();
    expect($quiz->settings['show_scoreboard'])->toBeFalse();
});

test('scoreboard setting defaults to true for a brand new quiz form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->assertSet('showScoreboard', true);
});

test('editing a quiz loads its scoreboard setting', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create([
        'user_id' => $user->id,
        'settings' => ['show_scoreboard' => false],
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->assertSet('showScoreboard', false);
});

test('editing a quiz without the scoreboard setting defaults it to true', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create([
        'user_id' => $user->id,
        'settings' => ['enable_time_bonus' => true],
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->assertSet('showScoreboard', true);
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

test('adding a category requires choosing a styled theme', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);

    // No theme selected (the default empty state) must not silently save.
    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->set('newCategoryName', 'Science')
        ->set('newCategoryTheme', '')
        ->call('addCategory')
        ->assertHasErrors(['newCategoryTheme']);

    // The unstyled "default" fallback is not a selectable choice either.
    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->set('newCategoryName', 'Science')
        ->set('newCategoryTheme', 'default')
        ->call('addCategory')
        ->assertHasErrors(['newCategoryTheme']);

    expect($quiz->categories()->count())->toBe(0);
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

test('editing a question loads its data into the form', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'multiple_choice',
        'body' => 'What is 2+2?',
        'options' => ['3', '4', '5', '6'],
        'correct_answer' => '4',
        'points' => 10,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('editingQuestionId', $question->id)
        ->assertSet('questionBody', 'What is 2+2?')
        ->assertSet('questionType', 'multiple_choice')
        ->assertSet('questionOptions', ['3', '4', '5', '6'])
        ->assertSet('questionCorrectAnswer', '4')
        ->assertSet('questionPoints', 10)
        ->assertSet('questionTimeLimit', 30);
});

test('user can update an existing question', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'multiple_choice',
        'body' => 'What is 2+2?',
        'options' => ['3', '4', '5', '6'],
        'correct_answer' => '4',
        'points' => 10,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('questionBody', 'What is 3+3?')
        ->set('questionOptions', ['5', '6', '7', '8'])
        ->set('questionCorrectAnswer', '6')
        ->set('questionPoints', 20)
        ->set('questionTimeLimit', 45)
        ->call('saveQuestion')
        ->assertHasNoErrors()
        ->assertSet('editingQuestionId', null);

    $question->refresh();
    expect($question->body)->toBe('What is 3+3?');
    expect($question->correct_answer)->toBe('6');
    expect($question->points)->toBe(20);
    expect($question->time_limit_seconds)->toBe(45);
    expect($question->order)->toBe(1);
    expect($category->questions()->count())->toBe(1);
});

test('editing an ordering question loads the correct order into the form', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'ordering',
        'body' => 'Order the planets',
        'options' => [['label' => 'Mars'], ['label' => 'Earth'], ['label' => 'Venus'], ['label' => 'Mercury']],
        'correct_answer' => ['Mercury', 'Venus', 'Earth', 'Mars'],
        'points' => 10,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionType', 'ordering')
        ->assertSet('questionOptions', ['Mercury', 'Venus', 'Earth', 'Mars']);
});

test('editing a geo guesser question loads its coordinates', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'geo_guesser',
        'body' => 'Where is the Eiffel Tower?',
        'options' => [],
        'correct_answer' => ['lat' => 48.8584, 'lng' => 2.2945],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionType', 'geo_guesser')
        ->assertSet('questionGeoLat', '48.8584')
        ->assertSet('questionGeoLng', '2.2945');
});

test('user can set per-question threshold and max distance on a geo guesser question', function () {
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
        ->set('questionGeoThresholdKm', '25')
        ->set('questionGeoMaxDistanceKm', '1000')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = $category->questions()->where('type', 'geo_guesser')->firstOrFail();
    expect((float) $question->options['threshold_km'])->toBe(25.0);
    expect((float) $question->options['max_distance_km'])->toBe(1000.0);
});

test('geo guesser threshold and max distance default to empty options when blank', function () {
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
    expect($question->options)->toBe([]);
});

test('geo guesser threshold must be less than max distance', function () {
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
        ->set('questionGeoThresholdKm', '1000')
        ->set('questionGeoMaxDistanceKm', '500')
        ->set('questionPoints', 100)
        ->set('questionTimeLimit', 30)
        ->call('saveQuestion')
        ->assertHasErrors(['questionGeoThresholdKm']);

    expect($category->questions()->count())->toBe(0);
});

test('editing a geo guesser question loads its per-question scoring options', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'geo_guesser',
        'body' => 'Where is the Eiffel Tower?',
        'options' => ['threshold_km' => 25.0, 'max_distance_km' => 1000.0],
        'correct_answer' => ['lat' => 48.8584, 'lng' => 2.2945],
        'points' => 100,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('questionGeoThresholdKm', '25')
        ->assertSet('questionGeoMaxDistanceKm', '1000');
});

test('user cannot edit a question belonging to another users quiz', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $owner->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'multiple_choice',
        'body' => 'What is 2+2?',
        'options' => ['3', '4'],
        'correct_answer' => '4',
        'points' => 10,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    $otherQuiz = Quiz::factory()->create(['user_id' => $otherUser->id]);

    Livewire::actingAs($otherUser)
        ->test(QuizBuilder::class, ['quiz' => $otherQuiz])
        ->call('editQuestion', $question->id)
        ->assertStatus(403);
});

test('user can delete a question', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'multiple_choice',
        'body' => 'What is 2+2?',
        'options' => ['3', '4'],
        'correct_answer' => '4',
        'points' => 10,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('deleteQuestion', $question->id);

    expect(Question::find($question->id))->toBeNull();
});

test('deleting the question being edited resets the form', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'multiple_choice',
        'body' => 'What is 2+2?',
        'options' => ['3', '4'],
        'correct_answer' => '4',
        'points' => 10,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->assertSet('editingQuestionId', $question->id)
        ->call('deleteQuestion', $question->id)
        ->assertSet('editingQuestionId', null)
        ->assertSet('questionBody', '');

    expect(Question::find($question->id))->toBeNull();
});

test('user cannot delete a question belonging to another users quiz', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $quiz = Quiz::factory()->create(['user_id' => $owner->id]);
    $category = Category::factory()->create(['quiz_id' => $quiz->id]);
    $question = $category->questions()->create([
        'type' => 'multiple_choice',
        'body' => 'What is 2+2?',
        'options' => ['3', '4'],
        'correct_answer' => '4',
        'points' => 10,
        'time_limit_seconds' => 30,
        'order' => 1,
    ]);

    $otherQuiz = Quiz::factory()->create(['user_id' => $otherUser->id]);

    Livewire::actingAs($otherUser)
        ->test(QuizBuilder::class, ['quiz' => $otherQuiz])
        ->call('deleteQuestion', $question->id)
        ->assertStatus(403);

    expect(Question::find($question->id))->not->toBeNull();
});
