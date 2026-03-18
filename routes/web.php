<?php

use App\Http\Controllers\GameController;
use App\Livewire\Dashboard;
use App\Livewire\HostDashboard;
use App\Livewire\JoinGame;
use App\Livewire\PlayerScreen;
use App\Livewire\QuizBuilder;
use App\Livewire\QuizIndex;
use App\Livewire\SpectatorScreen;
use App\Livewire\WelcomePage;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomePage::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('/quizzes', QuizIndex::class)->name('quizzes.index');
    Route::get('/quizzes/create', QuizBuilder::class)->name('quizzes.create');
    Route::get('/quizzes/{quiz}/edit', QuizBuilder::class)->name('quizzes.edit');
    Route::post('/game/create/{quiz}', [GameController::class, 'create'])->name('game.create');
    Route::get('/game/{code}/host', HostDashboard::class)->name('game.host');
});

// Public game routes (no auth required)
Route::get('/game/{code}/spectator', SpectatorScreen::class)->name('game.spectator');
Route::get('/game/{code}/play', PlayerScreen::class)->name('game.play');
Route::get('/join/{code}', JoinGame::class)->name('game.join');

require __DIR__.'/settings.php';
