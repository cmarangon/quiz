# Quiz2 Party Trivia Game — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a party trivia game with host controls, spectator TV display, and player phone screens using Laravel 12, Livewire, and Reverb.

**Architecture:** Hybrid server-authoritative game loop. Server owns all state; host sends commands (start, next, skip); server validates and broadcasts to spectator + player screens via Reverb WebSockets. Pluggable question types via strategy pattern.

**Tech Stack:** Laravel 12, Livewire 4 (starter kit), Laravel Reverb, Pest, Tailwind CSS, Blade components.

---

## Task 1: Project Scaffolding

**Files:**
- Create: Laravel project at `/home/cmarangon/dev/quiz2/`
- Modify: `.env`
- Modify: `config/app.php`

**Step 1: Create Laravel project with Livewire starter kit**

```bash
cd /home/cmarangon/dev
rm -rf quiz2/.git quiz2/docs
laravel new quiz2 --using=livewire
cd quiz2
```

Select SQLite as database, and Pest as test framework when prompted.

**Step 2: Install broadcasting with Reverb**

```bash
php artisan install:broadcasting
```

Accept all defaults (install Node deps, etc.).

**Step 3: Verify the install works**

```bash
npm install && npm run build
php artisan migrate
php artisan test
```

Expected: All default tests pass.

**Step 4: Restore the design docs**

```bash
mkdir -p docs/plans
```

Copy back the two design documents from this plan into `docs/plans/`.

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: scaffold Laravel 12 project with Livewire starter kit and Reverb"
```

---

## Task 2: Quiz Config File & Question Type Interface

**Files:**
- Create: `config/quiz.php`
- Create: `app/Contracts/QuestionTypeInterface.php`
- Create: `app/Services/QuestionTypeRegistry.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Unit/QuestionTypeRegistryTest.php`

**Step 1: Write failing test for QuestionTypeRegistry**

```php
// tests/Unit/QuestionTypeRegistryTest.php
<?php

use App\Services\QuestionTypeRegistry;
use App\Contracts\QuestionTypeInterface;

test('registry resolves a registered question type', function () {
    $registry = app(QuestionTypeRegistry::class);

    $type = $registry->resolve('multiple_choice');

    expect($type)->toBeInstanceOf(QuestionTypeInterface::class);
});

test('registry throws on unknown question type', function () {
    $registry = app(QuestionTypeRegistry::class);

    $registry->resolve('nonexistent');
})->throws(InvalidArgumentException::class, 'Unknown question type: nonexistent');

test('registry lists all registered types', function () {
    $registry = app(QuestionTypeRegistry::class);

    expect($registry->registered())->toContain('multiple_choice', 'true_false');
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Unit/QuestionTypeRegistryTest.php
```

Expected: FAIL — classes don't exist yet.

**Step 3: Create the interface**

```php
// app/Contracts/QuestionTypeInterface.php
<?php

namespace App\Contracts;

use App\Models\Question;

interface QuestionTypeInterface
{
    public function renderSpectatorComponent(): string;
    public function renderPlayerComponent(): string;
    public function validateAnswer(mixed $answer, Question $question): bool;
    public function calculatePoints(Question $question, int $timeTakenMs, array $quizSettings): int;
    public function validateOptions(array $options): bool;
}
```

**Step 4: Create the registry**

```php
// app/Services/QuestionTypeRegistry.php
<?php

namespace App\Services;

use App\Contracts\QuestionTypeInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class QuestionTypeRegistry
{
    public function __construct(
        private readonly Container $container,
        private readonly array $types,
    ) {}

    public function resolve(string $typeKey): QuestionTypeInterface
    {
        if (! isset($this->types[$typeKey])) {
            throw new InvalidArgumentException("Unknown question type: {$typeKey}");
        }

        return $this->container->make($this->types[$typeKey]);
    }

    public function registered(): array
    {
        return array_keys($this->types);
    }
}
```

**Step 5: Create stub question type classes** (minimal, just to satisfy registry tests)

```php
// app/QuestionTypes/MultipleChoiceType.php
<?php

namespace App\QuestionTypes;

use App\Contracts\QuestionTypeInterface;
use App\Models\Question;

class MultipleChoiceType implements QuestionTypeInterface
{
    public function renderSpectatorComponent(): string
    {
        return 'question-types.multiple-choice-spectator';
    }

    public function renderPlayerComponent(): string
    {
        return 'question-types.multiple-choice-player';
    }

    public function validateAnswer(mixed $answer, Question $question): bool
    {
        return $answer === $question->correct_answer;
    }

    public function calculatePoints(Question $question, int $timeTakenMs, array $quizSettings): int
    {
        $base = $question->points;
        $timeBonus = $quizSettings['enable_time_bonus'] ?? true;

        if (! $timeBonus) {
            return $base;
        }

        $limitMs = $question->time_limit_seconds * 1000;
        $remaining = max(0, $limitMs - $timeTakenMs);

        return (int) round($base * ($remaining / $limitMs));
    }

    public function validateOptions(array $options): bool
    {
        return count($options) === 4 && collect($options)->every(fn ($o) => isset($o['label']));
    }
}
```

```php
// app/QuestionTypes/TrueFalseType.php
<?php

namespace App\QuestionTypes;

use App\Contracts\QuestionTypeInterface;
use App\Models\Question;

class TrueFalseType implements QuestionTypeInterface
{
    public function renderSpectatorComponent(): string
    {
        return 'question-types.true-false-spectator';
    }

    public function renderPlayerComponent(): string
    {
        return 'question-types.true-false-player';
    }

    public function validateAnswer(mixed $answer, Question $question): bool
    {
        return (bool) $answer === (bool) $question->correct_answer;
    }

    public function calculatePoints(Question $question, int $timeTakenMs, array $quizSettings): int
    {
        $base = $question->points;
        $timeBonus = $quizSettings['enable_time_bonus'] ?? true;

        if (! $timeBonus) {
            return $base;
        }

        $limitMs = $question->time_limit_seconds * 1000;
        $remaining = max(0, $limitMs - $timeTakenMs);

        return (int) round($base * ($remaining / $limitMs));
    }

    public function validateOptions(array $options): bool
    {
        return count($options) === 2;
    }
}
```

**Step 6: Create config and register in service provider**

```php
// config/quiz.php
<?php

return [
    'question_types' => [
        'multiple_choice' => \App\QuestionTypes\MultipleChoiceType::class,
        'true_false' => \App\QuestionTypes\TrueFalseType::class,
    ],
];
```

```php
// In app/Providers/AppServiceProvider.php — add to register() method:
use App\Services\QuestionTypeRegistry;

public function register(): void
{
    $this->app->singleton(QuestionTypeRegistry::class, function ($app) {
        return new QuestionTypeRegistry(
            container: $app,
            types: config('quiz.question_types'),
        );
    });
}
```

**Step 7: Run tests**

```bash
php artisan test tests/Unit/QuestionTypeRegistryTest.php
```

Expected: PASS (3 tests). Note: Question model doesn't exist yet, but registry tests don't need it.

**Step 8: Commit**

```bash
git add -A
git commit -m "feat: add question type interface, registry, and two built-in types"
```

---

## Task 3: Database Migrations & Models

**Files:**
- Create: `database/migrations/xxxx_create_quizzes_table.php`
- Create: `database/migrations/xxxx_create_categories_table.php`
- Create: `database/migrations/xxxx_create_questions_table.php`
- Create: `database/migrations/xxxx_create_game_sessions_table.php`
- Create: `database/migrations/xxxx_create_players_table.php`
- Create: `database/migrations/xxxx_create_player_answers_table.php`
- Create: `app/Models/Quiz.php`
- Create: `app/Models/Category.php`
- Create: `app/Models/Question.php`
- Create: `app/Models/GameSession.php`
- Create: `app/Models/Player.php`
- Create: `app/Models/PlayerAnswer.php`
- Create: `database/factories/QuizFactory.php`
- Create: `database/factories/CategoryFactory.php`
- Create: `database/factories/QuestionFactory.php`
- Create: `database/factories/GameSessionFactory.php`
- Create: `database/factories/PlayerFactory.php`
- Create: `database/factories/PlayerAnswerFactory.php`
- Create: `tests/Unit/Models/QuizModelTest.php`
- Create: `tests/Unit/Models/GameSessionModelTest.php`

**Step 1: Write failing model relationship tests**

```php
// tests/Unit/Models/QuizModelTest.php
<?php

use App\Models\Quiz;
use App\Models\Category;
use App\Models\Question;
use App\Models\User;

test('quiz belongs to a user', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();

    expect($quiz->user->id)->toBe($user->id);
});

test('quiz has many categories', function () {
    $quiz = Quiz::factory()->create();
    Category::factory()->count(3)->for($quiz)->create();

    expect($quiz->categories)->toHaveCount(3);
});

test('quiz settings default to time bonus and streaks enabled', function () {
    $quiz = Quiz::factory()->create();

    expect($quiz->settings)->toBe([
        'enable_time_bonus' => true,
        'enable_streaks' => true,
    ]);
});

test('category has many questions', function () {
    $category = Category::factory()->create();
    Question::factory()->count(5)->for($category)->create();

    expect($category->questions)->toHaveCount(5);
});

test('questions are ordered by order column', function () {
    $category = Category::factory()->create();
    Question::factory()->for($category)->create(['order' => 3]);
    Question::factory()->for($category)->create(['order' => 1]);
    Question::factory()->for($category)->create(['order' => 2]);

    $orders = $category->questions->pluck('order')->toArray();
    expect($orders)->toBe([1, 2, 3]);
});
```

```php
// tests/Unit/Models/GameSessionModelTest.php
<?php

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Quiz;
use App\Models\User;

test('game session belongs to quiz and host', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create();

    expect($session->quiz->id)->toBe($quiz->id);
    expect($session->host->id)->toBe($user->id);
});

test('game session has many players', function () {
    $session = GameSession::factory()->create();
    Player::factory()->count(4)->for($session, 'gameSession')->create();

    expect($session->players)->toHaveCount(4);
});

test('game session generates a 6 char join code on creation', function () {
    $session = GameSession::factory()->create();

    expect($session->join_code)->toHaveLength(6);
    expect($session->join_code)->toMatch('/^[A-Z0-9]{6}$/');
});

test('game session status defaults to waiting', function () {
    $session = GameSession::factory()->create();

    expect($session->status)->toBe('waiting');
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Unit/Models/
```

Expected: FAIL — models and tables don't exist.

**Step 3: Create migrations**

```bash
php artisan make:model Quiz -mf
php artisan make:model Category -mf
php artisan make:model Question -mf
php artisan make:model GameSession -mf
php artisan make:model Player -mf
php artisan make:model PlayerAnswer -mf
```

Then fill in each migration:

**Quizzes migration:**
```php
Schema::create('quizzes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('visibility')->default('private'); // public, private
    $table->json('settings')->default(json_encode([
        'enable_time_bonus' => true,
        'enable_streaks' => true,
    ]));
    $table->timestamps();
});
```

**Categories migration:**
```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('slug');
    $table->string('theme')->default('default');
    $table->text('description')->nullable();
    $table->unsignedInteger('order')->default(0);
    $table->timestamps();
});
```

**Questions migration:**
```php
Schema::create('questions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->string('type'); // maps to QuestionTypeRegistry key
    $table->text('body');
    $table->json('options');
    $table->json('correct_answer');
    $table->unsignedInteger('points')->default(10);
    $table->unsignedInteger('time_limit_seconds')->default(30);
    $table->unsignedInteger('order')->default(0);
    $table->timestamps();
});
```

**GameSessions migration:**
```php
Schema::create('game_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
    $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
    $table->string('join_code', 6)->unique();
    $table->string('status')->default('waiting'); // waiting, playing, reviewing, finished
    $table->unsignedInteger('current_question_index')->default(0);
    $table->foreignId('current_category_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->json('settings')->nullable();
    $table->timestamps();
});
```

**Players migration:**
```php
Schema::create('players', function (Blueprint $table) {
    $table->id();
    $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('nickname');
    $table->unsignedInteger('score')->default(0);
    $table->unsignedInteger('streak')->default(0);
    $table->boolean('is_connected')->default(true);
    $table->timestamps();
});
```

**PlayerAnswers migration:**
```php
Schema::create('player_answers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('player_id')->constrained()->cascadeOnDelete();
    $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
    $table->foreignId('question_id')->constrained()->cascadeOnDelete();
    $table->json('answer')->nullable();
    $table->boolean('is_correct')->default(false);
    $table->unsignedInteger('time_taken_ms')->default(0);
    $table->unsignedInteger('points_earned')->default(0);
    $table->timestamps();

    $table->unique(['player_id', 'question_id']);
});
```

**Step 4: Fill in model classes**

```php
// app/Models/Quiz.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'description', 'visibility', 'settings'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('order');
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function questions(): HasManyThrough
    {
        return $this->hasManyThrough(Question::class, Category::class);
    }
}
```

```php
// app/Models/Category.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['quiz_id', 'name', 'slug', 'theme', 'description', 'order'];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }
}
```

```php
// app/Models/Question.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'type', 'body', 'options', 'correct_answer',
        'points', 'time_limit_seconds', 'order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_answer' => 'json',
            'points' => 'integer',
            'time_limit_seconds' => 'integer',
            'order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

```php
// app/Models/GameSession.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 'host_user_id', 'join_code', 'status',
        'current_question_index', 'current_category_id', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'current_question_index' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GameSession $session) {
            if (empty($session->join_code)) {
                $session->join_code = strtoupper(Str::random(6));
            }
        });
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function currentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'current_category_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }
}
```

```php
// app/Models/Player.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id', 'user_id', 'nickname', 'score', 'streak', 'is_connected',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'streak' => 'integer',
            'is_connected' => 'boolean',
        ];
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }
}
```

```php
// app/Models/PlayerAnswer.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id', 'game_session_id', 'question_id',
        'answer', 'is_correct', 'time_taken_ms', 'points_earned',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'json',
            'is_correct' => 'boolean',
            'time_taken_ms' => 'integer',
            'points_earned' => 'integer',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
```

**Step 5: Create factories**

```php
// database/factories/QuizFactory.php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'visibility' => 'private',
            'settings' => [
                'enable_time_bonus' => true,
                'enable_streaks' => true,
            ],
        ];
    }
}
```

```php
// database/factories/CategoryFactory.php
<?php

namespace Database\Factories;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'name' => fake()->word(),
            'slug' => fake()->slug(1),
            'theme' => 'default',
            'description' => fake()->sentence(),
            'order' => 0,
        ];
    }
}
```

```php
// database/factories/QuestionFactory.php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'type' => 'multiple_choice',
            'body' => fake()->sentence() . '?',
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
}
```

```php
// database/factories/GameSessionFactory.php
<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'host_user_id' => User::factory(),
            'status' => 'waiting',
            'current_question_index' => 0,
        ];
    }
}
```

```php
// database/factories/PlayerFactory.php
<?php

namespace Database\Factories;

use App\Models\GameSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory(),
            'nickname' => fake()->userName(),
            'score' => 0,
            'streak' => 0,
            'is_connected' => true,
        ];
    }
}
```

```php
// database/factories/PlayerAnswerFactory.php
<?php

namespace Database\Factories;

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'game_session_id' => GameSession::factory(),
            'question_id' => Question::factory(),
            'answer' => 'Option A',
            'is_correct' => false,
            'time_taken_ms' => fake()->numberBetween(1000, 30000),
            'points_earned' => 0,
        ];
    }
}
```

**Step 6: Run migrations and tests**

```bash
php artisan migrate:fresh
php artisan test tests/Unit/Models/
```

Expected: PASS (all model tests).

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: add database schema, models, and factories for quiz game"
```

---

## Task 4: Scoring Service

**Files:**
- Create: `app/Services/ScoringService.php`
- Create: `tests/Unit/ScoringServiceTest.php`

**Step 1: Write failing tests**

```php
// tests/Unit/ScoringServiceTest.php
<?php

use App\Models\Question;
use App\Services\ScoringService;

test('full points when time bonus disabled', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => false];

    $points = $service->calculate($question, timeTakenMs: 25000, streak: 0, settings: $settings);

    expect($points)->toBe(10);
});

test('time bonus gives full points at instant answer', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => false];

    $points = $service->calculate($question, timeTakenMs: 0, streak: 0, settings: $settings);

    expect($points)->toBe(10);
});

test('time bonus gives zero points at time limit', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => false];

    $points = $service->calculate($question, timeTakenMs: 30000, streak: 0, settings: $settings);

    expect($points)->toBe(0);
});

test('time bonus gives half points at half time', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => false];

    $points = $service->calculate($question, timeTakenMs: 15000, streak: 0, settings: $settings);

    expect($points)->toBe(5);
});

test('streak multiplier 1x for streak 0-2', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => true];

    expect($service->calculate($question, 0, streak: 0, settings: $settings))->toBe(10);
    expect($service->calculate($question, 0, streak: 1, settings: $settings))->toBe(10);
    expect($service->calculate($question, 0, streak: 2, settings: $settings))->toBe(10);
});

test('streak multiplier 1.5x for streak 3-4', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => true];

    expect($service->calculate($question, 0, streak: 3, settings: $settings))->toBe(15);
    expect($service->calculate($question, 0, streak: 4, settings: $settings))->toBe(15);
});

test('streak multiplier 2x for streak 5+', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => true];

    expect($service->calculate($question, 0, streak: 5, settings: $settings))->toBe(20);
    expect($service->calculate($question, 0, streak: 10, settings: $settings))->toBe(20);
});

test('time bonus and streak combine correctly', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => true, 'enable_streaks' => true];

    // Half time (5 pts base) * 2x streak = 10
    $points = $service->calculate($question, timeTakenMs: 15000, streak: 5, settings: $settings);

    expect($points)->toBe(10);
});

test('streaks disabled ignores streak value', function () {
    $service = new ScoringService();
    $question = Question::factory()->make(['points' => 10, 'time_limit_seconds' => 30]);
    $settings = ['enable_time_bonus' => false, 'enable_streaks' => false];

    $points = $service->calculate($question, 0, streak: 10, settings: $settings);

    expect($points)->toBe(10);
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Unit/ScoringServiceTest.php
```

Expected: FAIL — ScoringService doesn't exist.

**Step 3: Implement ScoringService**

```php
// app/Services/ScoringService.php
<?php

namespace App\Services;

use App\Models\Question;

class ScoringService
{
    public function calculate(Question $question, int $timeTakenMs, int $streak, array $settings): int
    {
        $base = $question->points;

        $timeFactor = 1.0;
        if ($settings['enable_time_bonus'] ?? true) {
            $limitMs = $question->time_limit_seconds * 1000;
            $remaining = max(0, $limitMs - $timeTakenMs);
            $timeFactor = $remaining / $limitMs;
        }

        $streakMultiplier = 1.0;
        if ($settings['enable_streaks'] ?? true) {
            $streakMultiplier = match (true) {
                $streak >= 5 => 2.0,
                $streak >= 3 => 1.5,
                default => 1.0,
            };
        }

        return (int) round($base * $timeFactor * $streakMultiplier);
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/ScoringServiceTest.php
```

Expected: PASS (all 9 tests).

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add scoring service with time bonus and streak multiplier support"
```

---

## Task 5: Question Type Unit Tests

**Files:**
- Create: `tests/Unit/QuestionTypes/MultipleChoiceTypeTest.php`
- Create: `tests/Unit/QuestionTypes/TrueFalseTypeTest.php`

**Step 1: Write tests for MultipleChoice**

```php
// tests/Unit/QuestionTypes/MultipleChoiceTypeTest.php
<?php

use App\Models\Question;
use App\QuestionTypes\MultipleChoiceType;

test('validates correct answer', function () {
    $type = new MultipleChoiceType();
    $question = Question::factory()->make([
        'correct_answer' => 'Option B',
    ]);

    expect($type->validateAnswer('Option B', $question))->toBeTrue();
    expect($type->validateAnswer('Option A', $question))->toBeFalse();
});

test('validates options require exactly 4 with labels', function () {
    $type = new MultipleChoiceType();

    $valid = [
        ['label' => 'A'], ['label' => 'B'], ['label' => 'C'], ['label' => 'D'],
    ];
    expect($type->validateOptions($valid))->toBeTrue();

    $tooFew = [['label' => 'A'], ['label' => 'B']];
    expect($type->validateOptions($tooFew))->toBeFalse();

    $noLabel = [['text' => 'A'], ['label' => 'B'], ['label' => 'C'], ['label' => 'D']];
    expect($type->validateOptions($noLabel))->toBeFalse();
});

test('returns correct livewire component names', function () {
    $type = new MultipleChoiceType();

    expect($type->renderSpectatorComponent())->toBe('question-types.multiple-choice-spectator');
    expect($type->renderPlayerComponent())->toBe('question-types.multiple-choice-player');
});
```

**Step 2: Write tests for TrueFalse**

```php
// tests/Unit/QuestionTypes/TrueFalseTypeTest.php
<?php

use App\Models\Question;
use App\QuestionTypes\TrueFalseType;

test('validates correct answer as boolean', function () {
    $type = new TrueFalseType();
    $question = Question::factory()->make(['correct_answer' => true]);

    expect($type->validateAnswer(true, $question))->toBeTrue();
    expect($type->validateAnswer(false, $question))->toBeFalse();
});

test('validates options require exactly 2', function () {
    $type = new TrueFalseType();

    expect($type->validateOptions([['label' => 'True'], ['label' => 'False']]))->toBeTrue();
    expect($type->validateOptions([['label' => 'A']]))->toBeFalse();
});

test('returns correct livewire component names', function () {
    $type = new TrueFalseType();

    expect($type->renderSpectatorComponent())->toBe('question-types.true-false-spectator');
    expect($type->renderPlayerComponent())->toBe('question-types.true-false-player');
});
```

**Step 3: Run tests**

```bash
php artisan test tests/Unit/QuestionTypes/
```

Expected: PASS (all tests).

**Step 4: Commit**

```bash
git add -A
git commit -m "test: add unit tests for multiple choice and true/false question types"
```

---

## Task 6: Game Session State Machine Service

**Files:**
- Create: `app/Services/GameService.php`
- Create: `tests/Unit/GameServiceTest.php`

**Step 1: Write failing tests**

```php
// tests/Unit/GameServiceTest.php
<?php

use App\Models\GameSession;
use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GameService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create();
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    Question::factory()->count(3)->for($this->category)->sequence(
        ['order' => 0],
        ['order' => 1],
        ['order' => 2],
    )->create();
    $this->session = GameSession::factory()
        ->for($this->quiz)
        ->for($this->user, 'host')
        ->create(['status' => 'waiting']);
    $this->service = new GameService();
});

test('start game transitions from waiting to playing', function () {
    $this->service->start($this->session);

    expect($this->session->fresh()->status)->toBe('playing');
    expect($this->session->fresh()->current_question_index)->toBe(0);
    expect($this->session->fresh()->current_category_id)->toBe($this->category->id);
});

test('start game fails if not in waiting status', function () {
    $this->session->update(['status' => 'playing']);

    $this->service->start($this->session);
})->throws(LogicException::class);

test('getCurrentQuestion returns the correct question', function () {
    $this->service->start($this->session);

    $question = $this->service->getCurrentQuestion($this->session->fresh());

    expect($question->order)->toBe(0);
});

test('advanceToNextQuestion increments index', function () {
    $this->service->start($this->session);
    $this->session->update(['status' => 'reviewing']);

    $result = $this->service->advanceToNextQuestion($this->session->fresh());

    expect($result)->toBeTrue();
    expect($this->session->fresh()->current_question_index)->toBe(1);
    expect($this->session->fresh()->status)->toBe('playing');
});

test('advanceToNextQuestion returns false when no more questions', function () {
    $this->service->start($this->session);
    $this->session->update([
        'status' => 'reviewing',
        'current_question_index' => 2,
    ]);

    $result = $this->service->advanceToNextQuestion($this->session->fresh());

    expect($result)->toBeFalse();
    expect($this->session->fresh()->status)->toBe('finished');
});

test('finishQuestion transitions from playing to reviewing', function () {
    $this->service->start($this->session);

    $this->service->finishQuestion($this->session->fresh());

    expect($this->session->fresh()->status)->toBe('reviewing');
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Unit/GameServiceTest.php
```

Expected: FAIL.

**Step 3: Implement GameService**

```php
// app/Services/GameService.php
<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Question;
use LogicException;

class GameService
{
    public function start(GameSession $session): void
    {
        if ($session->status !== 'waiting') {
            throw new LogicException("Cannot start game in '{$session->status}' status.");
        }

        $firstCategory = $session->quiz->categories()->orderBy('order')->first();

        $session->update([
            'status' => 'playing',
            'current_question_index' => 0,
            'current_category_id' => $firstCategory?->id,
        ]);
    }

    public function getCurrentQuestion(GameSession $session): ?Question
    {
        $questions = $this->getAllQuestionsOrdered($session);

        return $questions->get($session->current_question_index);
    }

    public function finishQuestion(GameSession $session): void
    {
        if ($session->status !== 'playing') {
            throw new LogicException("Cannot finish question in '{$session->status}' status.");
        }

        $session->update(['status' => 'reviewing']);
    }

    public function advanceToNextQuestion(GameSession $session): bool
    {
        if ($session->status !== 'reviewing') {
            throw new LogicException("Cannot advance in '{$session->status}' status.");
        }

        $questions = $this->getAllQuestionsOrdered($session);
        $nextIndex = $session->current_question_index + 1;

        if ($nextIndex >= $questions->count()) {
            $session->update(['status' => 'finished']);
            return false;
        }

        $nextQuestion = $questions->get($nextIndex);
        $session->update([
            'current_question_index' => $nextIndex,
            'current_category_id' => $nextQuestion->category_id,
            'status' => 'playing',
        ]);

        return true;
    }

    private function getAllQuestionsOrdered(GameSession $session): \Illuminate\Support\Collection
    {
        return $session->quiz
            ->categories()
            ->orderBy('order')
            ->with(['questions' => fn ($q) => $q->orderBy('order')])
            ->get()
            ->flatMap->questions
            ->values();
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/GameServiceTest.php
```

Expected: PASS (all 6 tests).

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add game service with state machine for session lifecycle"
```

---

## Task 7: Broadcast Events

**Files:**
- Create: `app/Events/PlayerJoined.php`
- Create: `app/Events/CategoryChanged.php`
- Create: `app/Events/QuestionStarted.php`
- Create: `app/Events/PlayerAnswered.php`
- Create: `app/Events/QuestionEnded.php`
- Create: `app/Events/GameFinished.php`
- Create: `tests/Feature/Events/BroadcastEventsTest.php`

**Step 1: Write failing tests**

```php
// tests/Feature/Events/BroadcastEventsTest.php
<?php

use App\Events\PlayerJoined;
use App\Events\CategoryChanged;
use App\Events\QuestionStarted;
use App\Events\PlayerAnswered;
use App\Events\QuestionEnded;
use App\Events\GameFinished;
use App\Models\GameSession;
use App\Models\Category;
use App\Models\Player;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;

test('PlayerJoined broadcasts on game channel', function () {
    $session = GameSession::factory()->create();
    $player = Player::factory()->for($session, 'gameSession')->create();

    $event = new PlayerJoined($session, $player);
    $channels = $event->broadcastOn();

    expect(collect($channels)->map->name)->toContain('game.' . $session->id);
});

test('QuestionStarted broadcasts on game channel without correct answer', function () {
    $session = GameSession::factory()->create();
    $question = Question::factory()->create();

    $event = new QuestionStarted($session, $question);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('body');
    expect($data)->toHaveKey('options');
    expect($data)->not->toHaveKey('correct_answer');
});

test('QuestionEnded broadcasts with correct answer and scores', function () {
    $session = GameSession::factory()->create();
    $question = Question::factory()->create();

    $event = new QuestionEnded($session, $question, scores: [['player_id' => 1, 'points' => 10]]);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('correct_answer');
    expect($data)->toHaveKey('scores');
});

test('GameFinished broadcasts final leaderboard', function () {
    $session = GameSession::factory()->create();

    $event = new GameFinished($session, leaderboard: [['nickname' => 'Alex', 'score' => 100]]);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('leaderboard');
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Events/BroadcastEventsTest.php
```

Expected: FAIL.

**Step 3: Create event classes**

```php
// app/Events/PlayerJoined.php
<?php

namespace App\Events;

use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class PlayerJoined implements ShouldBroadcastNow
{
    public function __construct(
        public readonly GameSession $session,
        public readonly Player $player,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'player_id' => $this->player->id,
            'nickname' => $this->player->nickname,
            'player_count' => $this->session->players()->count(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.joined';
    }
}
```

```php
// app/Events/CategoryChanged.php
<?php

namespace App\Events;

use App\Models\Category;
use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CategoryChanged implements ShouldBroadcastNow
{
    public function __construct(
        public readonly GameSession $session,
        public readonly Category $category,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'category_id' => $this->category->id,
            'name' => $this->category->name,
            'theme' => $this->category->theme,
        ];
    }

    public function broadcastAs(): string
    {
        return 'category.changed';
    }
}
```

```php
// app/Events/QuestionStarted.php
<?php

namespace App\Events;

use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class QuestionStarted implements ShouldBroadcastNow
{
    public function __construct(
        public readonly GameSession $session,
        public readonly Question $question,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->question->id,
            'body' => $this->question->body,
            'type' => $this->question->type,
            'options' => $this->question->options,
            'time_limit_seconds' => $this->question->time_limit_seconds,
            'question_index' => $this->session->current_question_index,
        ];
    }

    public function broadcastAs(): string
    {
        return 'question.started';
    }
}
```

```php
// app/Events/PlayerAnswered.php
<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class PlayerAnswered implements ShouldBroadcastNow
{
    public function __construct(
        public readonly GameSession $session,
        public readonly int $answeredCount,
        public readonly int $totalPlayers,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'answered_count' => $this->answeredCount,
            'total_players' => $this->totalPlayers,
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.answered';
    }
}
```

```php
// app/Events/QuestionEnded.php
<?php

namespace App\Events;

use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class QuestionEnded implements ShouldBroadcastNow
{
    public function __construct(
        public readonly GameSession $session,
        public readonly Question $question,
        public readonly array $scores,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->question->id,
            'correct_answer' => $this->question->correct_answer,
            'scores' => $this->scores,
        ];
    }

    public function broadcastAs(): string
    {
        return 'question.ended';
    }
}
```

```php
// app/Events/GameFinished.php
<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class GameFinished implements ShouldBroadcastNow
{
    public function __construct(
        public readonly GameSession $session,
        public readonly array $leaderboard,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('game.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'leaderboard' => $this->leaderboard,
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.finished';
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Feature/Events/BroadcastEventsTest.php
```

Expected: PASS.

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add broadcast events for all game phases"
```

---

## Task 8: Theme Config

**Files:**
- Create: `config/themes.php`
- Create: `tests/Unit/ThemeConfigTest.php`

**Step 1: Write failing test**

```php
// tests/Unit/ThemeConfigTest.php
<?php

test('all themes have required keys', function () {
    $themes = config('themes');

    expect($themes)->not->toBeEmpty();

    foreach ($themes as $key => $theme) {
        expect($theme)->toHaveKeys(['gradient', 'accent', 'icon', 'background_pattern']);
    }
});

test('default theme exists', function () {
    expect(config('themes.default'))->not->toBeNull();
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Unit/ThemeConfigTest.php
```

**Step 3: Create theme config**

```php
// config/themes.php
<?php

return [
    'default' => [
        'gradient' => 'from-gray-800 to-gray-900',
        'accent' => 'blue-400',
        'icon' => 'question-mark-circle',
        'background_pattern' => 'none',
    ],
    'science' => [
        'gradient' => 'from-indigo-900 to-purple-900',
        'accent' => 'cyan-400',
        'icon' => 'beaker',
        'background_pattern' => 'molecules',
    ],
    'nature' => [
        'gradient' => 'from-green-900 to-emerald-900',
        'accent' => 'lime-400',
        'icon' => 'leaf',
        'background_pattern' => 'leaves',
    ],
    'history' => [
        'gradient' => 'from-amber-900 to-yellow-900',
        'accent' => 'amber-300',
        'icon' => 'book-open',
        'background_pattern' => 'parchment',
    ],
    'pop-culture' => [
        'gradient' => 'from-pink-900 to-rose-900',
        'accent' => 'pink-400',
        'icon' => 'star',
        'background_pattern' => 'stars',
    ],
    'sports' => [
        'gradient' => 'from-red-900 to-orange-900',
        'accent' => 'orange-400',
        'icon' => 'trophy',
        'background_pattern' => 'field',
    ],
    'geography' => [
        'gradient' => 'from-sky-900 to-blue-900',
        'accent' => 'sky-400',
        'icon' => 'globe-alt',
        'background_pattern' => 'map',
    ],
];
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/ThemeConfigTest.php
```

Expected: PASS.

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add category theme configuration with 7 built-in themes"
```

---

## Task 9: Quiz CRUD — Routes & Livewire Components

**Files:**
- Modify: `routes/web.php`
- Create: `app/Livewire/QuizIndex.php` + view
- Create: `app/Livewire/QuizBuilder.php` + view
- Create: `tests/Feature/QuizCrudTest.php`

**Step 1: Write failing feature tests**

```php
// tests/Feature/QuizCrudTest.php
<?php

use App\Models\Quiz;
use App\Models\User;
use App\Livewire\QuizIndex;
use App\Livewire\QuizBuilder;
use Livewire\Livewire;

test('guest cannot access quiz index', function () {
    $this->get('/quizzes')->assertRedirect('/login');
});

test('authenticated user sees their quizzes', function () {
    $user = User::factory()->create();
    Quiz::factory()->count(3)->for($user)->create();

    Livewire::actingAs($user)
        ->test(QuizIndex::class)
        ->assertSee($user->quizzes->first()->title);
});

test('user can create a quiz', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class)
        ->set('title', 'My Trivia Quiz')
        ->set('description', 'A fun quiz')
        ->call('save')
        ->assertHasNoErrors();

    expect(Quiz::where('user_id', $user->id)->count())->toBe(1);
    expect(Quiz::first()->title)->toBe('My Trivia Quiz');
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
    $quiz = Quiz::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->set('newCategoryName', 'Science')
        ->set('newCategoryTheme', 'science')
        ->call('addCategory')
        ->assertHasNoErrors();

    expect($quiz->categories()->count())->toBe(1);
    expect($quiz->categories->first()->name)->toBe('Science');
});

test('user can add a question to a category', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = \App\Models\Category::factory()->for($quiz)->create();

    Livewire::actingAs($user)
        ->test(QuizBuilder::class, ['quiz' => $quiz])
        ->call('addQuestion', $category->id, [
            'type' => 'multiple_choice',
            'body' => 'What is 2+2?',
            'options' => [
                ['label' => '3'],
                ['label' => '4'],
                ['label' => '5'],
                ['label' => '6'],
            ],
            'correct_answer' => '4',
            'points' => 10,
            'time_limit_seconds' => 30,
        ])
        ->assertHasNoErrors();

    expect($category->questions()->count())->toBe(1);
});

test('user cannot edit another users quiz', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get("/quizzes/{$quiz->id}/edit")
        ->assertForbidden();
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/QuizCrudTest.php
```

**Step 3: Add routes**

```php
// In routes/web.php — add within auth middleware group:
use App\Livewire\QuizIndex;
use App\Livewire\QuizBuilder;

Route::middleware(['auth'])->group(function () {
    Route::get('/quizzes', QuizIndex::class)->name('quizzes.index');
    Route::get('/quizzes/create', QuizBuilder::class)->name('quizzes.create');
    Route::get('/quizzes/{quiz}/edit', QuizBuilder::class)->name('quizzes.edit');
});
```

**Step 4: Create QuizIndex component**

```php
// app/Livewire/QuizIndex.php
<?php

namespace App\Livewire;

use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class QuizIndex extends Component
{
    public function render()
    {
        return view('livewire.quiz-index', [
            'quizzes' => Quiz::where('user_id', Auth::id())
                ->withCount('categories')
                ->latest()
                ->get(),
        ])->layout('components.layouts.app');
    }

    public function delete(Quiz $quiz)
    {
        $this->authorize('delete', $quiz);
        $quiz->delete();
    }
}
```

```blade
{{-- resources/views/livewire/quiz-index.blade.php --}}
<div class="max-w-4xl mx-auto py-8 px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">My Quizzes</h1>
        <a href="{{ route('quizzes.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            New Quiz
        </a>
    </div>

    <div class="space-y-4">
        @forelse ($quizzes as $quiz)
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-semibold">{{ $quiz->title }}</h2>
                    <p class="text-sm text-gray-500">{{ $quiz->categories_count }} categories</p>
                </div>
                <a href="{{ route('quizzes.edit', $quiz) }}" class="text-blue-500 hover:underline">Edit</a>
            </div>
        @empty
            <p class="text-gray-500">No quizzes yet. Create your first one!</p>
        @endforelse
    </div>
</div>
```

**Step 5: Create QuizBuilder component**

```php
// app/Livewire/QuizBuilder.php
<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class QuizBuilder extends Component
{
    public ?Quiz $quiz = null;

    public string $title = '';
    public string $description = '';
    public bool $enableTimeBonus = true;
    public bool $enableStreaks = true;

    public string $newCategoryName = '';
    public string $newCategoryTheme = 'default';

    public function mount(?Quiz $quiz = null)
    {
        if ($quiz?->exists) {
            if ($quiz->user_id !== Auth::id()) {
                abort(403);
            }
            $this->quiz = $quiz;
            $this->title = $quiz->title;
            $this->description = $quiz->description ?? '';
            $this->enableTimeBonus = $quiz->settings['enable_time_bonus'] ?? true;
            $this->enableStreaks = $quiz->settings['enable_streaks'] ?? true;
        }
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|min:3',
        ]);

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'settings' => [
                'enable_time_bonus' => $this->enableTimeBonus,
                'enable_streaks' => $this->enableStreaks,
            ],
        ];

        if ($this->quiz) {
            $this->quiz->update($data);
        } else {
            $this->quiz = Auth::user()->quizzes()->create($data);
        }
    }

    public function addCategory()
    {
        $this->validate([
            'newCategoryName' => 'required|min:1',
            'newCategoryTheme' => 'required',
        ]);

        $maxOrder = $this->quiz->categories()->max('order') ?? -1;

        $this->quiz->categories()->create([
            'name' => $this->newCategoryName,
            'slug' => Str::slug($this->newCategoryName),
            'theme' => $this->newCategoryTheme,
            'order' => $maxOrder + 1,
        ]);

        $this->reset('newCategoryName', 'newCategoryTheme');
    }

    public function addQuestion(int $categoryId, array $questionData)
    {
        $category = Category::findOrFail($categoryId);

        if ($category->quiz_id !== $this->quiz->id) {
            abort(403);
        }

        $maxOrder = $category->questions()->max('order') ?? -1;

        $category->questions()->create([
            'type' => $questionData['type'],
            'body' => $questionData['body'],
            'options' => $questionData['options'],
            'correct_answer' => $questionData['correct_answer'],
            'points' => $questionData['points'] ?? 10,
            'time_limit_seconds' => $questionData['time_limit_seconds'] ?? 30,
            'order' => $maxOrder + 1,
        ]);
    }

    public function render()
    {
        return view('livewire.quiz-builder', [
            'categories' => $this->quiz?->categories()->with('questions')->orderBy('order')->get() ?? collect(),
            'themes' => array_keys(config('themes')),
            'questionTypes' => array_keys(config('quiz.question_types')),
        ])->layout('components.layouts.app');
    }
}
```

```blade
{{-- resources/views/livewire/quiz-builder.blade.php --}}
<div class="max-w-4xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-6">{{ $quiz ? 'Edit Quiz' : 'Create Quiz' }}</h1>

    {{-- Quiz details --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow mb-6">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Title</label>
                <input type="text" wire:model="title" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea wire:model="description" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"></textarea>
            </div>
            <div class="flex gap-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="enableTimeBonus"> Time Bonus
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="enableStreaks"> Streaks
                </label>
            </div>
            <button wire:click="save" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Quiz</button>
        </div>
    </div>

    @if ($quiz)
        {{-- Categories --}}
        <div class="space-y-4">
            @foreach ($categories as $category)
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <h3 class="text-lg font-semibold">{{ $category->name }} <span class="text-sm text-gray-500">({{ $category->theme }})</span></h3>
                    <div class="mt-2 space-y-2">
                        @foreach ($category->questions as $question)
                            <div class="pl-4 border-l-2 border-gray-200 dark:border-gray-700">
                                <p class="text-sm">{{ $question->body }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Add category form --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow mt-6">
            <h3 class="font-semibold mb-2">Add Category</h3>
            <div class="flex gap-2">
                <input type="text" wire:model="newCategoryName" placeholder="Category name" class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                <select wire:model="newCategoryTheme" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    @foreach ($themes as $theme)
                        <option value="{{ $theme }}">{{ ucfirst($theme) }}</option>
                    @endforeach
                </select>
                <button wire:click="addCategory" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Add</button>
            </div>
        </div>
    @endif
</div>
```

**Step 6: Add `quizzes` relationship to User model**

In `app/Models/User.php`, add:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function quizzes(): HasMany
{
    return $this->hasMany(Quiz::class);
}
```

**Step 7: Run tests**

```bash
php artisan test tests/Feature/QuizCrudTest.php
```

Expected: PASS.

**Step 8: Commit**

```bash
git add -A
git commit -m "feat: add quiz CRUD with Livewire components for index and builder"
```

---

## Task 10: Game Session — Create, Join, Lobby

**Files:**
- Modify: `routes/web.php`
- Create: `app/Livewire/GameLobby.php` + view
- Create: `app/Livewire/JoinGame.php` + view
- Create: `app/Livewire/SpectatorScreen.php` + view
- Create: `app/Livewire/PlayerScreen.php` + view
- Create: `app/Livewire/HostDashboard.php` + view
- Create: `tests/Feature/GameLobbyTest.php`

**Step 1: Write failing tests**

```php
// tests/Feature/GameLobbyTest.php
<?php

use App\Models\Quiz;
use App\Models\Category;
use App\Models\Question;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\User;
use App\Livewire\GameLobby;
use App\Livewire\JoinGame;
use App\Livewire\HostDashboard;
use App\Events\PlayerJoined;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('host can create a game session from a quiz', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    Category::factory()->for($quiz)->has(Question::factory()->count(3))->create();

    $this->actingAs($user)
        ->post("/game/create/{$quiz->id}")
        ->assertRedirectToRoute('game.host', ['code' => GameSession::first()->join_code]);

    expect(GameSession::count())->toBe(1);
    expect(GameSession::first()->status)->toBe('waiting');
});

test('player can join a game with a nickname', function () {
    Event::fake([PlayerJoined::class]);
    $session = GameSession::factory()->create();

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'Alex')
        ->call('join')
        ->assertHasNoErrors();

    expect($session->players()->count())->toBe(1);
    expect($session->players->first()->nickname)->toBe('Alex');
    Event::assertDispatched(PlayerJoined::class);
});

test('duplicate nickname gets number appended', function () {
    $session = GameSession::factory()->create();
    Player::factory()->for($session, 'gameSession')->create(['nickname' => 'Alex']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'Alex')
        ->call('join')
        ->assertHasNoErrors();

    expect($session->players()->count())->toBe(2);
    expect($session->players()->latest('id')->first()->nickname)->toBe('Alex 2');
});

test('player cannot join a game that is already playing', function () {
    $session = GameSession::factory()->create(['status' => 'playing']);

    Livewire::test(JoinGame::class, ['code' => $session->join_code])
        ->set('nickname', 'Alex')
        ->call('join')
        ->assertHasErrors(['join']);
});

test('non-host cannot access host dashboard', function () {
    $session = GameSession::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get("/game/{$session->join_code}/host")
        ->assertForbidden();
});

test('spectator page is accessible without auth', function () {
    $session = GameSession::factory()->create();

    $this->get("/game/{$session->join_code}/spectator")
        ->assertOk();
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/GameLobbyTest.php
```

**Step 3: Add routes**

```php
// Add to routes/web.php:
use App\Livewire\JoinGame;
use App\Livewire\SpectatorScreen;
use App\Livewire\PlayerScreen;
use App\Livewire\HostDashboard;
use App\Http\Controllers\GameController;

Route::middleware(['auth'])->group(function () {
    // ... existing quiz routes ...
    Route::post('/game/create/{quiz}', [GameController::class, 'create'])->name('game.create');
    Route::get('/game/{code}/host', HostDashboard::class)->name('game.host');
});

Route::get('/game/{code}/spectator', SpectatorScreen::class)->name('game.spectator');
Route::get('/game/{code}/play', PlayerScreen::class)->name('game.play');
Route::get('/join/{code}', JoinGame::class)->name('game.join');
```

**Step 4: Create GameController**

```php
// app/Http/Controllers/GameController.php
<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\GameSession;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    public function create(Quiz $quiz)
    {
        if ($quiz->user_id !== Auth::id()) {
            abort(403);
        }

        $session = GameSession::create([
            'quiz_id' => $quiz->id,
            'host_user_id' => Auth::id(),
        ]);

        return redirect()->route('game.host', ['code' => $session->join_code]);
    }
}
```

**Step 5: Create JoinGame component**

```php
// app/Livewire/JoinGame.php
<?php

namespace App\Livewire;

use App\Events\PlayerJoined;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class JoinGame extends Component
{
    public string $code = '';
    public string $nickname = '';
    public ?Player $player = null;

    public function mount(string $code)
    {
        $this->code = strtoupper($code);
    }

    public function join()
    {
        $session = GameSession::where('join_code', $this->code)->first();

        if (! $session || $session->status !== 'waiting') {
            $this->addError('join', 'This game is not accepting new players.');
            return;
        }

        $this->validate(['nickname' => 'required|min:1|max:20']);

        $nickname = $this->resolveUniqueNickname($session, $this->nickname);

        $this->player = $session->players()->create([
            'nickname' => $nickname,
            'user_id' => Auth::id(),
        ]);

        broadcast(new PlayerJoined($session, $this->player));

        $this->redirect(route('game.play', ['code' => $this->code]) . '?player_id=' . $this->player->id);
    }

    private function resolveUniqueNickname(GameSession $session, string $nickname): string
    {
        $existing = $session->players()->where('nickname', $nickname)->exists();

        if (! $existing) {
            return $nickname;
        }

        $counter = 2;
        while ($session->players()->where('nickname', "{$nickname} {$counter}")->exists()) {
            $counter++;
        }

        return "{$nickname} {$counter}";
    }

    public function render()
    {
        return view('livewire.join-game')->layout('components.layouts.app');
    }
}
```

```blade
{{-- resources/views/livewire/join-game.blade.php --}}
<div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-lg max-w-sm w-full text-center">
        <h1 class="text-2xl font-bold mb-2">Join Game</h1>
        <p class="text-gray-500 mb-6">Code: {{ $code }}</p>

        <div class="space-y-4">
            <input type="text" wire:model="nickname" placeholder="Your nickname"
                   class="w-full text-center text-lg rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            @error('nickname') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            @error('join') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

            <button wire:click="join" class="w-full py-3 bg-blue-600 text-white rounded-lg text-lg font-semibold hover:bg-blue-700">
                Join
            </button>
        </div>
    </div>
</div>
```

**Step 6: Create HostDashboard, SpectatorScreen, PlayerScreen stubs**

These are the skeleton components. Full game-flow UI will be added in Task 11.

```php
// app/Livewire/HostDashboard.php
<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Services\GameService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HostDashboard extends Component
{
    public GameSession $session;

    public function mount(string $code)
    {
        $this->session = GameSession::where('join_code', $code)->firstOrFail();

        if ($this->session->host_user_id !== Auth::id()) {
            abort(403);
        }
    }

    public function startGame()
    {
        app(GameService::class)->start($this->session);
    }

    public function nextQuestion()
    {
        app(GameService::class)->advanceToNextQuestion($this->session->fresh());
    }

    public function render()
    {
        return view('livewire.host-dashboard', [
            'players' => $this->session->players()->get(),
        ])->layout('components.layouts.app');
    }
}
```

```blade
{{-- resources/views/livewire/host-dashboard.blade.php --}}
<div class="max-w-2xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-4">Host Dashboard</h1>
    <p class="mb-2">Code: <span class="font-mono text-xl">{{ $session->join_code }}</span></p>
    <p class="mb-4">Status: {{ $session->status }}</p>

    <div class="mb-6">
        <h2 class="font-semibold mb-2">Players ({{ $players->count() }})</h2>
        <ul class="space-y-1">
            @foreach ($players as $player)
                <li class="flex items-center gap-2">
                    <span class="{{ $player->is_connected ? 'text-green-500' : 'text-red-500' }}">●</span>
                    {{ $player->nickname }}
                </li>
            @endforeach
        </ul>
    </div>

    @if ($session->status === 'waiting')
        <button wire:click="startGame" class="px-6 py-3 bg-green-600 text-white rounded-lg text-lg font-semibold hover:bg-green-700">
            Start Game
        </button>
    @elseif ($session->status === 'reviewing')
        <button wire:click="nextQuestion" class="px-6 py-3 bg-blue-600 text-white rounded-lg text-lg font-semibold hover:bg-blue-700">
            Next Question
        </button>
    @endif
</div>
```

```php
// app/Livewire/SpectatorScreen.php
<?php

namespace App\Livewire;

use App\Models\GameSession;
use Livewire\Component;

class SpectatorScreen extends Component
{
    public GameSession $session;

    public function mount(string $code)
    {
        $this->session = GameSession::where('join_code', $code)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.spectator-screen')->layout('components.layouts.app');
    }
}
```

```blade
{{-- resources/views/livewire/spectator-screen.blade.php --}}
<div class="min-h-screen flex items-center justify-center bg-gray-900 text-white">
    @if ($session->status === 'waiting')
        <div class="text-center">
            <h1 class="text-6xl font-bold mb-8">Join the game!</h1>
            <p class="text-4xl font-mono mb-8">{{ $session->join_code }}</p>
            <div class="mb-8">
                {!! QrCode::size(250)->generate(route('game.join', $session->join_code)) !!}
            </div>
            <p class="text-xl text-gray-400">{{ $session->players()->count() }} players joined</p>
        </div>
    @else
        <p class="text-2xl">Game in progress...</p>
    @endif
</div>
```

Note: QR code generation requires `simplesoftwareio/simple-qrcode` package:
```bash
composer require simplesoftwareio/simple-qrcode
```

```php
// app/Livewire/PlayerScreen.php
<?php

namespace App\Livewire;

use App\Models\GameSession;
use App\Models\Player;
use Livewire\Component;

class PlayerScreen extends Component
{
    public GameSession $session;
    public Player $player;

    public function mount(string $code)
    {
        $this->session = GameSession::where('join_code', $code)->firstOrFail();

        $playerId = request()->query('player_id');
        $this->player = Player::findOrFail($playerId);
    }

    public function render()
    {
        return view('livewire.player-screen')->layout('components.layouts.app');
    }
}
```

```blade
{{-- resources/views/livewire/player-screen.blade.php --}}
<div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900">
    @if ($session->status === 'waiting')
        <div class="text-center">
            <h1 class="text-2xl font-bold mb-2">Welcome, {{ $player->nickname }}!</h1>
            <p class="text-gray-500">Waiting for the host to start...</p>
        </div>
    @else
        <p>Game in progress...</p>
    @endif
</div>
```

**Step 7: Run tests**

```bash
php artisan test tests/Feature/GameLobbyTest.php
```

Expected: PASS.

**Step 8: Commit**

```bash
git add -A
git commit -m "feat: add game session creation, join flow, and lobby screens"
```

---

## Task 11: Game Flow — Host Controls, Spectator Display, Player Answers

**Files:**
- Modify: `app/Livewire/HostDashboard.php`
- Modify: `app/Livewire/SpectatorScreen.php`
- Modify: `app/Livewire/PlayerScreen.php`
- Create: `app/Actions/SubmitAnswer.php`
- Create: `tests/Feature/GameFlowTest.php`

**Step 1: Write failing feature tests**

```php
// tests/Feature/GameFlowTest.php
<?php

use App\Models\Quiz;
use App\Models\Category;
use App\Models\Question;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\User;
use App\Actions\SubmitAnswer;
use App\Events\PlayerAnswered;
use App\Events\QuestionStarted;
use App\Events\QuestionEnded;
use App\Events\CategoryChanged;
use App\Events\GameFinished;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->quiz = Quiz::factory()->for($this->user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $this->category = Category::factory()->for($this->quiz)->create(['order' => 0]);
    $this->questions = Question::factory()->count(2)->for($this->category)->sequence(
        ['order' => 0, 'correct_answer' => 'Option A', 'points' => 10],
        ['order' => 1, 'correct_answer' => 'Option B', 'points' => 10],
    )->create();
    $this->session = GameSession::factory()
        ->for($this->quiz)
        ->for($this->user, 'host')
        ->create(['status' => 'waiting']);
    $this->player = Player::factory()
        ->for($this->session, 'gameSession')
        ->create(['nickname' => 'Alex']);
});

test('starting game broadcasts CategoryChanged and QuestionStarted', function () {
    Event::fake([CategoryChanged::class, QuestionStarted::class]);

    app(GameService::class)->start($this->session);

    Event::assertDispatched(CategoryChanged::class);
    Event::assertDispatched(QuestionStarted::class);
});

test('player can submit a correct answer', function () {
    Event::fake([PlayerAnswered::class, CategoryChanged::class, QuestionStarted::class]);
    app(GameService::class)->start($this->session);

    $action = app(SubmitAnswer::class);
    $result = $action->execute(
        session: $this->session->fresh(),
        player: $this->player,
        questionId: $this->questions[0]->id,
        answer: 'Option A',
        timeTakenMs: 5000,
    );

    expect($result['is_correct'])->toBeTrue();
    expect($result['points_earned'])->toBe(10);
    expect($this->player->fresh()->score)->toBe(10);
    Event::assertDispatched(PlayerAnswered::class);
});

test('player cannot submit answer twice for same question', function () {
    Event::fake();
    app(GameService::class)->start($this->session);

    $action = app(SubmitAnswer::class);
    $action->execute($this->session->fresh(), $this->player, $this->questions[0]->id, 'Option A', 5000);

    $action->execute($this->session->fresh(), $this->player, $this->questions[0]->id, 'Option B', 5000);
})->throws(LogicException::class);

test('wrong answer gives zero points and resets streak', function () {
    Event::fake();
    app(GameService::class)->start($this->session);
    $this->player->update(['streak' => 3]);

    $action = app(SubmitAnswer::class);
    $result = $action->execute($this->session->fresh(), $this->player, $this->questions[0]->id, 'Wrong', 5000);

    expect($result['is_correct'])->toBeFalse();
    expect($result['points_earned'])->toBe(0);
    expect($this->player->fresh()->streak)->toBe(0);
});

test('full game flow from start to finish', function () {
    Event::fake();
    $service = app(GameService::class);
    $action = app(SubmitAnswer::class);

    // Start
    $service->start($this->session);
    expect($this->session->fresh()->status)->toBe('playing');

    // Answer question 1
    $action->execute($this->session->fresh(), $this->player, $this->questions[0]->id, 'Option A', 5000);

    // Finish question 1
    $service->finishQuestion($this->session->fresh());
    expect($this->session->fresh()->status)->toBe('reviewing');

    // Advance to question 2
    $service->advanceToNextQuestion($this->session->fresh());
    expect($this->session->fresh()->status)->toBe('playing');
    expect($this->session->fresh()->current_question_index)->toBe(1);

    // Answer question 2
    $action->execute($this->session->fresh(), $this->player, $this->questions[1]->id, 'Option B', 5000);

    // Finish question 2
    $service->finishQuestion($this->session->fresh());

    // Advance past last question
    $result = $service->advanceToNextQuestion($this->session->fresh());
    expect($result)->toBeFalse();
    expect($this->session->fresh()->status)->toBe('finished');
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/GameFlowTest.php
```

**Step 3: Create SubmitAnswer action**

```php
// app/Actions/SubmitAnswer.php
<?php

namespace App\Actions;

use App\Events\PlayerAnswered;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Services\QuestionTypeRegistry;
use App\Services\ScoringService;
use LogicException;

class SubmitAnswer
{
    public function __construct(
        private readonly QuestionTypeRegistry $registry,
        private readonly ScoringService $scoring,
    ) {}

    public function execute(
        GameSession $session,
        Player $player,
        int $questionId,
        mixed $answer,
        int $timeTakenMs,
    ): array {
        if ($session->status !== 'playing') {
            throw new LogicException('Game is not in playing state.');
        }

        $existing = PlayerAnswer::where('player_id', $player->id)
            ->where('question_id', $questionId)
            ->exists();

        if ($existing) {
            throw new LogicException('Player already answered this question.');
        }

        $question = $session->quiz->questions()->findOrFail($questionId);
        $type = $this->registry->resolve($question->type);

        $isCorrect = $type->validateAnswer($answer, $question);
        $pointsEarned = 0;

        if ($isCorrect) {
            $pointsEarned = $this->scoring->calculate(
                $question,
                $timeTakenMs,
                $player->streak,
                $session->quiz->settings,
            );
            $player->increment('score', $pointsEarned);
            $player->increment('streak');
        } else {
            $player->update(['streak' => 0]);
        }

        PlayerAnswer::create([
            'player_id' => $player->id,
            'game_session_id' => $session->id,
            'question_id' => $questionId,
            'answer' => $answer,
            'is_correct' => $isCorrect,
            'time_taken_ms' => $timeTakenMs,
            'points_earned' => $pointsEarned,
        ]);

        $answeredCount = PlayerAnswer::where('game_session_id', $session->id)
            ->where('question_id', $questionId)
            ->count();

        broadcast(new PlayerAnswered($session, $answeredCount, $session->players()->count()));

        return [
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
        ];
    }
}
```

**Step 4: Update GameService to broadcast events**

Add event dispatching to `GameService::start()`, `finishQuestion()`, and `advanceToNextQuestion()`:

```php
// Update app/Services/GameService.php — add to start() after the update:
use App\Events\CategoryChanged;
use App\Events\QuestionStarted;
use App\Events\QuestionEnded;
use App\Events\GameFinished;

// In start() — after $session->update(...):
$question = $this->getCurrentQuestion($session->fresh());
if ($firstCategory) {
    broadcast(new CategoryChanged($session, $firstCategory));
}
if ($question) {
    broadcast(new QuestionStarted($session->fresh(), $question));
}

// In advanceToNextQuestion() — after $session->update([...status => 'playing']):
$prevCategory = $session->current_category_id;
// ... (the update already sets current_category_id)
$updatedSession = $session->fresh();
if ($nextQuestion->category_id !== $prevCategory) {
    broadcast(new CategoryChanged($updatedSession, $nextQuestion->category));
}
broadcast(new QuestionStarted($updatedSession, $nextQuestion));

// In advanceToNextQuestion() — after $session->update(['status' => 'finished']):
$leaderboard = $session->players()->orderByDesc('score')->get()->map(fn ($p) => [
    'nickname' => $p->nickname,
    'score' => $p->score,
])->toArray();
broadcast(new GameFinished($session, $leaderboard));
```

**Step 5: Run tests**

```bash
php artisan test tests/Feature/GameFlowTest.php
```

Expected: PASS.

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add SubmitAnswer action and wire up game flow with broadcast events"
```

---

## Task 12: Channel Authorization

**Files:**
- Modify: `routes/channels.php`
- Create: `tests/Feature/ChannelAuthTest.php`

**Step 1: Write failing tests**

```php
// tests/Feature/ChannelAuthTest.php
<?php

use App\Models\GameSession;
use App\Models\User;

test('host can access host private channel', function () {
    $user = User::factory()->create();
    $session = GameSession::factory()->for($user, 'host')->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-game.' . $session->id . '.host',
        ])
        ->assertOk();
});

test('non-host cannot access host private channel', function () {
    $other = User::factory()->create();
    $session = GameSession::factory()->create();

    $this->actingAs($other)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-game.' . $session->id . '.host',
        ])
        ->assertForbidden();
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/ChannelAuthTest.php
```

**Step 3: Add channel authorization**

```php
// routes/channels.php
<?php

use App\Models\GameSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('game.{sessionId}.host', function ($user, int $sessionId) {
    $session = GameSession::find($sessionId);
    return $session && $session->host_user_id === $user->id;
});
```

**Step 4: Run tests**

```bash
php artisan test tests/Feature/ChannelAuthTest.php
```

Expected: PASS.

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add broadcast channel authorization for host private channel"
```

---

## Task 13: Dashboard — Game History & Stats

**Files:**
- Modify: `resources/views/dashboard.blade.php` (or equivalent Livewire component)
- Create: `app/Livewire/Dashboard.php`
- Create: `tests/Feature/DashboardTest.php`

**Step 1: Write failing tests**

```php
// tests/Feature/DashboardTest.php
<?php

use App\Models\User;
use App\Models\Quiz;
use App\Models\GameSession;
use App\Models\Player;
use App\Livewire\Dashboard;
use Livewire\Livewire;

test('dashboard shows users quizzes', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create(['title' => 'My Trivia']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('My Trivia');
});

test('dashboard shows recent game sessions', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create([
        'status' => 'finished',
    ]);
    Player::factory()->for($session, 'gameSession')->count(5)->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('5 players');
});

test('dashboard shows player stats when user has played games', function () {
    $user = User::factory()->create();
    $session = GameSession::factory()->create(['status' => 'finished']);
    Player::factory()->for($session, 'gameSession')->create([
        'user_id' => $user->id,
        'score' => 150,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('150');
});
```

**Step 2: Run tests, verify fail, implement, run tests, commit**

Follow standard TDD flow. Create `app/Livewire/Dashboard.php` showing:
- User's quizzes with link to edit/play
- Recent game sessions they hosted (with player count, date)
- Games they participated in as a player (with scores)

**Step 3: Commit**

```bash
git add -A
git commit -m "feat: add dashboard with quiz list, game history, and player stats"
```

---

## Task 14: Full Integration — Wire Up Livewire to Echo/Reverb

**Files:**
- Modify: all Livewire screen components to listen to Echo events
- Modify: `resources/js/echo.js` (or equivalent)
- Create: `tests/Feature/Integration/FullGameFlowTest.php`

**Step 1: Write integration test**

```php
// tests/Feature/Integration/FullGameFlowTest.php
<?php

use App\Models\Quiz;
use App\Models\Category;
use App\Models\Question;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\User;
use App\Services\GameService;
use App\Actions\SubmitAnswer;
use Illuminate\Support\Facades\Event;

test('complete game flow end to end', function () {
    Event::fake();

    // Setup
    $host = User::factory()->create();
    $quiz = Quiz::factory()->for($host)->create([
        'settings' => ['enable_time_bonus' => true, 'enable_streaks' => true],
    ]);
    $cat1 = Category::factory()->for($quiz)->create(['order' => 0, 'theme' => 'science']);
    $cat2 = Category::factory()->for($quiz)->create(['order' => 1, 'theme' => 'nature']);
    Question::factory()->for($cat1)->create(['order' => 0, 'correct_answer' => 'A', 'points' => 10]);
    Question::factory()->for($cat2)->create(['order' => 0, 'correct_answer' => 'B', 'points' => 20]);

    $session = GameSession::create([
        'quiz_id' => $quiz->id,
        'host_user_id' => $host->id,
    ]);

    $player1 = Player::create(['game_session_id' => $session->id, 'nickname' => 'Alice']);
    $player2 = Player::create(['game_session_id' => $session->id, 'nickname' => 'Bob']);

    $service = app(GameService::class);
    $action = app(SubmitAnswer::class);

    // Start game
    $service->start($session);
    $session->refresh();
    expect($session->status)->toBe('playing');
    expect($session->currentCategory->theme)->toBe('science');

    // Both players answer question 1
    $q1 = $service->getCurrentQuestion($session);
    $action->execute($session, $player1, $q1->id, 'A', 5000);
    $action->execute($session, $player2, $q1->id, 'Wrong', 10000);

    // Finish + advance
    $service->finishQuestion($session->fresh());
    $service->advanceToNextQuestion($session->fresh());
    $session->refresh();
    expect($session->currentCategory->theme)->toBe('nature');

    // Answer question 2
    $q2 = $service->getCurrentQuestion($session);
    $action->execute($session, $player1, $q2->id, 'B', 3000);
    $action->execute($session, $player2, $q2->id, 'B', 8000);

    // Finish + advance (should end game)
    $service->finishQuestion($session->fresh());
    $result = $service->advanceToNextQuestion($session->fresh());
    expect($result)->toBeFalse();
    expect($session->fresh()->status)->toBe('finished');

    // Verify scores
    expect($player1->fresh()->score)->toBeGreaterThan(0);
    expect($player2->fresh()->score)->toBeGreaterThan(0);
    expect($player1->fresh()->score)->toBeGreaterThan($player2->fresh()->score);
});
```

**Step 2: Add Echo event listeners to Livewire components**

Update `SpectatorScreen`, `PlayerScreen`, and `HostDashboard` to use Livewire's `#[On('echo:...')]` attributes or `getListeners()` to react to broadcast events and re-render.

**Step 3: Run full test suite**

```bash
php artisan test
```

Expected: ALL tests pass.

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: wire up Livewire components to Echo/Reverb broadcast events"
```

---

## Task 15: QR Code Package & Spectator Polish

**Files:**
- Modify: `composer.json` (add simplesoftwareio/simple-qrcode)
- Modify: `resources/views/livewire/spectator-screen.blade.php`

**Step 1: Install QR code package**

```bash
composer require simplesoftwareio/simple-qrcode
```

**Step 2: Update spectator lobby view with QR code**

The spectator lobby should show:
- Game title
- Large join code
- QR code pointing to `route('game.join', $code)`
- Live player list (updates via Echo)

**Step 3: Test manually**

```bash
composer run dev
```

Open spectator URL in browser, verify QR code renders and links to join URL.

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add QR code to spectator lobby screen"
```

---

## Task 16: Error Handling — Disconnects & Edge Cases

**Files:**
- Create: `app/Listeners/HandlePlayerDisconnect.php`
- Modify: `app/Services/GameService.php`
- Create: `tests/Feature/EdgeCasesTest.php`

**Step 1: Write failing tests**

```php
// tests/Feature/EdgeCasesTest.php
<?php

use App\Models\GameSession;
use App\Models\Player;
use App\Models\Question;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\User;
use App\Actions\SubmitAnswer;
use App\Services\GameService;
use Illuminate\Support\Facades\Event;

test('late answer after timer is rejected', function () {
    Event::fake();
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create();
    $category = Category::factory()->for($quiz)->create();
    $question = Question::factory()->for($category)->create([
        'time_limit_seconds' => 30,
        'correct_answer' => 'A',
    ]);
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create();
    $player = Player::factory()->for($session, 'gameSession')->create();
    app(GameService::class)->start($session);

    // Answer after time limit + grace period (30500ms)
    $action = app(SubmitAnswer::class);
    $action->execute($session->fresh(), $player, $question->id, 'A', 31000);
})->throws(LogicException::class);

test('disconnected player scores zero for missed questions', function () {
    Event::fake();
    $user = User::factory()->create();
    $quiz = Quiz::factory()->for($user)->create([
        'settings' => ['enable_time_bonus' => false, 'enable_streaks' => false],
    ]);
    $category = Category::factory()->for($quiz)->create();
    Question::factory()->for($category)->count(2)->sequence(
        ['order' => 0, 'correct_answer' => 'A'],
        ['order' => 1, 'correct_answer' => 'B'],
    )->create();
    $session = GameSession::factory()->for($quiz)->for($user, 'host')->create();
    $player = Player::factory()->for($session, 'gameSession')->create(['is_connected' => false]);

    app(GameService::class)->start($session);

    // Player never answers — score stays 0
    expect($player->fresh()->score)->toBe(0);
});
```

**Step 2: Add timer validation to SubmitAnswer**

In `app/Actions/SubmitAnswer.php`, add before scoring:

```php
$gracePeriodMs = 500;
$timeLimitMs = $question->time_limit_seconds * 1000 + $gracePeriodMs;
if ($timeTakenMs > $timeLimitMs) {
    throw new LogicException('Answer submitted after time limit.');
}
```

**Step 3: Run tests**

```bash
php artisan test tests/Feature/EdgeCasesTest.php
```

Expected: PASS.

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add timer validation and edge case handling"
```

---

## Task 17: Final Test Suite & Cleanup

**Step 1: Run the full test suite**

```bash
php artisan test --parallel
```

Expected: ALL tests pass.

**Step 2: Run static analysis if available**

```bash
./vendor/bin/pint
```

Fix any code style issues.

**Step 3: Commit**

```bash
git add -A
git commit -m "chore: code style fixes and final cleanup"
```

---

## Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | Project scaffolding | Laravel + Livewire + Reverb |
| 2 | Question type system | Interface, Registry, 2 types |
| 3 | Database & models | 6 migrations, 6 models, 6 factories |
| 4 | Scoring service | ScoringService with time bonus + streaks |
| 5 | Question type tests | Unit tests for MC + T/F |
| 6 | Game state machine | GameService |
| 7 | Broadcast events | 6 event classes |
| 8 | Theme config | 7 built-in themes |
| 9 | Quiz CRUD | QuizIndex + QuizBuilder |
| 10 | Game lobby | Create, join, host/spectator/player screens |
| 11 | Game flow | SubmitAnswer action, full game loop |
| 12 | Channel auth | Private channel authorization |
| 13 | Dashboard | Game history + player stats |
| 14 | Integration | Wire Livewire to Echo/Reverb |
| 15 | QR code | Spectator lobby QR code |
| 16 | Edge cases | Timer validation, disconnects |
| 17 | Cleanup | Full test suite, code style |
